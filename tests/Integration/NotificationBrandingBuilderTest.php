<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/Configuration-glpi-auto
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Configurationglpiauto\Tests\Integration;

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\NotificationBrandingBuilder;
use Notification;
use Notification_NotificationTemplate;
use NotificationTemplate;
use NotificationTemplateTranslation;
use PHPUnit\Framework\TestCase;

/**
 * `NotificationBrandingBuilder` re-points 23 real, native GLPI `Ticket` notification events away
 * from the single shared default template (id confirmed live on this instance) to 23 dedicated
 * branded ones — a real, global, shared-instance side effect (see the class's own docblock). This
 * suite captures each touched event's ORIGINAL template id in `setUp()` (rather than assuming it,
 * since it could legitimately differ across environments) and restores every one of them in
 * `tearDown()`, alongside deleting the templates/translations this suite itself created, so no
 * lasting change reaches other tests or real usage on the shared dev instance.
 */
final class NotificationBrandingBuilderTest extends TestCase
{
    private const EVENTS = [
        'new', 'update', 'solved', 'closed', 'delete', 'alertnotclosed',
        'add_followup', 'update_followup', 'delete_followup',
        'add_task', 'update_task', 'delete_task',
        'requester_user', 'requester_group', 'observer_user', 'observer_group',
        'assign_user', 'assign_group', 'assign_supplier', 'user_mention', 'add_document',
        'validation', 'validation_answer',
    ];

    /** @var array<string, int> event => original mailing template id */
    private array $originalTemplateIdByEvent = [];

    protected function setUp(): void
    {
        global $DB;
        foreach (self::EVENTS as $event) {
            $notification = new Notification();
            $notification->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => $event]);
            $join = new Notification_NotificationTemplate();
            $join->getFromDBByCrit(['notifications_id' => $notification->getID(), 'mode' => 'mailing']);
            $this->originalTemplateIdByEvent[$event] = (int) $join->fields['notificationtemplates_id'];
        }
    }

    protected function tearDown(): void
    {
        foreach (self::EVENTS as $event) {
            $notification = new Notification();
            $notification->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => $event]);
            $join = new Notification_NotificationTemplate();
            if ($join->getFromDBByCrit(['notifications_id' => $notification->getID(), 'mode' => 'mailing'])) {
                $join->update(['id' => $join->getID(), 'notificationtemplates_id' => $this->originalTemplateIdByEvent[$event]]);
            }
        }

        global $DB;
        foreach ($DB->request(['FROM' => NotificationTemplate::getTable(), 'WHERE' => ['name' => ['LIKE', 'Notification personnalisée —%']]]) as $row) {
            $DB->delete(NotificationTemplateTranslation::getTable(), ['notificationtemplates_id' => $row['id']]);
            (new NotificationTemplate())->delete(['id' => $row['id']], true);
        }
    }

    private function buildConfig(bool $enabled): Config
    {
        $config = new Config();
        $config->fields = array_merge(Config::getDefaults(), [
            'notification_branding_enabled' => $enabled ? 1 : 0,
        ]);

        return $config;
    }

    public function testReturnsZeroWhenDisabled(): void
    {
        $count = (new NotificationBrandingBuilder())->apply($this->buildConfig(false), '#206bc4', null);

        $this->assertSame(0, $count);
    }

    public function testAppliesCreatesAllTwentyThreeTemplatesAndReassignsTheirEvents(): void
    {
        $count = (new NotificationBrandingBuilder())->apply($this->buildConfig(true), '#206bc4', null);

        $this->assertSame(23, $count);

        $template = new NotificationTemplate();
        $this->assertTrue($template->getFromDBByCrit(['name' => 'Notification personnalisée — Nouveau ticket', 'itemtype' => 'Ticket']));

        $notification = new Notification();
        $notification->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => 'new']);
        $join = new Notification_NotificationTemplate();
        $join->getFromDBByCrit(['notifications_id' => $notification->getID(), 'mode' => 'mailing']);
        $this->assertSame(
            (int) $template->getID(),
            (int) $join->fields['notificationtemplates_id'],
            'The native "new" ticket event must be re-pointed to the dedicated branded template.'
        );
        $this->assertNotSame($this->originalTemplateIdByEvent['new'], (int) $template->getID());
    }

    public function testFrenchTranslationContainsTheRealTicketContentTagAndTheMarker(): void
    {
        (new NotificationBrandingBuilder())->apply($this->buildConfig(true), '#206bc4', null);

        $template = new NotificationTemplate();
        $template->getFromDBByCrit(['name' => 'Notification personnalisée — Nouveau ticket', 'itemtype' => 'Ticket']);

        $translation = new NotificationTemplateTranslation();
        $this->assertTrue($translation->getFromDBByCrit(['notificationtemplates_id' => $template->getID(), 'language' => '']));
        $this->assertStringContainsString('<!-- configurationglpiauto:notification-branding -->', $translation->fields['content_html']);
        $this->assertStringContainsString('##ticket.content##', $translation->fields['content_html']);
        $this->assertStringContainsString('##ticket.url##', $translation->fields['content_html']);
    }

    public function testEnglishTranslationUsesEnglishLabelsNotFrenchOnes(): void
    {
        (new NotificationBrandingBuilder())->apply($this->buildConfig(true), '#206bc4', null);

        $template = new NotificationTemplate();
        $template->getFromDBByCrit(['name' => 'Notification personnalisée — Nouveau ticket', 'itemtype' => 'Ticket']);

        $translation = new NotificationTemplateTranslation();
        $this->assertTrue($translation->getFromDBByCrit(['notificationtemplates_id' => $template->getID(), 'language' => 'en_GB']));
        $this->assertStringContainsString('View the ticket in GLPI', $translation->fields['content_html']);
        $this->assertStringNotContainsString('Voir le ticket dans GLPI', $translation->fields['content_html']);
    }

    public function testValidationEventUsesTheValidationUrlAsItsCallToAction(): void
    {
        (new NotificationBrandingBuilder())->apply($this->buildConfig(true), '#206bc4', null);

        $template = new NotificationTemplate();
        $template->getFromDBByCrit(['name' => 'Notification personnalisée — Demande de validation', 'itemtype' => 'Ticket']);
        $translation = new NotificationTemplateTranslation();
        $translation->getFromDBByCrit(['notificationtemplates_id' => $template->getID(), 'language' => '']);

        $this->assertStringContainsString('##ticket.urlvalidation##', $translation->fields['content_html']);
    }

    /**
     * Idempotent via an HTML comment marker rather than "row already exists, skip" (see the class's
     * own docblock) : a second run must NOT recount templates that already carry the marker — a
     * genuinely different contract from most other builders in this plugin.
     */
    public function testASecondRunDoesNotRecountAlreadyMarkedTemplates(): void
    {
        $builder = new NotificationBrandingBuilder();
        $builder->apply($this->buildConfig(true), '#206bc4', null);

        $second = $builder->apply($this->buildConfig(true), '#206bc4', null);

        $this->assertSame(0, $second, 'Every template already carries the marker — nothing left to (re)apply.');
    }

    /**
     * If an admin has genuinely edited the content (the marker is gone), a re-run must take
     * ownership again rather than respect a marker that no longer reflects reality.
     */
    public function testARunRecountsATemplateWhoseMarkerWasRemoved(): void
    {
        $builder = new NotificationBrandingBuilder();
        $builder->apply($this->buildConfig(true), '#206bc4', null);

        $template = new NotificationTemplate();
        $template->getFromDBByCrit(['name' => 'Notification personnalisée — Nouveau ticket', 'itemtype' => 'Ticket']);
        $translation = new NotificationTemplateTranslation();
        $translation->getFromDBByCrit(['notificationtemplates_id' => $template->getID(), 'language' => '']);
        $translation->update(['id' => $translation->getID(), 'content_html' => '<p>Contenu modifié à la main par un admin</p>']);

        $second = $builder->apply($this->buildConfig(true), '#206bc4', null);

        $this->assertSame(1, $second, 'Only the un-marked template must be re-applied — the other 22 still carry the marker and are skipped.');

        $translation = new NotificationTemplateTranslation();
        $translation->getFromDBByCrit(['notificationtemplates_id' => $template->getID(), 'language' => '']);
        $this->assertStringContainsString(
            '<!-- configurationglpiauto:notification-branding -->',
            $translation->fields['content_html'],
            'Re-applying must restore the marker, not leave the admin-edited content half-owned.'
        );
    }
}

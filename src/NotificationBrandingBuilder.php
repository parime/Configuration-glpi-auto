<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Parime
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

namespace GlpiPlugin\Configurationglpiauto;

use Notification;
use Notification_NotificationTemplate;
use NotificationTemplate;
use NotificationTemplateTranslation;

/**
 * Replaces GLPI's single shared "Tickets" notification template (`glpi_notifications_notificationtemplates`
 * — confirmed in a fresh install: the `new`/`update`/`solved`/`add_followup` ticket events *all*
 * point at the same template id, so they're stuck with identical, plain-text-looking HTML) with one
 * dedicated, branded template per event — using the admin's own primary color (`BrandingBuilder`)
 * and root-entity logo, real GLPI notification tags (`##ticket.xxx##`, confirmed one by one against
 * `NotificationTargetCommonITILObject`/`NotificationTargetTicket` source rather than guessed).
 *
 * Layout directly modeled on a real production HTML notification set handed over for audit (dark
 * header, colored accent bar, colored CTA button, "-- via GLPI" style footer) — same "generalize a
 * real pattern, drop the org-specific branding" approach already used for `RuleRightBuilder`/
 * `SlaBuilder`. The reference export's exact colors/company name are never reused, only the
 * structure; the admin's own computed color/logo (already collected in step 9) replace them.
 *
 * Idempotent via an HTML comment marker rather than the usual "row already exists, skip" pattern:
 * this *modifies* a native GLPI row (or one of our own, on a second run) rather than creating a new
 * one, so "already has our marker" is the only safe re-run signal — an admin's own hand-edit after
 * the fact (no marker anymore, since they'd naturally replace the whole content) is respected,
 * matching the project's "point of entry, not final" convention rather than clobbering it silently.
 */
class NotificationBrandingBuilder
{
    private const MARKER = '<!-- configurationglpiauto:notification-branding -->';

    /**
     * @var array<int, array{name: string, event: string, badge: string, body_label: string, body_tag: string, date_label: string, date_tag: string}>
     */
    private const TEMPLATES = [
        [
            'name' => 'Notification personnalisée — Nouveau ticket',
            'event' => 'new',
            'badge' => '##ticket.id##',
            'body_label' => 'Description',
            'body_tag' => '##ticket.content##',
            'date_label' => 'Ouvert le',
            'date_tag' => '##ticket.creationdate##',
        ],
        [
            'name' => 'Notification personnalisée — Mise à jour du ticket',
            'event' => 'update',
            'badge' => '##ticket.id##',
            'body_label' => 'Description',
            'body_tag' => '##ticket.content##',
            'date_label' => 'Statut',
            'date_tag' => '##ticket.status##',
        ],
        [
            'name' => 'Notification personnalisée — Ticket résolu',
            'event' => 'solved',
            'badge' => '##ticket.id##',
            'body_label' => 'Solution apportée',
            'body_tag' => '##ticket.solution.description##',
            'date_label' => 'Résolu le',
            'date_tag' => '##ticket.solvedate##',
        ],
        [
            'name' => 'Notification personnalisée — Nouveau suivi',
            'event' => 'add_followup',
            'badge' => '##ticket.id##',
            'body_label' => 'Description',
            'body_tag' => '##ticket.content##',
            'date_label' => 'Statut',
            'date_tag' => '##ticket.status##',
        ],
    ];

    public function apply(Config $config, string $color, ?string $logoDataUri): int
    {
        if (empty($config->fields['notification_branding_enabled'])) {
            return 0;
        }

        $count = 0;
        foreach (self::TEMPLATES as $definition) {
            if ($this->applyOne($definition, $color, $logoDataUri)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array{name: string, event: string, badge: string, body_label: string, body_tag: string, date_label: string, date_tag: string} $definition
     */
    private function applyOne(array $definition, string $color, ?string $logoDataUri): bool
    {
        $html = $this->buildHtml($definition, $color, $logoDataUri);

        $templateId = $this->getOrCreateTemplate($definition['name'], $html);
        if ($templateId === null) {
            // Marker already present: an earlier run (or, once edited, an admin) owns this content
            // now — don't overwrite it again.
            return false;
        }

        $this->assignToEvent($definition['event'], $templateId);

        return true;
    }

    private function getOrCreateTemplate(string $name, string $html): ?int
    {
        $template = new NotificationTemplate();
        if ($template->getFromDBByCrit(['name' => $name, 'itemtype' => 'Ticket'])) {
            $templateId = (int) $template->getID();

            $translation = new NotificationTemplateTranslation();
            if ($translation->getFromDBByCrit(['notificationtemplates_id' => $templateId, 'language' => ''])) {
                $existing = (string) ($translation->fields['content_html'] ?? '');
                if (str_contains($existing, self::MARKER)) {
                    return null;
                }
                $translation->update(['id' => $translation->getID(), 'content_html' => $html]);
            } else {
                $translation->add([
                    'notificationtemplates_id' => $templateId,
                    'language' => '',
                    'subject' => '##ticket.title##',
                    'content_html' => $html,
                    'content_text' => '',
                ]);
            }

            return $templateId;
        }

        $templateId = (int) $template->add([
            'name' => $name,
            'itemtype' => 'Ticket',
            'comment' => 'Créé par Configuration GLPI Auto — habillage HTML personnalisé.',
        ]);

        (new NotificationTemplateTranslation())->add([
            'notificationtemplates_id' => $templateId,
            'language' => '',
            'subject' => '##ticket.title##',
            'content_html' => $html,
            'content_text' => '',
        ]);

        return $templateId;
    }

    /**
     * Re-points the existing `mailing` join row for this event from GLPI's shared default template
     * to our dedicated one — the row already exists natively for every core Ticket event, so this
     * is always an update, never an insert (confirmed via `DESCRIBE`: one row per event+mode).
     */
    private function assignToEvent(string $event, int $templateId): void
    {
        $notification = new Notification();
        if (!$notification->getFromDBByCrit(['itemtype' => 'Ticket', 'event' => $event])) {
            return;
        }
        $notificationId = (int) $notification->getID();

        $join = new Notification_NotificationTemplate();
        if ($join->getFromDBByCrit(['notifications_id' => $notificationId, 'mode' => 'mailing'])) {
            $join->update(['id' => $join->getID(), 'notificationtemplates_id' => $templateId]);
        } else {
            $join->add([
                'notifications_id' => $notificationId,
                'mode' => 'mailing',
                'notificationtemplates_id' => $templateId,
            ]);
        }
    }

    /**
     * @param array{name: string, event: string, badge: string, body_label: string, body_tag: string, date_label: string, date_tag: string} $definition
     */
    private function buildHtml(array $definition, string $color, ?string $logoDataUri): string
    {
        [$r, $g, $b] = $this->hexToRgb($color);
        $ctaFg = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 149 ? '#1e293b' : '#ffffff';

        $logoBlock = $logoDataUri !== null
            ? '<img src="' . htmlspecialchars($logoDataUri, ENT_QUOTES, 'UTF-8') . '" alt="" style="max-height:32px;margin-bottom:12px;display:block;">'
            : '';

        return self::MARKER . "\n"
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f0f5;padding:30px 0;"><tr><td align="center">'
            . '<table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;border-radius:12px;overflow:hidden;">'
            . '<tr><td style="background:#1a1a2e;padding:30px 36px 24px 36px;">'
            . $logoBlock
            . '<div style="border-left:4px solid ' . $color . ';padding-left:16px;">'
            . '<a href="##ticket.url##" style="color:#ffffff;text-decoration:none;font-size:20px;font-weight:bold;font-family:Arial,sans-serif;">##ticket.title##</a>'
            . '</div>'
            . '<div style="margin-top:16px;padding-left:20px;">'
            . '<span style="background:rgba(255,255,255,0.1);border:1px solid ' . $color . ';color:' . $color . ';font-size:11px;padding:4px 12px;border-radius:20px;font-family:Arial,sans-serif;"><strong>' . $definition['badge'] . '</strong></span>'
            . '&nbsp;&nbsp;<span style="color:#888888;font-size:12px;font-family:Arial,sans-serif;">' . $definition['date_label'] . ' ' . $definition['date_tag'] . '</span>'
            . '</div></td></tr>'
            . '<tr><td style="padding:32px 36px;background:#ffffff;">'
            . '<div style="font-size:10px;color:' . $color . ';letter-spacing:3px;text-transform:uppercase;font-family:Arial,sans-serif;margin-bottom:12px;">' . $definition['body_label'] . '</div>'
            . '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td width="4" style="background:' . $color . ';border-radius:4px;">&nbsp;</td>'
            . '<td style="background:#f9f9fb;padding:16px 20px;border-radius:0 8px 8px 0;font-family:Arial,sans-serif;font-size:14px;color:#333333;line-height:1.8;">' . $definition['body_tag'] . '</td>'
            . '</tr></table></td></tr>'
            . '<tr><td align="center" style="background:' . $color . ';padding:18px 36px;">'
            . '<a href="##ticket.url##" style="color:' . $ctaFg . ';text-decoration:none;font-family:Arial,sans-serif;font-size:13px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">→ Voir le ticket dans GLPI</a>'
            . '</td></tr>'
            . '<tr><td align="center" style="background:#f8f8fa;padding:12px 36px;border-top:1px solid #eeeeee;">'
            . '<span style="font-family:Arial,sans-serif;font-size:11px;color:#bbbbbb;">##ticket.id## &nbsp;·&nbsp; <a href="##ticket.url##" style="color:' . $color . ';text-decoration:none;">Accéder à GLPI</a></span>'
            . '</td></tr>'
            . '</table></td></tr></table>';
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $color): array
    {
        $hex = ltrim($color, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}

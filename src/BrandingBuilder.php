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

namespace GlpiPlugin\Configurationglpiauto;

use Entity;

/**
 * Turns a Config's branding settings (primary color, and per-entity logo) into real per-entity CSS
 * overrides, using GLPI's own built-in `Entity::enable_custom_css`/`custom_css_code` mechanism
 * (Configuration > Entités > onglet général, "Personnalisation CSS") — no file writes, no touching
 * GLPI's own static assets, just data already natively supported by GLPI for exactly this purpose.
 * Color can also be set independently per top-level entity (`applyPerClientColors()`) rather than
 * one shared color for all — the same per-entity-panel pattern `entity_logos_enabled` already uses.
 *
 * Color and logo are two independent toggles (`branding_enabled`/`entity_logos_enabled`) that can
 * both write into the *same* `custom_css_code` field, so each writes its own block delimited by a
 * comment marker (`mergeCssBlock()`) rather than blindly overwriting the whole field — re-running
 * one doesn't erase the other, and neither clobbers anything an admin might have added by hand
 * outside those markers.
 *
 * Fifth completeness audit (Sprint 35, 2026-08-12) replaced the earlier version of this class, which
 * only touched `--tblr-primary`/`--tblr-primary-rgb` (buttons/links) and `--glpi-logo`/
 * `--glpi-logo-reduced` (sidebar logo, expanded/collapsed) — guessed one variable at a time across
 * several sprints, never checked against the full list. Confirmed directly in this GLPI 11.0.8
 * install's own `css/includes/_base.scss` (not just researched) which variables exist and how they
 * derive from each other:
 * - `--glpi-logo`/`--glpi-logo-reduced` are themselves aliases of `--glpi-logo-light`/
 *   `--glpi-logo-light-reduced` (`--glpi-logo: var(--glpi-logo-light);`) — overriding only the alias
 *   left `--glpi-logo-dark`/`-dark-reduced` (referenced directly by some rules, not just through the
 *   alias) and the two login-page variables (`--glpi-logo-light-login`/`-dark-login`,
 *   `.page-anonymous .glpi-logo` hardcodes the `-dark-login` one) completely untouched — an admin's
 *   uploaded logo never appeared on the login screen.
 * - `--tblr-primary-darken` (`color-mix(in srgb, var(--tblr-primary), black 10%)`) and
 *   `--glpi-mainmenu-active-bg`/`--glpi-illustrations-*` (all `color-mix()`/`hsl(from ...)` derived
 *   from `--glpi-mainmenu-bg`) don't need overriding at all: CSS custom properties resolve at
 *   used-value time, so once the variable they reference is overridden, they recompute automatically
 *   — confirmed by reading the derivation chain, not assumed.
 * - The wizard's own live preview (`cga-branding-preview-header`) has always simulated the *sidebar*
 *   turning the chosen color, but the implementation only ever recolored buttons/links
 *   (`--tblr-primary`) — `--glpi-mainmenu-bg` (the actual sidebar background) was never touched,
 *   a real promise/result mismatch. Now applies the same color there too, with `--glpi-mainmenu-fg`/
 *   `-fg-muted` recomputed for contrast the same way `--tblr-primary-fg` already is.
 *
 * Embedded as a `data:` URI (base64) rather than uploaded as a `Document` — self-contained in the
 * same field GLPI already stores this kind of customization in, no separate file storage/serving
 * path to keep in sync.
 */
class BrandingBuilder
{
    private const COLOR_BLOCK_KEY = 'branding-color';

    private const LOGO_BLOCK_KEY = 'branding-logo';

    // GLPI's own default foreground for --tblr-primary-fg (css/includes/_base.scss) — reused here
    // as the "dark text" half of the contrast choice so a custom color close to GLPI's own default
    // still resolves to the exact same foreground GLPI ships, not a slightly different dark tone.
    private const DARK_FG = '#1e293b';

    private const LIGHT_FG = '#ffffff';

    /**
     * @param int[] $entityIds
     */
    public function apply(Config $config, array $entityIds): bool
    {
        if (empty($config->fields['branding_enabled'])) {
            return false;
        }

        $color = (string) ($config->fields['branding_primary_color'] ?? '#206bc4');
        $css = $this->buildColorCss($color);

        foreach ($entityIds as $entityId) {
            $this->mergeCssBlock($entityId, self::COLOR_BLOCK_KEY, $css);
        }

        return true;
    }

    /**
     * @param array<int, string> $entityIdToDataUri Entity ID => `data:image/...;base64,...` URI.
     * @return int Number of entities with a logo applied.
     */
    public function applyLogos(array $entityIdToDataUri): int
    {
        $count = 0;
        foreach ($entityIdToDataUri as $entityId => $dataUri) {
            $css = $this->buildLogoCss($dataUri);
            $this->mergeCssBlock((int) $entityId, self::LOGO_BLOCK_KEY, $css);
            $count++;
        }

        return $count;
    }

    /**
     * Same mechanism as `apply()`, one independent color per entity instead of the single shared
     * one — for an MSP/multi-site admin who wants each client/site to keep its own brand color
     * rather than all of them matching. Deliberately a separate method (not a variant of `apply()`)
     * following the same "per-client is an independent path, not a parameter on the shared one"
     * shape already established by `applyLogos()` alongside `apply()`.
     *
     * @param array<int, string> $entityIdToColor Entity ID => `#rrggbb`.
     * @return int Number of entities with their own color applied.
     */
    public function applyPerClientColors(array $entityIdToColor): int
    {
        $count = 0;
        foreach ($entityIdToColor as $entityId => $color) {
            $css = $this->buildColorCss($color);
            $this->mergeCssBlock((int) $entityId, self::COLOR_BLOCK_KEY, $css);
            $count++;
        }

        return $count;
    }

    /**
     * Overrides both Tabler's (GLPI's admin theme) primary-color variables (buttons, links) *and*
     * the sidebar/header background (`--glpi-mainmenu-*`) with the same admin-chosen color — the
     * wizard's own preview already simulates the sidebar turning that color, this makes the real
     * result match it. Foreground text color is recomputed for each background rather than left at
     * GLPI's own default (`--tblr-primary-fg`/`--glpi-mainmenu-fg` assume GLPI's own default
     * yellow/navy backgrounds; an admin-chosen color close to white or black would otherwise pair
     * with the wrong text color and become unreadable).
     */
    private function buildColorCss(string $color): string
    {
        [$r, $g, $b] = $this->hexToRgb($color);
        $fg = $this->contrastingForeground($r, $g, $b);

        return ":root {\n"
            . "  --tblr-primary: {$color} !important;\n"
            . "  --tblr-primary-rgb: {$r}, {$g}, {$b} !important;\n"
            . "  --tblr-primary-fg: {$fg} !important;\n"
            . "  --glpi-mainmenu-bg: {$color} !important;\n"
            . "  --glpi-mainmenu-fg: {$fg} !important;\n"
            . "  --glpi-mainmenu-fg-muted: {$fg}99 !important;\n"
            . '}';
    }

    /**
     * Perceptual (not full WCAG-contrast-ratio) luminance heuristic — matches the weighting
     * (`0.299R + 0.587G + 0.114B`) commonly used for this exact "pick readable text for an arbitrary
     * background" problem; a full WCAG relative-luminance/contrast-ratio computation would be more
     * rigorous but is overkill for a binary black-or-white choice.
     */
    private function contrastingForeground(int $r, int $g, int $b): string
    {
        $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;

        return $luminance > 149 ? self::DARK_FG : self::LIGHT_FG;
    }

    /**
     * Overrides every logo-related CSS custom property GLPI 11 exposes with the same uploaded
     * image: the sidebar/header alias (`--glpi-logo`/`-reduced`) *and* the "light"/"dark" source
     * variables it points to (some rules reference `--glpi-logo-dark`/`-dark-reduced` directly,
     * bypassing the alias — confirmed in `_base.scss`), plus the two login-page variables
     * (`--glpi-logo-light-login`/`-dark-login`, `.page-anonymous .glpi-logo` hardcodes the latter).
     * One logo image can't be optimized for every one of those backgrounds at once, so this
     * deliberately favors "the custom logo is visible everywhere" over "each variant has ideal
     * contrast" — still strictly better than the previous behavior, where the login screen and any
     * rule bypassing the `--glpi-logo` alias silently kept GLPI's own default logo.
     *
     * `background-size: contain` is mandatory here, not cosmetic: confirmed in GLPI's own
     * `_base.scss`, the expanded-sidebar `.page .glpi-logo` rule sets a fixed 100×55px box with
     * `background: var(--glpi-logo) no-repeat` and *no* `background-size` at all (unlike the
     * collapsed-sidebar `.glpi-logo` rule, which already sets `background-size: contain`) — GLPI's
     * own shipped logo images happen to be pre-cropped to exactly that ratio, but an admin-uploaded
     * logo of arbitrary dimensions renders at its native size and overflows the box uncropped
     * (confirmed visually: a tall/wide logo spills over the menu below it). `!important` needed to
     * outweigh that core rule's own specificity, same as the variable overrides below it.
     */
    private function buildLogoCss(string $dataUri): string
    {
        $escaped = str_replace('"', '\\"', $dataUri);
        $url = "url(\"{$escaped}\") !important";

        return ".page .glpi-logo { background-size: contain !important; }\n"
            . ":root {\n"
            . "  --glpi-logo: {$url};\n"
            . "  --glpi-logo-reduced: {$url};\n"
            . "  --glpi-logo-light: {$url};\n"
            . "  --glpi-logo-light-reduced: {$url};\n"
            . "  --glpi-logo-dark: {$url};\n"
            . "  --glpi-logo-dark-reduced: {$url};\n"
            . "  --glpi-logo-light-login: {$url};\n"
            . "  --glpi-logo-dark-login: {$url};\n"
            . '}';
    }

    /**
     * Replaces this plugin's own previously-written block for `$blockKey` (if any) and appends the
     * new one — never touches content outside its own markers, so color/logo/any future
     * CSS-writing feature and an admin's own manual additions all coexist safely across reruns.
     */
    private function mergeCssBlock(int $entityId, string $blockKey, string $css): void
    {
        $entity = new Entity();
        if (!$entity->getFromDB($entityId)) {
            return;
        }

        $existing = (string) ($entity->fields['custom_css_code'] ?? '');
        $startMarker = "/* configurationglpiauto:{$blockKey}:start */";
        $endMarker = "/* configurationglpiauto:{$blockKey}:end */";

        $pattern = '/' . preg_quote($startMarker, '/') . '.*?' . preg_quote($endMarker, '/') . '/s';
        $stripped = trim((string) preg_replace($pattern, '', $existing));

        $block = "{$startMarker}\n{$css}\n{$endMarker}";
        $merged = $stripped === '' ? $block : "{$stripped}\n{$block}";

        $entity->update([
            'id' => $entityId,
            'enable_custom_css' => 1,
            'custom_css_code' => $merged,
        ]);
    }

    /**
     * Writes the same logo/color already applied to the UI into each entity's
     * `mailing_signature` (`Entity::getUsedConfig('mailing_signature', ...)`, appended by GLPI core
     * — confirmed in `NotificationTemplate.php` — after "-- " at the end of every outgoing
     * notification email, HTML *and* plain-text versions). Confirmed empirically (Symfony
     * `HtmlSanitizer::sanitize()`, the same one GLPI runs the signature through via
     * `RichText::getSafeHtml()`) that a `data:` URI `<img>` survives sanitization — GLPI itself
     * doesn't strip it. The colored text stays reliable everywhere; the logo image is a bonus that
     * some mail clients (Outlook desktop in particular) are known to block `data:` images for, same
     * "point de départ, ajustable" spirit as the rest of this plugin rather than a hard requirement.
     * Plain-text fallback (`getTextFromHtml()`, called by GLPI core itself) degrades to just the
     * entity name, so nothing breaks for recipients on a text-only client either.
     *
     * @param array<int, string>      $entityIdToDataUri Entity ID => logo `data:` URI (per-entity
     *        only — this plugin has no separate "shared logo" concept, `entity_logos_enabled`
     *        always uploads one per node).
     * @param array<int, string>      $entityIdToColor   Entity ID => `#rrggbb`, falls back to
     *        `$sharedColor` for any entity not present (e.g. per-client color toggle disabled, or
     *        this specific node kept the shared value).
     * @return int Number of entities with a signature applied.
     */
    public function applyMailingSignatures(
        array $entityIds,
        array $entityIdToDataUri,
        array $entityIdToColor,
        string $sharedColor,
    ): int {
        $count = 0;
        foreach ($entityIds as $entityId) {
            $entity = new Entity();
            if (!$entity->getFromDB($entityId)) {
                continue;
            }

            $color = $entityIdToColor[$entityId] ?? $sharedColor;
            $dataUri = $entityIdToDataUri[$entityId] ?? null;
            $html = $this->buildSignatureHtml((string) $entity->fields['name'], $color, $dataUri);

            $entity->update([
                'id' => $entityId,
                'mailing_signature' => $html,
            ]);
            $count++;
        }

        return $count;
    }

    private function buildSignatureHtml(string $entityName, string $color, ?string $dataUri): string
    {
        $safeName = htmlspecialchars($entityName, ENT_QUOTES, 'UTF-8');
        $logoTag = $dataUri !== null
            ? '<img src="' . htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8') . '" alt="' . $safeName . '" style="max-height:32px;vertical-align:middle;margin-right:8px;"><br>'
            : '';

        return '<div style="margin-top:8px;">' . $logoTag
            . '<span style="color:' . $color . ';font-weight:bold;">' . $safeName . '</span>'
            . '</div>';
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}

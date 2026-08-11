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

use Entity;

/**
 * Turns a Config's branding settings (primary color, and per-entity logo) into real per-entity CSS
 * overrides, using GLPI's own built-in `Entity::enable_custom_css`/`custom_css_code` mechanism
 * (Configuration > Entités > onglet général, "Personnalisation CSS") — no file writes, no touching
 * GLPI's own static assets, just data already natively supported by GLPI for exactly this purpose.
 *
 * Color and logo are two independent toggles (`branding_enabled`/`entity_logos_enabled`) that can
 * both write into the *same* `custom_css_code` field, so each writes its own block delimited by a
 * comment marker (`mergeCssBlock()`) rather than blindly overwriting the whole field — re-running
 * one doesn't erase the other, and neither clobbers anything an admin might have added by hand
 * outside those markers.
 *
 * The logo itself is confirmed in GLPI's own `_base.scss` to be entirely CSS-custom-property-driven
 * (`.glpi-logo { background: var(--glpi-logo) no-repeat; }`, with `--glpi-logo-reduced` for the
 * collapsed sidebar state) — the same "override a CSS variable, not an arbitrary DOM selector"
 * approach this class already used for the primary color, confirmed more stable than targeting
 * GLPI's markup directly. Embedded as a `data:` URI (base64) rather than uploaded as a `Document` —
 * self-contained in the same field GLPI already stores this kind of customization in, no separate
 * file storage/serving path to keep in sync. Login-page-specific logo variables are deliberately
 * not touched: `custom_css_code` only applies once inside an authenticated entity context, by which
 * point the login screen is already behind the user.
 */
class BrandingBuilder
{
    private const COLOR_BLOCK_KEY = 'branding-color';

    private const LOGO_BLOCK_KEY = 'branding-logo';

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
     * Overrides Tabler's (GLPI's admin theme) primary-color CSS custom properties.
     */
    private function buildColorCss(string $color): string
    {
        [$r, $g, $b] = $this->hexToRgb($color);

        return ":root { --tblr-primary: {$color} !important; --tblr-primary-rgb: {$r}, {$g}, {$b} !important; }";
    }

    /**
     * Overrides the header/sidebar logo (`--glpi-logo`) and its collapsed-sidebar variant
     * (`--glpi-logo-reduced`, confirmed in `_global-menu.scss`) with the uploaded image.
     */
    private function buildLogoCss(string $dataUri): string
    {
        $escaped = str_replace('"', '\\"', $dataUri);

        return ":root { --glpi-logo: url(\"{$escaped}\") !important; --glpi-logo-reduced: url(\"{$escaped}\") !important; }";
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

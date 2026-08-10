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
 * Turns a Config's branding settings (primary color) into a real per-entity CSS override, using
 * GLPI's own built-in `Entity::enable_custom_css`/`custom_css_code` mechanism (Configuration >
 * Entités > onglet général, "Personnalisation CSS") — no file writes, no touching GLPI's own
 * static assets, just data already natively supported by GLPI for exactly this purpose.
 */
class BrandingBuilder
{
    /**
     * @param int[] $entityIds
     */
    public function apply(Config $config, array $entityIds): bool
    {
        if (empty($config->fields['branding_enabled'])) {
            return false;
        }

        $color = (string) ($config->fields['branding_primary_color'] ?? '#206bc4');
        $css = $this->buildCss($color);

        foreach ($entityIds as $entityId) {
            (new Entity())->update([
                'id' => $entityId,
                'enable_custom_css' => 1,
                'custom_css_code' => $css,
            ]);
        }

        return true;
    }

    /**
     * Overrides Tabler's (GLPI's admin theme) primary-color CSS custom properties.
     */
    private function buildCss(string $color): string
    {
        [$r, $g, $b] = $this->hexToRgb($color);

        return ":root { --tblr-primary: {$color} !important; --tblr-primary-rgb: {$r}, {$g}, {$b} !important; }";
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

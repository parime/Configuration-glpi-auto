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

/**
 * Turns on `custom_palette_enabled` into a real GLPI 11 custom theme (Configuration > Générale >
 * Apparence's "palette" dropdown, and every user's own "Personnalisation" preference tab) instead
 * of `BrandingBuilder`'s per-entity `custom_css_code` override — a different, complementary
 * mechanism confirmed directly against this GLPI 11.0.8 install's own `Glpi\UI\ThemeManager`:
 *
 * - `ThemeManager::getCustomThemes()` scans `GLPI_THEMES_DIR` (`files/_themes/`) for `*.scss`
 *   *or* `*.css` files and lists each as a selectable theme, using a `/* theme-name: ... *\/`
 *   comment for its display name and a literal `$is-dark: true;` line (regex-matched on raw file
 *   text, not real SCSS parsing) to flag it as a dark theme.
 * - **Confirmed the hard way**: `Theme::getPath()` unconditionally appends `.scss` regardless of
 *   which extension was actually found — writing a `.css` file crashed *every* page on the whole
 *   instance (`Safe\filemtime()` fatal on the wrong, nonexistent `.scss` path,
 *   `ThemeManager::getCustomThemesPaths()` is called from `Html::includeHeader()` on every
 *   request, login page included). The file must be named `.scss` even though its *content* here
 *   is plain CSS — GLPI never actually compiles it, `ThemeManager` only greps/serves the raw text.
 * - Rules must be scoped under `:root[data-glpi-theme="{key}"]` (confirmed in GLPI's own native
 *   palette partials, e.g. `_midnight.scss`) rather than a bare `:root` — the base "auror" theme's
 *   `:root` loads unconditionally, so a custom theme only needs to override what it wants to
 *   change; everything else falls through to auror's own values.
 * - Making the theme the instance-wide *default* (not just an available option nobody discovers)
 *   goes through `\Config::setConfigurationValues('core', ['palette' => $key])` — `palette` is one
 *   of GLPI's generic `user_pref_field` entries (`glpi_configs` 'core' context holds the
 *   instance-wide default for any user whose own `glpi_users.palette` is `NULL`), the exact same
 *   mechanism this plugin already uses for other GLPI-core defaults elsewhere
 *   (`GeneralSettingsBuilder`). Confirmed end-to-end: a fresh session with no personal palette
 *   preference picks up `data-glpi-theme` from this config value.
 *
 * Deliberately reuses `branding_primary_color` (the same color `BrandingBuilder` already applies
 * per-entity) rather than a second color picker — one "what's your brand color" question, applied
 * through both mechanisms. `BrandingBuilder` forces the look on everyone in a given entity with no
 * action needed; this one only sets the *default* a user gets before personally changing their own
 * preference — genuinely complementary, not redundant.
 *
 * `native_palette` (added alongside `custom_palette_enabled`) is the simpler alternative: instead
 * of generating a file, just point `core.palette` directly at one of GLPI's own 17 built-in
 * palette keys (`Glpi\UI\ThemeManager::getCoreThemes()`, no plugin-owned file at all) — for an
 * admin who just wants "Midnight" or "Purple Haze" instance-wide rather than a brand color.
 * Mutually exclusive with the custom palette in the wizard's own UI (one wins client-side); if
 * both were somehow submitted, custom wins here since it reflects a more specific admin choice
 * (an actual chosen color) than picking from a fixed native list.
 */
class PaletteBuilder
{
    private const THEME_KEY = 'cga_custom';

    // Same rationale as BrandingBuilder::DARK_FG/LIGHT_FG — GLPI's own default --tblr-primary-fg,
    // reused here so a color close to GLPI's own default resolves to the exact same foreground.
    private const DARK_FG = '#1e293b';

    private const LIGHT_FG = '#ffffff';

    public function apply(Config $config): bool
    {
        if (!empty($config->fields['custom_palette_enabled'])) {
            $color = (string) ($config->fields['branding_primary_color'] ?? '#206bc4');
            $scss = $this->buildThemeFile($color);

            $path = GLPI_THEMES_DIR . '/' . self::THEME_KEY . '.scss';
            file_put_contents($path, $scss);

            \Config::setConfigurationValues('core', ['palette' => self::THEME_KEY]);

            return true;
        }

        $nativePalette = (string) ($config->fields['native_palette'] ?? '');
        if ($nativePalette !== '') {
            \Config::setConfigurationValues('core', ['palette' => $nativePalette]);

            return true;
        }

        return false;
    }

    private function buildThemeFile(string $color): string
    {
        [$r, $g, $b] = $this->hexToRgb($color);
        $fg = $this->contrastingForeground($r, $g, $b);
        $key = self::THEME_KEY;

        return <<<SCSS
        /* theme-name: Personnalisée */
        :root[data-glpi-theme="{$key}"] {
          --tblr-primary: {$color};
          --tblr-primary-rgb: {$r}, {$g}, {$b};
          --tblr-primary-fg: {$fg};
          --glpi-mainmenu-bg: {$color};
          --glpi-mainmenu-fg: {$fg};
          --glpi-mainmenu-fg-muted: {$fg}99;
        }

        SCSS;
    }

    /**
     * Same perceptual-luminance heuristic as `BrandingBuilder::contrastingForeground()` — not
     * factored into a shared helper for a 2-caller, 3-line formula, matching this plugin's existing
     * preference for small local duplication over an extra abstraction layer (every builder already
     * keeps its own `getOrCreate()` rather than sharing a base class).
     */
    private function contrastingForeground(int $r, int $g, int $b): string
    {
        $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;

        return $luminance > 149 ? self::DARK_FG : self::LIGHT_FG;
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

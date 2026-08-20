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

        // Neither toggle is on (custom palette unchecked, or "Aucune" selected in the native
        // dropdown): confirmed real bug (2026-08-20) — this branch used to just `return false`
        // without touching `core.palette` at all whenever `$nativePalette === ''`, so re-running
        // the wizard after having previously checked "menu bleu"/picked a native palette left the
        // instance-wide default stuck on whatever was applied last run instead of actually
        // reverting to GLPI's own native default (auror). `core.palette` must be *actively* reset
        // to '' here — an empty value is what GLPI itself treats as "no override", same value the
        // "Aucune (garder Auror...)" option already sends — rather than skipped, so unchecking is a
        // real undo of a previous run, not just a no-op that leaves the old choice in place.
        $nativePalette = (string) ($config->fields['native_palette'] ?? '');
        \Config::setConfigurationValues('core', ['palette' => $nativePalette]);

        // Also drop the previously-generated custom theme file, if any: leaving it in
        // `GLPI_THEMES_DIR` after moving away from it serves no purpose (GLPI's `ThemeManager`
        // keeps listing it as a selectable theme in every user's own "Personnalisation" preference
        // tab even once it's no longer the instance-wide default) and is real, unnecessary leftover
        // state from a previous run — same "unchecking must undo, not just stop re-doing" fix as
        // the config reset above.
        $path = GLPI_THEMES_DIR . '/' . self::THEME_KEY . '.scss';
        if (is_file($path)) {
            unlink($path);
        }

        return $nativePalette !== '';
    }

    private function buildThemeFile(string $color): string
    {
        [$r, $g, $b] = $this->hexToRgb($color);
        $fg = $this->contrastingForeground($r, $g, $b);
        $key = self::THEME_KEY;

        // Sidebar submenu hover state — two *different* rules involved, both confirmed live via
        // getComputedStyle()/CSSOM inspection on a real hovered menu item, neither guessed:
        //
        // 1. Tabler's own generic `.dropdown-item:hover` (used by ordinary dropdowns elsewhere in
        //    the UI) reads `--tblr-dropdown-link-hover-color`/`-bg`, falling through to
        //    `--tblr-primary` + a semi-transparent black overlay when unset. Pinned explicitly
        //    below.
        // 2. GLPI's *own* sidebar-specific override — `.sidebar #navbar-menu .nav-item ...
        //    .dropdown-item:hover` / `.nav-item:hover .nav-link` (in its compiled
        //    css_glpi.min.css) — hardcodes `color: var(--tblr-primary)` directly, bypassing (1)
        //    entirely: an ID selector (`#navbar-menu`) wins over Tabler's plain-class rule
        //    regardless of source order or the variable pin above, confirmed by finding it via
        //    `document.styleSheets` when pinning (1) alone had no visible effect.
        //
        // Both rules are harmless in stock GLPI, where `--tblr-primary` (bright accent) and
        // `--glpi-mainmenu-bg` (dark neutral) are different colors, so hovered text reads clearly
        // against its own slightly-darkened background. Here both are driven by the *same*
        // admin-chosen brand color, so hovered text converged on nearly the same hue as its own
        // background — illegible (confirmed: a real screenshot showed unreadable menu labels on
        // hover). `!important` on override (2) is deliberate, not a shortcut: it targets a specific
        // color GLPI's own core CSS hardcodes for exactly this element, which no amount of
        // additional selector nesting on our side can outrank without duplicating GLPI's own
        // internal markup structure (`#navbar-menu` etc.) as brittle guesswork.
        //
        // 3. A third, *independent* bug in the same family, found afterward on a real screenshot:
        //    a top-level item whose submenu is expanded (Bootstrap's `.show`/`.active` class on the
        //    `.nav-link` itself, applied regardless of :hover) is styled by GLPI core as
        //    `color: color-mix(in srgb, var(--tblr-primary), transparent 10%)` — 90%-opacity
        //    primary text. In stock GLPI that reads fine against the dark neutral sidebar; here,
        //    with the sidebar background *equal to* `--tblr-primary`, it renders the label at ~90%
        //    opacity of its own background color — confirmed via getComputedStyle() showing the
        //    label's text color and the sidebar's background color are the same hue, and via a
        //    screenshot showing the "Configuration" label fully invisible the moment its own
        //    submenu is open (i.e. more visible with the mouse away from the menu than on it,
        //    exactly backwards from what a user expects of a hover/active state).
        $hoverOverlay = $fg === self::LIGHT_FG ? 'rgba(255,255,255,.15)' : 'rgba(0,0,0,.08)';

        return <<<SCSS
        /* theme-name: Personnalisée */
        :root[data-glpi-theme="{$key}"] {
          --tblr-primary: {$color};
          --tblr-primary-rgb: {$r}, {$g}, {$b};
          --tblr-primary-fg: {$fg};
          --glpi-mainmenu-bg: {$color};
          --glpi-mainmenu-fg: {$fg};
          --glpi-mainmenu-fg-muted: {$fg}99;
          --tblr-dropdown-link-hover-color: {$fg};
          --tblr-dropdown-link-hover-bg: {$hoverOverlay};
        }

        :root[data-glpi-theme="{$key}"] .sidebar #navbar-menu .nav-item .nav-link.active + .dropdown-menu .dropdown-item:hover,
        :root[data-glpi-theme="{$key}"] .sidebar #navbar-menu .nav-item .nav-link.show + .dropdown-menu .dropdown-item:hover,
        :root[data-glpi-theme="{$key}"] .sidebar #navbar-menu .nav-item:hover .nav-link {
          color: {$fg} !important;
          border-left-color: {$fg} !important;
        }

        :root[data-glpi-theme="{$key}"] .sidebar #navbar-menu .nav-item .nav-link.show,
        :root[data-glpi-theme="{$key}"] .sidebar #navbar-menu .nav-item .nav-link.active {
          color: {$fg} !important;
          border-left-color: {$fg} !important;
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

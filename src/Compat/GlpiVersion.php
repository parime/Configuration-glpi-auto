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

namespace GlpiPlugin\Configurationglpiauto\Compat;

/**
 * GLPI 11 and GLPI 12 from a single code base.
 *
 * GLPI 12 typed most properties of its base classes (CommonGLPI::$rightname becomes `string`...),
 * while GLPI 11 leaves them untyped. PHP requires a subclass to repeat EXACTLY the parent's type,
 * so no single declaration works on both versions (fatal error either way). The traits in this
 * namespace therefore exist in two variants (compat/glpi11 and compat/glpi12 at the plugin root),
 * loaded according to the installed version; each class using them provides its own value through
 * a class constant.
 */
final class GlpiVersion
{
    public static function isAtLeast12(): bool
    {
        // '12.0.0-dev' (not '12.0.0'): includes GLPI 12 pre-releases (alpha/beta/rc), which
        // already carry the API changes.
        return defined('GLPI_VERSION') && version_compare(GLPI_VERSION, '12.0.0-dev', '>=');
    }

    /** Absolute path of the trait variants matching the installed GLPI version. */
    public static function compatDir(): string
    {
        return dirname(__DIR__, 2) . '/compat/' . (self::isAtLeast12() ? 'glpi12' : 'glpi11');
    }
}

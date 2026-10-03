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

namespace GlpiPlugin\Configurationglpiauto\Compat\Base;

use CommonDBTM;
use GlpiPlugin\Configurationglpiauto\Compat\GlpiVersion;
use GlpiPlugin\Configurationglpiauto\ConfigHistory;

/*
 * Parent of \GlpiPlugin\Configurationglpiauto\ConfigHistory.
 *
 * Redeclares the GLPI core properties that class overrides: untyped on GLPI 11, typed on GLPI 12
 * (see GlpiVersion). A class, not a trait: on PHP 8.2-8.4 a trait cannot redeclare an inherited
 * property with another value (fatal error, or on PHP 8.2 a slot silently shared with the
 * parent). Values come from the child class's own constants.
 */
if (GlpiVersion::isAtLeast12()) {
    abstract class ConfigHistoryBase extends CommonDBTM
    {
        public static string $rightname = ConfigHistory::RIGHTNAME;
    }
} else {
    abstract class ConfigHistoryBase extends CommonDBTM
    {
        public static $rightname = ConfigHistory::RIGHTNAME;
    }
}

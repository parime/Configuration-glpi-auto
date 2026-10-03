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

// Declares trait GlpiPlugin\Configurationglpiauto\Compat\HasRightname in the variant matching the
// installed GLPI version (see GlpiVersion). Each using class provides its value via RIGHTNAME.
require_once \GlpiPlugin\Configurationglpiauto\Compat\GlpiVersion::compatDir() . '/HasRightname.php';

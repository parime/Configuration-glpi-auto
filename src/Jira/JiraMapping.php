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

namespace GlpiPlugin\Configurationglpiauto\Jira;

use GlpiPlugin\Configurationglpiauto\Import\TicketMapping;

/**
 * Issue #345 : correspondances proposées par défaut entre Jira et GLPI. Depuis l'issue #430, elles
 * sont communes à tous les imports de tickets (TicketMapping) ; cette classe garde le nom utilisé
 * par l'import Jira.
 */
final class JiraMapping extends TicketMapping
{
}

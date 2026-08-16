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

/**
 * PHPUnit bootstrap for `tests/Integration/` — unlike `tests/Unit/` (pure PHP, no GLPI needed),
 * these tests exercise real builders against a real, running GLPI instance: `CommonDBTM::add()`,
 * real DB writes, real rule engine evaluation. Requires this suite to run *inside* an actual GLPI
 * installation with this plugin's own vendor/ present alongside it — either the local
 * `docker-compose.test.yml` stack (`docker compose exec glpi vendor/bin/phpunit -c phpunit.xml`,
 * run from this plugin's own directory under `plugins/configurationglpiauto/`) or GLPI's official
 * `glpi-project/plugin-ci-workflows` reusable CI job, which installs+activates the plugin against a
 * real GLPI+DB container before running PHPUnit — confirmed by reading that workflow's own source
 * (`bin/console database:install` / `plugin:install` / `plugin:activate`, then `vendor/bin/phpunit`
 * from the plugin's own directory) rather than assumed.
 *
 * Bootstrap sequence confirmed by hand against the real `docker-compose.test.yml` container before
 * relying on it here — `require`-ing `inc/includes.php` alone (the legacy front-controller
 * convention used elsewhere in this plugin, e.g. `front/wizard.php`) does *not* establish a DB
 * connection outside of a real HTTP request/Apache context in GLPI 11's Symfony-based runtime.
 * `new \Glpi\Kernel\Kernel()` + `->boot()` (the exact mechanism GLPI's own `bin/console` uses, same
 * no-argument default) does — confirmed live: `global $DB` is a real connected `DBmysql` instance
 * immediately after `boot()`, and every native GLPI class (`Calendar`, `CommonDBTM`...) is usable.
 * Do not hardcode an environment here (e.g. `new Kernel('production')`) — see the comment right
 * before the `boot()` call below for why that broke GLPI's own official CI image. GLPI's own Kernel
 * boot does *not* autoload this plugin's own `GlpiPlugin\Configurationglpiauto\*` classes by itself
 * (no full HTTP request cycle ran the plugin-init hooks) — this plugin's own Composer autoloader is
 * required explicitly as a second step.
 *
 * Paths resolved relatively (`dirname(__DIR__, 3)`, i.e. `tests/` → plugin root → `plugins/` →
 * GLPI root) rather than hardcoded — the local Docker image (`diouxx/glpi`, GLPI at
 * `/var/www/html/glpi`) and GLPI's own CI image (`ghcr.io/glpi-project/githubactions-glpi-apache`,
 * GLPI at `/var/www/glpi`) use different absolute paths, but always the same plugin nesting depth.
 */

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

// No environment hardcoded here — `null` mirrors bin/console's own default (`$options['env'] ??
// null`), letting `Glpi\Application\Environment::get()` resolve it from `GLPI_ENVIRONMENT_TYPE`
// exactly the same way `bin/console database:install`/`plugin:install`/`plugin:activate` already
// did in this same container before this bootstrap runs. Confirmed real bug (2026-08-16): an
// earlier version of this file hardcoded `'production'`, which happened to match on the local
// docker-compose.test.yml image (no GLPI_ENVIRONMENT_TYPE set there) but silently broke on GLPI's
// official CI image, which sets `GLPI_ENVIRONMENT_TYPE=testing` — `Environment::TESTING` redirects
// `GLPI_CONFIG_DIR` to `tests/config/`, so booting under the wrong environment made the Kernel look
// for `config_db.php` in the wrong directory, leaving `global $DB` null (not an exception — GLPI
// tolerates an unconfigured DB so its own install wizard can render) until the first real query
// ("Call to a member function request() on null" deep inside Auth::login()).
$kernel = new \Glpi\Kernel\Kernel();

// `public/index.php` and `bin/console` both assign `$kernel` at their own true top-level script
// scope, which is what makes it a real PHP global — GLPI's own legacy code (e.g. `isAPI()` in
// src/autoload/misc-functions.php, on the CI image's GLPI patch: `global $kernel; $kernel->
// getMainRequest()...`) relies on exactly that. PHPUnit's bootstrap script is different: it is
// `include_once`'d from *inside* a method (`Application::loadBootstrapScript()`), so a plain
// `$kernel = ...` here is only local to that method's scope and never reaches `$GLOBALS` on its
// own — `global $kernel` elsewhere then sees nothing and the call blows up on null. Root-caused
// live (2026-08-16, third CI failure on the same PR) by pulling GLPI's own official CI image
// (`ghcr.io/glpi-project/githubactions-glpi-apache:php-8.2-glpi-11.0.x`) and reproducing the exact
// failure locally against a throwaway MariaDB container — confirmed this exact assignment fixes
// it, and that a previous attempt at fixing this by pushing a request onto the `request_stack`
// service (harmless, but not the actual cause: `Kernel::getMainRequest()` already falls back to
// `Request::createFromGlobals()` on its own when no HTTP request went through `Kernel::handle()`)
// did not, since `$kernel` itself — not the request — was what `global $kernel` couldn't find.
$GLOBALS['kernel'] = $kernel;

$kernel->boot();

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Every integration test needs to act with full rights (CommonDBTM::add() etc. check
// Session::haveRight() internally) — a real logged-in session, not a hand-built $_SESSION array:
// confirmed live that Auth::login() is what actually initializes every session key GLPI's rights
// checks depend on (active entity, active profile...), rather than guessing which subset of keys a
// hand-crafted session would need. Same default superadmin account ("glpi"/"glpi") this plugin's
// own docker-compose.test.yml stack and GLPI's official CI image both provision out of the box.
Session::start();
$auth = new Auth();
if (!$auth->login('glpi', 'glpi', false)) {
    throw new RuntimeException('Integration test bootstrap: could not log in as the default "glpi" superadmin account.');
}

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Sprint 13 — calendar and SLA can differ per site/client (2026-08-10)

Documented as a known gap in ROADMAP.md since Sprint 11: multi-entity mode built exactly one
shared calendar and one shared SLA for the whole tree, whether the admin picked "plusieurs sites
d'une même entreprise" or "plusieurs entreprises clientes" — in reality every client/site tends to
have its own opening hours and its own service commitment.

#### Added
- Steps 3 (Calendrier) and 4 (SLA) each get a "différent par site ou client" toggle, shown for
  any multi-entity mode (not just MSP — widened from the original plan after user feedback: a
  same-company multi-site org can just as reasonably want per-site hours). OFF (default): exactly
  today's shared behavior, untouched. ON: one panel per top-level tree node (client/site), each
  with its own enabled/days/hours (calendar) or enabled/TTO/TTR/astreinte (SLA) — a site with no
  override falls back to the shared calendar/SLA, unchanged from before this sprint.
- No new database column: the override lives inside the existing `entity_tree` JSON column, as an
  optional `settings.calendar`/`settings.sla` object on top-level nodes only (see
  `Config::sanitizeTree()`) — reuses the tree's existing single-hidden-field sync mechanism
  instead of a second data channel that would need to stay aligned with the tree by index.
  `_entity_structure_fields.html.twig` exposes `window.cgaTree` (live reference) and a
  `cga:tree-changed` DOM event so steps 3/4 can react when a client is added/renamed/removed in
  step 2 without a tighter coupling between the two scripts.
- `CalendarBuilder`/`SlaBuilder` gain `buildFromOverride()` (same logic as `build()`, from a
  per-client settings array instead of `$config->fields`, named after the client) and `assignMap()`
  (a different calendar/SLA per entity instead of one for all of them) — the existing `build()`/
  `assignToEntities()` are untouched, still the shared-path default.
- `front/wizard.php`'s finish handler now pairs each `EntityBuilder::build()` result with its
  matching top-level tree node (same index) to pick override vs. shared per client — the shared
  calendar/SLA is still built eagerly up front (not lazily), so which pairing every non-overridden
  client falls back to no longer depends on iteration order.

Validated against the real GLPI 11.0.8 test instance via Playwright (ephemeral
`mcr.microsoft.com/playwright` container on the `docker-compose.test.yml` network, no browser
installed on the host): built a 2-client MSP tree, gave each client its own calendar hours and SLA
hours, left a third client on the shared settings, submitted, and confirmed in the database that
each overridden client got its own `glpi_calendars`/`glpi_slms` row with the right hours and the
right entity → calendar linkage, while the non-overridden client correctly pointed at the shared
"Horaires standard" calendar.

Also researched (web search, sources in ROADMAP.md) and confirmed with the user: real ITSM
practice defines SLAs per ticket priority (P1-P4), not one flat delay for everything, and GLPI's
own documented mechanism for this — a `RuleTicket` matching on `priority` — is the same primitive
`SlaBuilder` already uses to match on `entities_id`. Deliberately not built in this sprint (would
mean redesigning the SLA data model mid-sprint); captured as the next one in ROADMAP.md instead,
along with a "SLA par défaut vs personnalisé par client" design sketch from the user.

### Sprint 12 — plain-language profiles, no acronyms (2026-08-10)

Testing Sprint 11 surfaced two more issues, both fixed here:

1. Step 1 still listed PME/ETI/MSP as unexplained acronyms — not usable by someone who doesn't
   already know GLPI/business jargon, and the plugin's whole point is to make GLPI configuration
   approachable for novices, not just professionals.
2. Once framework-vs-size was untangled in Sprint 11, PME/ETI/Grande entreprise started returning
   *byte-for-byte identical* suggested defaults — three acronym-labeled options that silently did
   the same thing is worse than clutter, it's actively misleading.

#### Changed
- `ConfigurationProfile::getTypes()` down to 4 plain-French options, no acronyms: "Installation
  simple", "Plusieurs sites ou services (une seule entreprise)", "Plusieurs entreprises clientes
  (infogérance)", "Personnalisé". Each now has a short `description` shown under its label in the
  wizard (e.g. "Un seul site, pas de sous-structure").
- Install/upgrade migration deactivates the old `sme`/`eti`/`enterprise` rows (same
  deactivate-don't-delete approach as Sprint 11's `iso27001`/`itil` cleanup) and renames/inserts
  rows for the surviving 4 types.
- Removed `front/config.php` + `templates/config_form.html.twig`: a second, older single-page
  settings screen (entity mode + tree only, no calendar/SLA/branding/profile) that predates the
  wizard and was still wired to the "configure" wrench icon on Configuration > Plugins — landing
  there instead of the wizard is what "je n'ai qu'un truc dans le paramétrage du plugin" was about.
  `Hooks::CONFIG_PAGE` in `setup.php` now points at `front/wizard.php`, same as the main menu
  entry — a single coherent entry point regardless of how the admin gets there.

#### Fixed
- **`ConfigurationProfile::getSuggestedDefaults('msp')` never actually applied its own SLA
  override.** `['entity_mode' => ...] + $goodPracticeBaseline + ['sla_tto_hours' => 1, ...]` — PHP's
  `+` operator keeps the *left* array's value on a key collision (unlike `array_merge()`), so once
  `$goodPracticeBaseline` had already set `sla_tto_hours` to 4, the trailing `+ [...]` override was
  silently discarded. MSP was suggesting the generic 4h/48h SLA with astreinte off instead of its
  intended 1h/8h with astreinte on. Caught by the Playwright validation added this sprint (see
  below) — switched to `array_merge()`.

Validated with a Playwright script run via an ephemeral `mcr.microsoft.com/playwright` container
joined to the `docker-compose.test.yml` network (no browser installed on the host) against the
real GLPI 11.0.8 test instance: logged in, confirmed the plugin's main menu entry and the
"configure" wrench icon on Configuration > Plugins both open the wizard directly, confirmed the 4
profile labels/descriptions render as expected, confirmed picking "Plusieurs entreprises clientes"
sets `entity_mode=multi_msp` + astreinte checked + SLA 1h/8h, and picking "Plusieurs sites ou
services" sets `entity_mode=multi_same_company` + astreinte unchecked + SLA 4h/48h. Local suite
green (phpunit 5/5, phpstan clean, php-cs-fixer 0 files).

### Sprint 11 — profiles are a size choice, not a framework choice (2026-08-10)

Sprint 10 (below) made profile choice pre-fill later steps, but conflated two different
questions: `getSuggestedDefaults()` treated ITIL and ISO 27001 as if they were org sizes on the
same footing as PME/ETI/Grande entreprise/MSP. Proof it never made sense: `'itil'` and
`'enterprise'` returned *exactly* the same values (same entity mode, same calendar, same SLA
2h/24h) — a coincidence that only happens when a distinction was never really implemented. ITIL
and ISO 27001 are practice frameworks any organization can follow regardless of size, not a size
category — a small company can be ISO 27001 certified, a large one might follow no formal
framework at all.

#### Changed
- `ConfigurationProfile::getTypes()` drops `'iso27001'`/`'itil'` — back to 6 profiles (minimal,
  sme, eti, enterprise, msp, custom). A calendar-scoped SLA *is* the ITIL/ISO27001 baseline, so
  every non-minimal profile now suggests one by default instead of only the "advanced" ones.
- Install/upgrade migration deactivates (`is_active = 0`, not deleted) any existing `iso27001`/
  `itil` profile rows so they stop appearing in the wizard without losing data.
- `ConfigurationProfile::getSearchURL()` now points the plugin's main admin menu entry straight
  at the wizard instead of the generic profile CRUD list (`front/profile.php`) — that list wasn't
  useful as a landing page, the wizard is the actual point of entry.

#### Added
- New `sla_astreinte` setting (wizard step 4): on-call/standby coverage outside opening hours.
  GLPI treats `SLM.calendars_id = 0` as "no calendar" = 24/7 countdown (confirmed in core
  `SLM.php`) — the same mechanism the codebase already used by accident when no calendar existed;
  `SlaBuilder` now uses it deliberately when astreinte is enabled, instead of the built business
  calendar. MSP profile suggests astreinte on by default (round-the-clock contractual coverage is
  characteristic of that business model, not of being "bigger") — every other profile suggests it
  off.
- `ROADMAP.md`: documented two follow-up gaps found while testing this — calendar hours are a
  single begin/end pair applied to every checked day (no per-day hours, no lunch-break split), and
  "multi-entité même entreprise" vs "MSP" have zero behavioral difference today (verified nothing
  in `EntityBuilder`/`CalendarBuilder`/`SlaBuilder`/`BrandingBuilder` reads `entity_mode` besides
  which wizard radio pre-checks) — a real MSP distinction needs per-client calendar/SLA/branding
  and entity rights isolation.

Validated: local suite green (phpunit 5/5, phpstan clean, php-cs-fixer 0 files, `php -l` clean).
Migration verified against the real GLPI 11.0.8 test instance — `iso27001`/`itil` rows correctly
deactivated, `sla_astreinte` column present, plugin reactivates with no errors in
`files/_log/php-errors.log`. Full click-through validated via Playwright in Sprint 12 below (which
is also where that pass caught the MSP astreinte bug this sprint had actually shipped).

### Sprint 10 — Profile choice actually does something (2026-08-10)

Step 1 of the wizard ("Quel profil correspond le mieux à votre organisation ?") has always said
picking a profile would "pré-remplir les prochaines étapes" — until now that was aspirational
text, the choice was only stored, nothing downstream read it.

#### Added
- `ConfigurationProfile::getSuggestedDefaults(string $type): array` — per profile type, a
  starting point for entity mode + calendar + SLA (e.g. "Installation minimale" suggests
  mono-entité and nothing else; "MSP" suggests multi-entité MSP with a tight 1h/8h SLA;
  "ISO 27001" suggests the same org shape as ETI/Enterprise but a much tighter 1h/4h SLA, since
  fast incident acknowledgement is the point of that profile). Deliberately never touches
  `entity_tree` itself — no realistic way to guess real client/site names — only the mode, so
  the admin still builds their own tree in step 2. "Personnalisé" returns no suggestions.
- Picking a profile radio in the wizard now live-applies its suggestions to steps 2-5's fields
  (entity mode, calendar toggle/days/hours, SLA toggle/hours) — a starting point, not a lock-in;
  every field stays a normal input the admin can still change in later steps.

Validated with Playwright against a real GLPI 11.0.8 instance: picking "MSP" set entity_mode to
multi_msp, enabled the calendar, and set SLA to 1h/8h as expected; switching to "Installation
minimale" afterward correctly switched entity_mode back to mono.

## [0.4.0] - 2026-08-10

The arbitrary entity tree editor, plus repo hygiene: branch protection on `main`, and the CI
pipeline actually passing green for the first time (see Sprint 8's entry below for the fixes) —
including reconciling with three Dependabot dependency-update PRs opened once
`.github/dependabot.yml` started working (`actions/checkout` v4→v7, `codecov/codecov-action`
v3→v7, `phpstan/phpstan` ^1.10→^2.2, `squizlabs/php_codesniffer` ^3.7→^4.0), all verified
locally before merging rather than accepted blind.

### Sprint 9 — Arbitrary entity tree, not just uniform levels (2026-08-10)

The entity-structure step's data model changed from "N levels, same shape repeated under every
top-level name" to a genuinely arbitrary tree: any node can have any number of children, at any
depth, independent of its siblings — e.g. "Client A" has 6 children and one of those has 3
children of its own while another has 2, and "Client B" has none.

#### Added
- `Config::getEntityTree()`/`entity_tree` column (JSON, replaces `entity_levels`/`level_labels`/
  `top_level_names`): an array of `{name, children}` nodes, recursively. `prepareInput()`
  sanitizes the whole tree server-side (trims names, drops empty-named nodes, caps depth at
  `MAX_LEVELS`) regardless of what the client sends.
- `_entity_structure_fields.html.twig` rewritten as a real recursive tree editor: each node is a
  row (name input, "+" add-child button, "×" remove button) with its own children indented
  beneath; a top-level "+" adds another root node. The whole tree is serialized to one hidden
  `entity_tree_json` field on every change (rather than kept in sync via indexed input names,
  which would need renumbering siblings on every add/remove at an arbitrary depth). The live
  preview now renders the *exact* tree directly — no more "A"/"B" illustrative approximation,
  since the real shape is always fully known now.
- `EntityBuilder::build()` rewritten to walk the tree recursively; `describe()`/`topEntityIds()`
  updated to the new per-top-level-node result shape (`{name, entities_id, count}`).

Validated by building the exact asymmetric structure from the feature request — one client with
two sub-entities, only one of which has further sub-sub-entities — confirmed correct in
`glpi_entities` (`Entité racine > client 1 > sous test 1-1 > {sous sous test 1-1-1, sous sous
test 1-1-2}`, sibling `sous teste 1-2` with none, siblings `client 2`/`client 3` with none).

### Sprint 8 — SLA step, and CI was never actually green (2026-08-10)

#### Added
- New wizard step "Niveaux de service (SLA)" (now 6 steps, between Calendrier and
  Personnalisation): optional toggle + time-to-own/time-to-resolve delays (hours), creating a
  real GLPI SLM ("SLA standard") with two SLA entries under it.
- `SlaBuilder` (`src/SlaBuilder.php`). Unlike Calendar (a direct `Entity::calendars_id` field),
  GLPI has no per-entity "default SLA" field — confirmed by reading `Entity.php`: the
  `slas_id_tto`/`slas_id_ttr` fields live on `glpi_tickets`, only ever set by the business-rules
  engine. So `assignToEntities()` creates a real `RuleTicket` per entity ("entity is X" →
  "assign these SLAs on ticket creation") instead of an (impossible) Entity update — an initial
  version wrongly assumed a direct Entity field, caught before shipping by actually reading the
  core source rather than guessing from the Calendar precedent.
- Validated as strongly as this plugin's features get: not just a DB read of the created
  `RuleTicket`/`RuleCriteria`/`RuleAction` rows, but creating a **real `Ticket`** in the target
  entity and confirming GLPI's own rules engine auto-populated `slas_id_tto`/`slas_id_ttr` with
  the exact SLA IDs `SlaBuilder` created.

#### Fixed — CI trigger, and the licence-header check's own reference file
- `continuous-integration.yml`/`locales-sync.yml` watched a branch named `develop` in their
  `push`/`pull_request` filters; this repo's actual working branch (established Sprint 1) is
  `dev` — CI had never triggered on a single `dev` push before this, only caught by pushing and
  finding no run at all in `gh run list`, rather than a failed one.
- `tools/HEADER` (used by the reusable GLPI CI workflow's licence-header check, not a check of
  my own) was a fully-formatted PHP `/** ... */` comment block — but the tool that reads it
  (`glpi-project/tools`' `licence-headers-check`) treats the file as **plain text** and wraps it
  itself per file type (`/** */` for PHP, `{# #}` for Twig, `#` for YAML), matching a stripped
  line-by-line comparison against each file's actual header. A fully-formatted reference file
  made every single file compare as "outdated" against itself, and `--fix` (before this was
  understood) wrapped the existing header inside a *second* one, corrupting several files with
  an unterminated comment (caught by `php -l` before it was committed, reverted, redone
  correctly). Root cause confirmed by reading the tool's own comparison source, not guessed.

#### Fixed — the CI pipeline had never actually run successfully, on any commit
Every push (including every release tag so far) failed CI; nothing had surfaced this because
this plugin's actual releases are validated by the separate, real `release.yml` workflow, not
by `continuous-integration.yml`. Root causes, all inherited from the original fictional
scaffold and never exercised before now:
- `composer.json`'s dev-only `vimeo/psalm`/`rector/rector`/`phpmd/phpmd` were never actually
  used by anything (only `phpstan`/`php-cs-fixer` are real, working checks) — worse,
  `vimeo/psalm` pulls in `amphp/amp` as a transitive dependency, which **fatally conflicts**
  with GLPI core's own `amphp/amp` copy the moment both get autoloaded in the same PHP process
  (`Cannot redeclare Amp\delay()`), breaking the reusable `glpi-project/plugin-ci-workflows`
  job outright — every other job in the workflow depends on it, so nothing downstream ever ran.
  Removed all three; `.github/workflows/continuous-integration.yml`'s `psalm`/`rector` jobs
  removed to match.
- `phpstan.neon` had `paths: []` (from an earlier fix that scoped out all GLPI-dependent code
  without leaving anything in) — PHPStan itself errors on an empty `paths` list rather than
  analysing nothing. Added `tests/Unit` (see below) as a real, non-empty, analysable path.
- `.php-cs-fixer.php`'s large hand-written rule list referenced half a dozen renamed/nonexistent
  PHP-CS-Fixer option and rule names (e.g. `trailing_comma_in_singleline`,
  `native_type_declaration_spacing`, `braces.position_after_functions`,
  `cast_spaces.spacing`, `function_declaration.closure_fn_spacer`) — every one a hard error, not
  a style violation. Also had `array_syntax`/`list_syntax` set to `'long'`, which would have
  silently rewritten this entire codebase's `[]` arrays to `array()` the first time anyone ran
  `--fix` instead of `--dry-run`. Replaced with a much smaller, verified-correct rule set.
- The `validation` job's "Check for duplicates" step ran `composer dups`, not a real Composer
  command (removed); its `dependabot` job ran `composer audit` with no prior `composer install`
  step, which needs a lockfile/installed packages to audit against (added the missing install
  step); its YAML validation used yamllint's strict defaults (80-char lines, mandatory `---`,
  no `on:` truthy keys) against real GitHub Actions files that violate all three by convention
  — added `.yamllint.yml` relaxing exactly those three, which is standard practice for
  repos with Actions workflows, not a weakening of real checks. `.github/dependabot.yml` had a
  fictional `npm` ecosystem entry (no `package.json`/JS anywhere in this repo) and two invalid
  keys (`pr-priority`, `milestone: 0`) — removed.
- `tests/Unit/EntityBuilderTest.php` + `phpunit.xml.dist`: the `phpunit` CI job had nothing to
  run against (zero GLPI-independent code existed). Added real tests for `EntityBuilder`'s two
  pure static helpers (`describe()`, `topEntityIds()`) — confirmed these genuinely run without
  a GLPI bootstrap, unlike everything else in this plugin.

## [0.3.0] - 2026-08-10

The wizard's branding step (real primary-color customization).

### Sprint 7 — Branding step (2026-08-10)

#### Added
- New wizard step "Personnalisation graphique" (now 5 steps, between Calendrier and
  Récapitulatif): optional toggle + color picker to apply a primary color to the created
  entities' interface, using GLPI's own built-in `Entity::enable_custom_css`/`custom_css_code`
  mechanism — no file writes, no touching GLPI's static assets.
- `BrandingBuilder` (`src/BrandingBuilder.php`): generates a `:root { --tblr-primary: ...;
  --tblr-primary-rgb: ...; }` override and sets it as the target entities' custom CSS.
- `Config` gained `branding_enabled`/`branding_primary_color`, migrated in for existing
  installs.

Validated against a real GLPI 11.0.8 instance, including a visual check (not just a DB read):
after applying a red (`#ff0000`) primary color via the wizard, `.btn-primary`'s actual computed
`background-color` was confirmed `rgb(255, 0, 0)`, and a screenshot shows the "Ajouter" button,
active-menu highlight, and user avatar all rendering in red.

## [0.2.0] - 2026-08-10

Real entity creation, the setup wizard, and the calendar step — see below for the sprint-by-sprint
detail.

### Sprint 6 — Calendar step (2026-08-10)

#### Added
- New wizard step "Calendrier" (now 4 steps: Profil → Entités → Calendrier → Récapitulatif):
  optional toggle to create a real GLPI `Calendar` with one `CalendarSegment` per selected
  weekday (Lun-Ven 08:00-18:00 by default), assigned to every top-level entity the wizard
  created (or to the root entity in mono-entité mode).
- `CalendarBuilder` (`src/CalendarBuilder.php`): idempotent (reuses a calendar of the same
  name, skips a segment that already exists at that day/time).
- `EntityBuilder::build()` return shape changed from a flat name list per branch to
  `['names' => [...], 'entities_id' => int]` so the wizard can hang the calendar off the right
  entity; `EntityBuilder::topEntityIds()` added for that lookup, `describe()` updated to match.
- `Config` gained `calendar_enabled`/`calendar_name`/`calendar_days`/`calendar_begin`/
  `calendar_end`, migrated in for existing installs.

Validated against a real GLPI 11.0.8 instance: enabling the calendar step with Lun/Mar/Mer
09:00-17:00 produced a real `Calendar` row named "Horaires Bureau" with exactly those three
`CalendarSegment` rows, and the mono-entité root entity's `calendars_id` pointing at it (GLPI
normalizes `calendars_strategy` to `0` — "see calendars_id" — for any non-inherited, non-24/7
value; confirmed by reading `Entity::getSpecificValueToDisplay()`'s own resolution logic).

### Sprint 5 — Real, named entity branches (2026-08-10)

#### Added
- Optional "real names" field on the entity-structure step (wizard and standalone settings
  screen): a dynamic add/remove list — client names in MSP mode, first-level entity names in
  same-company mode (e.g. real site names). `EntityBuilder` now creates one full branch per
  name instead of a single generic-labelled template branch; still idempotent (re-applying
  after adding a name only creates what's missing). Leaving the list empty keeps the previous
  behaviour (one generic template branch) unchanged.
- The live preview now renders the *exact* real tree (one line per real name) once names are
  given, instead of the illustrative "A"/"B" two-example approximation — which is now only
  shown while no real names have been entered yet.
- `Config.top_level_names`, migrated in for existing installs.

Validated against a real GLPI 11.0.8 instance: entering three client names in the wizard
(Entreprise Dupont/Martin/Petit) produced exactly three full branches in `glpi_entities`; leaving
the field empty still produces the single generic-template branch as before.

### Sprint 4 — Setup wizard (2026-08-10)

#### Added
- `front/wizard.php` + `templates/wizard.html.twig`: the actual "assistant graphique" from the
  plugin's vision, a 3-step JS-driven wizard (progress bar, Précédent/Suivant, no page reload
  between steps) — Profil (pick a `ConfigurationProfile`) → Entités (the mode/levels/labels
  live-preview screen, reused as-is) → Récapitulatif (summary of both, "Terminer" creates the
  entities for real). Reachable from a new "Lancer l'assistant" button on the profiles list.
- `templates/_entity_structure_fields.html.twig`: the entity-structure fields + live preview
  extracted out of `config_form.html.twig` into a shared partial so the wizard and the
  standalone settings screen (kept for quick later adjustments, per explicit request — the
  wizard isn't the only way in) render and behave identically with one copy of the logic.
- `Config.configurationprofiles_id`: records which profile the wizard's step 1 selected,
  migrated in for existing installs via `Migration::addField()`.
- Validated end-to-end with Playwright against a real GLPI 11.0.8 instance: full 3-step
  navigation, profile pick, MSP mode with 2 custom level labels, summary correctly reflecting
  every choice, and "Terminer" producing exactly `Client > Site > Departement` in
  `glpi_entities` with `configurationprofiles_id` saved.

#### Fixed
- `ConfigurationProfile::find()` in `front/wizard.php` was called with `['sort_order' => 'ASC']`
  as the order argument — `CommonDBTM::find()` passes `$order` straight through as GLPI query
  builder `ORDERBY` criteria, which expects a list of `"field ASC"` strings, not an associative
  array. The associative form silently ordered by the literal column name `ASC` (which doesn't
  exist), 500ing with `Unknown column 'ASC' in 'ORDER BY'`. Fixed to `['sort_order ASC']`.
- GLPI caches compiled Twig templates under `files/_cache` — edits to `.html.twig` files are not
  picked up automatically on the test image, which briefly made it look like the extracted
  `_entity_structure_fields.html.twig` partial wasn't being included. Documented in
  `docker-compose.test.yml`: clear `files/_cache` after any template change.

### Sprint 3 — Apply the entity structure for real (2026-08-10)

#### Added
- `EntityBuilder` (`src/EntityBuilder.php`): turns a saved `Config` into real GLPI `Entity`
  records, matching the settings screen's live preview shape exactly — mono-entité creates
  nothing (the GLPI root entity already is the single entity), multi-entité (same company)
  creates one template chain (one entity per configured level), multi-entité (MSP) nests that
  same chain under a "Client" placeholder entity. Idempotent: re-applying after tweaking a
  level's label reuses existing entities instead of duplicating them.
- "Enregistrer et créer les entités" button on the settings screen (`front/config.php`), next
  to the existing "Enregistrer" (save-only), with a confirmation prompt since it creates real
  data. Validated against a real GLPI 11.0.8 instance: applying `multi_same_company` with
  levels `Site`/`Service` created exactly `Entité racine > Site > Service` in
  `glpi_entities`, confirmed a second identical apply created zero duplicates.

## [0.1.0] - 2026-08-10

First real release. Nothing before this tag ever installed — see the historical note below.

### Sprint 2 — Entity structure settings (2026-08-10)

#### Added
- `Config` (`src/Config.php`): plugin-wide settings screen (Configuration > Plugins > wrench
  icon), a single settings row. First setting: the entity structure the future entity-creation
  wizard will build — mono-entité, multi-entité (same company), or multi-entité (MSP managing
  several client companies) — with a configurable number of sub-entity levels (up to 5) and a
  label per level. Does not create any `Entity` yet; only records the shape for the wizard.
- `front/config.php` + `templates/config_form.html.twig`: settings form (Bootstrap
  card/form-check style, same visual language as remise-glpi/glpi-vulnerability-manager) with a
  live, client-side tree preview that re-renders on every change (mode switch, level count,
  level labels) with no page reload or server round-trip — validated interactively with
  Playwright against a real GLPI 11.0.8 instance.
- `Profile::RIGHT_CONFIG` (`plugin_configurationglpiauto_config`): dedicated right for this
  settings screen, granted to Super-Admin by default, same registration pattern as
  `RIGHT_PROFILE`.

### Note on the [1.0.0] entry below

The `[1.0.0] - 2026-08-07` entry that used to be here described a fully-featured release. It did
not correspond to the actual state of the repository: on 2026-08-10 the codebase consisted almost
entirely of documentation and CI scaffolding — `composer.json` referenced non-existent packages,
`phpstan.neon` pointed at files that didn't exist, and every class referenced by `setup.php` /
`hook.php` beyond a handful was never written, making the plugin impossible to install. Sprint 1
(below) rebuilds real, validated foundations from that point. The entry is kept below, relabeled,
for history rather than deleted outright.

### Sprint 1 — Infra & first real entity (2026-08-10)

#### Added
- Real plugin bootstrap (`setup.php`/`hook.php`) following the modern GLPI 11 plugin functions
  convention (`plugin_init_*`, `plugin_version_*`, `plugin_*_install/uninstall`,
  `plugin_*_check_prerequisites/check_config`), validated against a real GLPI 11.0.8 instance.
- `ConfigurationProfile` (`src/ConfigurationProfile.php`): the first real, working entity — a
  catalog of predefined configuration profiles (Installation minimale, PME, ETI, Grande
  entreprise, MSP, ISO 27001, ITIL, Personnalisé), with full CRUD (list, add, edit, delete) via
  `front/profile.php` / `front/profile.form.php`.
- `Installer` (`src/Install/Installer.php`): creates the plugin's schema and seeds the eight
  default profiles on install; drops it cleanly on uninstall.
- `Profile` (`src/Profile.php`): registers a dedicated `plugin_configurationglpiauto_profile`
  right in GLPI's standard profile matrix, granted to Super-Admin by default.
- `docker-compose.test.yml`: local GLPI + MariaDB stack for manual/CI-independent validation.

#### Fixed
- `composer.json` referenced `glpi-project/glpi` (not a real Packagist package — GLPI core is
  provided by the host instance, not a Composer dependency) and mis-named/irrelevant dev tooling
  (`rectorphp/rector`, `php-compatibility/php-compatibility`, `nunomaduro/larastan` — a Laravel
  tool with no relevance to a GLPI plugin). Fixed to only require what actually resolves.
- `phpstan.neon` included a non-existent stub file and a non-existent
  `vendor/glpi-project/tools/phpstan/glpi.php`. Rescoped to GLPI-independent code only (none
  exists yet, same constraint documented on the sibling glpi-vulnerability-manager plugin), and
  the CI workflow's PHPStan step updated to match.
- Namespacing the profile entity under `...\Entity\ConfigurationProfile` triggered GLPI's
  automatic table-name derivation to treat the `Entity` segment as GLPI's own core `Entity`
  class, producing a bogus many-to-many relation table name
  (`glpi_plugin_configurationglpiauto_entities_configurationprofiles`) instead of
  `..._profiles`, and fatally breaking install. Fixed by flattening the namespace and overriding
  `getTable()` explicitly — same lesson already documented on glpi-vulnerability-manager.
- Reusing GLPI's core `config` right for the profile CRUD screens 403'd on "Ajouter" for the
  built-in super-admin: `config` only ever grants READ/UPDATE (it models a per-entity singleton),
  never CREATE/PURGE. Fixed by introducing a dedicated plugin right (see `Profile` above).
- The `itemlink` search-option datatype on the profile's `name` column resolves its target
  itemtype via a reverse table-name lookup (`getItemTypeForTable()`), which fails for any class
  with a manually-overridden `getTable()` (see above) — surfaced as a 500
  (`Class name must be a valid object or a string` in `SQLProvider::giveItem()`). Fixed by
  declaring `itemtype` explicitly in the search option instead of relying on the reverse lookup.

---

## [1.0.0] - 2026-08-07 (historical — description did not match the actual code, see note above)

### Added
- First stable release of Configuration GLPI Auto plugin
- All core features as described in the original README
- Complete wizard interface
- All deployment profiles (PME, ETI, Enterprise, MSP, ISO 27001, ITIL)
- All modules (Configuration, Calendars, SLA, Entities, Service Catalog, Templates, etc.)
- Audit mode for existing instances
- Blueprint export/import functionality
- Intelligent locations assistant with geocoding
- Comprehensive security features (dry run, backup, rollback)
- Detailed reporting system

### Technical Features
- Full PSR-12 compliance
- SOLID architecture principles
- Service-oriented design
- Repository pattern for data access
- DTO pattern for data transfer
- Dependency injection
- Centralized configuration
- Complete test coverage
- Internationalization support (French, English)

---

## Template Sections for Future Releases

---

### [Added]
- New features
- New modules
- New profiles
- New integrations

### [Changed]
- Breaking changes
- Behavior changes
- API changes
- Performance improvements

### [Fixed]
- Bug fixes
- Security fixes
- Performance fixes

### [Removed]
- Deprecated features
- Removed functionality
- Breaking changes

### [Security]
- Security vulnerabilities fixed
- Security improvements

### [Deprecated]
- Features that will be removed in future versions

---

## Notes

- **Breaking Changes** are marked with `BREAKING CHANGE:` prefix in commit messages
- **Security Fixes** are marked with `SECURITY:` prefix in commit messages
- **Deprecations** are marked with `DEPRECATED:` prefix in commit messages

---

[Unreleased]: https://github.com/parime/Configuration-glpi-auto/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.4.0
[0.3.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.3.0
[0.2.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.2.0
[0.1.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.1.0

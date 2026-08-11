# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

## [0.6.0] - 2026-08-11

Sprints 21-23: Urgency/Observers/Location hidden on GLPI's native self-service forms, French
public holidays seeded on the calendar, and a 23-form Service Catalog on GLPI 11's native Form
system (ROADMAP item 5, closing a long-standing gap) — plus a plugin logo and a vendor-neutral
naming/icon follow-up on the catalog. No breaking changes.

### Fixed — Service Catalog: vendor-neutral naming and real icons (2026-08-11)

Direct follow-up to Sprint 23, from user feedback on the real catalog screenshot: one service name
and one category-tree branch name referenced Microsoft (Teams/SharePoint/Microsoft 365) — not every
organization is on Microsoft 365. Separately, every rubric shared the same generic default icon.

#### Changed
- "Microsoft 365 / Workspace" (an `ITILCategory` branch, part of the tree since Sprint 17) renamed
  to "Messagerie & Collaboration"; "Demande d'accès à un espace Teams / SharePoint" (Service
  Catalog) renamed to "Demande d'accès à un espace collaboratif d'équipe". Both are plugin-created
  objects matched by exact name elsewhere in the code, so an already-created row has to be renamed
  in the DB too (`Installer.php` migration), not just in the source constants, or the next wizard
  run would create a duplicate instead of reusing it. Also fixes up the `DropdownTranslation` rows
  for this category — both `name` (only set once at creation) and `completename` (GLPI
  auto-derives this second breadcrumb-path translation from `name`, so it needed its own explicit
  `DropdownTranslation::regenerateAllCompletenameTranslationsFor()` call, not just an UPDATE).
- Each of the 11 Service Catalog rubrics — and every service form within it — now gets a real
  identifying icon (computer for IT & SI, car for Flotte, building for Bâtiment, shield for
  Sécurité, people for RH...) instead of GLPI's generic default. Uses GLPI's own bundled
  illustration catalog (`Glpi\UI\IllustrationManager`, `public/lib/glpi-project/illustrations/
  icons.json`) — no custom SVG import needed. Backfilled via migration for rubrics/forms created
  before this fix, guarded to skip anything already customized by hand.

Validated against the real GLPI 11.0.8 test instance with the real `post-only` (Self-Service)
account: `/ServiceCatalog` screenshot confirms distinct, recognizable icons per rubric and the
vendor-neutral names; DB confirms the renamed `ITILCategory`'s `name` and `completename`
translations both read correctly. Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer
clean).

### Sprint 23 — Service Catalog (ROADMAP item 5) (2026-08-11)

Second item out of the real-GLPI-export audit and the biggest remaining ROADMAP gap: a self-service
catalog, "never done" since the original roadmap. GLPI 11 has an entirely native, dedicated system
for this (`Glpi\Form\Form`/`Question`/`Category`, `/Form/Render/N` — same subsystem
`HelpdeskFormBuilder`, Sprint 21, works with, not the classic `ITILTemplate`).

#### Changed
- New `ServiceCatalogBuilder`: for each selected `category_branches` branch, creates/reuses a
  `Glpi\Form\Category` catalog rubric (same icon/name as `CategoryBuilder`'s branch), then 23
  service forms across 7 branches (IT & SI, Bâtiment, Flotte, RH, Achats, Sécurité, Services
  Généraux) — adapted from the production export, generalized rather than copied verbatim (dropped
  company-specific ones like "télétravail depuis l'étranger").
- Each service form has only Title + Description (`Question`, same minimal philosophy as
  `HelpdeskFormBuilder`) — **no category field shown to the user**. The resulting ticket's category
  is fixed per service via `FormDestinationTicket`'s `ITILCategoryFieldConfig` with the
  `SPECIFIC_VALUE` strategy, pointing at the matching `ITILCategory` `CategoryBuilder` already
  built (resolved by name at build time, not hardcoded IDs — a service whose target category can't
  be resolved, e.g. its branch was deselected, is skipped rather than created without one).
  Confirmed via source (`Glpi\Form\Destination\CommonITILField\ITILCategoryFieldStrategy`) that the
  alternative `LAST_VALID_ANSWER` strategy would instead read from a "Category"-type question —
  deliberately not used, since picking a specific *service* already implies the category.
- `AbstractConfigField::getKey()` (`Toolbox::slugify(static::class)`) used to build the destination
  `config` JSON key at runtime instead of hardcoding the string — matches exactly what GLPI's own
  admin UI produces.
- `Form::add()` already creates a first `Section` and a default `FormDestination` on its own
  (confirmed in `Form::post_addItem()`) — reused instead of hand-building them.
- New `service_catalog_enabled` toggle, a new wizard step 6 (right after Catégories, since it
  depends on that tree already existing) — `STEP_COUNT` 10→11, all later steps renumbered.

Validated against the real GLPI 11.0.8 test instance: ran the wizard, confirmed in DB 23
`glpi_forms_forms` rows across 11 `glpi_forms_categories` rubrics, and each
`glpi_forms_destinations_formdestinations.config` pointing at the correct `specific_itilcategory_id`
(spot-checked 4). **End-to-end test with the real `post-only` (Self-Service) account**: opened
`/Form/Render/7` ("Réinitialisation de mot de passe"), confirmed only Title + Description are shown
(screenshot), submitted it, and confirmed in DB the resulting `Ticket` row landed with
`itilcategories_id` correctly set to "Mot de passe & Réinitialisation" (id 136) — automatically,
with no category ever shown to the user. Local suite green (phpunit 5/5, phpstan clean,
php-cs-fixer clean).

### Sprint 22 — French public holidays on the calendar (2026-08-11)

First item out of the real-GLPI-export audit: `glpi_holidays` ships empty on a fresh install
(confirmed), so SLA/OLA due dates keep counting through a public holiday until an admin adds them
by hand. Seeds the 8 fixed-date French public holidays.

#### Changed
- `CalendarBuilder` now optionally attaches 8 `Holiday` rows (Jour de l'An, Fête du Travail,
  Victoire 1945, Fête nationale, Assomption, Toussaint, Armistice 1918, Noël) to whichever calendar
  it builds — the shared one and any per-client MSP override — via the native `Calendar_Holiday`
  relation. `Holiday.is_perpetual = 1` makes `Calendar::isHoliday()` compare month/day only, so the
  reference year in `begin_date`/`end_date` is arbitrary.
- Deliberately excludes the 3 movable holidays tied to Easter (Lundi de Pâques, Ascension, Lundi de
  Pentecôte) — GLPI's `Holiday` model has no "recompute from Easter" mechanism, only fixed
  month/day recurrence, so a movable date seeded once would silently go stale every year. Documented
  in the wizard rather than seeded and forgotten.
- New `calendar_holidays_enabled` toggle, a sub-option under the existing Calendar step (step 3),
  not a new step. Suggested `true` for every non-minimal profile.

Validated against the real GLPI 11.0.8 test instance: ran the wizard with the toggle on, confirmed
in DB all 8 holidays created with correct `begin_date`/`end_date`/`is_perpetual = 1`, and linked via
`glpi_calendars_holidays` to all 3 calendars present (the shared one plus 2 per-client MSP
overrides) — confirming the per-client path also received holidays. Local suite green (phpunit
5/5, phpstan clean, php-cs-fixer clean).

### Fixed — plugin-list logo (2026-08-11)

The Marketplace plugin-list badge ("CG" initials) isn't driven by `configurationglpiauto.xml`'s
`<logo>` at all — confirmed in `Glpi\Marketplace\View::getPluginIcon()`: GLPI looks for a literal
`logo.png` at the plugin's own root directory and serves it via `/Plugin/{key}/Logo`, regardless of
the manifest. Restored `logo.png` at the plugin root (`misc/logos/logo.png`, added in Sprint 21,
only satisfies the manifest's marketing URL) — both now present. Verified visually on the real test
instance: the real logo now replaces the "CG" badge.

### Sprint 21 — hide Urgency/Observers/Location on GLPI's native self-service forms (2026-08-11)

Real-world testing (a genuine `post-only` Self-Service login, not the Super-Admin preview tab)
caught a gap Sprint 19/20 missed entirely: GLPI 11's actual "Report an issue"/"Request a service"
self-service portal pages don't go through `TicketTemplate` at all — they're built on a separate,
newer form engine (`Glpi\Form\Form`/`Question`, `/Helpdesk`, `/Form/Render/N`). Every
`TicketTemplateHiddenField` configured in Sprint 19/20 had zero effect there, however it was set.

#### Changed
- New `HelpdeskFormBuilder`, a distinct class from `TicketTemplateBuilder` for this distinct GLPI
  subsystem: hides Urgency, Observers, and Location on both native forms (ids matched by name —
  "Report an issue"/"Request a service" — not hardcoded, same defensive convention used
  throughout). ITIL rationale for Urgency specifically: a base user has no visibility into real
  business impact and reliably rates their own issue as urgent — a documented ITSM anti-pattern;
  better decided by the service desk during triage (or derived from category) than self-reported.
  Location and Observers are staff/triage concerns, not something a base user needs to pick.
- `Glpi\Form\Question` has no "always hidden" visibility strategy, only `ALWAYS_VISIBLE`/
  `VISIBLE_IF`/`HIDDEN_IF` (`Glpi\Form\Condition\VisibilityStrategy`). Confirmed in
  `Engine::computeConditions()` that an empty condition list evaluates to `false`; paired with
  `VISIBLE_IF` (which returns that result as-is), an empty condition list is therefore permanently
  hidden — no dummy/always-false condition needed.
- New `helpdesk_form_hide_fields` toggle, a sub-option under step 9 (Modèles de tickets) rather than
  a new step — same screen, since it serves the same "simplify what a base user sees" goal, just
  through a different GLPI mechanism. Suggested `true` for every non-minimal profile.

Also fixed in passing: `configurationglpiauto.xml`'s `<logo>`/`<screenshots>` pointed at
`misc/logos/`/`misc/screenshots/` files that were never added to the repo — 404 since the manifest
was first written, caught by the official `glpi-project/plugin-ci-workflows` manifest validation
(pull_request-only, which is why it never showed up on a plain `dev` push). Removed the broken
screenshot entries; added a real logo (resized 1254×1254 → 512×512, 1.5MB → 297KB) at
`misc/logos/logo.png` and restored the `<logo>` entry.

Validated against the real GLPI 11.0.8 test instance: confirmed via a genuine `post-only`
(Self-Service profile) Playwright login — not the admin preview tab, which doesn't reflect real
self-service rendering — that Urgency/Observers/Location are gone from both
`/Form/Render/1` and `/Form/Render/2`, leaving only Category, User devices, Title, Description,
Attachments. DB confirmed all 6 expected question rows (3 fields × 2 forms) switched to
`visible_if` with empty conditions. Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer
clean).

## [0.5.0] - 2026-08-10

Sprints 5 through 20: arbitrary per-node entity tree, calendar, SLA/OLA (flat and per-priority),
per-client/site overrides, graphic branding, a real topical ticket-category tree (11 selectable
top-level branches, up to 3 levels), element states, GLPI core general settings, and ticket
templates split by profile (minimal for base users, full qualification for staff). No breaking
changes — every addition is an opt-in toggle in the wizard, defaulting off except where a
configuration profile suggests it on.

### Sprint 20 — simplified template refinements: parent-only categories, real SLA/OLA hiding (2026-08-10)

Direct follow-up to Sprint 19, based on user testing of the real hidden-fields admin tab: the
simplified template should keep category access (just restricted to the 11 top-level branches, not
the ~92 leaf categories meant for staff triage) instead of hiding it outright, and "niveaux de
service" (SLA/OLA due dates) needed to actually be hidden — which an earlier version of
`TicketTemplateBuilder` silently failed to do.

#### Fixed
- `TicketTemplateBuilder` previously hand-resolved SearchOption IDs via `Ticket::
  getSearchOptionIDByField()`, and concluded SLA/OLA fields (`slas_id_tto`/`_ttr`, `olas_id_tto`/
  `_ttr`, `time_to_own`, `internal_time_to_own`/`_resolve`) weren't hideable — wrong: they're
  defined in `TicketTemplate::getExtraAllowedFields()` (a different method, on the template class,
  not the ticket class), which a real screenshot of GLPI's own "Champs masqués" tab caught. Rewrote
  to resolve every field through `TicketTemplate::getAllowedFields(true)` instead — the exact same
  authoritative map GLPI's own admin tab is built from — rather than re-deriving lookups by hand.

#### Changed
- Simplified template no longer hides `itilcategories_id` — category stays visible, but
  `CategoryBuilder` now sets `is_helpdeskvisible` only on the 11 top-level branches (`0` on every
  child), which is what GLPI's own ticket-creation category search option filters on for the
  Self-Service interface specifically (`CommonITILObject::rawSearchOptions()`, condition added only
  when `Session::getCurrentInterface() == 'helpdesk'`). A base user now picks a broad theme, not one
  of the ~90 leaf categories.
  - Existing installs: an explicit `ensureNotHidden()` cleanup call removes the now-stale
    `itilcategories_id` hidden-field row a Sprint 19 run would have already created.
- Simplified template now genuinely hides the "Niveaux de service" panel — all 7 SLA/OLA fields —
  in addition to the fields already hidden in Sprint 19.

Also investigated and explained rather than built: restricting which entity a Self-Service user can
select at ticket creation isn't controllable via the ticket-template mechanism — `entities_id` isn't
part of `ITILTemplate`'s hideable-field set at all (confirmed reading `fields_panel.html.twig`: the
entity dropdown is gated only by `is_multi_entities_mode()`, and lists every entity the user's
account has access to). That's an account/entity-assignment concern — ROADMAP item 6
("Droits/profils GLPI par entité"), not yet built.

Validated against the real GLPI 11.0.8 test instance via Playwright: wiped the previously-created
category tree and re-ran the wizard fresh — confirmed in DB all 11 top-level branches have
`is_helpdeskvisible = 1` and all 92 children have `0`; confirmed the simplified template (id 2) has
exactly 20 hidden-field rows (all previously-missing SLA/OLA/duration fields now present, resolved
to the correct SearchOption `num`s) and no `itilcategories_id` entry; profile assignment unchanged
(Self-Service/Read-Only → simplified, all others → complete). Local suite green (phpunit 5/5,
phpstan clean, php-cs-fixer clean).

### Sprint 19 — ticket templates split by profile, project task state mapping (2026-08-10)

Two follow-up items requested right after Sprint 18. First: GLPI ships exactly 3 native
`ProjectState` rows ("New"/"Processing"/"Closed") but leaves the "Statuts des tâches"
unstarted/in-progress/completed bucket mapping unset, so project task progress tracking silently
does nothing — folded into `GeneralSettingsBuilder` as requested ("à ajouter avec les réglages
automatique déjà existant"), not a new step. Second: the ROADMAP's "Templates de tickets" gap,
now unblocked by Sprint 17's categories — user's explicit split: base users (Self-Service,
Read-Only) enter the least possible (title + description), every other profile gets the full
qualification interface (category, urgency, impact, priority, status...).

#### Changed
- `GeneralSettingsBuilder::projectTaskStateMapping()`: matches GLPI's 3 native `ProjectState` rows
  by exact name (not hardcoded IDs — an admin could have reordered/recreated them) and sets
  `projecttask_unstarted_states_id`/`_inprogress_states_id`/`_completed_states_id`. Skipped
  entirely if any of the 3 native names isn't found, rather than writing a guessed ID.
- New `TicketTemplateBuilder`: creates two `TicketTemplate` rows and wires them to GLPI's native
  per-profile override (`glpi_profiles.tickettemplates_id`, confirmed in `Profile.php`) — a
  mechanism independent from, and requested instead of, per-category templates (industry practice
  research: per-category templates are usually a service-catalog concern, not a raw-category one;
  recommended sticking to one template per audience instead, which the user confirmed).
  - "Ticket simplifié (libre-service)": only `content` (Description) mandatory; `itilcategories_id`,
    `urgency`, `impact`, `priority`, `status`, `locations_id`, the 3 date/duration fields, and all
    assignment/observer actor fields are hidden. Assigned to `Self-Service` and `Read-Only`.
  - "Ticket complet (support)": `content`, `itilcategories_id`, `urgency` mandatory; nothing
    hidden. Assigned to every other existing profile (Observer, Admin, Super-Admin, Hotliner,
    Technician, Supervisor by default, but driven by "every profile not in the simplified list",
    not a hardcoded whitelist — a custom/renamed profile still gets the complete template).
  - Field `num`s are GLPI SearchOption IDs, resolved via `getSearchOptionIDByField()` (the same
    method `ITILTemplate::getAllowedFields()` itself uses) rather than hardcoded, except for the
    handful of actor pseudo-fields GLPI's own core hardcodes the same way (`_users_id_assign` etc.).
- New `ticket_template_enabled` toggle, step 9 of the wizard (`STEP_COUNT` 9→10).

Also resolved in passing: a user report of missing icons on a real "État" dropdown turned out not
to be a bug — `$_SESSION['glpi_dropdowntranslations']` is cached at login, so a session started
before the wizard created the translations doesn't see them until the next login. Confirmed via a
fresh Playwright session against the real Computer creation form (`states_id` select2 dropdown):
icons render correctly once the session is fresh.

Validated against the real GLPI 11.0.8 test instance via Playwright: reset the 3
`projecttask_*_states_id` keys to 0, reran the wizard, confirmed they resolve to the correct
native `ProjectState` IDs. Ticket templates: confirmed in DB the exact expected SearchOption `num`s
on both templates (14 hidden fields on the simplified one, 3 mandatory on each), profile
assignment (`Self-Service`→2, `Read-Only`→2, all others→3), and — on the real central ticket
creation form as Super-Admin — Description/Catégorie/Urgence show as mandatory (red asterisk) with
nothing hidden, matching the "complete" template. Local suite green (phpunit 5/5, phpstan clean,
php-cs-fixer clean).

### Sprint 18 — GLPI core general settings (2026-08-10)

User feedback with screenshots on GLPI's own general-settings pages: several core defaults are
unhelpful out of the box (notifications off entirely, merged action buttons, search/pagination
below results instead of above, no financial info by default, no new-ticket homepage widget). All
6 screenshot-shown settings implemented (2 mentioned explicitly in text plus 4 others visible in
the screenshots — notifications counts as 3 distinct `glpi_configs` keys, for 8 total).

#### Changed
- New `GeneralSettingsBuilder`: writes straight through GLPI core's own
  `\Config::setConfigurationValues('core', [...])` (not raw SQL) so the write goes through GLPI's
  normal cache/session invalidation. Referenced with a leading `\` throughout — this plugin has its
  own `Config` class in the same namespace, so a bare `Config::` call here would resolve to the
  wrong class.
- 8 `glpi_configs` (context `core`) keys set: `use_notifications`, `notifications_mailing`,
  `notifications_ajax` (the 3 notification sub-toggles), `timeline_action_btn_layout` →
  `Config::TIMELINE_ACTION_BTN_SPLITTED` (split Répondre/Observation/Solution buttons instead of
  merged), `show_search_form`, `search_pagination_on_top`, `show_jobs_at_login`,
  `auto_create_infocoms`. Keys and their matching French labels confirmed by grepping GLPI's own
  Twig setup templates (`setup_notifications.html.twig`, `preferences_setup.html.twig`,
  `assets_setup.html.twig`), not guessed.
- New `general_settings_enabled` toggle (`Config`), instance-wide, not per-entity/per-client —
  unlike calendar/SLA these settings don't vary by org size, same reasoning already applied to
  categories/states, so it's suggested `true` for every non-minimal profile.
- Step 8 (Réglages généraux) added to the wizard: single master toggle plus a read-only list of
  what gets changed (all-or-nothing, since `GeneralSettingsBuilder::apply()` itself is
  all-or-nothing — no granular sub-toggles for 8 keys that all point the same direction).
  `STEP_COUNT` 8→9, Récapitulatif renumbered 8→9 with a new "Réglages généraux" recap line.

Validated against the real GLPI 11.0.8 test instance via Playwright: confirmed all 8 keys read `0`
before running the wizard, ran the wizard with the new step's toggle checked, confirmed all 8 keys
read `1` afterward via direct DB query — including `timeline_action_btn_layout = 1`, matching
`Config::TIMELINE_ACTION_BTN_SPLITTED`. Flash message includes "Réglages généraux GLPI appliqués."
Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer clean).

### Sprint 17 — real topical category tree, replacing the ITIL-type one (2026-08-10)

Sprint 16's "one category per ITIL type" (Incidents/Demandes/Problèmes/Changements) turned out to
be pointless: `Ticket` already has a native `type` field for Incident/Demande, and Problem/Change
are already their own GLPI object types, not ticket sub-types — a category per type duplicated a
distinction GLPI already makes elsewhere. Replaced with what the user actually needed: a real
topical category tree (IT & SI, Bâtiment & Moyens Généraux, Flotte Automobile, RH, Achats,
Sécurité, Services Généraux, Administratif, Communication, Qualité, Maintenance Industrielle), up
to 3 levels deep, ~92-115 categories depending on which of the 11 top-level branches are selected.

#### Changed
- `CategoryBuilder` rewritten: recursive tree builder (`itilcategories_id` already supports
  arbitrary parent/child nesting, same mechanism as `Entity` — reused `EntityBuilder`'s recursion
  pattern) instead of 4 flat rows. Every category gets all 4 `is_incident`/`is_request`/
  `is_problem`/`is_change` flags — the category doesn't decide which ticket type it's usable for,
  that's the orthogonal, native concern the old version wrongly conflated with categorization.
- `category_branches` (JSON, new `Config` field): each of the 11 top-level branches is
  independently selectable — an organization without a vehicle fleet or industrial maintenance
  doesn't have to end up with those branches. All 11 selected by default on a fresh install/
  profile suggestion (trim what doesn't apply, easier than re-checking from nothing).
- Icons (`category_icons_enabled`, same mechanism as `State` from Sprint 16 — a
  `DropdownTranslation` on `fr_FR`/`name`, since GLPI renders that value as escaped plain text,
  never HTML) only on the two levels the user actually gave an emoji for; leaf bullet items never
  had one in the original list.
- Confirmed with the user: parenthetical text in the original list (e.g. "Accessoires (Dock USB-C,
  Webcam, Casque, Clavier, Souris, Batterie)") is example/guidance text for the admin, not a 4th
  tree level — becomes each node's `comment` field instead, keeping the tree at the stated 3
  levels (N1/N2/N3).
- Step 5 (Catégories) gets one checkbox per top-level branch plus an icon toggle, both feeding a
  read-only recursive preview (new Twig macro, `_self.category_tree()`) — same "point of entry,
  not final" philosophy as every other step in this wizard.
- Migration hides (`is_helpdeskvisible = 0`, not deleted — `ITILCategory` has no soft-disable
  flag, and these are real objects that could already have tickets attached) the 4 old root
  categories from Sprint 16 on upgrade, restricted to exact-name matches at the root so nothing an
  admin created themselves gets touched.

Validated against the real GLPI 11.0.8 test instance via Playwright: 11 branch checkboxes render,
unchecking a branch hides its preview subtree live, icon toggle shows/hides preview icons; on
submit with 2 of 11 branches unchecked, exactly 92 categories were created (none from the excluded
branches) with the correct 3-level `itilcategories_id` hierarchy — "Accessoires" confirmed at
level 3 with its parenthetical content correctly landing in `comment`, not a level-4 category.
Screenshot of Configuration > Intitulés > Catégories ITIL compared directly against the requested
structure. Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer clean).

### Sprint 16 — ticket categories (ITIL types) and element states (2026-08-10)

Two more items from the completeness audit, requested explicitly with a precise reference list for
the second one: ITIL-typed ticket categories, and the 14 asset/element states GLPI ships with
*none* of by default (confirmed: `glpi_states` is empty on a fresh 11.0.8 install — a genuine gap,
not a cosmetic one). Neither is a per-entity/per-client concept like calendar/SLA — a category
tree or a status list means the same thing across the whole instance, so both are instance-wide
(`entities_id => 0`, `is_recursive => 1`), with no per-client wizard panel needed.

#### Added
- `CategoryBuilder`: 4 starting `ITILCategory` rows (Incidents/Demandes/Problèmes/Changements),
  one per GLPI's native `is_incident`/`is_request`/`is_problem`/`is_change` flags — a defensible
  universal starting point (unlike the entity tree, ITIL's 4 base types aren't business-specific),
  admin renames/extends natively in GLPI afterward.
- `StateBuilder`: the 14 states, each with the exact name/comment provided, plus:
  - **Visibility** (`DropdownVisibility` rows) — confirmed by reading `State::post_getFromDB()`
    that a visibility field defaults to *not visible* unless an explicit row says otherwise (the
    all-visible default only applies to GLPI's own blank "add new state" form), so only the ~9
    itemtypes that should show "Oui" (Computer, Phone, SoftwareLicense, Line, Contract, Unmanaged,
    Monitor, Peripheral, Printer) need a row — not the full ~30-itemtype list with "Non".
  - **Icons**, gated behind a "state_icons_enabled" checkbox, stored as a `DropdownTranslation`
    (fr_FR, field `name`) — never on the `name` field itself, per instruction. Caught during
    validation: the translation `value` renders as *escaped plain text* in GLPI's UI, not HTML —
    an `<i class="ti ...">` tag showed up literally instead of rendering, so the icon had to
    become a plain Unicode emoji prepended to the name instead, not markup. Fixed before this
    reached the user.
- Wizard grows two steps (5 "Catégories", 6 "Statuts des éléments"; Personnalisation/Récapitulatif
  shift to 7/8), each with a read-only preview (categories: name + ITIL type; states: name, with
  the icon shown/hidden live as the icon checkbox is toggled) before anything is created.
- `ConfigurationProfile::getSuggestedDefaults()`: both features suggested on for every non-minimal
  profile — unlike calendar/SLA they don't vary by org size or business model.

Validated against the real GLPI 11.0.8 test instance via Playwright: both preview lists render
correctly (4/14 rows), finish flash message confirms creation, and in the database: 4
`glpi_itilcategories` with the right flags, 14 `glpi_states` with the exact names/comments, 126
`glpi_dropdownvisibilities` rows (9 × 14, matching the intended "Oui" grid) and 28
`glpi_dropdowntranslations` rows (GLPI auto-mirrors `name` into `completename`). Screenshot of
Configuration > Intitulés > Statuts des éléments compared directly against the reference — emoji
icons render correctly next to plain-text names, matching what was asked. Local suite green
(phpunit 5/5, phpstan clean, php-cs-fixer clean).

### Sprint 15 — OLA, the internal commitment behind the SLA (2026-08-10)

Following a completeness audit against ITIL/ISO27001/GLPI best practices (requested by the user —
see ROADMAP.md "Audit de complétude"): OLA (Operational Level Agreement) is the internal
commitment between the helpdesk and support teams that has to be met *before* the external SLA
deadline for the SLA to actually be kept (e.g. SLA "resolve within 4h" to the customer ⇒ internal
OLA "tier 1 triage within 30min, tier 2 diagnosis within 2h"). Confirmed in GLPI core: `OLA`
extends the same `LevelAgreement` base class as `SLA`, `glpi_olas` has the identical schema to
`glpi_slas`, and — the key finding — OLA attaches to the *same* `SLM` container as SLA (same
`slms_id`), so one "Niveau de service" naturally carries both. `RuleTicket` already had
`olas_id_tto`/`olas_id_ttr` as valid actions alongside `slas_id_tto`/`slas_id_ttr`.

#### Added
- `Config` gains `ola_enabled`/`ola_tiers` (same 6-priority-level shape as `sla_tiers`), plus a
  tighter `DEFAULT_OLA_TIERS` starting point (OLA has to land before its paired SLA). Per-client
  override (`settings.sla`, Sprint 13/14) gains `ola_enabled`/`ola_tiers` as sibling keys rather
  than a separate `settings.ola` object — an OLA only ever exists attached to its client's SLA/SLM,
  so nesting it separately would just be two objects that always have to agree on which client
  they belong to.
- Step 4's shared section gets an "Ajouter des engagements internes (OLA)" toggle under the SLA
  table, revealing a second 6-row table (only shown/meaningful when SLA itself is enabled — OLA
  doesn't stand alone in this plugin's model). The per-client SLA panel gets the same, inside its
  "custom" block.
- `SlaBuilder::buildSlm()` now creates OLA rows in the *same* SLM as SLA when enabled — no second
  container class needed. `getOrCreateSla()` generalized to `getOrCreateLevelAgreement(string
  $class, ...)`, serving both `SLA::class` and `OLA::class` (identical schema, only the class
  differs). `assignOne()` adds `olas_id_tto`/`olas_id_ttr` `RuleAction`s onto the *same* rule that
  already assigns the SLA for that priority/entity — still 6 rules per entity, not 12.

Validated end-to-end against the real GLPI 11.0.8 test instance via Playwright: enabled shared
SLA+OLA with distinct values, confirmed the 6-row OLA table renders and the finish flash message
mentions both; in the database, the same `slms_id` carries both SLA and OLA rows, and each of the
6 rules for the test entity carries all 4 actions (`slas_id_tto`, `slas_id_ttr`, `olas_id_tto`,
`olas_id_ttr`). Created a real ticket with priority=Majeure via the entity's helpdesk form and
confirmed it received both the correct SLA ids *and* the correct OLA ids — the first sprint to
verify SLA and OLA together against an actual ticket, not just the database rows the wizard
produces. Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer clean).

### Sprint 14 — SLA per priority level, not one flat delay for everything (2026-08-10)

Confirmed by research and the user (Sprint 13): real ITSM practice defines SLAs per ticket
priority, not one flat "prise en charge/résolution" pair for every ticket regardless of severity.
GLPI itself has 6 native priority levels (`CommonITILObject::getPriorityName()`, Très basse=1
through Majeure=6, computed from an instance-wide urgency×impact matrix) and documents assigning
SLAs via a `RuleTicket` matching on `priority` — the same mechanism `SlaBuilder` already used to
match on `entities_id`.

#### Changed
- `sla_tto_hours`/`sla_ttr_hours` (flat ints) replaced by `sla_tiers` (JSON, one
  `{tto_hours, ttr_hours}` pair per GLPI priority level 1-6). Migration reads the old singleton's
  flat value first and seeds all 6 levels with it, so upgrading doesn't silently lose the existing
  setting.
- Step 4's shared section is now a 6-row table (one per priority, labelled via GLPI's own
  `getPriorityName()` so it respects the instance's language) instead of two number fields.
- Per-client SLA panel (Sprint 13) gains a "Utiliser le SLA par défaut" checkbox, checked by
  default: checked → this client follows the shared table, nothing stored for it (same
  no-override-means-shared principle as Sprint 13). Unchecked → its own 6-row table, pre-filled
  from the shared table's current values as a starting point rather than blank fields.
- `SlaBuilder` now builds 12 `glpi_slas` rows per SLM (6 levels × TTO/TTR) and one `RuleTicket`
  per (entity × priority level) instead of one flat pair and one rule per entity —
  `getOrCreateSla()`'s uniqueness key gained `name` since 6 TTO rows now share the same
  `slms_id`+`type`.

#### Fixed
- **SLA assignment rules never actually fired on a real ticket, since this plugin started
  creating them (Sprint 6/7) — not something introduced this sprint.** `SlaBuilder::assignOne()`
  created each `RuleTicket` without `is_recursive => 1`; GLPI's `RuleCollection` only evaluates a
  rule for its own `entities_id` (root, 0 by default) unless it's marked recursive, so it was
  silently skipped for every ticket created in any sub-entity — the only kind of entity this
  plugin ever assigns an SLA to. Every previous sprint's validation checked that the
  `RuleTicket`/`RuleCriteria`/`RuleAction` rows existed in the database with the right values, but
  never actually created a real ticket to confirm GLPI's rule engine assigned anything — this
  sprint's end-to-end Playwright check (create a ticket with a known priority, read back
  `slas_id_tto`/`slas_id_ttr`) is what caught it. Existing rule rows from earlier sprints are
  fixed by a migration (`$DB->update` on `is_recursive`), new ones are created correctly.

Validated against the real GLPI 11.0.8 test instance via Playwright: shared table renders 6 rows
with correct priority labels; a client with "Utiliser le SLA par défaut" unchecked correctly
pre-fills from the shared table and its own override for one level (Majeure, set to 99h/199h)
lands in the database exactly as entered (12 distinct `glpi_slas` rows, 6 `RuleTicket` rows with
`entities_id` + `priority` criteria); a ticket created directly via the entity's helpdesk form
with priority=Majeure correctly received `slas_id_tto`/`slas_id_ttr` matching that override — the
first time this plugin's SLA assignment has been verified against a real ticket rather than just
the database rows the wizard produces. Local suite green (phpunit 5/5, phpstan clean, php-cs-fixer
clean).

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

[Unreleased]: https://github.com/parime/Configuration-glpi-auto/compare/v0.6.0...HEAD
[0.6.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.6.0
[0.5.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.5.0
[0.4.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.4.0
[0.3.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.3.0
[0.2.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.2.0
[0.1.0]: https://github.com/parime/Configuration-glpi-auto/releases/tag/v0.1.0

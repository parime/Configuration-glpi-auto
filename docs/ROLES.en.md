[🇫🇷 Français](ROLES.md) | [🇬🇧 English](ROLES.en.md)

# Guides by role

Each guide is a few steps: what the person does, where they click and what they get. Paths are
those of the English interface. The plugin has a single menu entry, **Setup > Configuration GLPI
Auto**, which opens the wizard; its other screens are tabs at the top of the page: **Profiles and
Blueprints**, **Configuration audit**, **History**, **Address plan** and **Import tickets** (Jira
or CSV). The plugin settings (check thresholds, periodic report, end of support) are on its
configuration page: **Setup > Plugins**, "Configuration GLPI Auto" row, **Configure** icon. For the steps in detail, with
screenshots, see the [tutorial](TUTORIAL.md#-english); for the ITIL reasoning behind each step,
the [ITIL guide](ITIL_GUIDE.md) (in French).

The examples use fictitious data: a company "Example Ltd" with two sites, Lyon and Nantes, and a
support mailbox `support@example.org`.

| Role | What they do with Configuration GLPI Auto | Guide |
|---|---|---|
| GLPI administrator | Installs the plugin and runs the wizard | [Administrator](#glpi-administrator) |
| Asset manager | Statuses, locations, asset types, dictionaries, fleet checks | [Assets](#asset-manager) |
| Helpdesk manager | Categories, form catalog, SLAs, receiving tickets by email | [Helpdesk](#helpdesk-manager) |
| Configuration auditor | Checks the instance and follows up on gaps | [Auditor](#configuration-auditor) |

---

## GLPI administrator

Goal: a new GLPI instance configured in one sitting, with nothing written to GLPI before you have
reviewed the result. First configuration in ten steps:

1. **Install**: extract the release archive into `plugins/` (see
   [Installation](../README.en.md#installation)), then **Setup > Plugins**, "Configuration GLPI
   Auto" row, **Install** then **Enable**.
2. **Check rights**: the Super-Admin profile receives the plugin rights on install. For another
   profile: **Administration > Profiles**, open the profile, **Configuration GLPI Auto** tab:
   **Configuration profiles** right (the wizard templates) and **Configuration** right (apply the
   wizard, audit, imports).
3. **Open the wizard**: **Setup > Configuration GLPI Auto**. At the top of the page, the
   "Environment check" and "Current operator's rights" boxes point out anything that would limit a
   feature; you can carry on anyway.
4. **Step 1, Profile**: pick the closest profile (Simple installation, Several sites or departments,
   Several client companies, Custom). For a small organization, the **Express configuration** button
   applies the profile's recommended settings straight away.
5. **Step 2, Entity structure**: for example, one entity per site (Lyon, Nantes); the preview on the
   right updates live.
6. **Steps 3 to 5**: locations and addresses, calendar (the country's public holidays), service
   levels.
7. **Steps 6 to 19**: most are optional; the asset manager and the helpdesk manager can review them
   with you (see their guides below).
8. **Summary** (last step, 20 or 21 depending on the enabled plugins): review, then **Finish**. This is the only moment the wizard writes to GLPI.
   What already exists is reused, nothing is deleted, and a backup of the configuration is saved
   each time it is applied.
9. **Check**: open **Setup > Configuration GLPI Auto**, **Configuration audit** tab, and handle the most
   serious findings first (see [Auditor](#configuration-auditor)).
10. **Run it again later**: the wizard can be run again at any time (new site, adjusted SLA); only
    missing items are created.

## Asset manager

Goal: a usable inventory, where every item has a status, a location and a normalized software
name.

1. Wizard **Step 3, Locations**: address of each site, and child locations (building, floor,
   room). Example: "Lyon > Building A > Server room".
2. **Step 8, Item statuses**: tick the statuses to create (In stock, Assigned, Broken...). When the
   assetsign plugin is enabled, tick **assetsign plugin detected: link its form triggers to these
   statuses**: an item that moves into one of these statuses triggers the form to be signed; a
   trigger already set in assetsign is never changed.
3. **Step 6, Ticket categories**: the dedicated branches also offer optional asset types (vehicle,
   server, premises, fire safety and first aid, physical security), each with its own fields
   (inspection dates, warranty...).
4. **Step 14, General GLPI settings**: enable GLPI's native inventory (GLPI Agent) if needed.
5. **Step 15, General & Tools**: manufacturers and dictionaries (manufacturers, software, operating
   systems), so that one piece of software does not show up under ten different names.
6. **Address plan** (**Setup > Configuration GLPI Auto**, **Address plan** tab): paste each site's IP
   networks and VLANs from a spreadsheet (for example `192.0.2.0/24`, VLAN 10, Lyon site), check the
   preview, then create the valid lines. Running the import again updates instead of duplicating.
   When the Docker inventory plugin is enabled, a **Docker Inventory** wizard step declares its
   connections (Portainer, WUD, Harbor...); each is tested before saving, and nothing is imported
   until you start the first import from that plugin.
7. **Follow the fleet**: in the plugin settings (**Setup > Plugins**, **Configure** icon, **Audit**
   tab), **Fleet checks** section: set the thresholds "Equipment not reporting to inventory for
   (days)" and "Warranties expiring within (days)" (0 disables the check). Then, in the
   **Configuration audit** tab, fleet findings: **Create the tickets** opens tickets linked to the
   items concerned, **Merge** groups duplicate software records, **Categorise** files software
   without a category. The items themselves are never changed.

## Helpdesk manager

Goal: requests correctly classified as soon as they arrive, through the portal as well as by
email.

1. **Step 6, Ticket categories**: choose the useful branches (workstation, accounts, security...),
   up to three levels.
2. **Step 7, Service catalog**: the wizard creates native GLPI forms from these categories. Users
   describe their need in plain words, the ticket lands in the right category, with urgency,
   location, device, observers and approval taken from the answers. The forms then live in GLPI
   (Administration > Forms); a form you have edited is never touched by the wizard again.
3. **Steps 5, 9 and 10**: service levels with escalation between support levels, pending reasons
   with automatic reminders to the requester, ticket templates (simplified for requesters, complete
   for technicians).
4. **Steps 11 and 12**: libraries of tasks, solutions, follow-ups and validations, ready to insert.
5. **Step 18, SMTP configuration**: GLPI's email sending settings. The sending test is done after
   the wizard, from **Setup > Notifications**.
6. **Step 19, Receiving tickets by email**: for each mailbox (example: `support@example.org`, server
   `imap.example.org`, IMAP), choose the entity, category and group in charge. Keep the option that
   refuses automatic replies ticked, to avoid loops. The mailbox becomes a GLPI receiver (**Setup >
   Receivers**) **created disabled** until **Enable collection now** is ticked.
7. After the wizard, come back to step 19: each saved receiver has a **Test the connection** button
   (GLPI counts the messages without collecting them).
8. **Bring over the history** of another tool: **Import from Jira** (CSV export or API) or **Import
   tickets (CSV)**, **Import tickets** tab of **Setup > Configuration GLPI Auto**. Each import shows a dry-run preview
   before writing, and re-importing creates no duplicates.

## Configuration auditor

Goal: know whether the instance follows good practice, with no risk of changing it by mistake.

1. **Read-only rights**: on the auditor's profile, the plugin's **Configuration** right in read
   (**Configuration GLPI Auto** tab of the profile) and read access to GLPI's setup. Without update
   rights, the page shows no fix button at all.
2. Open **Setup > Configuration GLPI Auto**, **Configuration audit** tab. The analysis covers the actual
   state of the instance, whether or not the wizard was used: default accounts, password policy,
   notifications, calendars, SLAs, statuses, categories, entities without an address, mail
   receivers, network, fleet.
3. Read the findings by severity (**Critical**, **To watch**, **Information**), each with its
   recommendation. A **Fix** button only appears when the right setting is beyond doubt and the user
   is allowed to change the configuration.
4. **Periodic email report**: in the plugin settings (**Audit** tab), choose the frequency
   (**Weekly** or **Monthly**), then **Edit the template and recipients** (GLPI notification). To
   send it right away, use **Send the report now** on the audit page. The report gives the
   number of checks passed, warning and failed, and the change since the previous one; it contains
   no password or secret.
5. For a broader ISO 27001 audit (risks, SoA, internal audits), see the
   [GRC Manager](https://github.com/parime/glpi-iso27001-management) plugin.

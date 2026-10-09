# AB Project Hub — Dynamic Configuration Audit

Audit of which business values are managed in the admin panel and which stay in code, carried
out on 2026-10-09 against the code in this repository. Each item was traced from the admin
screen through the controller, validation, database and every form/list that uses it.

**Data source:** MySQL is the source of truth for every record, option, template, role and access
rule. Files are used only for (a) Markdown document *bodies* of registered documents, and
(b) seed sources (`resources/doc-templates/*.md`, `database/seeders/data/abn_workbook.json`)
that are read once by seeders.

## A. Audit matrix

Legend: **Dynamic** = managed in the admin panel and stored in the DB · **Static (by design)** =
intentionally in code, see section E.

| Item | Product / module | Source | Frontend usage | Backend usage | Admin control | Dependency impact | Action taken | Status | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Task status / type / priority | Tasks (G), Modules (priority) | `options` | Task & module forms, task list filters, badges, dashboard | `Options::rule`, `TaskController`, `behavior` drives open/blocked/closed | Add, rename, colour, reorder, deactivate, delete unused, set default | Deleting is blocked while in use; the last open status is protected | Added defaults; removed hardcoded *Medium* priority; fixed inactive initial status | Dynamic | `DynamicConfigurationTest`, `GlobalSettingsTest` |
| Product / application / repository / module status | B–E | `options` | Forms, list filters, badges | `Options::rule` in form requests | Same as above | Same | Defaults on create forms; inactive values kept in filters | Dynamic | `DynamicConfigurationTest` |
| Application type / classification, repository layer | C, D | `options` | Forms, filters | `Options::rule` | Same | Same | Same | Dynamic | `DynamicConfigurationTest` |
| Dependency type / criticality / status | J | `options` | Dependency form, filters | `DependencyController` + `Options::rule` | Same | Same | Same | Dynamic | `DynamicConfigurationTest` |
| Deployment method / target / status, release status, reference status, design type | K | `options` | Registry forms, status filter | `Registries` field definitions + `Options::rule` | Same | Same | Same | Dynamic | `DynamicConfigurationTest`, `InventoryTest` |
| Document category | F | `options` | Document form, docs filter | `DocumentController` | Same | Blocked from deletion while used by documents **or templates** | Usage check extended to templates | Dynamic | `DynamicConfigurationTest` |
| Document templates | F | `document_templates` (was a PHP constant + files) | *New document* template picker | `DocumentController::create` | Add, edit, reorder, deactivate, delete, default category | Inactive templates hidden; title-only fallback if none active | New table, admin CRUD, seeder | Dynamic | `DynamicConfigurationTest` |
| Roles (name, description, custom roles) | H | `roles` | User form role select, roles screen | `RoleController`, `AccessService` | Add custom, rename, describe, delete unused custom | Super Admin cannot be renamed; system roles cannot be deleted; roles in use cannot be deleted | Added CRUD; seeder no longer overwrites names | Dynamic | `DynamicConfigurationTest` |
| Role capabilities | H | `permission_role` | Roles screen | `Gate` / `Capabilities` | Toggle per role | Super Admin always has all; only Super Admins change admin capabilities | — (already dynamic) | Dynamic | `AdministrationTest` |
| User access rules (read/write/deny per scope) | H | `access_rules` | Access screen | `AccessService` | Grant/revoke | Deny overrides grants | — | Dynamic | `AccessControlTest` |
| Products, applications, repositories, modules, registries, tasks | B–K | DB tables | All screens | Controllers + policies | Full CRUD for authorised users | — | — | Dynamic | Feature tests per workflow |
| Option groups and the columns they feed | All | `OptionCatalog` (code) | — | Usage checks, FK columns | Values only | A new group needs a column | — | Static (by design) | E.1 |
| Access levels, scope types, document visibilities | H, F | Code constants | Access & document forms | `AccessService`, `DocumentPolicy` | — | Security model | — | Static (by design) | E.2 |
| Task status behaviours (`open`/`blocked`/`closed`) | G | `Option::TASK_STATUS_BEHAVIORS` | Option edit form | Dashboards, filters, initial status | Each status's behaviour is admin-set | Logic depends on the three values | — | Static (by design) | E.3 |
| Capability catalogue | H | `Capabilities` | Roles screen | Gate checks | Assignment is dynamic | Each capability has a code check | — | Static (by design) | E.4 |
| Rate limits, password policy, session settings | Security | `AppServiceProvider`, `routes/web.php`, `.env` | — | Middleware | — | — | — | Static (by design) | E.5 |
| Page size, max document size, search limits | Technical | `config/hub.php` | Lists, docs | Controllers | — | — | — | Static (by design) | E.6 |

## B. Gap and risk report

| Severity | Finding | Resolution |
| --- | --- | --- |
| Critical | None found. All option fields are validated against active options of the right group on the backend; inactive, wrong-group and unknown IDs are rejected. | Verified by tests |
| High | No admin-managed defaults; task priority was hardcoded to `medium` in `TaskController::create`. | `options.is_default`, admin "set default", `Options::defaultOrFirstId` |
| High | The initial status for new tasks was the first *open* status even if it was **inactive**. | `Options::initialTaskStatusId()` uses the active default, otherwise the first active open status |
| Medium | Document templates were a PHP constant plus files, so they could not be changed without a deployment. | `document_templates` table and admin CRUD |
| Medium | Roles could not be added, renamed or removed from the admin panel. | Custom role CRUD with safeguards |
| Medium | List filters showed only active options, so records with a deactivated value could not be filtered. | `Options::forFilter()`; inactive values marked "(inactive)" |
| Low | Option reordering was not audited. | `option.reordered` audit entry |
| Low | `RolesAndPermissionsSeeder` overwrote admin role renames on every run. | Name/description/order set only for new roles |
| Low | The full `db:seed` includes `DemoUserSeeder` with the shared password `password` (blocked only in production). | Documented in setup, deployment and known limitations; not changed (repository owner's choice) |

## C. Implementation summary

- **Migration** `2026_10_09_000008_add_option_defaults_and_document_templates`: adds
  `options.is_default` (initial defaults: task status Backlog, type Feature, priority Medium) and
  the `document_templates` table.
- **Options:** `OptionController::setDefault` (one default per group, only active, only *open*
  task statuses; cleared on deactivation or a behaviour change), reorder auditing.
  `Options::defaultId / defaultOrFirstId / initialTaskStatusId / forFilter`. The `option-select`
  component preselects the default on create forms (`use-default`).
- **Document templates:** `DocumentTemplate` model, `Admin/DocumentTemplateController`
  (`settings.manage`), views under `admin/templates`, `DocumentTemplateSeeder` (fills an empty
  table from `resources/doc-templates/*.md`), audit and activity-feed integration.
- **Roles:** `RoleController::store / updateDetails / destroy`, inline editing on the roles screen.
- **Filters:** all list filters use `Options::forFilter()`.
- **Tests:** `tests/Feature/DynamicConfigurationTest.php` (14 tests); smoke tests for the template
  screens.
- **Other:** the empty `Unit` test suite was removed from `phpunit.xml` because `tests/Unit` does
  not exist.

No new dependencies were added.

## D. Verification report

Run on 2026-10-09 against MySQL 8.4 (`ab_project_hub_test`):

- `php artisan test`: 91 passed, 0 failed (636 assertions)
- `vendor/bin/phpstan analyse` (level 7): 0 errors
- `vendor/bin/pint`: passed

Upgrade path: `php artisan migrate --force` on the existing development database set the three
defaults, and `DocumentTemplateSeeder` created 8 templates. Fresh install:
`migrate:fresh --seed` produced 3 defaults and 8 templates.

What the tests cover: adding, renaming, reordering and deactivating options, and how that shows
up in forms; defaults (preselection, fallback, eligibility); rejection of invalid, inactive or
wrong-group submissions; template CRUD and its effect on *New document*; custom role rules;
filters on inactive values; 403 on every configuration route for non-administrators; changes
applying on the next request (the `Options` cache lasts one request, so there is no cache to
clear).

## E. Remaining exceptions (static by design)

1. **Option groups and their usage map** (`OptionCatalog`): each group feeds a specific foreign
   key column, so a new group needs a migration and form code anyway. Values inside groups are
   fully dynamic.
2. **Access levels (read/write/deny), scope types, document visibilities, clarification states
   (open/resolved):** these are the security and workflow model. Making them editable would let
   configuration weaken enforcement.
3. **Task status behaviours (open/blocked/closed):** dashboard, blocked-task and filter logic
   depends on exactly these three. Admins choose each status's behaviour.
4. **Capability catalogue:** each capability matches a check in code. Which roles have it is
   admin-managed.
5. **Rate limits, password policy, login throttling, CSP and session settings:** security
   controls set in code and `.env` so they cannot be relaxed from the UI.
6. **Technical limits** (`config/hub.php` page sizes `per_page` / `detail_per_page`, max document
   size, search limits, the 7-day "due soon" window in reports): these are operational settings,
   not business data.
7. **Registry field definitions and component types** (`Registries`,
   `ComponentDependency::COMPONENT_TYPES`): tied to table columns and morph types.
8. **Option colours:** a fixed Bootstrap palette, so that badges stay readable under the CSP (no
   inline styles).
9. **Task list state/sort choices and sidebar navigation:** tied to columns, behaviours and routes.
10. **Deployment settings** (`HUB_DOCS_*`, mail, `APP_NAME`): set per environment in `.env`.
11. **Password reset email:** the Laravel default notification text.
12. **Seed-only constants** (`WorkbookSeeder` product codes, `OptionCatalog::INITIAL_DEFAULTS`):
    used only on first install.
13. **Not applicable:** localisation (the app is English only) and a JSON API (the app is
    server-rendered; options are loaded server-side and there are no API consumers).

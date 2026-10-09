# AB Project Hub — Implementation Status

Status legend: **Done** — implemented and covered by automated tests ·
**Done (manual)** — implemented, verified manually only · **Partial** — usable with gaps ·
**Not started**.

A screen existing does not make a feature "Done"; each Done item has passing feature tests.

## Modules

| # | Feature | Status | Related files | Limitations | Next action |
| --- | --- | --- | --- | --- | --- |
| A | Dashboard (visible products with counts, my tasks, blocked tasks, recent docs, activity) | Done | `DashboardController`, `resources/views/dashboard.blade.php`, `ActivityFeed` | No charts; no per-user customisation | — |
| B | Products (CRUD, archive/restore, creator gets write) | Done | `ProductController`, `resources/views/products/*` | No hard delete | — |
| C | Applications | Done | `ApplicationController`, `resources/views/applications/*` | — | — |
| D | Repositories (filters, sensitive notes, environments, modules, docs, tasks) | Done | `RepositoryController`, `resources/views/repositories/*` | No Git host API integration (by design) | — |
| E | Modules (primary + linked repositories) | Done | `ModuleController`, `resources/views/modules/*` | — | — |
| F | Markdown documentation (real `.md` files, templates, preview, revisions, conflict detection, link rewriting, Git links) | Done | `DocumentController`, `app/Support/Docs/*`, `resources/views/docs/*`, `resources/doc-templates/*` | Path fixed after creation; metadata-only revisions; no Git operations; no UI to register files added via Git | Add "scan and register" admin screen |
| G | Tasks (keys, filters, assignment, progress, comments, document links, blockers) | Done | `TaskController`, `TaskCommentController`, `TaskPolicy`, `resources/views/tasks/*` | No notifications, sub-tasks or issue-tracker sync | Decide whether email notifications are wanted |
| H | Users and scoped access (read/write/deny, deny overrides, lock-out prevention, history, backend enforcement, no IDOR) | Done | `Admin/UserController`, `Admin/AccessRuleController`, `Admin/RoleController`, `AccessService`, policies, `EnsureUserIsActive` | No teams/groups; access managers see all resource names; visibility computed in PHP | Team-based rules if the user count grows |
| I | Global options settings | Done | `Admin/OptionController`, `OptionCatalog`, `Options`, `resources/views/admin/options/*` | New option *groups* require code (`OptionCatalog`) | — |
| J | Dependencies (repository/module/API/database/integration, criticality, failure impact) | Done | `DependencyController`, `ComponentDependency`, `resources/views/dependencies/*` | No graph visualisation | Optional dependency graph view |
| K | API / database / Figma / environment / deployment / release / integration references (no secrets) | Done | `RegistryController`, `app/Support/Registries.php`, `resources/views/registries/*` | Deployment records are manual; no CI/CD integration | — |
| L | Audit log | Done | `Audit`, `Auditable`, `ActivityFeed`, `Admin/ActivityLogController` | Logins not audited; no retention policy | Define retention period |
| — | Global search (permission-aware, includes document content) | Done | `SearchController`, `resources/views/search.blade.php` | Document content search scans files | FULLTEXT index if docs grow |
| — | Needs Clarification tracking (workbook gaps, resolve/reopen) | Done | `ClarificationController`, `Clarification`, `WorkbookSeeder` | Clarifications are created by the seeder; no "new clarification" form | Add a create form if the team wants it |
| — | Profile (name, password change with current password) | Done | `ProfileController`, `resources/views/profile/edit.blade.php` | Users cannot change their own email (administrators can) | — |

## Security requirements

| Requirement | Status | Notes |
| --- | --- | --- |
| CSRF | Done | Laravel middleware; all forms use `@csrf`. |
| XSS | Done | Blade escaping, Markdown HTML escaping, unsafe links removed, optional strict CSP (tests run with CSP on). |
| SQL injection | Done | Eloquent bindings; whitelisted sort/filter values. |
| IDOR | Done | 404 for unreadable records; submitted foreign keys validated for access and product consistency. |
| Rate limiting | Done | Login, password reset, password change, preview, comments, admin routes. |
| Secure sessions | Done | Database sessions, regeneration on login, invalidation on logout, secure-cookie setting for production. |
| Password reset | Done | Self-service and admin-initiated; no links for inactive accounts; enumeration-safe message. |
| Deactivation | Done | Sessions deleted, remember token rotated, middleware ends remaining sessions. |
| No secrets in records/docs/seeders | Done | `NoSecrets` rule; credentials sheet not imported; secret-manager reference field only. |
| Path traversal / source-code editing prevented | Done | `DocumentPath`, `LocalMarkdownStorage` (root check, symlink refusal, `.md` only). |

## Acceptance workflows

| # | Workflow | Status | Tests |
| --- | --- | --- | --- |
| 1 | Administration | Done | `tests/Feature/AdministrationTest.php` |
| 2 | Access control | Done | `tests/Feature/AccessControlTest.php` |
| 3 | Documentation | Done | `tests/Feature/DocumentationTest.php` |
| 4 | Development tracking | Done | `tests/Feature/TaskTrackingTest.php` |
| 5 | Global settings | Done | `tests/Feature/GlobalSettingsTest.php` |
| 6 | Project inventory | Done | `tests/Feature/InventoryTest.php` |
| 7 | Installation (clone, configure, migrate, seed, start) | Done (manual) | No automated test. Migrate + seed verified locally; CI runs `composer setup` (install, key, migrate, asset build). |

## Documentation deliverables

| Document | Status |
| --- | --- |
| `README.md` (repository root) | Done |
| `docs/PROJECT_OVERVIEW.md`, `ARCHITECTURE.md`, `DATABASE_SCHEMA.md`, `ACCESS_CONTROL.md` | Done |
| `docs/DEVELOPMENT_SETUP.md`, `DEVELOPMENT_WORKFLOW.md`, `MARKDOWN_DOCUMENTATION_GUIDE.md` | Done |
| `docs/DEPLOYMENT.md`, `SECURITY.md`, `TESTING.md`, `KNOWN_LIMITATIONS.md`, `IMPLEMENTATION_STATUS.md` | Done |
| Ecosystem docs from the workbook (`docs/README.md`, `glossary.md`, product overviews, AutonOS, SHR authentication, integrations, databases, environments, decisions) | Partial — written only from workbook facts; gaps are marked **Needs Clarification** |

## Open items needing clarification (not implementable without input)

- Production hosting, secret manager, and how hub edits to `docs/` are committed to Git.
- Accountable owners for products/repositories (workbook lists team labels).
- Missing repository URLs, API specifications and deployment details listed on the
  *Clarifications* page.
- Security contact for vulnerability reports.

## Test results

Most recent run, executed on 2026-10-09 with `php artisan test`, `vendor/bin/phpstan analyse`
and `vendor/bin/pint --test` against MySQL 8.4 (`ab_project_hub_test`):

- Pest: **77 passed, 0 failed** (524 assertions)
- PHPStan (level 7): **0 errors**
- Pint: **passed**
- `npm run build`: succeeded

Installation check (workflow 7, manual): `php artisan migrate:fresh --seed --force` on an empty
database created 4 products, 9 applications, 16 repositories, 17 modules, 33 clarification items,
109 options, 1 Super Admin and registered all 24 shipped documents; `php artisan serve` then
answered `/login` (200), `/` (302 to login for guests) and `/up` (200).

The CI workflow (`.github/workflows/tests.yml`, MySQL 8.4 service) has been written but has not
yet run on a Git host.

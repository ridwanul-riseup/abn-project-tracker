# AB Project Hub — Testing

## Tools

| Tool | Command | Purpose |
| --- | --- | --- |
| Pest 4 | `php artisan test` | Feature tests against MySQL |
| Larastan (PHPStan) level 7 | `composer types:check` | Static analysis (`phpstan.neon`) |
| Laravel Pint | `composer lint:check` / `composer lint` | Code style |
| All of the above | `composer test` | What CI runs (after `npm run build`) |

## Test database

Tests use MySQL, not SQLite, so that foreign keys, JSON columns and locking behave as in
production. `phpunit.xml` sets `DB_DATABASE=ab_project_hub_test`; the database must exist and the
`.env` user must have full rights on it. Every test runs inside `RefreshDatabase`.

Other test settings (from `phpunit.xml` / `tests/TestCase.php`):

- `HUB_DOCS_PATH=storage/framework/testing/docs` — a scratch docs directory, recreated empty
  before each test. Tests never touch the real `docs/`.
- `HUB_CSP_ENABLED=true` — pages are rendered with the strict CSP header.
- Roles/permissions, global options and document templates are seeded before each test; Vite is
  disabled.
- There is only a `Feature` suite (`tests/Feature`); there are no unit tests.

Helpers in `tests/Pest.php`: `userWithRole()`, `superAdmin()`, `grantAccess()`, `makeProduct()`,
`makeRepository()`, `makeModule()`, `makeDocument()`, `optionId()`, `docsRoot()`.

## Coverage by acceptance workflow

| Workflow | Test file | What is verified |
| --- | --- | --- |
| 1. Administration | `tests/Feature/AdministrationTest.php` | Login, wrong password, deactivated login refused, login throttling, self-service password reset (no links for inactive/unknown accounts, invalid token rejected), profile name/password change, create user + assign role + grant/revoke product access + deactivate, deactivated user signed out, lock-out prevention, user managers cannot touch Super Admins, non-admins forbidden, role capability changes audited. |
| 2. Access control | `tests/Feature/AccessControlTest.php` | Restricted products hidden, 404 on direct IDs, cannot move records into unwritable products, deny overrides inherited grant, document/task edit requires permission, search does not leak, sensitive notes hidden, changes apply immediately, all-users documents honour denies, activity feed does not leak names, security headers, auth required everywhere. |
| 3. Documentation | `tests/Feature/DocumentationTest.php` | Browse, create real `.md` file, scope enforcement, no user-supplied paths, update + revision, concurrent-edit conflict, Markdown rendering, unsafe HTML/JS neutralised, relative link rewriting, restricted docs unreadable, only access managers set all-users visibility, secrets rejected, archive keeps file, path validation, traversal and symlink refusal, preview permissions, all shipped documents exist and register. |
| 4. Development tracking | `tests/Feature/TaskTrackingTest.php` | Create task linked to product/repository/module/document and assign, assignee must have access, cross-product links rejected, assignee can update progress but not edit, filters, QA can report bugs but not assign. |
| 5. Global settings | `tests/Feature/GlobalSettingsTest.php` | New option appears in forms, deactivating an in-use option keeps records, values immutable, last open task status protected, delete unused + reorder, non-admins forbidden. |
| 6. Project inventory | `tests/Feature/InventoryTest.php` | Workbook seed produces products/applications/repositories/modules, AutonOS distinct from ABOS ERP, repository browse/filter/search, dependencies, registry references, Needs Clarification instead of invented values, idempotent seeding, registry write scope, secrets rejected / ordinary text accepted, clarifications filtered by scope. |
| Dynamic configuration | `tests/Feature/DynamicConfigurationTest.php` | New/renamed/reordered options reflected in forms; defaults preselected on create only and used for new tasks with fallback when deactivated; only active (and open, for task status) options can be defaults; inactive / wrong-group / unknown IDs rejected on submit; document template CRUD drives *New document* (inactive hidden, secrets rejected, title-only fallback, category in use cannot be deleted); custom role create/rename/delete rules; inactive options still usable as list filters; unauthorized users get 403 on every configuration route; changes apply on the next request. |
| Role-based access | `tests/Feature/RoleBasedAccessTest.php` | Seeded default permissions per role (audit log not a "view" permission), module-scoped Developer sees and documents only that module and cannot edit it, module-scoped QA can report tasks without product access, repository-scoped Project Manager edits/assigns only there, application-scoped Project Manager creates repositories only under that application, application-wide assignment applies everywhere but grants no admin permissions, denies beat assignments, removing a `view` permission hides the feature (403 list, 404 detail, no dashboard link), assign/revoke via admin UI applies immediately and is audited, escalation guards, legacy permission conversion by the seeder. |
| Reports | `tests/Feature/ReportsTest.php` | Portfolio counts per product and status, overdue / due-soon / unassigned ignore closed tasks, product report down to module level incl. tasks without a module, drill-down links return exactly the counted tasks, module and workload reports, filters carried into links, access-restricted products/tasks excluded (404 on restricted product report), new status adds a column, report rows paginated. |
| Pagination | `tests/Feature/PaginationTest.php` | Related lists on a detail page paged independently, task comments paged and posting returns to the newest page, option values paged in Global Settings. |
| Smoke | `tests/Feature/PageSmokeTest.php` | Every screen renders for a Super Admin and for a restricted viewer without errors. |
| 7. Installation | — | **Not automated.** Verified manually: `composer install`, `.env`, `migrate --seed`, `serve`. CI exercises `composer setup` (install, key, migrate, build) on every push. |

## Running

```bash
php artisan test                          # all tests
php artisan test --filter=AccessControl   # one file / pattern
php artisan test --compact                # terse output
```

## Last recorded run

See [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md#test-results) for the most recent results
that were actually executed.

## Not covered

- Browser/JavaScript behaviour (preview tab, confirm dialogs) — no browser tests.
- Real mail delivery (tests fake notifications and assert they were sent).
- Performance with large data sets.
- Concurrent task-number allocation under real parallel load (the lock is exercised only
  sequentially).

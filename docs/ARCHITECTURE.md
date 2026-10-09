# AB Project Hub — Architecture

A conventional server-rendered Laravel monolith. No SPA, no microservices, no AI services.

## Stack

| Layer | Technology |
| --- | --- |
| Language / framework | PHP 8.5, Laravel 13 |
| Database | MySQL 8 (8.4 used in development) |
| Views | Blade (anonymous components in `resources/views/components`) |
| UI | Bootstrap 5, Bootstrap Icons, small vanilla JS file (`resources/js/app.js`), built with Vite |
| Markdown | `league/commonmark` (CommonMark + GFM, HTML input escaped) |
| Tests | Pest 4 (on PHPUnit), Larastan level 7, Laravel Pint |

Application dependencies beyond the Laravel skeleton: `league/commonmark` (declared explicitly).
Front-end runtime dependencies: `bootstrap`, `bootstrap-icons`, `@popperjs/core`.

## Request flow

```
Browser ──► web middleware (session, CSRF, EnsureUserIsActive, SecurityHeaders)
        ──► auth middleware ──► Controller
                                 ├─ authorize: Gate / Policy  ──► AccessService
                                 ├─ validate (FormRequest-style rules, NoSecrets)
                                 ├─ Eloquent models (Auditable trait → activity_logs)
                                 └─ Blade view
```

## Main building blocks

| Path | Responsibility |
| --- | --- |
| `app/Support/Access/AccessService.php` | Single source of truth for authorization: effective level (`write`/`read`/none) per user and record, capability checks, visible-ID lists, query restriction. Request-scoped cache. |
| `app/Support/Access/Capabilities.php` | Capability catalogue and default role → capability mapping. |
| `app/Support/Access/HasAccessScope.php`, `ScopedByAccess.php` | Interface/trait describing a model's parent scopes (product, application, repository, module). |
| `app/Policies/*` | `InventoryPolicy` (all inventory and registry models), `DocumentPolicy`, `TaskPolicy`. All delegate to `AccessService`. |
| `app/Providers/AppServiceProvider.php` | Morph map, policies, capability gates, scoped gates (`inventory.create-in`, `docs.create-in`, `tasks.create-in`), rate limiters, password rules. |
| `app/Support/Docs/*` | Markdown storage (`LocalMarkdownStorage`), path rules (`DocumentPath`), create/update/revision logic (`DocumentService`), rendering (`MarkdownRenderer`). |
| `app/Support/Options/*` | Global dropdown settings: catalogue of groups (`OptionCatalog`), lookups and validation rules (`Options`). |
| `app/Support/Registries.php` + `RegistryController` | Declarative definitions for the seven reference registries; one generic CRUD controller and set of views. |
| `app/Support/Audit.php`, `Auditable.php` | Audit trail written to `activity_logs` (create/update/delete diffs plus explicit events). |
| `app/Support/ActivityFeed.php` | Filters audit entries so users only see activity on records they may read. |
| `app/Rules/NoSecrets.php` | Validation rule rejecting obvious secrets (private keys, cloud/API tokens, credentials in URLs). |
| `app/Http/Middleware/EnsureUserIsActive.php` | Logs out deactivated users on their next request. |
| `app/Http/Middleware/SecurityHeaders.php` | Frame/sniff/referrer/permissions headers, optional CSP, `no-store` for authenticated pages. |
| `database/seeders/*` | Roles, options, initial admin (from env), workbook import, shipped document registration, optional demo users. |

## Authorization model (short)

Two independent checks must both pass:

1. **Capability** (role-based): e.g. `docs.write`, `tasks.manage`.
2. **Scope** (per-user access rules): effective `read`/`write` on the record, resolved through its
   ancestors (document → module/repository → application → product → global). An explicit
   deny anywhere in the chain wins.

Unreadable records return **404**, not 403, so their existence is not revealed. Details:
[ACCESS_CONTROL.md](ACCESS_CONTROL.md).

## Markdown documentation design

- Files live under the directory configured by `HUB_DOCS_PATH` (default `docs/`). The DB row in
  `documents` holds metadata and the SHA-256 hash of the last content written by the hub.
- Only registered documents are reachable. Paths are generated server-side (`DocumentPath::build`)
  and validated strictly; traversal, hidden files and symlinks are rejected by `LocalMarkdownStorage`.
- Writes are atomic (temp file + rename). Edits carry a `base_hash`; a mismatch raises a conflict
  instead of overwriting.
- The hub cannot edit anything except registered `.md` files inside the docs directory.
- Rendering escapes raw HTML, blocks unsafe link schemes, only allows remote HTTPS images and
  rewrites relative `.md` links to `docs/resolve`, which re-checks permissions.
- Git remains the source of history for content; the hub links to the file and its commit history
  when `HUB_DOCS_REPO_URL` is configured.

## Global settings

Dropdown values (statuses, priorities, types, layers, categories …) are rows in `options`, grouped
by `option_groups`, referenced by foreign key. Values are immutable once created; labels, colours,
order, default and active state are editable. `task_status` options carry a `behavior`
(`open`/`blocked`/`closed`) used by dashboards and filters. Forms list only active options and
validation (`Options::rule`) rejects anything else. List filters also show inactive options
(marked "(inactive)") so historical records stay findable. The `Options` service caches per
request only, so admin changes apply on the next request.

Document templates (`document_templates`) and custom roles are also managed in the admin panel.
See [CONFIGURATION_AUDIT.md](CONFIGURATION_AUDIT.md) for what is configurable and what stays
static by design.

## Reports and pagination

*Reports* (`ReportController`, `App\Support\Reports\TaskReport`) aggregate tasks with one grouped
SQL query per view (count per status plus overdue, due within 7 days and unassigned), always
starting from `Task::visibleTo($user)`. Products, modules and people outside the viewer's access
are not listed. Tasks under a module the viewer cannot see are counted in an unnamed
"outside your access" row. Each number links to `tasks.index` with matching filters (`status_id`,
`module_id`, `no_module`, `due=overdue|soon`, `unassigned`).

All lists are paginated: index pages use `hub.per_page` (25), related lists on detail pages use
`Controller::paginateList()` with `hub.detail_per_page` (10) and their own page parameter
(e.g. `?modules=2`), so several lists on one page page independently.

## Front end

Server-rendered pages with Bootstrap. JavaScript is limited to progressive enhancements
(confirm dialogs, copy-to-clipboard, Markdown preview tab, auto-submit filters, scope picker on
the access screen). The pages work with a strict CSP
(`HUB_CSP_ENABLED=true`): no inline scripts or styles.

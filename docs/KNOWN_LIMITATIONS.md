# AB Project Hub — Known Limitations

Deliberate trade-offs and gaps in the current implementation. Each item says what to do if it
becomes a problem.

## Access control

| Limitation | Impact | Possible remedy |
| --- | --- | --- |
| Visible-record lists are computed in PHP (`AccessService::visibleIds` loads IDs + scope columns for a model and evaluates rules). | Fine for hundreds or a few thousand records; slower for much larger inventories. | Push rule resolution into SQL or cache visible IDs per user with invalidation on rule/inventory changes. |
| Users with `access.manage` see the names of all products, applications, repositories, modules and documents in the access screen. | Access administrators can see that restricted resources exist. | Restrict the scope picker to resources the administrator can read. |
| Super Admins cannot be restricted. | By design (lock-out prevention). | Keep the number of Super Admins small. |
| Rules apply per user only; there are no groups/teams. | Granting a team access means one rule per member. | Add team-based rules. |

## Documentation

| Limitation | Impact | Possible remedy |
| --- | --- | --- |
| The document path never changes after creation, even if its product or title changes. | Folder may no longer match the product. | Add an explicit "move" action that renames the file and records the old path. |
| Revision rows store metadata (hash, size, line counts, summary, metadata changes) — not content. | No in-app diff or restore of older versions. | Use the Git history link (`HUB_DOCS_REPO_URL`); or store content snapshots. |
| The hub does not run Git (no commit/push/pull). | Hub edits must be committed by someone on the server/docs clone. | Scheduled commit job or a documented manual procedure (see [DEPLOYMENT.md](DEPLOYMENT.md)). |
| Content search reads matching visible files from disk on each search. | Slower with many/large documents. | Add a MySQL FULLTEXT index on a content copy. |
| New Markdown files added via Git are not discovered automatically. | They must be registered (currently by adding them to `DocumentSeeder`). | Add an admin "scan and register" screen. |
| Local images are not served; only remote `https://` images display. | Diagrams must be hosted elsewhere or embedded as Mermaid/text. | Serve images from an allowed docs sub-folder. |
| No real-time co-editing; concurrent edits are detected and rejected. | Second editor must reload and re-apply. | Acceptable for the expected team size. |
| `NoSecrets` is pattern-based. | Unusual secret formats are not detected. | Add a server-side secret scanner in CI on the docs repository. |

## Inventory and data

| Limitation | Impact | Possible remedy |
| --- | --- | --- |
| Re-running `WorkbookSeeder` resets workbook-sourced fields on workbook-coded records. | Hub edits to those fields are overwritten. | Run it only on first install (documented). |
| Re-running `OptionSeeder` recreates seeded options that were deleted. | A deleted default option reappears. | Deactivate instead of delete, or remove it from `OptionCatalog`. |
| Free-text fields copied from the workbook (owners, API references, module labels) are not normalised into separate entities. | Some data is text, not links. | Normalise as the team confirms values (see Clarifications). |
| No hard delete for products/applications/repositories/modules (archive only). | Mistaken records stay archived. | Database-level cleanup by an operator. |
| No bulk import/export UI (CSV/XLSX). | Bulk changes require seeders or manual entry. | Add CSV export/import. |
| The full `db:seed` includes `DemoUserSeeder` (shared password `password`); only `APP_ENV=production` stops it. | Seeding a staging or shared server creates guessable accounts. | Run it explicitly instead of from `DatabaseSeeder`, or use generated passwords. |
| New option *groups*, access levels, document visibilities, registry fields and capabilities need code changes. | Only values within existing groups are admin-configurable. | See [CONFIGURATION_AUDIT.md](CONFIGURATION_AUDIT.md) for the reasons. |

## Tasks

| Limitation | Impact |
| --- | --- |
| No notifications (email or in-app) on assignment or comments. | Users check the dashboard / *My tasks*. |
| No sub-tasks, sprints, estimates or time tracking. | Out of scope for the MVP. |
| No synchronisation with GitHub/Jira issues — only an *Issue URL* link. | Status must be updated in the hub manually. |
| A task's product cannot be changed after creation (task keys are per product). | Create a new task in the other product. |
| Reports show current counts only: no charts, burndown, history or CSV export. | Trends must be tracked outside the hub. |
| Report "progress" is closed ÷ total, and cancelled tasks count as closed (status behaviour `closed`). | A module with many cancelled tasks looks further along than it is. |
| Access only flows downwards: a user with a role or rule on a module or repository does not see the parent product's page or lists. The task form lists that product so tasks can still be placed. | Grant read on the product if the user should see it. |
| The roles permission matrix is not paginated (it is saved as one form). Search shows the top results per type instead of pages. | Use the list pages for complete results. |

## Operations and testing

| Limitation | Impact |
| --- | --- |
| Logins and failed logins are not written to the audit log. | Use web server / application logs. |
| No two-factor authentication or SSO. | Password-only authentication. |
| Installation workflow (clone → configure → migrate → seed → start) is verified manually, not by an automated test. CI runs `composer setup` + the test suite. | |
| No browser (JavaScript) tests. | Preview tab / confirm dialogs are verified manually. |
| Production hosting, secret manager and docs-commit procedure are **Needs Clarification**. | See [DEPLOYMENT.md](DEPLOYMENT.md). |

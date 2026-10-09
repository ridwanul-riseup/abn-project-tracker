# AB Project Hub — Project Overview

AB Project Hub is an internal, lightweight documentation and development tracker for the
AB Digital Ecosystem. It replaces the spreadsheet *"ABN Project Details for Development Team.xlsx"*
as the place where the team records what exists (products, applications, repositories, modules,
references), how it is documented (Markdown files), and what is being worked on (tasks).

It is **not** a project-management suite, a Git host, a CI system or a secret manager.

## Products tracked

The hub keeps the four products distinct. Codes are the workbook codes.

| Code | Product | Notes |
| --- | --- | --- |
| SHR | AB Shared Platform | Shared services (e.g. authentication) used by the other products. |
| OTT | ABN+ OTT | Streaming / OTT product. |
| ABI | AB Intelligence | Includes **AutonOS** (module `ABI-AUTONOS`). AutonOS is *not* ABOS ERP. |
| ERP | ABOS ERP | Enterprise resource planning product. |

Product details come only from the workbook. Anything missing or ambiguous was imported as a
**Needs Clarification** item (see *Clarifications* in the app) instead of being guessed.

## What the hub does

| Area | Summary |
| --- | --- |
| Dashboard | Visible products with repository/module counts, my open tasks, blocked tasks, recent documents, recent activity (filtered by permission). |
| Reports | Bird's-eye task progress: portfolio per product, each product down to module level, all modules across products, and workload per assignee. Counts per configured status (backlog, to do, in progress, in review, testing, done …), plus blocked, overdue, due in 7 days, unassigned and % closed. Every number opens the matching task list. |
| Inventory | Products, applications, repositories, modules and component dependencies, with archive/restore. |
| Reference registries | API, integration, database, Figma, environment, deployment and release references. No secrets. |
| Documentation | Markdown stored as real `.md` files under `docs/`, metadata in MySQL, templates, preview, revisions, conflict detection. |
| Tasks | Per-product task keys (`OTT-12`), status/priority/type from settings, assignment, progress updates, comments, document links. |
| Clarifications | Workbook gaps and other open questions; can be resolved and reopened. |
| Search | One search box across inventory, references, tasks and document metadata/content — permission-filtered. |
| Administration | Users, roles with per-module permissions, scoped role assignments (application-wide, product, application, repository or module), scoped access rules (read/write/deny), global dropdown settings, audit log. |

## Who uses it

Six system roles are seeded: Super Admin, Project Admin, Project Manager, Developer, QA Engineer
and Viewer. Roles decide *what* a user may do; scoped access rules decide *where*. See
[ACCESS_CONTROL.md](ACCESS_CONTROL.md).

## Related documents

- [ARCHITECTURE.md](ARCHITECTURE.md) — how the application is built
- [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) — tables and relationships
- [DEVELOPMENT_SETUP.md](DEVELOPMENT_SETUP.md) — install and run locally
- [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) — what is done and what is not
- [KNOWN_LIMITATIONS.md](KNOWN_LIMITATIONS.md) — known trade-offs
- [README.md](README.md) — ecosystem documentation home

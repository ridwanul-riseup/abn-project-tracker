# AB Project Hub — Access Control

Every check is enforced on the server (controllers, policies, gates, route middleware and query
filters). Hidden menu items and buttons are a convenience only.

## Overview

| Part | Question | Where configured | Table |
| --- | --- | --- | --- |
| **Permissions** (per role) | *What may this role do, per feature module?* | *Administration → Roles & Permissions* | `permissions`, `permission_role` |
| **Global role** (per user) | *Which role applies to the user everywhere?* | *Administration → Users → Edit* | `users.role_id` |
| **Scoped role assignments** (per user) | *Which extra role does the user have on one product, application, repository or module, or application-wide?* | *Administration → Users → Access → Assign a role on a scope* | `role_assignments` |
| **Scoped access rules** (per user) | *Which records may the user read or write, and where are they denied?* | *Administration → Users → Access → Grant or deny access* | `access_rules` |

An action is allowed only if the user has **both**:

1. the **permission** for it (for example `repositories.update`), from the global role, an
   application-wide role assignment, or a role assigned on the record or one of its ancestors; and
2. the required **access level** on the record (read or write), from access rules and role
   assignments, with denies always winning.

Example: a user whose global role is Viewer and who is assigned **Developer on module OTT-PLAYER**
can see that module and its documents and tasks, and can write documents there. They cannot edit the
module itself (Developer has no `modules.update`), and they cannot see sibling modules or the
product page.

## Permissions per feature module

Each feature module has its own permissions: `view`, `create`, `update`, and `archive` or `delete`
(plus a few special ones). The `view` permission is checked together with read access, so taking
`repositories.view` away from a role hides repositories from its users everywhere: the index page
returns 403, detail pages return 404 and the menu entry disappears.

Permissions in the *Administration* group, and `products.create`, are **application-wide only**.
They count from the global role or an application-wide role assignment, never from a role
assigned on a product, repository or module.

### Roles and default permissions

| Permission | Super Admin | Project Admin | Project Manager | Developer | QA Engineer | Viewer |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| **Products** | | | | | | |
| `products.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `products.create` | ✔ | ✔ | | | | |
| `products.update` | ✔ | ✔ | ✔ | | | |
| `products.archive` | ✔ | ✔ | ✔ | | | |
| **Applications** | | | | | | |
| `applications.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `applications.create` | ✔ | ✔ | ✔ | | | |
| `applications.update` | ✔ | ✔ | ✔ | | | |
| `applications.archive` | ✔ | ✔ | ✔ | | | |
| `applications.view_credentials` | ✔ | ✔ | ✔ | | | |
| **Repositories** | | | | | | |
| `repositories.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `repositories.create` | ✔ | ✔ | ✔ | | | |
| `repositories.update` | ✔ | ✔ | ✔ | | | |
| `repositories.archive` | ✔ | ✔ | ✔ | | | |
| `repositories.view_sensitive` | ✔ | ✔ | | | | |
| **Modules** | | | | | | |
| `modules.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `modules.create` | ✔ | ✔ | ✔ | | | |
| `modules.update` | ✔ | ✔ | ✔ | | | |
| `modules.archive` | ✔ | ✔ | ✔ | | | |
| **Dependencies** | | | | | | |
| `dependencies.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `dependencies.create` | ✔ | ✔ | ✔ | | | |
| `dependencies.update` | ✔ | ✔ | ✔ | | | |
| `dependencies.delete` | ✔ | ✔ | ✔ | | | |
| **References** (APIs, integrations, databases, Figma, environments, deployments, releases) | | | | | | |
| `registries.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `registries.create` | ✔ | ✔ | ✔ | | | |
| `registries.update` | ✔ | ✔ | ✔ | | | |
| `registries.delete` | ✔ | ✔ | ✔ | | | |
| **Documentation** | | | | | | |
| `docs.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `docs.create` | ✔ | ✔ | ✔ | ✔ | | |
| `docs.update` | ✔ | ✔ | ✔ | ✔ | | |
| `docs.archive` | ✔ | ✔ | ✔ | | | |
| **Tasks** | | | | | | |
| `tasks.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `tasks.create` | ✔ | ✔ | ✔ | | ✔ | |
| `tasks.update` | ✔ | ✔ | ✔ | | | |
| `tasks.assign` | ✔ | ✔ | ✔ | | | |
| `tasks.update_assigned` | ✔ | ✔ | ✔ | ✔ | ✔ | |
| `tasks.comment` | ✔ | ✔ | ✔ | ✔ | ✔ | |
| **Clarifications** | | | | | | |
| `clarifications.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `clarifications.resolve` | ✔ | ✔ | ✔ | | | |
| **Reports** | | | | | | |
| `reports.view` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| **Administration** | | | | | | |
| `users.manage` | ✔ | | | | | |
| `access.manage` | ✔ | | | | | |
| `roles.manage` | ✔ | | | | | |
| `settings.manage` | ✔ | | | | | |
| `audit.view` | ✔ | ✔ | | | | |

The defaults are defined in `App\Support\Access\Capabilities::defaultsByRole()` and seeded by
`RolesAndPermissionsSeeder`. Users with `roles.manage` can change them. The seeder never overwrites
the permissions of existing roles, except Super Admin, which always has every permission. Only
Super Admins can change permissions in the *Administration* group.

Users with `roles.manage` can also add **custom roles**, rename roles and edit their descriptions.
A role key is fixed once created, and the Super Admin role cannot be renamed. The six system roles
cannot be deleted. A custom role can only be deleted when no user has it as their global role and it
has never been used in a scoped role assignment. Every role change is audited, and re-running the
seeder keeps renamed roles.

### Upgrading from the earlier coarse permissions

Earlier versions had `inventory.write`, `docs.write` and `tasks.manage`. Re-running
`RolesAndPermissionsSeeder` converts them on every role, including custom roles, and then deletes
them:

| Old permission | Replaced by |
| --- | --- |
| `inventory.write` | `products.update/archive`; `applications`, `repositories` and `modules` `.create/update/archive`; `dependencies` and `registries` `.create/update/delete`; `clarifications.resolve` |
| `docs.write` | `docs.create`, `docs.update`, `docs.archive` |
| `tasks.manage` | `tasks.update`, `tasks.assign` |

During the conversion every existing role also receives all `*.view` permissions and
`reports.view`, because viewing had no permission before. As a result, existing roles keep exactly
what they could do before.

## Scoped role assignments

A role assignment is `(user, role, scope)`. The scope can be `global` (application-wide, "root"),
`product`, `application`, `repository` or `module`. Inside the scope and everything below it:

- the role's **permissions** apply (except the application-wide-only permissions above);
- the user gets **read** access, or **write** access if the role has any content-changing
  permission (create, update, archive, delete, assign or resolve). Creating tasks, updating
  assigned tasks and commenting only need read access.

The assignment's access level is merged with the user's explicit access rules **on the same scope**
(the stronger one wins). A more specific rule or assignment still overrides a broader one, and a
**deny** on the record or any ancestor always removes access.

Examples:

- *Project Manager on repository OTT-API*: the user can edit and archive OTT-API, and edit and assign
  its tasks. Other OTT repositories stay at whatever level their access rules give.
- *Project Manager on application OTT-APP*: the user can add repositories and modules under that
  application, but not loose ones directly under the product.
- *QA Engineer on module OTT-PLAYER*: the user can report bugs in that module even without access
  to the product. The task form offers the module's product for this purpose.
- *Project Manager application-wide*: Project Manager permissions with write access everywhere.
  This is additive to the user's global role.

Rules:

- Users with `access.manage` add and revoke assignments. Every change is audited
  (`role.assigned`, `role.unassigned`). Revoked assignments stay in the table with `revoked_by`.
- The Super Admin role cannot be assigned per scope, and Super Admins cannot receive assignments.
- Only Super Admins can assign a role with administration permissions application-wide.
- Only Super Admins can assign roles to themselves.
- The same role cannot be assigned twice on the same scope. Different roles on the same scope add up.
- Changes apply on the next request.

## Scoped access rules

A rule is `(user, scope, permission)`:

- **scope**: `global`, `product`, `application`, `repository`, `module` or `document`
- **permission**: `read`, `write` or `deny`

Scopes inherit downwards:

- a **product** rule covers the product and everything that belongs to it (applications,
  repositories, modules, documents, tasks, references);
- an **application** rule covers the application and the repositories and modules whose
  application is set to it, plus their documents and tasks;
- a **repository** or **module** rule covers that record and the documents, tasks and references
  attached to it;
- a **document** rule covers only that document.

Access does not flow upwards: a user with access to a module does not automatically see its product.

### Resolution order (`AccessService::level`)

1. Inactive user → **no access**.
2. Super Admin → **write** everywhere (cannot be denied; prevents lock-out).
3. Any active **deny** on the record or any of its ancestors → **no access**.
4. Otherwise the **most specific** grant wins, counting both access rules and role assignments
   (document > module = repository > application > product > global).
   On a tie at the same level, write beats read.
5. Otherwise, a document with visibility *All active users* → **read**.
6. Otherwise → **no access**.

Reading a record additionally needs the `view` permission of its feature module
(`AccessService::canRead`).

Component dependencies are readable only when both endpoints are readable; the level is the
level on the source.

### Rule management

- Granting a rule for a scope where the user already has an active rule revokes the old one
  (one active rule per user and scope). Revoked rules stay in the table with `revoked_by`.
- Super Admins cannot receive rules (they do not need them). A **global deny** is refused.
- Rule changes apply on the next request (the access cache is request-scoped).
- The *Access* screen shows an effective-access table for products, applications,
  repositories, modules and documents so administrators can verify the result.
- Every grant, deny and revoke is written to the audit log (`access.granted`, `access.denied`,
  `access.revoked`).

## What each action requires

"Permission" means: held through the global role, an application-wide assignment, or a role
assigned on the record or one of its ancestors.

| Action | Permission | Access requirement |
| --- | --- | --- |
| Open a list page or menu entry | `<feature>.view` anywhere | lists only show readable records |
| View a record | `<feature>.view` | read |
| Create product | `products.create` (application-wide) | — (creator receives a write rule on the new product) |
| Edit product / application / repository / module | `<feature>.update` | write on the record |
| Archive or restore them | `<feature>.archive` | write on the record |
| Create application | `applications.create` | write on the product |
| Manage live site URLs and credentials on an application | `applications.view_credentials` + `applications.update` | write on the application |
| View credential rows (masked password) | `applications.view_credentials` | read on the application |
| Create repository / module | `repositories.create` / `modules.create` | write on the selected application, or on the product if none |
| Move a record to another product or application | the create permission | write on the new scope |
| Create / edit / delete dependency | `dependencies.create` / `.update` / `.delete` | write on the source, read on the target |
| Create / edit / delete reference | `registries.create` / `.update` / `.delete` | write on the parent product/repository, or **global write** for records without a parent (e.g. environments) |
| Resolve / reopen clarification | `clarifications.resolve` | write on the record |
| See repository sensitive notes | `repositories.view_sensitive` | read |
| Create document | `docs.create` | write on the most specific placement (module → repository → product → global) |
| Edit / archive document | `docs.update` / `docs.archive` | write on the document |
| Make a document visible to all users | `access.manage` | — |
| Create task | `tasks.create` | read on the most specific placement (module → repository → application → product) |
| Edit task | `tasks.update` | write on the task (and on a new placement) |
| Choose or change the assignee | `tasks.assign` | write on the task; others keep the current assignee |
| Update status/progress | `tasks.update_assigned` + assignee | read (or full edit rights above) |
| Comment | `tasks.comment` | read |
| View reports | `reports.view` | figures only include readable records |
| Manage users | `users.manage` | — |
| Grant / revoke access rules and scoped roles | `access.manage` | — |
| Change role permissions | `roles.manage` | — |
| Manage dropdown options and templates | `settings.manage` | — |
| View audit log | `audit.view` | entries on unreadable records show as "Restricted record" |

Task assignees must be active and able to read the task.

## Information-leak protections

- Unreadable records return **404** (not 403) on direct ID access, including documents opened via
  relative links (`docs/resolve`).
- Lists, dashboards, search, dropdowns, linked-record panels and the activity feed are filtered by
  the same `AccessService` rules. Restricted document titles, paths and metadata are never shown.
- When a user edits a task, links to documents they cannot see are preserved but not displayed.
- The task form also lists the products of readable applications, repositories and modules, so
  users scoped below product level can place tasks. Only product code and name are shown.

## Lock-out prevention

- Super Admins always have full access and cannot be denied.
- Users cannot deactivate themselves or change their own role.
- The last active Super Admin cannot be deactivated or demoted.
- Only Super Admins can create, edit or deactivate Super Admin accounts or assign that role.

## Deactivation

Deactivated users cannot log in. Their database sessions are deleted and remember-me token
rotated; any other live session is ended on the next request by `EnsureUserIsActive`.
Their records, comments and audit history remain.

## Known limitations

See [KNOWN_LIMITATIONS.md](KNOWN_LIMITATIONS.md): visible-ID lists are computed in PHP, and
users with `access.manage` can see the names of all resources when assigning access.

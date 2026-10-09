# AB Project Hub — Access Control

Every check is enforced on the server (controllers, policies, gates and query filters).
Hidden menu items and buttons are a convenience only.

## Two layers

| Layer | Question | Where configured |
| --- | --- | --- |
| **Capabilities** (role) | *What kind of action may this user perform?* | *Administration → Roles* (`permission_role`) |
| **Scoped access rules** (user) | *On which records?* | *Administration → Users → Access* (`access_rules`) |

An action is allowed only if **both** layers allow it. Example: a Developer has `docs.write`, but
can only edit documents in scopes where they have **write** access.

## Roles and default capabilities

| Capability | Super Admin | Project Admin | Project Manager | Developer | QA Engineer | Viewer |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| `products.create` | ✔ | ✔ | | | | |
| `inventory.write` | ✔ | ✔ | ✔ | | | |
| `repositories.view_sensitive` | ✔ | ✔ | | | | |
| `docs.write` | ✔ | ✔ | ✔ | ✔ | | |
| `tasks.create` | ✔ | ✔ | ✔ | | ✔ | |
| `tasks.manage` | ✔ | ✔ | ✔ | | | |
| `tasks.update_assigned` | ✔ | ✔ | ✔ | ✔ | ✔ | |
| `tasks.comment` | ✔ | ✔ | ✔ | ✔ | ✔ | |
| `users.manage` | ✔ | | | | | |
| `access.manage` | ✔ | | | | | |
| `roles.manage` | ✔ | | | | | |
| `settings.manage` | ✔ | | | | | |
| `audit.view` | ✔ | ✔ | | | | |

These are the seeded defaults; Super Admins can change them. The seeder never overwrites
existing role assignments except for Super Admin, which always has every capability.
Only Super Admins can change capabilities in the *Administration* group.

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

### Resolution order (`AccessService::level`)

1. Inactive user → **no access**.
2. Super Admin → **write** everywhere (cannot be denied; prevents lock-out).
3. Any active **deny** on the record or any of its ancestors → **no access**.
4. Otherwise the **most specific** grant wins
   (document > module = repository > application > product > global).
   On a tie at the same level, write beats read.
5. Otherwise, a document with visibility *All active users* → **read**.
6. Otherwise → **no access**.

Component dependencies are readable only when both endpoints are readable; the level is the
level on the source.

### Rule management

- Granting a rule for a scope where the user already has an active rule revokes the old one
  (one active rule per user and scope). Revoked rules stay in the table with `revoked_by`.
- Super Admins cannot receive rules (they do not need them). A **global deny** is refused.
- Rule changes apply on the next request (the access cache is request-scoped).
- The *Access* screen shows an effective-permission table for products, applications,
  repositories, modules and documents so administrators can verify the result.
- Every grant, deny and revoke is written to the audit log (`access.granted`, `access.denied`,
  `access.revoked`).

## What each action requires

| Action | Capability | Scope requirement |
| --- | --- | --- |
| View any record | — | read |
| Create product | `products.create` | — (creator receives a write rule on the new product) |
| Edit / archive inventory or registry record | `inventory.write` | write on the record |
| Create inventory/registry record | `inventory.write` | write on the parent product/repository, or **global write** for records without a product (e.g. environments) |
| Move a record to another product | `inventory.write` | write on both old and new scope |
| See repository sensitive notes | `repositories.view_sensitive` | read |
| Create / edit / archive document | `docs.write` | write on the most specific scope (module → repository → product → global) |
| Make a document visible to all users | `access.manage` | — |
| Create task | `tasks.create` | read on the product (and selected application/repository/module) |
| Edit / assign task | `tasks.manage` | write on the task |
| Update status/progress | `tasks.update_assigned` + assignee | read (or full edit rights above) |
| Comment | `tasks.comment` | read |
| Manage users | `users.manage` | — |
| Grant / revoke access | `access.manage` | — |
| Change role capabilities | `roles.manage` | — |
| Manage dropdown options | `settings.manage` | — |
| View audit log | `audit.view` | entries on unreadable records show as "Restricted record" |

Task assignees must be active and able to read the task.

## Information-leak protections

- Unreadable records return **404** (not 403) on direct ID access, including documents opened via
  relative links (`docs/resolve`).
- Lists, dashboards, search, dropdowns, linked-record panels and the activity feed are filtered by
  the same `AccessService` rules. Restricted document titles, paths and metadata are never shown.
- When a user edits a task, links to documents they cannot see are preserved but not displayed.

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

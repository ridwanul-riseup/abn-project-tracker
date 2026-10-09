# AB Project Hub — Development Workflow

How to change the hub itself, and how the team is expected to use it day to day.

## Changing the application

1. Branch from `main` (`feature/<short-name>` or `fix/<short-name>`).
2. Make the change. Keep to the existing structure:
   - authorization goes through `AccessService`, policies or the scoped gates — never only in Blade;
   - free-text fields that could hold secrets use the `NoSecrets` rule;
   - new dropdown values belong in `OptionCatalog` (as a group) rather than hard-coded lists;
   - new reference types can usually be added as a definition in `app/Support/Registries.php`.
3. Add or update Pest tests in `tests/Feature` for the behaviour, especially permission edges
   (restricted user gets 404, deny overrides grant, no leak in lists/search).
4. Run `composer test` (style, static analysis, tests). All must pass.
5. Update the affected documents in `docs/` — at minimum [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md)
   and, if relevant, [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md), [ACCESS_CONTROL.md](ACCESS_CONTROL.md)
   and [KNOWN_LIMITATIONS.md](KNOWN_LIMITATIONS.md).
6. Open a pull request. CI (`.github/workflows/tests.yml`) runs the same checks against MySQL.

### Schema changes

- Always add a new migration; never edit a migration that has run in a shared environment.
- Inventory foreign keys use `restrictOnDelete`; prefer archiving to deleting.
- If a new model needs scoped access, implement `HasAccessScope` (use the `ScopedByAccess` trait)
  and register it in the morph map and with a policy in `AppServiceProvider`.

## Using the hub (team workflow)

### Inventory

- Product, application, repository and module records are the canonical inventory.
  Archive instead of deleting; archived records are hidden from default lists and can be shown
  with the list's *archived* filter by anyone who can read them.
- When information is unknown, leave the field empty and add or keep a **Needs Clarification**
  item. Resolve it with a short note once the answer is confirmed.

### Documentation

- Use the templates when creating documents (overview, architecture, module, API, integration,
  decision, troubleshooting).
- Write a short change summary on every save; it appears in the document's history.
- Documents can also be edited in Git. The hub detects that the file changed outside the hub and
  shows a notice; the next save from the hub records a normal revision.
- Never paste credentials, tokens or keys. Reference the secret-manager entry by name instead.

### Tasks

- Create tasks in the product they belong to; link the repository, module and documents.
- Statuses come from *Settings → Task status*. A status with *blocked* behaviour requires a
  blocker description. Status with *closed* behaviour hides the task from the default
  (open) list.
- Assignees update status and progress notes; managers edit, re-prioritise and reassign.
- External issue trackers can be linked with the *Issue URL* field. The hub does not sync with them.

### Access

- Grant access at the **product** level by default. Use repository/module/document rules only
  for exceptions, and **deny** for explicit exclusions.
- Use the effective-permission table on the user's *Access* page to verify changes.
- GitHub (or other Git host) permissions are managed in the Git host, not in the hub.

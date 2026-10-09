# Markdown Documentation Guide

How documentation is stored and how to write it so it works both in the hub and in Git.

## Where files live

All managed documents are `.md` files under the docs directory (`HUB_DOCS_PATH`, default `docs/`).
The database stores metadata only (title, category, product/repository/module, visibility, owner,
content hash). The file is the content.

```
docs/
  README.md                      ecosystem documentation home
  glossary.md
  products/
    shared-platform/             SHR
    ott/                         OTT
    ab-intelligence/             ABI (includes auton-os.md)
    abos-erp/                    ERP
  integrations/  databases/  deployment/  decisions/
  PROJECT_OVERVIEW.md … IMPLEMENTATION_STATUS.md   documentation of the hub itself
```

### How paths are chosen

The hub generates the path when a document is created; users cannot type a path.

- With a product: `products/<product-slug>/<title-slug>.md`
- Without a product: `<category-slug>/<title-slug>.md`
- If the path exists, `-2`, `-3`, … is appended.

The path **does not change** later, even if the title or product changes (links and Git history
stay stable).

Path rules (enforced by `DocumentPath`): relative, `/` separators, each segment starts with a
letter or digit and contains only `A–Z a–z 0–9 . _ -`, no `..`, ends with `.md`, max 255 chars.

## Creating and editing in the hub

1. *Documentation → New document*, pick a template, product (optional), repository/module
   (optional, must belong to the product), category and visibility.
2. Write in the editor; the *Preview* tab renders exactly what readers will see.
3. Enter a change summary and save.

If someone else saved the same document after you opened it, the hub refuses to overwrite and
asks you to reload and re-apply your change.

### Visibility

| Visibility | Who can read |
| --- | --- |
| *Restricted — inherits access* (default) | Users with access to the document's product/repository/module, or a document rule. |
| *All active users* | Every active user unless explicitly denied. Only users with `access.manage` can choose this. |

Editing always requires `docs.write` plus write access to the document's scope.

### Templates

`resources/doc-templates/*.md`: blank, overview, architecture, module, api, integration, decision,
troubleshooting. Placeholders `{{title}}`, `{{product}}` and `{{date}}` are filled in on creation.

## Editing in Git

Files may also be edited in an editor and committed. Afterwards:

- If the file is already registered, the hub shows *"changed outside the hub"* until the next hub
  save. History for such changes is in Git, not in the hub's revision list.
- New files must be **registered** to appear in the hub. Shipped files are registered by
  `DocumentSeeder` (add new entries there); there is no UI to import arbitrary files.

## Writing rules

- One `#` H1 per document — the title.
- Use relative links between documents: `[Glossary](../glossary.md)` or
  `[Auth](authentication.md#login-flow)`. The hub turns them into links that respect permissions;
  readers without access get "not found".
- Use fenced code blocks with a language: <code>```php</code>.
- GitHub-flavoured tables, task lists and strikethrough are supported.
- Images: only remote `https://` images are displayed. Local image files are not served.
- Raw HTML is shown as text (escaped), and `javascript:`/`data:` links are removed.
- Maximum content length when saving from the hub is 524,288 characters (`hub.docs.max_bytes`).
- Mark unknown facts as **Needs Clarification** instead of guessing.

## Never put in documents

Passwords, API keys, tokens, private keys, connection strings with credentials, or production
secrets. Saving content that matches common secret patterns is rejected. Refer to the secret by
name and location in the secret manager (for example *"Vault: ott/prod/db"*), and record that
reference in the deployment's *Secret-manager reference* field.

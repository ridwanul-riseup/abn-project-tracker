# AB Project Hub — Security

Summary of the security controls that are implemented, and the responsibilities that remain
with operators.

## Authentication and sessions

| Control | Implementation |
| --- | --- |
| Login | Email + password; only active accounts can authenticate (`LoginController`). Generic error message. |
| Brute-force protection | 5 failed attempts per email+IP, then a 60-second lockout; additionally `throttle:20,1` on the login route. |
| Session fixation | Session regenerated on login; invalidated and CSRF token regenerated on logout. |
| Password reset | Laravel password broker; reset routes throttled to 5/min per IP; tokens expire per `config/auth.php`. Administrators can send a reset link to a user. |
| Password rules | Production: ≥ 12 chars, mixed case, numbers, symbols, not in known breaches. Other environments: ≥ 8 chars. |
| Password change | Requires the current password; throttled (`sensitive`, 30/min). |
| Deactivation | Blocks login, deletes the user's database sessions, rotates the remember token; `EnsureUserIsActive` ends any other session on the next request. |
| Cookies | HTTP-only session cookie; `SESSION_SECURE_COOKIE=true` required in production; `SameSite=lax`. |

## Authorization

- Server-side on every request: route `auth` middleware, policies, capability gates and scoped
  gates, and query filtering through `AccessService` for every list, search, dashboard widget,
  dropdown and the activity feed. See [ACCESS_CONTROL.md](ACCESS_CONTROL.md).
- **IDOR**: records are loaded by ID and then checked; unreadable records return 404. Foreign keys
  submitted in forms (product, repository, module, document, assignee …) are validated to be
  readable/writable by the user and to belong to the same product.
- Mass assignment: controllers pass only validated fields; system fields (`created_by`,
  `content_hash`, task `key`/`number`, document `path`) are set server-side.
- Administrative lock-out protections (last Super Admin, self-deactivation, role escalation).

## Input and output

| Threat | Mitigation |
| --- | --- |
| CSRF | Laravel CSRF middleware on all state-changing routes; `@csrf` in every form; preview uses the token header. |
| XSS | Blade `{{ }}` escaping everywhere. Markdown rendered with `html_input: escape` and `allow_unsafe_links: false`; only `https://` images; external links get `rel="noopener noreferrer"`. Optional strict CSP with no inline scripts/styles. |
| SQL injection | Eloquent / query builder with bound parameters; sort and filter values are whitelisted. |
| Path traversal | Document paths generated server-side and validated by `DocumentPath`; storage resolves real paths, rejects anything outside the docs root and any symlink in the path. Only registered documents can be read or written. |
| Source-code editing | Impossible through the UI: the storage only writes `.md` files under `HUB_DOCS_PATH`. |
| Clickjacking | `X-Frame-Options: DENY`, CSP `frame-ancestors 'none'`. |
| Caching of private pages | `Cache-Control: no-store, private` for authenticated responses. |
| Rate limiting | Login, password reset, password change, Markdown preview, task comments, reset-link sending, and all admin routes (120/min). |

## Secrets

The hub is **not** a secret store.

- No credentials, tokens or keys are stored in seeders, Markdown or ordinary records. The
  workbook's credentials sheet was empty and was **not** imported; a clarification item records
  that a secret manager must be agreed.
- The `NoSecrets` validation rule rejects common secret formats (private-key blocks, AWS access
  key IDs, GitHub/Slack/Stripe/Google tokens, `user:password@` in URLs) in document content and
  free-text inventory fields. It is a safety net and cannot detect every secret.
- Deployments have a *Secret-manager reference* field for the **name/location** of a secret only.
- Repository *sensitive notes* are limited to users with `repositories.view_sensitive`; they are
  still subject to `NoSecrets`.
- Logs: request data is not logged by the application. The audit log stores field changes but
  never `password` or `remember_token` (`Audit::NEVER_LOG`), and repository `sensitive_notes`
  values are recorded as `[redacted]`.
- `.env` is git-ignored. Operators must store production values in a secret manager.

## Audit

`activity_logs` records create/update/delete with field diffs for audited models and explicit
events: access grants/denies/revokes, role capability changes, document revisions, task comments
and document links, password changes and reset-link sending. Logins are **not** audited (only
`users.last_login_at` is updated). Readers only see entries for records they can read.

## Responsibilities outside the application

- HTTPS, HSTS and web-server hardening.
- Protecting `.env`, database backups and the docs directory.
- Reviewing the docs directory's Git history for accidentally committed secrets.
- Choosing and operating the secret manager (**Needs Clarification**).
- Mail transport security for password-reset emails.

## Reporting a vulnerability

Report privately to the hub maintainers (contact: **Needs Clarification**). Do not open a public
issue.

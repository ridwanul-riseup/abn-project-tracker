# AB Project Hub — Deployment

The hub's production environment, hosting provider and secret manager are **Needs Clarification**.
This guide describes a standard single-server Laravel deployment that the application supports.
It does not describe an existing production setup.

## Server requirements

- Linux, Nginx (or Apache) with PHP-FPM 8.5 and the extensions listed in
  [DEVELOPMENT_SETUP.md](DEVELOPMENT_SETUP.md)
- MySQL 8
- HTTPS (TLS certificate)
- Node.js 22 only on the build machine (assets can be built in CI and shipped)
- Outgoing mail (SMTP or provider) for password-reset links

No queue worker or scheduler is required: mail is sent synchronously (`QUEUE_CONNECTION=sync`)
and there are no scheduled jobs.

## Production `.env`

Start from `.env.example` and set at least:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<hub-host>
APP_KEY=            # php artisan key:generate --show, store in the secret manager

DB_HOST=…  DB_DATABASE=…  DB_USERNAME=…  DB_PASSWORD=…   # from the secret manager

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
LOG_LEVEL=warning

MAIL_MAILER=smtp    # plus MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD / MAIL_FROM_ADDRESS

HUB_ADMIN_EMAIL=<first-admin-email>
HUB_ADMIN_PASSWORD=          # leave empty: a random password is printed once by the seeder
HUB_DOCS_PATH=docs           # or an absolute path outside the release directory (see below)
HUB_DOCS_REPO_URL=https://<git-host>/<org>/<repo>
HUB_DOCS_REPO_BRANCH=main
HUB_CSP_ENABLED=true
```

The `.env` file must not be committed and must not be readable by other users on the server.
In production, `Password::defaults()` requires at least 12 characters with mixed case, numbers,
symbols, and a check against known breached passwords (`uncompromised()`, which calls the
Have I Been Pwned range API).

## First deployment

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # or copy public/build from CI
php artisan migrate --force
php artisan db:seed --force      # ONLY on the first deployment (see below)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Note the generated Super Admin password from the seeder output, log in, and change it.

## Subsequent deployments

```bash
php artisan down
git pull                          # or switch the release symlink
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # new permissions, keeps role edits; converts inventory.write/docs.write/tasks.manage
php artisan db:seed --class=OptionSeeder --force                # new options, keeps label/order/default edits
php artisan db:seed --class=DocumentTemplateSeeder --force      # only fills an empty template table
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

**Do not run the full `db:seed` again on a live database.** `WorkbookSeeder` resets
workbook-sourced fields on workbook-coded records, overwriting edits made in the hub.
`OptionSeeder` recreates seeded options that an administrator deleted.

Migration `2026_10_09_000008` adds option defaults and sets initial ones (task status *Backlog*,
type *Feature*, priority *Medium*) where those options exist and are active. Administrators can
change them afterwards.

`DemoUserSeeder` is part of the full `db:seed` run. It refuses to run when `APP_ENV=production`,
but on any other environment it creates demo users with the shared password `password`. Do not
run the full seeder on staging or other reachable servers.

`DB::prohibitDestructiveCommands()` is active in production, so `migrate:fresh`, `db:wipe`, etc.
are refused.

## Markdown docs directory

The hub writes Markdown files to `HUB_DOCS_PATH`. Choose one of:

1. **Inside the Git checkout** (default `docs/`). Edits made in the hub are uncommitted changes
   on the server. Someone must commit and push them (or pull Git changes) — the hub does **not**
   run Git commands. With zero-downtime "release directory" deployments, this option loses hub
   edits on each release unless the directory is shared.
2. **Shared absolute path** (e.g. `/srv/ab-hub/docs`), a separate clone of the docs or the
   repository, symlinked or configured via `HUB_DOCS_PATH`. Recommended for release-directory
   deployments. Commit/push from that clone on a schedule or manually.

Which option the team uses is **Needs Clarification**. In either case:

- the PHP-FPM user needs write access to the directory (`0775` directories, `0664` files);
- the directory must contain only documentation — the hub can write any registered `.md` path
  inside it;
- back it up together with the database (document metadata and content must stay consistent).

## Web server

- Document root: `public/`.
- Deny access to dotfiles and everything outside `public/`.
- Redirect HTTP to HTTPS; enable HSTS at the web server.
- The app sends `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy`, and (with `HUB_CSP_ENABLED=true`) a strict CSP.
- Health check endpoint: `GET /up`.

## Docker

The repository ships a `Dockerfile` (PHP 8.5 + Apache, assets built with Node 22 in a separate
stage, production Composer dependencies only) and a `docker-compose.yml` with MySQL 8.4.

```bash
cp .env.docker.example .env.docker   # fill in the passwords; .env.docker is git-ignored
docker compose up -d --build         # http://localhost:8000 (change with HUB_HTTP_PORT=8080)
docker compose logs -f app
```

On every start the container entrypoint (`docker/entrypoint.sh`):

1. uses `APP_KEY` from `.env.docker`; if it is empty, it generates a key once and keeps it in the
   storage volume, so sessions survive restarts;
2. waits for the database, then runs `php artisan migrate --force`;
3. on the **first** start runs the full `db:seed` (roles, options, templates, initial Super Admin,
   workbook inventory, shipped documents). The generated admin password appears once in
   `docker compose logs app`. On later starts it only runs `RolesAndPermissionsSeeder`, which keeps
   admin edits. Set `HUB_SEED_ON_FIRST_RUN=false` to skip seeding. `DemoUserSeeder` refuses to run
   because `APP_ENV=production`;
4. caches config, routes and views (`php artisan optimize`) and starts Apache.

| Volume | Mounted at | Contents |
| --- | --- | --- |
| `hub-db` | `/var/lib/mysql` | MySQL data |
| `hub-docs` | `/var/www/html/docs` | Managed Markdown documents. Filled from the image on first creation |
| `hub-storage` | `/var/www/html/storage` | Logs, generated `APP_KEY`, first-seed marker |

Notes:

- Back up `hub-db` and `hub-docs` together, e.g.
  `docker compose exec db sh -c 'mysqldump --single-transaction -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > hub.sql`.
- Documents edited in the hub live in the `hub-docs` volume, not in the Git checkout. Newer shipped
  documents in a rebuilt image are not copied into an existing volume.
- If you delete `hub-db` but keep `hub-storage`, remove the volume's
  `storage/app/private/.docker-seeded` file (or the whole volume) so the next start seeds again.
- Changing `.env.docker` needs `docker compose up -d` (recreates the container and rebuilds the config cache).
- For HTTPS, put a reverse proxy (nginx, Traefik, Caddy) in front of the container and set
  `APP_URL=https://…` and `SESSION_SECURE_COOKIE=true`. Laravel's trusted proxies are not configured
  yet; configure them in `bootstrap/app.php` if the proxy terminates TLS.
- Run Artisan commands with `docker compose exec app php artisan <command>`.

## Backups

- Daily MySQL dump (`mysqldump --single-transaction`).
- The docs directory (or rely on the Git remote if every change is pushed).
- `.env` lives in the secret manager, not in backups.

## Rollback

1. `php artisan down`
2. Restore the previous release/commit and run `composer install`.
3. Only roll back migrations if the release added migrations and they are reversible:
   `php artisan migrate:rollback --step=<n> --force`.
4. Rebuild caches and `php artisan up`.

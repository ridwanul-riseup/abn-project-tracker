# AB Project Hub — Development Setup

## Requirements

- PHP 8.5 with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `zip`
- Composer 2
- MySQL 8 (8.4 recommended)
- Node.js 22 and npm (only to build CSS/JS assets)
- Git

## 1. Clone and install

```bash
git clone <repository-url> ab-project-hub
cd ab-project-hub
composer install
npm install
cp .env.example .env
php artisan key:generate
```

## 2. Create the databases

```sql
CREATE DATABASE ab_project_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE ab_project_hub_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ab_hub'@'localhost' IDENTIFIED BY '<choose-a-local-password>';
GRANT ALL PRIVILEGES ON ab_project_hub.* TO 'ab_hub'@'localhost';
GRANT ALL PRIVILEGES ON ab_project_hub_test.* TO 'ab_hub'@'localhost';
```

`ab_project_hub_test` is used by the automated tests (`phpunit.xml` sets `DB_DATABASE`), and is
wiped on every test run. Never point it at real data.

## 3. Configure `.env`

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Base URL, e.g. `http://localhost:8000`. Used for password-reset links and to tell internal from external links in Markdown. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection. |
| `SESSION_DRIVER` | `database` (default) — required for "deactivate signs the user out everywhere". |
| `SESSION_SECURE_COOKIE` | `true` in production (HTTPS). |
| `MAIL_*` | Mail transport for password-reset links. Local default `log` writes mails to `storage/logs/laravel.log`. |
| `HUB_ADMIN_NAME`, `HUB_ADMIN_EMAIL`, `HUB_ADMIN_PASSWORD` | Initial Super Admin created by the seeder. Leave the password empty to have a random one generated and printed once. |
| `HUB_DOCS_PATH` | Directory of managed Markdown, relative to the project root or absolute. Default `docs`. |
| `HUB_DOCS_REPO_URL`, `HUB_DOCS_REPO_BRANCH` | Optional `https://` URL of this Git repository; enables "View in repository" and "Git history" links on documents. |
| `HUB_CSP_ENABLED` | Send a strict Content-Security-Policy. Recommended in production; it blocks the Vite dev server's hot reload. |

## 4. Migrate and seed

```bash
php artisan migrate --seed
```

The default seeders create:

1. roles and capabilities,
2. global dropdown options,
3. the initial Super Admin (from `HUB_ADMIN_*`; password printed once if generated),
4. the workbook inventory (`database/seeders/data/abn_workbook.json`) with Needs Clarification items,
5. document records for the Markdown files shipped in `docs/`.

The seeders are idempotent — running `php artisan db:seed` again does not duplicate data.
**But** the workbook seeder resets workbook-sourced fields (name, description, owner, status, …)
of records whose code comes from the workbook. Once the team edits inventory in the hub, do not
re-run `WorkbookSeeder` against that database.

Optional, **local only**: one demo user per role with sample access (refuses to run in production):

```bash
php artisan db:seed --class=DemoUserSeeder
```

## 5. Run

```bash
npm run build          # build assets once
php artisan serve      # http://localhost:8000
```

Or run the server and the Vite dev server together: `composer dev` (wraps `php artisan dev`).
Keep `HUB_CSP_ENABLED=false` while using the Vite dev server.

Log in with the Super Admin email and password from step 4.

## 6. Checks

```bash
composer test          # pint --test, phpstan (level 7), Pest test suite
php artisan test       # tests only
vendor/bin/pint        # fix code style
```

See [TESTING.md](TESTING.md).

## Docs directory permissions

The web server user must be able to write to `HUB_DOCS_PATH` (default `docs/`) to create and edit
documents. Files are written with mode `0664`, directories `0775`.

## Re-importing the workbook

`database/seeders/data/abn_workbook.json` was generated from the `.xlsx` with
`scripts/xlsx_to_json.py` (Python 3, standard library only):

```bash
python3 scripts/xlsx_to_json.py "database/seeders/data/ABN Project Details for Development Team.xlsx" \
  database/seeders/data/abn_workbook.json
php artisan db:seed --class=WorkbookSeeder
```

The seeder matches records by their workbook codes and overwrites their workbook-sourced fields
with the spreadsheet values (hub edits to those fields are lost). It does not delete records that
were added in the hub.

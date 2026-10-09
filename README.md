# AB Project Hub

Internal documentation and development tracker for the **AB Digital Ecosystem**:
AB Shared Platform (SHR), ABN+ OTT (OTT), AB Intelligence (ABI, including AutonOS) and ABOS ERP (ERP).

It records the project inventory (products, applications, repositories, modules, dependencies,
API/database/Figma/environment/deployment/release/integration references), keeps documentation as
real Markdown files under [`docs/`](docs/README.md), tracks development tasks, and controls who can
see and change what with scoped read/write/deny access rules.

Built with Laravel 13, Blade, Bootstrap 5 and MySQL 8. No external services are required.

## Quick start

```bash
git clone <repository-url> ab-project-hub && cd ab-project-hub
composer install && npm install
cp .env.example .env && php artisan key:generate
# create the MySQL databases and set DB_* in .env (see docs/DEVELOPMENT_SETUP.md)
php artisan migrate --seed       # prints the initial Super Admin password once
npm run build
php artisan serve                # http://localhost:8000
```

Run the checks:

```bash
composer test                    # Pint, PHPStan, Pest (needs the ab_project_hub_test database)
```

## Configuration

Hub-specific environment variables (all others are standard Laravel):

| Variable | Default | Purpose |
| --- | --- | --- |
| `HUB_ADMIN_NAME`, `HUB_ADMIN_EMAIL` | `Hub Administrator`, `admin@example.com` | Initial Super Admin created by the seeder |
| `HUB_ADMIN_PASSWORD` | empty | Empty → a random password is generated and printed once |
| `HUB_DOCS_PATH` | `docs` | Directory of managed Markdown files |
| `HUB_DOCS_REPO_URL`, `HUB_DOCS_REPO_BRANCH` | empty, `main` | Optional links to files and their Git history |
| `HUB_CSP_ENABLED` | `false` | Send a strict Content-Security-Policy (enable in production) |

## Documentation

| Document | Contents |
| --- | --- |
| [Project overview](docs/PROJECT_OVERVIEW.md) | Purpose, products, features |
| [Architecture](docs/ARCHITECTURE.md) | Components, request flow, Markdown storage design |
| [Database schema](docs/DATABASE_SCHEMA.md) | Tables and relationships |
| [Access control](docs/ACCESS_CONTROL.md) | Roles, capabilities, scoped rules, precedence |
| [Development setup](docs/DEVELOPMENT_SETUP.md) | Local installation |
| [Development workflow](docs/DEVELOPMENT_WORKFLOW.md) | Changing the hub and using it day to day |
| [Markdown documentation guide](docs/MARKDOWN_DOCUMENTATION_GUIDE.md) | Writing and organising docs |
| [Deployment](docs/DEPLOYMENT.md) | Production setup, upgrades, backups |
| [Security](docs/SECURITY.md) | Implemented controls and operator responsibilities |
| [Testing](docs/TESTING.md) | Test setup and coverage per workflow |
| [Known limitations](docs/KNOWN_LIMITATIONS.md) | Trade-offs and gaps |
| [Implementation status](docs/IMPLEMENTATION_STATUS.md) | Checklist of done / incomplete work and latest test results |
| [Ecosystem documentation](docs/README.md) | Product docs derived from the project workbook |

## Important rules

- Never store credentials, tokens, private keys or production secrets in the hub, in Markdown or
  in seeders. Reference the secret-manager entry by name instead.
- Missing project facts are recorded as **Needs Clarification**, not guessed.
- The hub does not change GitHub permissions and does not run Git commands.

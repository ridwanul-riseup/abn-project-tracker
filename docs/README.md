# AB Digital Ecosystem — Documentation Home

This directory is the managed documentation store of **AB Project Hub**. Every
file here is plain Markdown and can be read on GitHub, in an editor, or in the
hub (which adds access control, search, revision history and links to related
records).

## Products

| ID  | Product | Description (from the project workbook) |
|-----|---------|------------------------------------------|
| SHR | [AB Shared Platform](products/shared-platform/overview.md) | Shared authentication, users, organizations, roles, permissions and platform services |
| OTT | [ABN+ OTT](products/ott/overview.md) | Streaming, VOD, live TV/sports, subscriptions, CMS and entitlement |
| ABI | [AB Intelligence](products/ab-intelligence/overview.md) | CRM, AI orchestration, client portal and AutonOS |
| ERP | [ABOS ERP](products/abos-erp/overview.md) | HR, attendance, inventory, procurement, finance and payroll |

The Shared Platform provides common services to the other three products.
Shared functionality is documented under SHR and must not be duplicated inside
a product's documentation; product documents link to it instead.

AutonOS is part of **AB Intelligence** ([AutonOS](products/ab-intelligence/auton-os.md)).
It is not related to ABOS ERP despite the similar name.

## Cross-product documentation

- [Glossary](glossary.md)
- [Integrations](integrations/overview.md)
- [Databases](databases/overview.md)
- [Environments and deployment](deployment/environments.md)
- [Architecture decision records](decisions/README.md)

## About the hub itself

- [Project overview](PROJECT_OVERVIEW.md) · [Architecture](ARCHITECTURE.md) · [Database schema](DATABASE_SCHEMA.md)
- [Access control](ACCESS_CONTROL.md) · [Security](SECURITY.md)
- [Development setup](DEVELOPMENT_SETUP.md) · [Development workflow](DEVELOPMENT_WORKFLOW.md) · [Testing](TESTING.md)
- [Markdown documentation guide](MARKDOWN_DOCUMENTATION_GUIDE.md) · [Deployment](DEPLOYMENT.md)
- [Known limitations](KNOWN_LIMITATIONS.md) · [Implementation status](IMPLEMENTATION_STATUS.md)

## Directory layout

```text
docs/
├── README.md                 this page
├── glossary.md
├── products/<product-slug>/  product, module and component documents
├── integrations/  databases/  deployment/  decisions/  …  cross-product categories
└── *.md (UPPER_CASE)         documentation of AB Project Hub itself
```

New documents created in the hub are placed automatically: product documents
under `products/<product-slug>/`, cross-product documents under the folder of
their category.

> Never write passwords, tokens, private keys or other secrets in these files.
> Reference where a secret is stored (for example "AWS Secrets Manager: `ott/prod/db`") instead.

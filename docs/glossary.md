# Glossary

Terms from sheet `01-glossary` of the project workbook, plus terms used by AB Project Hub.

## Product and layer abbreviations (workbook)

| Term | Meaning |
|------|---------|
| SHR  | AB Shared Platform |
| OTT  | ABN+ OTT |
| ABI  | AB Intelligence / CRM |
| ERP  | ABOS ERP |
| FE   | Frontend |
| BE   | Backend |
| INT  | Integration |
| DB   | Database |
| API  | Application Programming Interface |
| UAT  | User Acceptance Testing |
| ADR  | Architecture Decision Record |

## Identifier conventions observed in the workbook

| Pattern | Example | Meaning |
|---------|---------|---------|
| `{PRODUCT}-{APP}` | `OTT-WEB` | Application within a product |
| `{PRODUCT}-{APP}-{FE\|BE}` | `OTT-WEB-BE` | Repository (frontend or backend of an application) |
| `{PRODUCT}-{MODULE}` | `OTT-SUB` | Functional module |
| `{SOURCE}-{TARGET}-INT-{NNN}` | `SHR-OTT-INT-001` | Integration between two products |
| `{PRODUCT}-DB` | `OTT-DB` | Product database (`CRM-DB` is the ABI database) |
| `DEP-{NNN}` | `DEP-001` | Dependency between components |
| `ENV-{NAME}` | `ENV-UAT` | Environment |

## AB Project Hub terms

| Term | Meaning |
|------|---------|
| Needs Clarification | Information that was missing or ambiguous in the source data. The hub records the issue instead of inventing a value. |
| Access rule | A read, write or deny permission for one user on one scope (application-wide, product, application, repository, module or document). |
| Capability | Something a role is allowed to do (for example "Edit documentation"). Capabilities apply only inside scopes the user can access. |

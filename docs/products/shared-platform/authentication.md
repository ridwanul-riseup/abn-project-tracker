# Shared Authentication (SHR-AUTH)

> Module of the [AB Shared Platform](overview.md). Workbook status of the product: Planned.

## What the workbook says

| Field | Value |
|-------|-------|
| Module ID | SHR-AUTH |
| Module | Authentication |
| Application (workbook) | Platform |
| Primary repository | SHR-ADM-BE |
| API base path of SHR-ADM-BE | `/api/v1/shared/*` |

Dependency `DEP-001`: **OTT-WEB-BE depends on SHR-ADM-BE for authentication**.
Criticality: Critical. Failure impact: "OTT login unavailable".

All other product backends (ERP-ADM-BE, ABI-WEB-BE, ABI-CP-BE, OTT-ADM-BE,
OTT-ACT-BE) also list SHR-ADM-BE as a dependency, without stating the type.

## Needs Clarification

The following are not defined in the workbook and must be decided and documented here:

- Authentication protocol (for example OAuth 2.0 / OIDC, session, JWT) and token lifetime.
- How products validate tokens (introspection, shared keys, JWKS endpoint).
- Single sign-on across products and the tenant/organization model (see SHR-ORG).
- Password, MFA and account-recovery policy.
- Where signing keys and client secrets are stored (reference only — never paste them here).

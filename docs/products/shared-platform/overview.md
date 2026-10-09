# AB Shared Platform (SHR) — Overview

> Source: project workbook sheets 02–09. Status in the workbook: **Planned**.

## Purpose

Shared authentication, users, organizations, roles, permissions and platform
services used by ABN+ OTT, AB Intelligence and ABOS ERP.

| Field | Value |
|-------|-------|
| Product ID | SHR |
| Database | `SHR-DB` (`shared_db`), used by ABI/OTT/ERP |
| Primary owner | "Shared Platform" (team name — named owner **Needs Clarification**) |

## Applications and repositories

| Application | Repository | Layer | Repository name / URL | API |
|-------------|------------|-------|------------------------|-----|
| SHR-ADM Admin Panel | SHR-ADM-FE | Frontend | AB-Shared-Platform-Admin-Panel-Frontend-Design (URL **Needs Clarification**) | Shared Platform API |
| SHR-ADM Admin Panel | SHR-ADM-BE | Backend | AB-Shared-Platform-Admin-Panel-Backend (URL **Needs Clarification**) | `/api/v1/shared/*` |

Workbook module label for both repositories: "Platform Admin".

## Modules

| Module ID | Module | Primary repository |
|-----------|--------|--------------------|
| SHR-AUTH | [Authentication](authentication.md) | SHR-ADM-BE |
| SHR-USER | Users | SHR-ADM-BE |
| SHR-ORG | Organizations/Tenants | SHR-ADM-BE |
| SHR-RBAC | Roles & Permissions | SHR-ADM-BE |

## Consumers

Every product backend lists `SHR-ADM-BE` as a dependency in the workbook, and
the integrations `SHR-OTT-INT-001`, `SHR-ABI-INT-001` and `SHR-ERP-INT-001`
connect the Shared Platform to each product. See [Integrations](../../integrations/overview.md).

The only dependency with criticality recorded in the workbook is:

| ID | Source | Depends on | Type | Criticality | Failure impact |
|----|--------|-----------|------|-------------|----------------|
| DEP-001 | OTT-WEB-BE | SHR-ADM-BE | Authentication | Critical | OTT login unavailable |

## Deployments (workbook sheet 09)

| Deployment ID | Environment | Infrastructure | Method | Branch | Version | Status |
|---------------|-------------|----------------|--------|--------|---------|--------|
| SHR-ADM-BE-DEP-001 | DEV | AWS | CI/CD | develop | 0.1.0 | Planned |

## Design

Figma reference `SHR-ADM-FIG-001` (Shared Admin, UI/UX, repository SHR-ADM-FE). The design link is stored on the SHR-ADM-FE repository record.

## Needs Clarification

- Repository URLs for SHR-ADM-FE and SHR-ADM-BE.
- The SHR-ADM-BE dependency "Shared DB" was interpreted as `SHR-DB`; confirm.
- Authentication protocol, token format and tenant model are not specified in the workbook.

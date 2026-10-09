# ABN+ OTT (OTT) — Overview

> Source: project workbook sheets 02–11. Status in the workbook: **Planned**.

## Purpose

Streaming, VOD, live TV/sports, subscriptions, CMS and entitlement.

| Field | Value |
|-------|-------|
| Product ID | OTT |
| Database | `OTT-DB` (`ott_db`) |
| Primary owner | "OTT Product" (named owner **Needs Clarification**) |

## Applications and repositories

| Application | Repository | Layer | Repository name / URL | API |
|-------------|------------|-------|------------------------|-----|
| OTT-ADM Admin Panel | OTT-ADM-FE | Frontend | https://github.com/riseup-labs/ABN-OTT-Admin-Panel-Frontend.git | OTT Admin API |
| OTT-ADM Admin Panel | OTT-ADM-BE | Backend | ABN-Plus-OTT-Admin-Panel-Backend (URL **Needs Clarification**) | `/api/v1/ott/admin/*` |
| OTT-ACT Actor Dashboard | OTT-ACT-FE | Frontend | ABN-Plus-OTT-Actor-Dashboard-Frontend-Design (URL **Needs Clarification**) | OTT Actor API |
| OTT-ACT Actor Dashboard | OTT-ACT-BE | Backend | ABN-Plus-OTT-Actor-Dashboard-Backend (URL **Needs Clarification**) | `/api/v1/ott/actor/*` |
| OTT-WEB Website | OTT-WEB-FE | Frontend | https://github.com/riseup-labs/ABN-OTT-Frontend.git | OTT API |
| OTT-WEB Website | OTT-WEB-BE | Backend | ABN-Plus-OTT-Website-Backend (URL **Needs Clarification**) | `/api/v1/ott/*` |

Workbook module labels: OTT CMS (admin), Actor (actor dashboard), OTT Consumer (website).

## Modules

All OTT modules list **OTT-WEB-BE** as primary repository in the workbook.

| Module ID | Module |
|-----------|--------|
| OTT-CONTENT | Content |
| OTT-SUB | Subscription |
| OTT-PAY | Payment |
| OTT-ENT | Entitlement |
| OTT-VOD | VOD |
| OTT-LIVE | Live TV |

## Dependencies and integrations

- Every OTT backend depends on `SHR-ADM-BE` and `OTT-DB`; frontends depend on their backend and on `SHR-ADM-BE`.
- `DEP-001`: OTT-WEB-BE → SHR-ADM-BE, Authentication, **Critical**, "OTT login unavailable".
- Integrations: `SHR-OTT-INT-001` (all OTT repositories) and `OTT-ABI-INT-001` (OTT-WEB-BE). See [Integrations](../../integrations/overview.md).

## Deployments and releases

| Deployment ID | Repo | Environment | Infrastructure | Method | Branch | Version | Status |
|---------------|------|-------------|----------------|--------|--------|---------|--------|
| OTT-WEB-BE-DEP-001 | OTT-WEB-BE | DEV | AWS | CI/CD | develop | 0.1.0 | Planned |
| OTT-WEB-BE-DEP-002 | OTT-WEB-BE | QA | AWS | CI/CD | release/* | 0.1.0 | Planned |

Release `OTT-REL-001`: version 0.1.0 of OTT-WEB-FE and OTT-WEB-BE to DEV, status Planned, release date **TBD**.

## Design

Figma references: `OTT-WEB-FIG-001` (Website), `OTT-ADM-FIG-001` (Admin), `OTT-ACT-FIG-001` (Actor Dashboard). Links are stored on the corresponding frontend repository records.

## Needs Clarification

- Repository URLs for the four repositories listed above without a URL.
- Payment provider(s), DRM, CDN and streaming technology are not specified.
- What `OTT-ABI-INT-001` exchanges between OTT and AB Intelligence.

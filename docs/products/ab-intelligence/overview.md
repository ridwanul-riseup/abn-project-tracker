# AB Intelligence (ABI) — Overview

> Source: project workbook sheets 02–11. Status in the workbook: **Planned**.
> The workbook glossary also calls this product "AB Intelligence / CRM".

## Purpose

CRM, AI orchestration, client portal and [AutonOS](auton-os.md).

| Field | Value |
|-------|-------|
| Product ID | ABI |
| Database | `CRM-DB` (`crm_db`) |
| Primary owner | "ABI Product" (named owner **Needs Clarification**) |

## Applications and repositories

| Application | Repository | Layer | Repository name / URL | API |
|-------------|------------|-------|------------------------|-----|
| ABI-WEB Website | ABI-WEB-FE | Frontend | https://github.com/riseup-labs/AB-Intelligence-Marketing-Website-Frontend.git | ABI API |
| ABI-WEB Website | ABI-WEB-BE | Backend | AB-Intelligence(CRM)-Website-Backend (URL **Needs Clarification**) | `/api/v1/abi/*` |
| ABI-ADM Admin Panel | ABI-ADM-FE | Frontend | https://github.com/riseup-labs/AB-Intelligence-Admin-Frontend.git | ABI Admin API |
| ABI-AUT AutonOS | ABI-AUT-FE | Frontend | AB-Intelligence(CRM)-AutonOs-Frontend-Design (URL **Needs Clarification**) | AutonOS API |
| ABI-CP Client Portal | ABI-CP-FE | Frontend | https://github.com/riseup-labs/AB-Intelligence-Client-Portal-Frontend.git | Client Portal API |
| ABI-CP Client Portal | ABI-CP-BE | Backend | AB-Intelligence(CRM)-Client-Portal-Backend (URL **Needs Clarification**) | `/api/v1/abi/portal/*` |

The workbook lists ABI-ADM and ABI-AUT as frontend-only. Their backends are
not named: ABI-ADM-FE and ABI-AUT-FE depend on ABI-WEB-BE (**Needs Clarification**:
confirm that ABI-WEB-BE serves the admin panel and AutonOS).

## Modules

| Module ID | Module | Primary repository |
|-----------|--------|--------------------|
| ABI-CRM | CRM | ABI-WEB-BE |
| ABI-PORTAL | Client Portal | ABI-CP-BE |
| ABI-AUTONOS | Automation/AI Agents ([AutonOS](auton-os.md)) | ABI-AUT-FE |

## Dependencies and integrations

- ABI backends depend on `SHR-ADM-BE` and `CRM-DB`.
- Integrations: `SHR-ABI-INT-001` (Shared Platform ↔ ABI) and `ABI-AUT-INT-001` (ABI-AUT-FE, ABI-WEB-BE). See [Integrations](../../integrations/overview.md).

## Releases

Release `ABI-REL-001`: version 0.1.0 of ABI-CP-FE and ABI-CP-BE to DEV, status Planned, release date **TBD**.

## Design

Figma references `ABI-WEB-FIG-001` (Website) and `ABI-CP-FIG-001` (Client Portal); the admin and AutonOS design links are stored on the ABI-ADM-FE and ABI-AUT-FE repository records.

## Needs Clarification

- Repository URLs listed above, and backends for the admin panel and AutonOS.
- AI providers or models used by "AI orchestration" (none are named in the workbook).

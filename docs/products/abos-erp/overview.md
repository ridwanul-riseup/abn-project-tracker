# ABOS ERP (ERP) — Overview

> Source: project workbook sheets 02–11. Status in the workbook: **Planned**.

## Purpose

HR, attendance, inventory, procurement, finance and payroll.

| Field | Value |
|-------|-------|
| Product ID | ERP |
| Database | `ERP-DB` (`erp_db`) |
| Primary owner | "ERP Product" (named owner **Needs Clarification**) |

## Applications and repositories

| Application | Repository | Layer | Repository name / URL | API |
|-------------|------------|-------|------------------------|-----|
| ERP-ADM Admin Panel | ERP-ADM-FE | Frontend | https://github.com/riseup-labs/ABN-ERP-Frontend.git | ERP API |
| ERP-ADM Admin Panel | ERP-ADM-BE | Backend | ABOS-ERP-Admin-Panel-Backend (URL **Needs Clarification**) | `/api/v1/erp/*` |

Workbook module label for both: "ERP Admin".

## Modules

All ERP modules list **ERP-ADM-BE** as primary repository.

| Module ID | Module |
|-----------|--------|
| ERP-HR | HR |
| ERP-INV | Inventory |
| ERP-PROC | "ERP" (**Needs Clarification** — the ID suggests Procurement) |
| ERP-FIN | Finance |

The product description also mentions attendance and payroll, but the workbook
has no module for them (**Needs Clarification**).

## Dependencies and integrations

- ERP-ADM-BE depends on `SHR-ADM-BE` and `ERP-DB`; ERP-ADM-FE depends on ERP-ADM-BE and SHR-ADM-BE.
- Integration `SHR-ERP-INT-001`. See [Integrations](../../integrations/overview.md).

## Releases

Release `ERP-REL-001`: version 0.1.0 of ERP-ADM-FE and ERP-ADM-BE to DEV, status Planned, release date **TBD**.

## Design

Figma reference `ERP-ADM-FIG-001` (ERP Admin); the link is stored on the ERP-ADM-FE repository record.

## Needs Clarification

- Repository URL for ERP-ADM-BE.
- Name of module ERP-PROC; modules for attendance and payroll.

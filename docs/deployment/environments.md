# Environments and Deployment

## Environments (workbook sheet "10-environments-discuss-with-na")

The sheet name indicates the list was still to be discussed.

| Environment ID | Name | Purpose | Deployment |
|----------------|------|---------|------------|
| ENV-LOCAL | Local | Developer machine | Manual |
| ENV-DEV | DEV | Active development | CI/CD |
| ENV-QA | QA | Testing | CI/CD |
| ENV-UAT | UAT | Client/business validation | CI/CD |
| ENV-STG | Staging | Production-like validation | CI/CD |
| ENV-PROD | Production | Live | Controlled CI/CD |

### Inconsistencies — Needs Clarification

- Repositories list `dev/stage/uat/prod` (no QA, no Local).
- Databases list `DEV/QA/UAT/PROD` (no Staging).
- The deployments sheet uses DEV and QA.

The hub imported repository environments by mapping `stage` → ENV-STG.

## Deployment targets (from repositories)

- Frontends: **Web/CDN**
- Backends: **API Server**

## Deployment records (workbook sheet 09)

| Deployment ID | Repo | Environment | Infrastructure | Method | Branch | Version | Status |
|---------------|------|-------------|----------------|--------|--------|---------|--------|
| OTT-WEB-BE-DEP-001 | OTT-WEB-BE | DEV | AWS | CI/CD | develop | 0.1.0 | Planned |
| OTT-WEB-BE-DEP-002 | OTT-WEB-BE | QA | AWS | CI/CD | release/* | 0.1.0 | Planned |
| SHR-ADM-BE-DEP-001 | SHR-ADM-BE | DEV | AWS | CI/CD | develop | 0.1.0 | Planned |

## Credentials

The workbook sheet "12-credentials-discuss-with-nas" is empty. Credentials must
be kept in a secret manager; the hub stores only a *reference* to where a
secret lives (the "Secret-manager reference" field on deployments), never the secret.

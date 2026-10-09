# Databases

From workbook sheet `06-databases`. Connection details and credentials are
intentionally **not** recorded here or in the hub.

| DB ID | Database | Product | Owner | Used by | System of record | Environments |
|-------|----------|---------|-------|---------|------------------|--------------|
| SHR-DB | `shared_db` | SHR | Shared Platform | ABI/OTT/ERP | Shared Platform | DEV/QA/UAT/PROD |
| OTT-DB | `ott_db` | OTT | OTT | OTT repositories | OTT | DEV/QA/UAT/PROD |
| CRM-DB | `crm_db` | ABI | ABI | ABI repositories | ABI | DEV/QA/UAT/PROD |
| ERP-DB | `erp_db` | ERP | ERP | ERP repositories | ERP | DEV/QA/UAT/PROD |

## Notes

- The database environments (DEV/QA/UAT/PROD) differ from the repository
  environments (dev/stage/uat/prod) and from the environment sheet, which also
  lists Local and Staging. See [Environments](../deployment/environments.md).
- Database engine, hosting, backup policy and schema ownership are **Needs Clarification**.

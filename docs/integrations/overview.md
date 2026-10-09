# Cross-Product Integrations

Integration IDs appear in the "Integration" column of workbook sheet
`04-repositories`. The workbook has **no** integration sheet, so purpose,
protocol and data exchanged are **Needs Clarification** for every entry.

| Integration | Between | Repositories referencing it |
|-------------|---------|-----------------------------|
| SHR-OTT-INT-001 | Shared Platform ↔ ABN+ OTT | SHR-ADM-FE, SHR-ADM-BE, OTT-ADM-FE, OTT-ACT-FE, OTT-WEB-FE, OTT-ADM-BE, OTT-ACT-BE, OTT-WEB-BE |
| SHR-ABI-INT-001 | Shared Platform ↔ AB Intelligence | SHR-ADM-FE, SHR-ADM-BE, ABI-WEB-FE, ABI-ADM-FE, ABI-CP-FE, ABI-CP-BE, ABI-WEB-BE |
| SHR-ERP-INT-001 | Shared Platform ↔ ABOS ERP | SHR-ADM-FE, SHR-ADM-BE, ERP-ADM-FE, ERP-ADM-BE |
| ABI-AUT-INT-001 | AB Intelligence ↔ AutonOS | ABI-AUT-FE, ABI-WEB-BE |
| OTT-ABI-INT-001 | ABN+ OTT ↔ AB Intelligence | OTT-WEB-BE |

The likely common purpose of the `SHR-*` integrations is authentication and
user/organization data (see [Shared Authentication](../products/shared-platform/authentication.md)),
but this is not stated in the workbook.

To complete an integration, open it in the hub under **References → Integrations**,
fill in the description and link a document created from the *Integration* template.

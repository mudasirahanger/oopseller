# Architecture

## Decision

OopSeller starts as a modular monolith:

- Next.js owns the agency/client user experience.
- Laravel owns authentication, authorization, business logic, integrations, calculations, queues, and audit history.
- MySQL stores normalized transactional data.
- Redis handles cache, queue workload, locks, and rate-limit coordination.
- S3 stores raw API payloads, reports, exports, and client artifacts.

## Domains

1. Agency and tenancy
2. Clients and users
3. Amazon accounts and marketplaces
4. Catalog and listings
5. Listing optimization
6. Keyword projects and rank observations
7. Competitor intelligence
8. PPC intelligence
9. Tasks and approvals
10. Alerts and reporting
11. Billing and entitlements

## Data-provider boundary

Amazon SP-API and organic rank data are separate providers. Exact organic search-result position is implemented through a replaceable `RankProvider`, not assumed to come from SP-API.

## MySQL to PostgreSQL portability

- No database enums
- No stored procedures
- No MySQL-only SQL functions
- Laravel migrations for schema changes
- Decimal columns for money
- UTC timestamps
- Raw external payloads retained in object storage

## Security

Every tenant-owned row includes `organization_id`; most operational rows also include `client_id`. API requests resolve the current organization from a trusted header and verify user membership. Production must add full policies, scoped roles, encrypted secrets, token rotation, audit events, backup verification, and Amazon data-retention controls.

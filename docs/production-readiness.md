# Production readiness

This repository is a functional pre-production MVP. The following flows are implemented and database-backed:

- organization registration and authentication;
- client and product CRUD;
- marketplace listings and local listing versions;
- public Amazon SP-API authorization through Login with Amazon;
- encrypted refresh-token storage;
- Sellers, Catalog Items, Listings Items, and Product Type Definitions calls;
- queued seller-listing synchronization;
- Amazon validation preview and confirmed listing PATCH publishing;
- keyword projects, competitors, tasks, alerts, experiments, and report requests.

## Required before a public launch

1. Generate and commit `apps/api/composer.lock` in a network-enabled environment.
2. Replace bearer tokens stored in browser local storage with an HTTP-only first-party session/cookie deployment if the web and API are served as a first-party application.
3. Configure HTTPS, a production APP_KEY, `APP_DEBUG=false`, managed MySQL/Redis, backups, and secret management.
4. Complete Amazon application review and request only the roles required for each implemented operation.
5. Add a licensed rank provider, competitor data provider, and Amazon Ads OAuth/reporting before enabling those data sources.
6. Add end-to-end tests against Amazon's Draft authorization workflow and sandbox/static responses, followed by controlled production seller testing.
7. Add observability, queue alerts, database recovery drills, data-retention controls, and incident procedures.

The development Docker Compose stack intentionally uses Laravel's built-in server and Next.js development mode. Use a hardened production deployment (for example, reverse proxy plus an appropriate PHP application server and `next start`) for public traffic.

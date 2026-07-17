# API overview

Base path: `/api/v1`

## Public

- `GET /health`
- `POST /auth/register`
- `POST /auth/login`
- `GET /integrations/amazon/callback`

## Authenticated, organization-scoped

- Authentication: `GET /auth/me`, `POST /auth/logout`
- Dashboard: `GET /dashboard`
- Clients: REST resource `/clients`
- Products: REST resource `/products`
- Product Amazon refresh: `POST /products/{product}/refresh-amazon`
- Listings: list, show, update, refresh, Amazon preview, Amazon publish
- Listing audits: `POST /listings/{listing}/audits`
- Keyword projects: list, create, add keywords
- Tasks: list, create, update
- Competitors: list, create, update
- Advertising: `GET /advertising`
- Alert rules: list, create, update
- Reports: list, queue generation
- Experiments: list, create, update
- Marketplaces: `GET /marketplaces`
- Amazon accounts: list, authorize, sync, disconnect

## Headers

```text
Authorization: Bearer <Sanctum token>
X-Organization-Id: <organization ID>
Accept: application/json
```

Every tenant-owned query is restricted to the organization resolved by middleware. Creation endpoints additionally verify that referenced clients, products, listings, Amazon accounts, brands, and assignees belong to that organization where applicable.

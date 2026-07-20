---
name: amazon-sp-api
description: >-
  Amazon Selling Partner API integration guide for OopSeller. Use when adding or
  changing Amazon SP-API operations, order/inventory/reports sync, LWA auth, or
  channel-provider adapters. Maps SP-API operation areas to this project's code
  and records rate limits, gaps, and conventions. Triggers on: SP-API, Selling
  Partner, LWA, Amazon orders/inventory/reports/returns/FBA, ChannelProvider.
---

# Amazon SP-API in OopSeller

This project talks to Amazon's Selling Partner API through a thin, testable
layer. Reference operation surface adapted from the `amazon_sp_mcp` server
(github.com/jay-trivedi/amazon_sp_mcp); this skill maps those areas to our code
and records what is implemented vs. planned.

## Architecture (where SP-API lives)

- `app/Services/Amazon/AmazonLwaClient.php` — LWA token exchange + 50-min access
  token cache (`amazon_lwa_access_token:{id}`).
- `app/Services/Amazon/AmazonSpApiClient.php` — HTTP transport: regional
  endpoint, retries (429/5xx with backoff), header injection. **All SP-API HTTP
  goes through here.**
- `app/Services/Amazon/SpApiSellerDataProvider.php` — the operation methods
  (`marketplaceParticipations`, `importListings`, `importOrders`,
  `getCatalogItem`, `getListingItem`, `getProductTypeDefinition`,
  `previewListingPatch`, `publishListingPatch`).
- `app/Services/Amazon/Contracts/SellerDataProvider.php` — the interface. Add a
  method here + implement it in the provider; never call `Http` from a controller.
- `app/Services/Channels/AmazonChannelProvider.php` — adapts the Amazon provider
  to the generic `ChannelProvider` contract (this is the platform-agnostic seam;
  order/listing normalization for the shared sync services happens here).

Rule: controllers and jobs depend on `SellerDataProvider` /
`ChannelProvider`, not on `AmazonSpApiClient`. This keeps the sandbox/fake
swappable in tests (see `tests/Feature/*` binding stub providers).

## Operation areas → status in this repo

| SP-API area | MCP tools (reference) | OopSeller status |
|---|---|---|
| Sellers | — | ✅ `marketplaceParticipations` |
| Catalog Items 2022-04-01 | `get_product_details`, `search_catalog` | ✅ `getCatalogItem` |
| Listings Items 2021-08-01 | `get_listings` | ✅ get/search/patch + `VALIDATION_PREVIEW` before publish |
| Product Type Definitions | — | ✅ `getProductTypeDefinition` |
| Orders v0 | `get_orders`, `get_order_details`, `get_sales_metrics` | ✅ `importOrders` (+ per-order items) → `orders` table + `MetricSnapshot` |
| FBA Inventory | `get_inventory_summary`, `get_fba_inventory`, `check_stock_levels` | ❌ planned (Amazon deep-features phase) |
| Returns / Refunds | `get_returns`, `get_refund_info` | ❌ planned (order status tracks `returned` only) |
| Reports | `request_report`, `get_report`, `get_report_document` | ❌ planned (data-ingestion phase) |

## Authorization: Public vs Private apps (MD9100)

Amazon SP-API apps are either **Public** (Solution Provider, third-party OAuth)
or **Private** (self-authorized, single seller). This determines which flow
works — mixing them up is the #1 cause of confusion:

- **Public app** → `AmazonIntegrationController::authorizeSeller/callback`
  (redirect to `/apps/authorize/consent`, `SpApiSellerDataProvider::authorizationUrl`).
  Requires **OAuth Login URI** + **OAuth Redirect URI** saved on the app in the
  Amazon Developer Console. Using this flow on a Private app (which has no such
  fields) produces **error MD9100** ("not set up for third-party authorisation").
- **Private app** → no redirect flow exists. The seller self-authorizes in
  Seller Central (Apps & Services → Manage Your Apps → Authorize) and Amazon
  displays a refresh token directly on screen. OopSeller's
  `AmazonIntegrationController::connectManually` (`POST
  /integrations/amazon/accounts/manual`) accepts that pasted refresh token,
  validates it immediately via `marketplaceParticipations()` (rejects with 422
  and no leftover row if invalid — don't silently swallow the exception the way
  `syncMarketplaceParticipations()` does for the OAuth path), then stores it
  encrypted exactly like the OAuth path does.

Both paths converge on the same `AmazonAccount` row and the same sync
machinery — only how `refresh_token` gets populated differs.

## Conventions to follow when extending

- **New operation:** add to the `SellerDataProvider` interface, implement in
  `SpApiSellerDataProvider` using `$this->client->get/post/...`, then expose it
  through `AmazonChannelProvider` if it feeds a shared sync service.
- **Pagination:** SP-API uses `NextToken` (Orders/Reports) or `pagination.nextToken`
  (Listings). Loop until empty — see `importOrders` / `importListings`.
- **Dates:** always `Y-m-d\TH:i:s\Z` (UTC ISO-8601). Order sync is incremental
  with a 24h overlap; store the high-water mark in `channel_accounts.metadata`.
- **Rate limits:** SP-API enforces per-operation token-bucket limits (e.g. Orders
  `getOrders` ~0.0167 rps / burst 20; Reports lower). Respect the existing retry
  on 429; for new high-volume pulls prefer the Reports API over row-by-row calls.
  The reference MCP queues requests per endpoint — mirror that if we add bulk sync.
- **Money:** decimals, never floats in the DB; currency from the payload
  (`OrderTotal.CurrencyCode`), default `INR`.
- **Regions:** `AmazonConfiguration::endpoint($region)` (na/eu/fe); sandbox via
  `AMAZON_SPAPI_SANDBOX`.
- **Secrets:** LWA client id/secret and refresh tokens are config/encrypted —
  never log them; never hardcode. Rotate if leaked.

## When implementing a planned area

1. **FBA Inventory** — `GET /fba/inventory/v1/summaries` (`details=true`,
   `marketplaceIds`, `granularityType=Marketplace`). Map to a new
   `inventory_snapshots` table; alert when `fulfillableQuantity` < threshold.
2. **Returns** — `GET /orders/v0/orders/{id}/...` return data / Reports
   `GET_FBA_FULFILLMENT_CUSTOMER_RETURNS_DATA`. Feed the existing `returned`
   order status + refund totals.
3. **Reports** — `createReport` → poll `getReport` → `getReportDocument`
   (gzip + decrypt). Use for Search Query Performance and bulk order/settlement
   history instead of paginated live calls.

## Optional: the MCP server itself

`amazon_sp_mcp` can be run as a standalone MCP server (Orders/Returns/FBA/
Catalog/Reports tools with built-in rate limiting + LWA). Wiring it into this
repo means adding an entry to `.mcp.json` and supplying LWA credentials — a
config change that runs third-party code, so do it only on explicit request and
review the server first. This skill uses it purely as an operation-coverage
reference; the app's own SP-API code above is the source of truth.

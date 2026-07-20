# Amazon connector

**Auth:** OAuth 2.0 via Login with Amazon (SP-API website authorization workflow).
**Adapter:** `app/Services/Channels/AmazonChannelProvider.php` wrapping the full SP-API stack in `app/Services/Amazon/`.
**Identity:** ASIN (`products.asin`, mirrored into `products.external_id`); listings use the real Amazon marketplace ID (e.g. `A21TJRUUN4KGV`).

## Server setup

1. Register a Selling Partner API application in the Amazon Developer Console and note the LWA client ID/secret and the application ID.
2. Register the OAuth redirect URI exactly as configured here. Local default:
   `http://127.0.0.1:8000/api/v1/integrations/amazon/callback`
3. Set in `apps/api/.env`:

```dotenv
AMAZON_LWA_CLIENT_ID=
AMAZON_LWA_CLIENT_SECRET=
AMAZON_SPAPI_APPLICATION_ID=
AMAZON_REDIRECT_URI=http://127.0.0.1:8000/api/v1/integrations/amazon/callback
AMAZON_DEFAULT_REGION=eu
AMAZON_SPAPI_DRAFT=true     # keep true while the Amazon app is in Draft
AMAZON_SPAPI_SANDBOX=false
```

## Connecting an account

Amazon SP-API applications are either **Public** (published/Solution Provider,
supports the OAuth redirect flow) or **Private** (self-authorized, single
seller). OopSeller supports both — pick the mode in the Connect modal.

**Public app (OAuth):**

1. Integration hub → Amazon card → **Connect** → OAuth (Public app), pick the
   client and Seller Central marketplace (this selects the regional consent URL).
2. Approve the consent screen with the seller account; the callback stores the
   refresh token encrypted and queues the initial listing sync.

This requires the app's **OAuth Login URI** and **OAuth Redirect URI** to be
set and saved in the Amazon Developer Console — a Public/Solution-Provider-only
setting. Using the redirect flow on a Private app produces Amazon error
**MD9100** ("The application provided is not set up for third-party
authorisation using OAuth"), because Private apps have no such fields at all.

**Private app (manual refresh token):**

1. In Seller Central, go to **Apps & Services → Manage Your Apps**, open the
   app, and click **Authorize**. Amazon self-authorizes the app for that seller
   account directly and displays a refresh token on screen
   (`Atzr|...`) alongside the Selling Partner (seller) ID.
2. Integration hub → Amazon card → **Connect** → Refresh token (Private app),
   pick the client and marketplace, and paste the seller ID and refresh token.
3. `POST /api/v1/integrations/amazon/accounts/manual` validates the token by
   calling the Sellers API immediately — an invalid token is rejected with a
   422 and no row is left behind — then stores it encrypted (same as the OAuth
   path) and queues the initial listing sync. Owner/admin role required.

## Sandbox testing

Both connect modes have a **Use Sandbox environment** checkbox. This routes
that specific account's SP-API calls to Amazon's sandbox host
(`sandbox.sellingpartnerapi-*.amazon.com`, static test data, no real seller
data touched) instead of production. It's **per-account**, stored on
`channel_accounts.metadata.sandbox` and read at request time in
`AmazonSpApiClient::send()` — falling back to the server-wide
`AMAZON_SPAPI_SANDBOX` default only when an account has no explicit value. This
means a sandbox test account and a real connected account can coexist; you are
not forced to flip a global server setting to test.

Getting a refresh token for a sandbox account works the same way as for a
Private app (self-authorization in Seller Central) — Amazon does not require a
separate app registration for sandbox access, only a valid LWA app and access
token; which host receives the calls is entirely determined by this flag.

## Implemented operations

Sellers marketplace participation, Catalog Items 2022-04-01, Listings Items 2021-08-01 (search/get/patch), Product Type Definitions 2020-09-01, and `VALIDATION_PREVIEW` before every publish. Order sync is implemented (see below). FBA inventory, Ads, and Brand Analytics are separate roadmap phases.

## Order sync (added in the orders phase)

`getOrders()` is implemented for this connector. Use **Sync orders** on the account card, or rely on the hourly `orders:sync-active-channel-accounts` scheduler. Sync is incremental (continues from the last run with a 24h overlap; first run covers 30 days), upserts into the `orders` table idempotently, and rebuilds the daily `MetricSnapshot` revenue aggregates that power `/orders` and the monthly client reports. Revenue counts only `confirmed`/`shipped`/`delivered` orders; `cancelled`/`returned` are tracked separately.

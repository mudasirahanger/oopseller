# Flipkart connector

**Auth:** two Flipkart app types (per the official FMS API docs at
seller.flipkart.com/api-docs):
- **Self-access** — the seller's own app (Seller Dashboard → Manage Profile →
  Developer Access). Uses `grant_type=client_credentials` with the seller's
  `appId`/`app_secret`. **No consent screen.** This is what most single sellers
  have, and what OopSeller connects via app credentials.
- **Third-party / partner** — for aggregators managing many sellers (Partner
  Dashboard). Uses the authorization-code OAuth flow with seller consent, and
  requires the server-level `FLIPKART_*` credentials.

**Adapter:** `app/Services/Channels/FlipkartChannelProvider.php`
**Identity:** listings are FSN-based; products store the FSN in `products.external_id`.

> ⚠️ **"Oops! Something went wrong" on the Flipkart consent page** means a
> self-access app was pushed through the partner OAuth flow. Self-access apps
> have no consent screen — connect them with **app credentials** instead.

## Connecting a self-access account (most sellers)

No server-level configuration needed.

1. In the Flipkart Seller Dashboard: Manage Profile → Developer Access → create
   an application → copy the **Application ID** and **Application secret**.
2. Integration hub → Flipkart card → **Connect** → **App credentials
   (self-access)** tab → pick the client, paste both values.
3. `POST /api/v1/integrations/channels/flipkart/connect` verifies the
   credentials with a live `client_credentials` token request (invalid ones are
   rejected with a 422 and no leftover row), stores them **encrypted** in
   `channel_accounts.credentials`, and marks the account active.

## Connecting a partner (third-party) account

For aggregators only. Requires server-level app registration.

1. Register the app in the Flipkart **Partner Dashboard** and whitelist the
   callback URL (local default
   `http://127.0.0.1:8000/api/v1/integrations/channels/flipkart/callback`).
2. Set in `apps/api/.env`:

```dotenv
FLIPKART_CLIENT_ID=
FLIPKART_CLIENT_SECRET=
FLIPKART_REDIRECT_URI=http://127.0.0.1:8000/api/v1/integrations/channels/flipkart/callback
# FLIPKART_BASE_URL defaults to https://api.flipkart.net
```

3. Integration hub → Flipkart → **Connect** → **Authorize (partner app)** tab →
   pick the client. `POST /integrations/channels/flipkart/authorize` returns the
   consent URL (`/oauth-service/oauth/authorize`, `response_type=code`,
   `scope=Seller_Api`, `state`); after seller consent the callback exchanges the
   code (passing the original `state`, per the docs) for tokens, stores the
   refresh token encrypted, and queues an initial listing sync.

## Sync behavior

- Listings are pulled through the Listings v3 search endpoint with pagination and mapped into products (`platform=flipkart`, `external_id=FSN`) and listings (`marketplace_id=flipkart_in`). Price/currency land in `listings.attributes`.
- Access tokens are cached for 50 minutes and refreshed with the stored refresh token.
- Inventory and listing updates throw `UnsupportedChannelOperation` until their phases ship (order sync is implemented — see below).

## Caveats

- Both self-access and partner tokens run through the same `tokenRequest()` in the adapter (only the grant type differs), and the same Listings/Orders v3 endpoints. Connection failures to Flipkart's auth servers surface as a clean `ChannelApiException`, not an uncaught 500.
- Flipkart seller API access still requires the seller to have enabled Developer Access; endpoints may differ per program tier. The adapter isolates every Flipkart HTTP call, so path adjustments touch only this one class.

## Order sync (added in the orders phase)

`getOrders()` is implemented for this connector. Use **Sync orders** on the account card, or rely on the hourly `orders:sync-active-channel-accounts` scheduler. Sync is incremental (continues from the last run with a 24h overlap; first run covers 30 days), upserts into the `orders` table idempotently, and rebuilds the daily `MetricSnapshot` revenue aggregates that power `/orders` and the monthly client reports. Revenue counts only `confirmed`/`shipped`/`delivered` orders; `cancelled`/`returned` are tracked separately.

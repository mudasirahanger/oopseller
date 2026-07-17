# Flipkart connector

**Auth:** OAuth 2.0 (authorization code) against the Flipkart seller API gateway.
**Adapter:** `app/Services/Channels/FlipkartChannelProvider.php`
**Identity:** listings are FSN-based; products store the FSN in `products.external_id`.

## Server setup

1. Register an application in the Flipkart seller API portal (Seller Hub → API access) to obtain an application ID and secret.
2. Whitelist the callback URL with Flipkart. Local default:
   `http://127.0.0.1:8000/api/v1/integrations/channels/flipkart/callback`
3. Set in `apps/api/.env`:

```dotenv
FLIPKART_CLIENT_ID=
FLIPKART_CLIENT_SECRET=
FLIPKART_REDIRECT_URI=http://127.0.0.1:8000/api/v1/integrations/channels/flipkart/callback
# FLIPKART_BASE_URL defaults to https://api.flipkart.net
```

Until these are set the Integration Hub shows Flipkart as **Needs configuration** and the Connect button stays disabled.

## Connecting an account

1. Integration hub → Flipkart card → **Connect**, pick the client.
2. `POST /api/v1/integrations/channels/flipkart/authorize` returns the consent URL; the browser is redirected to Flipkart's OAuth screen (`/oauth-service/oauth/authorize`, scope `Seller_Api`).
3. The callback exchanges the code for tokens; the refresh token is stored encrypted on the `channel_accounts` row and an initial listing sync is queued.

## Sync behavior

- Listings are pulled through the Listings v3 search endpoint with pagination and mapped into products (`platform=flipkart`, `external_id=FSN`) and listings (`marketplace_id=flipkart_in`). Price/currency land in `listings.attributes`.
- Access tokens are cached for 50 minutes and refreshed with the stored refresh token.
- Orders/inventory/listing updates deliberately throw `UnsupportedChannelOperation` until their phases ship.

## Caveats

- Flipkart seller API access requires approval on their side; endpoints may differ per program tier. The adapter isolates every Flipkart HTTP call, so path adjustments touch only this one class.

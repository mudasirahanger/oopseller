# Snapdeal connector

**Auth:** per-account seller code + auth token from Snapdeal Seller Zone (no server-level app registration).
**Adapter:** `app/Services/Channels/SnapdealChannelProvider.php`
**Identity:** products store the SUPC in `products.external_id`; listings use `marketplace_id=snapdeal_in`.

## Connecting an account

1. In Snapdeal Seller Zone, request API access to obtain your seller code and auth token.
2. Integration hub → Snapdeal card → **Connect**, pick the client, paste the seller code and auth token.
3. `POST /api/v1/integrations/channels/snapdeal/connect` stores them **encrypted** in `channel_accounts.credentials`.
4. Use **Sync listings** on the account card to import Snapdeal listings.

## Sync behavior

- Offset-paginated listing fetch from `SNAPDEAL_BASE_URL` (default `https://apigateway.snapdeal.com`) with a bearer auth token and `X-Seller-Code` header.
- Normalized rows are persisted by the shared `ChannelCatalogSyncService`.
- Orders/inventory/listing updates throw `UnsupportedChannelOperation` until their phases ship.

## Caveats

- Snapdeal issues API access per seller program; exact endpoint paths can vary. All Snapdeal HTTP specifics live in the one adapter class, and `SNAPDEAL_BASE_URL` is env-overridable.

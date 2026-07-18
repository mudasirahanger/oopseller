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
- Inventory and listing updates throw `UnsupportedChannelOperation` until their phases ship (order sync is implemented — see below).

## Caveats

- Snapdeal issues API access per seller program; exact endpoint paths can vary. All Snapdeal HTTP specifics live in the one adapter class, and `SNAPDEAL_BASE_URL` is env-overridable.

## Order sync (added in the orders phase)

`getOrders()` is implemented for this connector. Use **Sync orders** on the account card, or rely on the hourly `orders:sync-active-channel-accounts` scheduler. Sync is incremental (continues from the last run with a 24h overlap; first run covers 30 days), upserts into the `orders` table idempotently, and rebuilds the daily `MetricSnapshot` revenue aggregates that power `/orders` and the monthly client reports. Revenue counts only `confirmed`/`shipped`/`delivered` orders; `cancelled`/`returned` are tracked separately.

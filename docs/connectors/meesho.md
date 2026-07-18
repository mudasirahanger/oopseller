# Meesho connector

**Auth:** per-account API key + secret from the Meesho Supplier Panel (no server-level app registration).
**Adapter:** `app/Services/Channels/MeeshoChannelProvider.php`
**Identity:** products store the Meesho product ID in `products.external_id`; listings use `marketplace_id=meesho_in`.

## Connecting an account

1. In the Meesho Supplier Panel, open the API/integration settings and generate credentials (supplier ID, API key, API secret). Meesho may require partner approval for API access.
2. Integration hub → Meesho card → **Connect**, pick the client, paste the three credentials.
3. `POST /api/v1/integrations/channels/meesho/connect` stores them **encrypted** in `channel_accounts.credentials`; the account becomes active immediately.
4. Use **Sync listings** on the account card to import the catalog.

## Sync behavior

- Paginated product fetch from `MEESHO_BASE_URL` (default `https://supplier-api.meesho.com`) with `X-Api-Key` / `X-Api-Secret` / `X-Supplier-Id` headers.
- Normalized rows are persisted by the shared `ChannelCatalogSyncService`.
- Inventory and listing updates throw `UnsupportedChannelOperation` until their phases ship (order sync is implemented — see below).

## Caveats

- Meesho's supplier API is partner-gated and not fully public; header names and paths may need adjustment to match the credentials issued to you. All Meesho HTTP specifics live in the one adapter class, and `MEESHO_BASE_URL` is env-overridable.

## Order sync (added in the orders phase)

`getOrders()` is implemented for this connector. Use **Sync orders** on the account card, or rely on the hourly `orders:sync-active-channel-accounts` scheduler. Sync is incremental (continues from the last run with a 24h overlap; first run covers 30 days), upserts into the `orders` table idempotently, and rebuilds the daily `MetricSnapshot` revenue aggregates that power `/orders` and the monthly client reports. Revenue counts only `confirmed`/`shipped`/`delivered` orders; `cancelled`/`returned` are tracked separately.

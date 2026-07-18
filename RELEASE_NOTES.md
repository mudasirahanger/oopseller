# OopSeller 0.4.0

Release date: 2026-07-18

Multi-channel platform release: OopSeller is no longer Amazon-only. This release
adds a platform-agnostic channel core, three working Indian marketplace
connectors, cross-channel order and revenue intelligence, a security-hardening
pass, and a redesigned navigation. Versioning is standardized on semver from
this release (the previous `VERSION` file used an internal counter).

## Security hardening (Phase 1)

- Password reset flow (email-enumeration-safe) and 401 (not 422) on bad login.
- Sanctum personal access tokens now expire (7 days) and are pruned daily;
  tokens are revoked on password reset.
- Role enforcement: `viewer` is read-only across the API; owner/admin required
  for client/product delete, channel disconnect, and org settings.
- `is_platform_admin` removed from mass assignment; sanitized OAuth error
  redirects; `SecurityHeaders` middleware; global authenticated-route throttle;
  Amazon sync deduplication; LIKE-wildcard stripping on search inputs.

## Multi-platform channel core

- New `channel_accounts` (with encrypted `credentials`), `channel_sync_runs`,
  and `platform` / `external_id` / `channel_account_id` columns on
  products/listings. `AmazonAccount` is now a platform-scoped subclass of
  `ChannelAccount`.
- `ChannelProvider` interface + `ChannelManager` registry in
  `app/Services/Channels/`; new adapters register in one place.
- `GET /integrations/channels` catalog and a rebuilt Integration Hub with a
  per-connector documentation icon and setup guide on every card.

## India marketplace connectors

- Working **Flipkart** (OAuth 2.0), **Meesho** (API key/secret), and
  **Snapdeal** (auth token) connectors: connect/authorize/callback/sync/
  disconnect endpoints, encrypted per-account credentials, and listing sync.
- Per-connector docs in `docs/connectors/*.md`. Meesho/Snapdeal endpoint paths
  are env-overridable pending partner-API access.

## Orders & revenue intelligence

- New `orders` table and `getOrders()` on all four adapters (Amazon Orders API
  v0 with order items, Flipkart Shipments v3, Meesho and Snapdeal orders).
- `ChannelOrderSyncService` with incremental windows (24h overlap, 30-day first
  sync), idempotent upserts, and daily `MetricSnapshot` revenue rebuilds;
  `SyncChannelOrders` job + hourly scheduler.
- `GET /orders` and `GET /orders/summary` (revenue, orders, units, AOV,
  by-platform, by-day, top products, cancelled/returned) and a `/orders`
  revenue dashboard with a validated colorblind-safe palette. This also makes
  the previously-empty monthly client reports produce real numbers.

## UI

- Redesigned sidebar: per-item icons, grouped sections
  (Workspace / Intelligence / Operations / Setup), a stronger active state, and
  a mobile fix (navigation was hidden below 600px; now a horizontal icon bar).
- Keywords page shows a clear "Add a product" action when no products exist
  instead of a silently-disabled button.

## Tooling

- 23 backend feature tests (up from 5). Amazon SP-API integration guidance
  captured as a project skill under `.claude/skills/amazon-sp-api/`, informed by
  the operation surface of the `amazon_sp_mcp` reference server.

# OopSeller 0.3.1

## Composer compatibility fix

- Updated `laravel/tinker` from 2.x to 3.x for Laravel 13 compatibility.
- Aligned Laravel development dependencies with the Laravel 13 application skeleton.
- Keeps PHP 8.5, SQLite, and manual macOS development support.

# OopSeller Manual macOS Development Release

Release date: 2026-07-17

This release merges the latest repaired application code and changes the default development environment from Docker/MySQL/Redis to a manual macOS workflow using PHP 8.5, Node.js 23, and SQLite.

## Included repairs

- Correct Laravel dependency installation instructions for the missing `vendor/autoload.php` error
- Working database-backed client and product creation
- Removal of fake seller, product, ranking, advertising, and competitor metrics
- Amazon LWA authorization and SP-API integration boundaries
- Catalog Items, Listings Items, Sellers, and Product Type Definitions support
- Listing validation preview before publishing
- SQLite-first local environment
- File cache, file sessions, synchronous queues, local storage, and log mail
- macOS environment doctor, setup, start, reset, and verification scripts
- Public npm registry URLs in the lockfile
- Optional Docker/MySQL/Redis environment retained separately

## First run

```bash
chmod +x scripts/*.sh
./scripts/setup-macos.sh
./scripts/start-dev.sh
```

## v4
- Fixed Laravel 13 scheduled callback discovery by assigning unique names before `withoutOverlapping()`.
- Disabled Horizon snapshot scheduling unless the active queue driver is Redis.

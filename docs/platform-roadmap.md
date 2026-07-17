# Platform roadmap — verified against the codebase (2026-07-17)

This validates the "Complete Platform Roadmap" audit table against what actually exists in `apps/api` and `apps/web`, then records the agreed build order. Verdict: the table is accurate. Nothing from Phases 1–11 of that roadmap exists yet except the Amazon foundation.

## Feature-by-feature verification

| Claimed | Verified state in code |
|---|---|
| Multi-tenant auth (org-based) | ✅ Confirmed — Sanctum + `EnsureOrganizationAccess`; now also role-aware (viewer read-only, admin-gated destructive actions) |
| Client CRM | ✅ Confirmed — full CRUD with org scoping |
| Amazon SP-API OAuth | ✅ Confirmed — LWA exchange, state cache, encrypted refresh tokens, sandbox flag |
| Listing sync & version history | ✅ Confirmed — `AmazonCatalogSyncService`, `ListingVersion` |
| Listing quality score | ✅ Confirmed — `ListingOptimizer` (0–100 with breakdown) |
| Keyword tracking | ⚠️ Partial — projects/keywords/rankings models + CRUD exist, but `RankProvider` is bound to `NullRankProvider`: **no real rank data is ever collected** until a licensed provider is integrated |
| A/B experiments | ✅ Confirmed — model + CRUD, but no automatic metric capture |
| Task management | ✅ Confirmed |
| Client reports | ⚠️ Basic — `GenerateMonthlyClientReports` aggregates `MetricSnapshot` rows, but nothing writes MetricSnapshots yet, so reports are empty; no PDF |
| Advertising module | ⚠️ Stub confirmed — models + read-only controller, no Ads API integration |
| Alert rules | ⚠️ Stub confirmed — CRUD only; no evaluator, no delivery channel |
| Competitor tracking | ⚠️ Stub confirmed — records only, no snapshot collection |
| Multi-platform integrations | ❌ Confirmed missing — Amazon-only (`AmazonAccount`, `amazon_account_id` FKs) |
| Shopify / WooCommerce / OpenCart / Magento | ❌ Confirmed missing |
| Orders / revenue / FBA data | ❌ Confirmed missing — no orders table, no inventory endpoints |
| AI-powered optimization | ❌ Confirmed missing — no LLM integration anywhere |
| Client portal | ❌ Missing, but the `viewer` role foundation now exists (read-only enforcement shipped in security Phase 1); no invitation flow yet |
| Billing / subscription | ❌ Confirmed missing |
| Notifications | ❌ Confirmed missing — `channels` column exists on alert_rules, no sender |
| Analytics / revenue charts | ❌ Confirmed missing — `MetricSnapshot` model exists, no writers and no chart UI |

## Reality checks on the proposed integrations

- **Flipkart** has a real seller API program (OAuth) — viable.
- **Meesho, Snapdeal, AJIO, Myntra**: official public APIs are limited or partner-gated; several "APIs" circulating are scraping wrappers. Each needs a signed partner/API agreement before it goes on a committed timeline. Build the `ChannelProvider` abstraction so any of them can plug in when access is granted, but don't promise clients data you can't compliantly fetch.
- **Shopify / WooCommerce / Magento / Walmart**: well-documented public APIs — viable as described.
- **Buy Box tracking / Brand Analytics**: Buy Box data comes from SP-API Pricing/Notifications (allowed); Brand Analytics reports require the Brand Analytics role in Amazon app review.

## Execution order (agreed)

0. ✅ **Security Phase 1** — shipped 2026-07-17 (see `security-audit.md`). Remaining before launch: rotate LWA secret, cookie auth, email verification.
1. ✅ **Channel core refactor** — shipped 2026-07-17. `channel_accounts` (+ encrypted `credentials` JSON for API-key platforms), `channel_sync_runs`, `channel_account_id` FKs and `platform`/`external_id` columns on products/listings (migration `2026_07_17_000900_create_channel_core`). `AmazonAccount` is now a platform-scoped subclass of `ChannelAccount`; the `ChannelProvider` interface + `ChannelManager` registry live in `app/Services/Channels/` with `AmazonChannelProvider` as the first adapter (new adapters register in `ChannelManager::PROVIDERS`). `GET /api/v1/integrations/channels` serves the platform catalog and the web `/integrations` page is now an Integration Hub grid (Amazon connectable, others "Coming soon").
2. **Orders & revenue** — `orders` table, per-platform order sync jobs, revenue dashboard. (Amazon Orders API first since it's already authorized.)
3. **India marketplaces** — Flipkart first (real OAuth API), others as partner access is signed.
4. **E-commerce bridges** — Shopify OAuth app, WooCommerce keys, webhook ingest.
5. **Amazon deep features** — FBA inventory, real Ads API sync, Buy Box tracker, Brand Analytics (needs app-review roles).
6. **AI optimization engine** — listing generator/rewriter (Claude/GPT, provider-configurable), competitor gap analysis, AI report narratives.
7. **Client portal** — invitations, viewer dashboards, approval workflow (viewer role + read-only enforcement already in place).
8. **Alerts & notifications** — evaluator job + email/WhatsApp/Slack/in-app senders.
9. **Billing** — Razorpay + Stripe, plans and limits.
10. **Analytics & PDF reporting**, then **international expansion**.

## Quick wins backlog (small, high-impact)

- Wire MetricSnapshot ingestion (orders sync will feed it) and dashboard charts
- Alert email delivery for existing rules
- Client fields: WhatsApp number, GSTIN/PAN, logo upload
- Team member invitations (unlocks roles shipped in security Phase 1)
- Listing filters (platform/score/status) and consistent pagination

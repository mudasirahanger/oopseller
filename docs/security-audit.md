# Security audit and production plan — 2026-07-17

> **Update (same day):** Phase 1 hardening implemented. Done: password reset flow (API + web pages), 401 on bad login, Sanctum token expiration (7 days) + daily pruning + revocation on password reset, role enforcement (viewer read-only middleware; owner/admin required for client/product delete, Amazon disconnect, org update), sanitized OAuth error redirects, Amazon sync dedupe, global authenticated-route throttle, security headers middleware, `is_platform_admin` removed from mass assignment, LIKE wildcard stripping, configurable org timezone/currency. Test suite grew from 5 to 10 passing tests.
> Still open: LWA credential rotation (user action — C1), cookie-based SPA auth (C2), email verification, full per-model Policies, member invitations UI, Next.js advisory (no stable fix exists yet — H5 accepted until 16.3.0 stable).

Scope: full review of `apps/api` (Laravel) and `apps/web` (Next.js), auth/tenancy middleware, all API controllers, Amazon SP-API services and jobs, queue/scheduler wiring, dependency audits (`composer audit`, `npm audit`), and the test/lint/build pipeline.

## Fixed in this pass

1. **Failing test** — `AmazonSpApiConfigurationTest` asserted the app-id-in-path consent URL; the implementation uses Amazon's documented `?application_id=` query format. Test corrected. Suite now 5/5.
2. **Runtime `env()` calls** in `AmazonIntegrationController::frontendRedirect()` and `HorizonServiceProvider` — these silently return `null` once `php artisan config:cache` runs in production, which would have broken every Amazon OAuth redirect. Moved to `config('app.frontend_url')` and `config('horizon.alert_email')`.
3. **Brute-force protection on login/register** — was sharing the generous 120/min `api-writes` limiter. Now a dedicated `throttle:auth` limiter: 10/min per IP and 5/min per email+IP.
4. **Hardcoded `user.id === 1` admin check** in `apps/web/src/app/settings/page.tsx` — replaced with the real `is_platform_admin` flag (API already enforced the role server-side).
5. **ESLint errors** (`any` in settings page, `require()` in tailwind config) and **Pint formatting** failures — fixed. `lint`, `tsc`, and `next build` all pass.

## Open findings

### Critical

- **C1. Live Amazon LWA credentials in `apps/api/.env`** (`AMAZON_LWA_CLIENT_ID` / `AMAZON_LWA_CLIENT_SECRET`). This project has been distributed as a ZIP, so the secret must be treated as leaked. Rotate it in the Amazon developer console and move production secrets to a secret manager. Never ship `.env` in archives.
- **C2. Bearer token + org ID in `localStorage`** (`apps/web/src/lib/api.ts`). Any XSS becomes full account takeover with no expiry (see C3). Move to Sanctum SPA cookie auth (HTTP-only, SameSite) for the first-party web app. Already noted in `production-readiness.md`; it is the single biggest pre-launch change.
- **C3. No authorization layer beyond membership.** `EnsureOrganizationAccess` verifies the user belongs to the org, but the `role` on the org pivot is only enforced in `OrganizationController`. Any member — including a future read-only or client-portal role — can delete clients, disconnect Amazon accounts, publish listings to Amazon, and manage all data. Implement Laravel Policies for every model plus a role matrix (owner / admin / member / viewer).

### High

- **H1. API tokens never expire.** No `config/sanctum.php`, so `expiration` is `null`; tokens are created with `['*']` abilities and are never pruned. Set an expiration, schedule `sanctum:prune-expired`, and revoke tokens on password change.
- **H2. No password reset, no email verification, no 2FA.** The account lifecycle is register/login only. Password reset is a launch blocker for a commercial SaaS.
- **H3. OAuth callback leaks internal exception messages** into the frontend redirect query string (`AmazonIntegrationController::callback` catch block). Replace with a generic message; keep details in logs.
- **H4. Unbounded sync queueing.** `POST /integrations/amazon/accounts/{id}/sync` has no throttle or dedupe — a user can queue unlimited `SyncAmazonListings` jobs and exhaust SP-API quotas. Skip dispatch when a run for the same account+marketplace is already `queued`/`running`, and rate-limit the route.
- **H5. Next.js 16.2.10 flagged by `npm audit`** (2 moderate, via bundled `postcss`). Upgrade Next. `composer audit` is clean.
- **H6. Debug defaults.** `APP_DEBUG=true`, `APP_ENV=local` in the example envs. Production deploy must set `APP_DEBUG=false`, `APP_ENV=production` — with debug on, validation/exception pages leak stack traces and env details.

### Medium

- **M1.** `is_platform_admin` is in `User::$fillable` — remove it; guard against future mass-assignment mistakes.
- **M2.** Unscoped `exists:clients,id` / `exists:products,id` / `exists:users,id` validation rules allow cross-tenant ID enumeration via validation error messages (access itself is correctly blocked afterwards). Scope with `Rule::exists()->where('organization_id', …)`.
- **M3.** `LIKE` searches don't escape `%`/`_` wildcards (clients, products, listings index).
- **M4.** Registration hardcodes `Asia/Kolkata` timezone and `INR` currency for every organization.
- **M5.** Disconnecting an Amazon account only nulls the stored refresh token; the seller's authorization remains active in Seller Central. Surface this to the user.
- **M6.** No security headers (HSTS, CSP, X-Frame-Options, Referrer-Policy) on API or web. Add at reverse proxy or middleware.
- **M7.** No audit log for sensitive actions (Amazon publish, disconnect, client delete, role changes).
- **M8.** Login returns 422 for bad credentials; use 401 with a uniform message.

### Test coverage gap

Only 4 feature tests exist. Missing: per-controller tenancy isolation matrix (every resource × foreign-org access), OAuth callback state handling, preview→publish flow with mocked SP-API, rate-limit behavior, and any frontend tests. This is the main safety net needed before refactoring toward production.

## Roadmap

### Phase 1 — Security hardening (launch blockers)
1. Rotate LWA credentials (C1) — do this immediately.
2. Cookie-based Sanctum SPA auth; remove localStorage tokens (C2), add token expiry/pruning for any remaining PAT use (H1).
3. Policies + role enforcement on every endpoint (C3), remove `is_platform_admin` from fillable (M1).
4. Password reset + email verification (H2).
5. Sanitize OAuth error redirects (H3); sync dedupe/throttling (H4).
6. Upgrade Next.js (H5); security headers (M6); scoped exists rules (M2); 401 on bad login (M8).

### Phase 2 — Production infrastructure
1. Managed MySQL + Redis, Horizon workers, `config:cache`/`route:cache` deploy pipeline, HTTPS behind a reverse proxy (php-fpm + `next start`, not dev servers).
2. Secrets management, automated DB backups + restore drill, error tracking (e.g. Sentry) and uptime monitoring, log aggregation.
3. CI gate: `scripts/check.sh` + `composer audit` + `npm audit` on every push.
4. Amazon application review: request only the roles the implemented operations need; test the Draft workflow end-to-end in sandbox.

### Phase 3 — Test depth
Tenancy isolation matrix, SP-API flows against fakes/sandbox, auth lifecycle tests, Playwright smoke tests for the web app.

### Phase 4 — Product (from docs/roadmap.md)
Data ingestion (Reports API, Data Kiosk, notifications, rate-limit coordination) → agency intelligence (rank provider, Ads OAuth, PDF reports, alert evaluator) → commercial SaaS (roles/invitations, billing, branding, audit log).

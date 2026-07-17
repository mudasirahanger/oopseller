# OopSeller

OopSeller is a Laravel + Next.js Amazon agency operating system for managing seller clients, products, listings, keywords, competitors, workflows, reports, and Amazon Selling Partner API connections.

## This ZIP is configured for manual macOS development

Default local stack:

- PHP 8.5 through Homebrew (Laravel requires PHP 8.3+)
- Composer
- Node.js 23 and npm
- SQLite
- Laravel file cache and file sessions
- Synchronous local queues
- Local filesystem and log mailer
- No MySQL, Redis, MinIO, Mailpit, or Docker required

Docker configuration is still included as an optional production-like environment.

## One-command setup

From the extracted project directory:

```bash
chmod +x scripts/*.sh
./scripts/setup-macos.sh
./scripts/start-dev.sh
```

Open:

- Web: http://localhost:3000
- Registration: http://localhost:3000/register
- API health: http://127.0.0.1:8000/api/v1/health

There is no fake login and no fake seller data. Register the first real agency account.

## Install prerequisites with Homebrew

```bash
brew install php composer node
```

Your installed Node.js 23 is above Next.js's minimum Node.js 20.9 requirement. If an ecosystem package behaves unexpectedly on the non-LTS Node 23 release, Node 22 LTS is the safest fallback.

Run the environment check:

```bash
./scripts/doctor.sh
```

## Manual commands

Terminal 1 — Laravel:

```bash
cd apps/api
cp .env.example .env
mkdir -p database storage/framework/{cache/data,sessions,views} storage/logs
: > database/database.sqlite
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2 — Next.js:

```bash
cd apps/web
cp .env.local.example .env.local
npm ci
npm run dev
```

Optional scheduler terminal:

```bash
cd apps/api
php artisan schedule:work
```

## Working modules

- Registration, login, Sanctum API tokens, and organization isolation
- Client create/read/update/archive
- Product create/read/update/archive
- Manual listings and listing versions
- Listing audits and optimization drafts
- Keyword projects and keyword creation
- Competitor records
- Tasks and status updates
- Optimization experiments
- Alerts
- Monthly report requests
- Real database-backed dashboard and honest empty states
- Amazon seller account authorization and marketplace discovery
- Amazon Catalog Items and Listings Items synchronization
- Product Type Definitions lookup
- Amazon listing validation preview before explicit publishing

## Amazon SP-API configuration

Edit `apps/api/.env`:

```dotenv
AMAZON_LWA_CLIENT_ID=
AMAZON_LWA_CLIENT_SECRET=
AMAZON_SPAPI_APPLICATION_ID=
AMAZON_REDIRECT_URI=http://127.0.0.1:8000/api/v1/integrations/amazon/callback
AMAZON_DEFAULT_REGION=eu
AMAZON_SPAPI_DRAFT=true
AMAZON_SPAPI_SANDBOX=false
```

The redirect URI must exactly match the URI registered in your Amazon developer application. A local HTTP callback may not be accepted for every Amazon application configuration; use an HTTPS tunnel or development domain when Amazon requires HTTPS.

Implemented SP-API operations:

- Website authorization consent URL
- LWA authorization-code exchange
- LWA refresh-token exchange
- Sellers API marketplace participation discovery
- Catalog Items API 2022-04-01 `getCatalogItem`
- Listings Items API 2021-08-01 search/get/patch
- Product Type Definitions API 2020-09-01
- `VALIDATION_PREVIEW` before listing publication

Amazon Ads OAuth/reporting is a separate integration and is not falsely represented as connected. Exact organic keyword ranking and competitor snapshots also require compliant external providers.

## SQLite notes

The local database is:

```text
apps/api/database/database.sqlite
```

Reset only your local development database:

```bash
./scripts/reset-sqlite.sh
```

SQLite is intended for local development. Use MySQL or PostgreSQL for production, concurrent workers, larger analytics datasets, and backups.

## Tests and build

```bash
./scripts/check.sh
```

This runs:

- Laravel tests
- Laravel Pint
- Next.js ESLint
- TypeScript checking
- Next.js production build

## Optional Docker environment

```bash
cp .env.docker.example .env
# Generate APP_KEY or copy one from apps/api/.env after local setup.
docker compose up -d --build
```

Docker uses MySQL, Redis, Horizon, MinIO, and Mailpit. Manual SQLite development does not.

## Repository layout

```text
apps/api                 Laravel API
apps/web                 Next.js frontend
docs                     Architecture and integration notes
scripts                  macOS manual setup and run scripts
infrastructure/docker    Optional container images and entrypoints
```

## Release status

This is a functional pre-production MVP. Core CRUD, tenant isolation, Amazon seller authorization, catalog/listing sync, listing preview/publish, and agency workflow screens are implemented. Amazon Ads, licensed ranking data, automated competitor collection, payments, and hardened production infrastructure remain explicit future integrations.

See `docs/production-readiness.md` for the launch checklist.

## Laravel 13 / Composer troubleshooting

OopSeller requires Laravel Tinker 3.x. If an older extracted package reports a conflict between Laravel 13 and `laravel/tinker ^2.10`, remove that copy and use this release. For an existing working tree, change the requirement to `^3.0`, then run:

```bash
cd apps/api
rm -rf vendor composer.lock
composer update --with-all-dependencies
```

Deprecation notices printed by Composer 2.8 on PHP 8.5 come from Composer's bundled dependencies, not from OopSeller. Update Homebrew Composer with `brew update && brew upgrade composer`.

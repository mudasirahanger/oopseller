# Deploying to cPanel/WHM as a subdomain (testing)

This deploys OopSeller under two subdomains on a single cPanel account:

- `api.yourdomain.com` → Laravel API (PHP, handled natively by cPanel)
- `app.yourdomain.com` → Next.js frontend, **built to static files** (`output: "export"` in `apps/web/next.config.ts`) — no Node.js process needs to run on the server at all.

Two subdomains (rather than one + a path) avoids needing reverse-proxy/Apache
rewrite access most shared cPanel accounts don't have, and matches the CORS
setup already in the codebase (`FRONTEND_URL` → `config/cors.php`).

**Requirements:** PHP **8.3+** (`composer.json` requires `^8.3`), Composer, a
MySQL database (or SQLite for a quick solo test), and ideally SSH/Terminal
access in cPanel (Composer installs are painful without it). Node.js is only
needed to *build* the frontend — you can build it on your own machine and
upload the static output, so the server itself never needs Node.

## 1. Create the subdomains

WHM/cPanel → **Domains → Subdomains** (or **Domains** in newer cPanel):

- `api` → document root **must** point to `apps/api/public` inside the repo
  once cloned (e.g. `/home/USER/repositories/oopseller/apps/api/public`).
- `app` → document root can point anywhere for now (e.g.
  `/home/USER/public_html/app`) — you'll upload the static build there.

## 2. Create a database

cPanel → **MySQL® Databases** → create a database + user, grant all
privileges, note the DB name/user/password (cPanel usually prefixes them with
your account name, e.g. `user_oopseller`).

*Fast path for solo testing:* skip this and set `DB_CONNECTION=sqlite` — no
DB setup needed. Switch to MySQL if more than one person will hit the test box.

## 3. Clone the repo

**With SSH (recommended):**

```bash
ssh USER@yourhost.com
mkdir -p ~/repositories && cd ~/repositories
git clone https://github.com/mudasirahanger/oopseller.git
cd oopseller
```

**Without SSH:** cPanel → **Git™ Version Control** → Create →
Clone URL `https://github.com/mudasirahanger/oopseller` → set the repository
path to somewhere *outside* `public_html` (e.g. `repositories/oopseller`) so
the whole monorepo isn't web-exposed, then use cPanel's Terminal app if
available for the composer/npm steps below. If your host offers neither SSH
nor Terminal, you cannot run Composer/artisan — ask support to enable one;
shared PHP hosting without shell access can't run this stack.

## 4. Backend (Laravel API)

```bash
cd ~/repositories/oopseller/apps/api
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Edit `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

# MySQL (skip and use DB_CONNECTION=sqlite for the fast path)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=user_oopseller
DB_USERNAME=user_oopseller
DB_PASSWORD=xxxxx

# Shared hosting has no persistent background workers — keep queues
# synchronous so jobs (Amazon sync, order sync, etc.) run inline.
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
CACHE_STORE=file

AMAZON_REDIRECT_URI=https://api.yourdomain.com/api/v1/integrations/amazon/callback
AMAZON_LWA_CLIENT_ID=
AMAZON_LWA_CLIENT_SECRET=
AMAZON_SPAPI_APPLICATION_ID=
AMAZON_SPAPI_DRAFT=true
```

If using SQLite instead:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/home/USER/repositories/oopseller/apps/api/database/database.sqlite
```
```bash
mkdir -p database storage/framework/{cache/data,sessions,views} storage/logs
: > database/database.sqlite
```

Then:

```bash
php artisan key:generate
php artisan migrate --force   # no --seed: no fake data by design
php artisan storage:link
chmod -R 775 storage bootstrap/cache
php artisan config:cache && php artisan route:cache
```

Set the **api** subdomain's PHP version via cPanel → **MultiPHP Manager** →
select `api.yourdomain.com` → PHP 8.3 or newer.

Verify: `https://api.yourdomain.com/api/v1/health` should return `200`.

## 5. Frontend (Next.js static export)

Build **locally** (or anywhere with Node 20+; `.nvmrc` pins 23 for local dev)
— the server never runs this:

```bash
cd apps/web
cp .env.local.example .env.local
# set: NEXT_PUBLIC_API_URL=https://api.yourdomain.com/api/v1
npm ci
npm run build
```

This produces `apps/web/out/` — plain static files. Upload its **contents**
(not the folder itself) to the `app` subdomain's document root, e.g.:

```bash
rsync -avz out/ USER@yourhost.com:~/public_html/app/
```

`NEXT_PUBLIC_API_URL` is baked in at build time (static export has no server
to read env vars at runtime) — rebuild and re-upload if the API URL changes.

## 6. SSL

cPanel → **SSL/TLS Status** → run **AutoSSL** for both subdomains (or install
Let's Encrypt certs). HTTPS matters here specifically because Amazon's OAuth
redirect URI must be registered exactly and Amazon may reject plain HTTP for
non-draft apps — see `docs/connectors/amazon.md`.

## 7. Cron (Laravel scheduler)

cPanel → **Cron Jobs** → every minute:

```
* * * * * cd /home/USER/repositories/oopseller/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

This drives the hourly order-sync scheduler, daily Sanctum token pruning, and
monthly report generation (see `apps/api/routes/console.php`).

## 8. Amazon app configuration

In the Amazon Developer Console, set the redirect URI to exactly
`https://api.yourdomain.com/api/v1/integrations/amazon/callback` (must match
byte-for-byte). Keep `AMAZON_SPAPI_DRAFT=true` while the app is in Draft.

## 9. Smoke test

1. `https://api.yourdomain.com/api/v1/health` → `200`.
2. `https://app.yourdomain.com/register` → create an account (no seed data,
   by design).
3. Log in, add a client/product, confirm `X-Organization-Id` requests work
   (check CORS: `FRONTEND_URL` in the API `.env` must exactly match the `app`
   origin, scheme included).
4. Integrations → Connect Amazon → confirm the redirect round-trips.

## Redeploying updates

```bash
cd ~/repositories/oopseller
git pull
cd apps/api && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
# frontend: rebuild locally, re-upload out/ contents to app subdomain
```

`.env` is gitignored, so `git pull` never touches it.

## Caveats specific to this being a shared-hosting test box

- **No persistent queue workers.** `QUEUE_CONNECTION=sync` above runs jobs
  inline on the request that dispatches them (same as local dev's default) —
  fine for testing, but a real Amazon sync of many listings/orders will run
  synchronously inside the HTTP request that triggers it, so it may be slow.
- **No Redis/Horizon** on typical shared hosting — the app doesn't require
  them (see `docker-compose.yml` for the optional production-parity stack).
- **Don't put real production Amazon credentials on a public test box** you
  don't fully control/monitor — use a Draft/sandbox Amazon app for this.
- SQLite works but is single-writer; move to MySQL if this stops being a
  solo test.

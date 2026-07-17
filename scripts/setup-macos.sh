#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="$ROOT_DIR/apps/api"
WEB_DIR="$ROOT_DIR/apps/web"

"$ROOT_DIR/scripts/doctor.sh"

echo
echo 'Preparing Laravel API...'
cd "$API_DIR"
[[ -f .env ]] || cp .env.example .env
mkdir -p database storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
touch database/database.sqlite
composer validate --no-check-publish
composer install --no-interaction --prefer-dist
php artisan key:generate --force
php artisan migrate --seed --force
php artisan storage:link >/dev/null 2>&1 || true
php artisan optimize:clear

echo
echo 'Preparing Next.js web application...'
cd "$WEB_DIR"
[[ -f .env.local ]] || cp .env.local.example .env.local
npm ci

echo
echo 'Setup completed.'
echo 'Run: ./scripts/start-dev.sh'
echo 'Then open: http://localhost:3000/register'

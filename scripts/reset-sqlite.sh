#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR/apps/api"
read -r -p 'Delete all local SQLite data and rebuild it? [y/N] ' answer
[[ "$answer" =~ ^[Yy]$ ]] || exit 0
rm -f database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh --seed --force
php artisan optimize:clear
echo 'Local SQLite database reset.'

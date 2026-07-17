#!/usr/bin/env sh
set -eu

cd /app

if [ ! -x node_modules/.bin/next ]; then
  echo "Node dependencies are missing; installing dependencies..."
  if [ -f package-lock.json ]; then
    npm ci --no-audit --no-fund
  else
    npm install --no-audit --no-fund
  fi
fi

exec "$@"

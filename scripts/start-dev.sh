#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PIDS=()

cleanup() {
  echo
  echo 'Stopping OopSeller development processes...'
  for pid in "${PIDS[@]:-}"; do
    kill "$pid" 2>/dev/null || true
  done
  wait 2>/dev/null || true
}
trap cleanup EXIT INT TERM

if [[ ! -f "$ROOT_DIR/apps/api/vendor/autoload.php" ]]; then
  echo 'Laravel dependencies are missing. Run ./scripts/setup-macos.sh first.' >&2
  exit 1
fi
if [[ ! -d "$ROOT_DIR/apps/web/node_modules" ]]; then
  echo 'Node dependencies are missing. Run ./scripts/setup-macos.sh first.' >&2
  exit 1
fi

(
  cd "$ROOT_DIR/apps/api"
  php artisan serve --host=127.0.0.1 --port=8000
) &
PIDS+=("$!")

(
  cd "$ROOT_DIR/apps/api"
  php artisan schedule:work
) &
PIDS+=("$!")

(
  cd "$ROOT_DIR/apps/web"
  npm run dev
) &
PIDS+=("$!")

echo 'OopSeller is starting:'
echo '  Web: http://localhost:3000'
echo '  API: http://127.0.0.1:8000/api/v1/health'
echo 'Press Ctrl+C to stop all processes.'
wait

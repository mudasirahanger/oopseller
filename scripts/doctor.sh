#!/usr/bin/env bash
set -euo pipefail

fail=0
check_command() {
  local command_name="$1"
  local help_text="$2"
  if command -v "$command_name" >/dev/null 2>&1; then
    printf '✓ %-12s %s\n' "$command_name" "$($command_name --version 2>/dev/null | head -n 1)"
  else
    printf '✗ %-12s missing — %s\n' "$command_name" "$help_text"
    fail=1
  fi
}

check_command php 'brew install php'
check_command composer 'brew install composer'
check_command node 'install Node.js 23 or Node.js 22 LTS'
check_command npm 'installed with Node.js'

if command -v php >/dev/null 2>&1; then
  php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
    && echo '✓ PHP version is supported (8.3+).' \
    || { echo '✗ PHP 8.3 or newer is required.'; fail=1; }

  for extension in pdo_sqlite curl mbstring openssl tokenizer xml; do
    if php -r "exit(extension_loaded('${extension}') ? 0 : 1);"; then
      printf '✓ PHP extension: %s\n' "$extension"
    else
      printf '✗ Missing PHP extension: %s\n' "$extension"
      fail=1
    fi
  done

  if php -r 'exit(in_array("sqlite", PDO::getAvailableDrivers(), true) ? 0 : 1);'; then
    echo '✓ PDO driver: sqlite'
  else
    echo '✗ Missing PDO SQLite driver.'
    fail=1
  fi
fi

if command -v node >/dev/null 2>&1; then
  node -e 'const [major, minor] = process.versions.node.split(".").map(Number); process.exit(major > 20 || (major === 20 && minor >= 9) ? 0 : 1)' \
    && echo '✓ Node.js satisfies Next.js minimum 20.9.' \
    || { echo '✗ Node.js 20.9 or newer is required.'; fail=1; }
fi

exit "$fail"

#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
unset PHP_CLI_SERVER_WORKERS || true

# Drop leftover PHP built-in servers so the preview port is free.
pkill -f 'php -S .*router.php' 2>/dev/null || true
pkill -f 'preview-proxy.py' 2>/dev/null || true
sleep 0.4

start_php() {
  local port="$1"
  php -S "127.0.0.1:${port}" -t "$ROOT" "$ROOT/router.php" \
    >"/tmp/vellisys-php-${port}.log" 2>&1 &
  echo $! >"/tmp/vellisys-php-${port}.pid"
}

start_php 43230
start_php 43231
start_php 43232
start_php 43233

echo "Vellisys proxy http://127.0.0.1:43219/ (PHP workers 43230-43233)"
exec python3 "$ROOT/scripts/preview-proxy.py" 43219

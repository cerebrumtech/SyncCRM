#!/usr/bin/env bash
#
# Starts PHP's built-in server for local development, with dev/router.php in front so
# URLs behave the way nginx behaves in production.
#
#   bash dev/serve.sh            # http://127.0.0.1:8080
#   bash dev/serve.sh 9000       # a different port
#
# Ctrl-C stops it. This is a development server only -- never expose it.
set -u
cd "$(dirname "$0")/.."
PORT="${1:-8080}"

if [ ! -f .env ]; then
  echo "No .env yet. Copy .env.example to .env and fill in the database settings first." >&2
  exit 1
fi

echo "SyncCRM on http://127.0.0.1:${PORT}  (Ctrl-C to stop)"
PHP_CLI_SERVER_WORKERS=8 php -S "127.0.0.1:${PORT}" -t public dev/router.php

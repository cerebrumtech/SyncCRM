#!/usr/bin/env bash
# Deploys / updates SyncCRM on a Cloudways application over SSH (no root needed).
#
# One-time:   bash <(curl -fsSL https://raw.githubusercontent.com/cerebrumtech/SyncCRM/main/deploy/cloudways.sh) install
# Update:     ~/synccrm/deploy/cloudways.sh update
#
# Layout (APP_DIR = ~/applications/<app>/private_html/synccrm):
#   code + .next build + uploads live under APP_DIR; public_html only holds the .htaccess proxy.
set -euo pipefail

MODE="${1:-install}"
NODE_VERSION="${NODE_VERSION:-22}"
PORT="${PORT:-3000}"
REPO="${REPO:-https://github.com/cerebrumtech/SyncCRM.git}"
BRANCH="${BRANCH:-main}"

# Locate the Cloudways application folder (the one containing public_html).
# - Application-level SSH user: its home IS the app folder.
# - Master user: apps live under ~/applications/<folder>; exactly one must be chosen
#   (APP_NAME=<folder> or APP_ROOT=<path>) so no other application is ever touched.
if [[ -z "${APP_ROOT:-}" ]]; then
  if [[ -d "$HOME/public_html" ]]; then
    APP_ROOT="$HOME"
  elif [[ -n "${APP_NAME:-}" ]]; then
    APP_ROOT="$HOME/applications/$APP_NAME"
  else
    mapfile -t APPS < <(ls -d "$HOME"/applications/*/ 2>/dev/null)
    if [[ ${#APPS[@]} -eq 1 ]]; then
      APP_ROOT="${APPS[0]%/}"
    else
      echo "This SSH user can see ${#APPS[@]} applications:" >&2
      printf '  %s\n' "${APPS[@]}" >&2
      echo "Choose the SyncCRM one explicitly: APP_NAME=<folder name> $0 $MODE" >&2
      echo "(or SSH in with the SyncCRM application's own credentials from Access Details)" >&2
      exit 1
    fi
  fi
fi
if [[ ! -d "$APP_ROOT/public_html" ]]; then
  echo "No public_html under $APP_ROOT — is this the right application folder?" >&2
  exit 1
fi
if [[ "$MODE" == "install" && -n "$(ls -A "$APP_ROOT/public_html" 2>/dev/null)" ]]; then
  # Cloudways' "Custom App" ships a placeholder site; anything else is treated as a real app.
  if grep -q "Node.js app started by PM2" "$APP_ROOT/public_html/.htaccess" 2>/dev/null; then
    echo "public_html already holds a SyncCRM install — continuing as an update."
  elif grep -qiE "cloud hosting|cloudways" "$APP_ROOT/public_html/index.php" "$APP_ROOT/public_html/index.html" 2>/dev/null; then
    BACKUP="$APP_ROOT/private_html/public_html_placeholder_$(date +%Y%m%d%H%M%S)"
    mkdir -p "$BACKUP"
    mv "$APP_ROOT/public_html/"* "$APP_ROOT/public_html/".[!.]* "$BACKUP"/ 2>/dev/null || true
    echo "Moved the Cloudways placeholder page to $BACKUP"
  elif [[ -n "$(ls -A "$APP_ROOT/public_html" | grep -v -E '^(index\.html|\.htaccess)$')" ]]; then
    echo "public_html of $APP_ROOT already contains files — refusing to install over another application." >&2
    ls -A "$APP_ROOT/public_html" >&2
    exit 1
  fi
fi
echo "Application folder: $APP_ROOT"
APP_DIR="$APP_ROOT/private_html/synccrm"
mkdir -p "$APP_ROOT/private_html"

echo "== Node $NODE_VERSION via nvm"
export NVM_DIR="$HOME/.nvm"
if [[ ! -s "$NVM_DIR/nvm.sh" ]]; then
  curl -fsSL https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
fi
# shellcheck disable=SC1090
source "$NVM_DIR/nvm.sh"
nvm install "$NODE_VERSION" >/dev/null
nvm use "$NODE_VERSION" >/dev/null
corepack enable >/dev/null 2>&1 || npm install -g corepack >/dev/null
command -v pm2 >/dev/null || npm install -g pm2 >/dev/null
echo "node $(node -v), pnpm $(pnpm -v 2>/dev/null || echo 'via corepack'), pm2 $(pm2 -v)"

echo "== Source"
if [[ -d "$APP_DIR/.git" ]]; then
  git -C "$APP_DIR" fetch --depth 1 origin "$BRANCH"
  git -C "$APP_DIR" reset --hard "origin/$BRANCH"
else
  git clone --depth 1 --branch "$BRANCH" "$REPO" "$APP_DIR"
fi
cd "$APP_DIR"

echo "== Environment"
if [[ ! -f .env ]]; then
  if [[ -z "${DATABASE_URL:-}" || -z "${APP_URL:-}" ]]; then
    echo "First install needs DATABASE_URL and APP_URL, e.g.:" >&2
    echo "  DATABASE_URL='postgresql://user:pass@host/db?sslmode=require' APP_URL='https://crm.example.com' $0 install" >&2
    exit 1
  fi
  cat > .env <<EOF
DATABASE_URL="$DATABASE_URL"
APP_URL="$APP_URL"
UPLOAD_DIR="$APP_DIR/uploads"
PORT=$PORT
EOF
  chmod 600 .env
fi
mkdir -p uploads

echo "== Build"
pnpm install --frozen-lockfile
pnpm exec prisma migrate deploy
pnpm build

echo "== Apache proxy (.htaccess in public_html)"
cp deploy/cloudways.htaccess "$APP_ROOT/public_html/.htaccess"
sed -i "s/__PORT__/$PORT/g" "$APP_ROOT/public_html/.htaccess"
# Cloudways' nginx serves files that exist in public_html itself and answers "/" on its own
# (403 without an index file), so a static index hands "/" to the app ...
cp deploy/cloudways-index.html "$APP_ROOT/public_html/index.html"
# ... and the build's static assets are published there so nginx can serve them directly.
rm -rf "$APP_ROOT/public_html/_next"
mkdir -p "$APP_ROOT/public_html/_next"
cp -r .next/static "$APP_ROOT/public_html/_next/static"
cp -r public/. "$APP_ROOT/public_html/"
cp src/app/icon.png "$APP_ROOT/public_html/icon.png"

echo "== Start with PM2"
pm2 delete synccrm >/dev/null 2>&1 || true
sleep 1
# Pick the first free localhost port at or above PORT so we never collide with another service.
port_in_use() { (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null && { exec 3>&-; return 0; } || return 1; }
while port_in_use "$PORT"; do
  echo "Port $PORT is in use by another service — trying $((PORT + 1))"
  PORT=$((PORT + 1))
done
sed -i "s/^PORT=.*/PORT=$PORT/" .env
sed -i -E "s#127\.0\.0\.1:[0-9]+#127.0.0.1:$PORT#g" "$APP_ROOT/public_html/.htaccess"
echo "Using port $PORT"
PORT="$PORT" pm2 start "pnpm exec next start -p $PORT" --name synccrm --cwd "$APP_DIR" --time
pm2 save >/dev/null

echo "== Watchdog"
chmod +x deploy/cloudways-watchdog.sh
echo
echo "Done. Add this cron job in the Cloudways panel (Application > Cron Job Management > Advanced), every minute:"
echo "  * * * * *  $APP_DIR/deploy/cloudways-watchdog.sh"
echo "Then open $(grep '^APP_URL' .env | cut -d'"' -f2) — first visit shows /setup (or run: pnpm db:seed for demo data)."

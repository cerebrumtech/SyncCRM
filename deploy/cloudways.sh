#!/usr/bin/env bash
# Installs or updates SyncCRM on one Cloudways application.
#
# SyncCRM runs on PHP 7.4 or newer with no third-party dependencies, so there is no
# Composer step and nothing to build on the server.
#
# First install (paste in the Cloudways SSH terminal):
#   APP_NAME=<app folder> DB_NAME=<db> DB_USER=<user> DB_PASS='<password>' APP_URL='https://crm.example.com' \
#   bash <(curl -fsSL https://raw.githubusercontent.com/cerebrumtech/SyncCRM/php-codeigniter/deploy/cloudways.sh) install
# Update:
#   APP_NAME=<app folder> ~/applications/<app folder>/public_html/deploy/cloudways.sh update
#
# Only the chosen application's folder is touched. Nothing server-wide changes.
set -euo pipefail

MODE="${1:-install}"
REPO="${REPO:-https://github.com/cerebrumtech/SyncCRM.git}"
BRANCH="${BRANCH:-php-codeigniter}"

# --- locate the application folder ------------------------------------------------------
if [[ -z "${APP_ROOT:-}" ]]; then
  if [[ -d "$HOME/public_html" ]]; then
    APP_ROOT="$HOME"
  elif [[ -n "${APP_NAME:-}" ]]; then
    APP_ROOT="$HOME/applications/$APP_NAME"
  else
    mapfile -t APPS < <(ls -d "$HOME"/applications/*/ 2>/dev/null)
    if [[ ${#APPS[@]} -eq 1 ]]; then APP_ROOT="${APPS[0]%/}"; else
      echo "This SSH user can see ${#APPS[@]} applications. Choose one: APP_NAME=<folder> $0 $MODE" >&2; exit 1; fi
  fi
fi
[[ -d "$APP_ROOT/public_html" ]] || { echo "No public_html under $APP_ROOT" >&2; exit 1; }
WEB="$APP_ROOT/public_html"
echo "Application folder: $APP_ROOT"

# --- PHP 7.4 or newer -----------------------------------------------------------------------
PHP=""
for c in php php8.3 php8.2 php8.1 php8.0 php7.4; do
  if command -v "$c" >/dev/null 2>&1 && "$c" -r 'exit(PHP_VERSION_ID >= 70400 ? 0 : 1);' 2>/dev/null; then PHP="$c"; break; fi
done
[[ -n "$PHP" ]] || { echo "PHP 7.4 or newer is required." >&2; exit 1; }
echo "Using $PHP ($("$PHP" -r 'echo PHP_VERSION;')) for install commands"
for ext in pdo_mysql mbstring json; do
  "$PHP" -m | grep -qi "^${ext}$" || { echo "PHP extension '$ext' is missing. Enable it in the Cloudways panel." >&2; exit 1; }
done

# --- stop the old Node.js deployment of SyncCRM, if this app ever ran it ---------------------
if [[ -s "$HOME/.nvm/nvm.sh" ]]; then
  # shellcheck disable=SC1090
  source "$HOME/.nvm/nvm.sh" >/dev/null 2>&1 || true
  if command -v pm2 >/dev/null 2>&1 && pm2 describe synccrm >/dev/null 2>&1; then
    pm2 delete synccrm >/dev/null 2>&1 && pm2 save >/dev/null 2>&1 && echo "Stopped the old Node.js process (pm2 synccrm)."
  fi
fi

# --- source code into public_html ---------------------------------------------------------
if [[ -f "$WEB/public/index.php" && -d "$WEB/.git" ]]; then
  echo "== Updating existing SyncCRM checkout"
  git -C "$WEB" fetch --depth 1 origin "$BRANCH"
  git -C "$WEB" reset --hard "origin/$BRANCH"
else
  if [[ -n "$(ls -A "$WEB" 2>/dev/null)" ]]; then
    if [[ "$MODE" != "install" ]]; then echo "public_html is not a SyncCRM checkout; run with 'install'." >&2; exit 1; fi
    if ! grep -qiE "cloud hosting|cloudways|SyncCRM|synccrm" "$WEB/index.php" "$WEB/index.html" "$WEB/.htaccess" 2>/dev/null \
       && [[ -n "$(ls -A "$WEB" | grep -vE '^(\.htaccess|\.user\.ini|index\.html|index\.php|phpcheck\.php|_next|brand|icon\.png|assets|.*\.svg)$')" ]]; then
      echo "public_html contains another application — refusing to overwrite:" >&2; ls -A "$WEB" >&2; exit 1
    fi
    BACKUP="$APP_ROOT/private_html/public_html_backup_$(date +%Y%m%d%H%M%S)"
    mkdir -p "$BACKUP"; mv "$WEB"/* "$WEB"/.[!.]* "$BACKUP"/ 2>/dev/null || true
    echo "Moved the previous public_html contents to $BACKUP"
  fi
  echo "== Cloning $REPO ($BRANCH)"
  git clone --depth 1 --branch "$BRANCH" "$REPO" "$WEB"
fi
cd "$WEB"

# --- environment ----------------------------------------------------------------------------
echo "== Environment"
if [[ ! -f .env ]]; then
  if [[ -z "${DB_NAME:-}" || -z "${DB_USER:-}" || -z "${DB_PASS:-}" || -z "${APP_URL:-}" ]]; then
    echo "First install needs DB_NAME, DB_USER, DB_PASS (Cloudways > Application > Access Details > MySQL Access) and APP_URL." >&2; exit 1; fi
  INSTALL_KEY="$("$PHP" -r 'echo bin2hex(random_bytes(12));')"
  cat > .env <<ENV
CI_ENVIRONMENT = production
app.baseURL = '${APP_URL%/}/'
app.uploadDir = '$APP_ROOT/private_html/uploads'
app.installKey = '$INSTALL_KEY'
app.timezone = 'Asia/Kolkata'
database.default.hostname = ${DB_HOST:-localhost}
database.default.database = $DB_NAME
database.default.username = $DB_USER
database.default.password = $DB_PASS
database.default.port = ${DB_PORT:-3306}
ENV
  chmod 600 .env
  echo "Wrote .env"
fi
mkdir -p "$APP_ROOT/private_html/uploads" writable/logs writable/imports writable/uploads
chmod -R 775 writable "$APP_ROOT/private_html/uploads" 2>/dev/null || true

# --- database -------------------------------------------------------------------------------
echo "== Database"
if [[ "${SEED:-0}" == "1" ]]; then
  "$PHP" schema/install.php --seed
else
  "$PHP" schema/install.php
fi

echo
echo "Done. Two settings in the Cloudways panel finish the job:"
echo "  1. Application Settings > General > Webroot  ->  public_html/public"
echo "     (the field already holds public_html/ — add 'public' on the end)"
echo "  2. Application Settings > Varnish            ->  Disabled"
echo
echo "Then open $(grep '^app.baseURL' .env | cut -d"'" -f2)"
echo "Demo data later:  cd $WEB && $PHP schema/install.php --seed"

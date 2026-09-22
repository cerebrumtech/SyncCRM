#!/usr/bin/env bash
# Installs or updates SyncCRM (PHP / CodeIgniter 4 / MySQL) on one Cloudways application.
#
# First install (paste in the Cloudways SSH session, master or application user):
#   APP_NAME=<app folder> DB_NAME=<db> DB_USER=<user> DB_PASS='<password>' APP_URL='https://crm.example.com' \
#   bash <(curl -fsSL https://raw.githubusercontent.com/cerebrumtech/SyncCRM/php-codeigniter/deploy/cloudways.sh) install
# Update to the latest code:
#   APP_NAME=<app folder> ~/applications/<app folder>/public_html/deploy/cloudways.sh update
#
# Only the chosen application's folder is touched (public_html + private_html). Nothing server-wide changes.
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

# --- PHP >= 8.1 ------------------------------------------------------------------------------
PHP=""
for c in php8.4 php8.3 php8.2 php; do
  if command -v "$c" >/dev/null 2>&1 && "$c" -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);' 2>/dev/null; then PHP="$c"; break; fi
done
[[ -n "$PHP" ]] || { echo "PHP 8.1 or newer is required (Cloudways: Server > Settings & Packages > PHP)." >&2; exit 1; }
echo "Using $PHP ($("$PHP" -r 'echo PHP_VERSION;')) for install commands"
DEFAULT_PHP_VER="$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)"
FPM_VERS="$( { ps -eo args 2>/dev/null | grep -oE 'php-fpm[0-9.]+|php/[0-9]+\.[0-9]+/' | grep -oE '[0-9]+\.[0-9]+' | sort -u | tr '\n' ' '; } || true )"
echo "Server default php CLI: $DEFAULT_PHP_VER   php-fpm versions running: ${FPM_VERS:-unknown}"
echo "NOTE: the web server must serve this app with PHP 8.1 or newer, which is a separate"
echo "      setting from the CLI above. If the site later shows a PHP version error, the"
echo "      application is still being served by an older PHP-FPM."

# --- stop the old Node.js deployment of SyncCRM, if this app ever ran it ---------------------
if [[ -s "$HOME/.nvm/nvm.sh" ]]; then
  # shellcheck disable=SC1090
  source "$HOME/.nvm/nvm.sh" >/dev/null 2>&1 || true
  if command -v pm2 >/dev/null 2>&1 && pm2 describe synccrm >/dev/null 2>&1; then
    pm2 delete synccrm >/dev/null 2>&1 && pm2 save >/dev/null 2>&1 && echo "Stopped the old Node.js process (pm2 synccrm)."
  fi
fi

# --- source code into public_html ---------------------------------------------------------
if [[ -f "$WEB/spark" && -d "$WEB/.git" ]]; then
  echo "== Updating existing SyncCRM checkout"
  git -C "$WEB" fetch --depth 1 origin "$BRANCH"
  git -C "$WEB" reset --hard "origin/$BRANCH"
else
  if [[ -n "$(ls -A "$WEB" 2>/dev/null)" ]]; then
    if [[ "$MODE" != "install" ]]; then echo "public_html is not a SyncCRM checkout; run with 'install'." >&2; exit 1; fi
    if ! grep -qiE "cloud hosting|cloudways|SyncCRM|synccrm" "$WEB/index.php" "$WEB/index.html" "$WEB/.htaccess" 2>/dev/null && [[ -n "$(ls -A "$WEB" | grep -vE '^(\.htaccess|\.user\.ini|index\.html|index\.php|_next|brand|icon\.png|.*\.svg)$')" ]]; then
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

# --- composer -------------------------------------------------------------------------------
# Always drive Composer with the PHP chosen above. A global "composer" launcher runs on the
# server's DEFAULT php, which on Cloudways is often 7.4 and fails the dependency check.
echo "== Composer"
# Run Composer with the PHP chosen above. Calling the bare "composer" launcher would use the
# server's DEFAULT php, which on Cloudways is often 7.4 and fails CodeIgniter's requirement.
COMPOSER_PATH="$(command -v composer 2>/dev/null || true)"
if [[ -z "$COMPOSER_PATH" ]] || ! "$PHP" "$COMPOSER_PATH" --version >/dev/null 2>&1; then
  COMPOSER_PATH="$APP_ROOT/private_html/composer.phar"
  [[ -f "$COMPOSER_PATH" ]] || curl -fsSL https://getcomposer.org/composer-stable.phar -o "$COMPOSER_PATH"
fi
echo "Composer: $COMPOSER_PATH run with $PHP"
"$PHP" "$COMPOSER_PATH" install --no-dev --no-interaction --prefer-dist --optimize-autoloader 2>&1 | tail -3

# --- environment ----------------------------------------------------------------------------
echo "== Environment"
if [[ ! -f .env ]]; then
  if [[ -z "${DB_NAME:-}" || -z "${DB_USER:-}" || -z "${DB_PASS:-}" || -z "${APP_URL:-}" ]]; then
    echo "First install needs DB_NAME, DB_USER, DB_PASS (Cloudways > Application > Access Details > MySQL Access) and APP_URL." >&2; exit 1; fi
  KEY="hex2bin:$("$PHP" -r 'echo bin2hex(random_bytes(32));')"
  INSTALL_KEY="$("$PHP" -r 'echo bin2hex(random_bytes(12));')"
  cat > .env <<ENV
CI_ENVIRONMENT = production
app.baseURL = '${APP_URL%/}/'
app.uploadDir = '$APP_ROOT/private_html/uploads'
app.installKey = '$INSTALL_KEY'
database.default.hostname = ${DB_HOST:-localhost}
database.default.database = $DB_NAME
database.default.username = $DB_USER
database.default.password = $DB_PASS
database.default.DBDriver = MySQLi
database.default.port = ${DB_PORT:-3306}
encryption.key = $KEY
ENV
  chmod 600 .env
  echo "Wrote .env"
fi
mkdir -p "$APP_ROOT/private_html/uploads" writable/cache writable/logs writable/session writable/imports
chmod -R 775 writable 2>/dev/null || true

# --- database -------------------------------------------------------------------------------
echo "== Database migrations"
"$PHP" spark migrate 2>&1 | tail -1
if [[ "${SEED:-0}" == "1" ]]; then "$PHP" spark db:seed DemoSeeder 2>&1 | tail -2; fi

echo
echo "Done. Two panel settings finish the job (Cloudways > Applications > this app > Application Settings):"
echo "  1. General > Webroot  ->  public_html/public     (type it exactly, including public_html/)"
echo "  2. Varnish tab        ->  Disabled               (CRM pages must never be cached)"
echo "Then open $(grep '^app.baseURL' .env | cut -d"'" -f2) — the first visit shows 'Set up your workspace'."
echo "Demo data instead: SEED=1 when installing, or: cd $WEB && $PHP spark db:seed DemoSeeder"

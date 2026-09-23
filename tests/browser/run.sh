#!/usr/bin/env bash
#
# Runs the SyncCRM browser suites against a running dev server.
#
#   bash tests/browser/run.sh                 # the five functional suites
#   bash tests/browser/run.sh audit-01-security audit-05-crawl
#   BASE=http://127.0.0.1:8080 bash tests/browser/run.sh
#
# Every table in the database named in .env is dropped before each run, so never point
# this at a database you care about. It reads the connection out of the app's .env.
set -u
cd "$(dirname "$0")"
APP="$(cd ../.. && pwd)"
mkdir -p tmp/shots

BASE="${BASE:-http://127.0.0.1:8080}"
export BASE

env_val() { sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" "$APP/.env" | head -1 | tr -d "'\"" ; }
DB_NAME="$(env_val 'database\.default\.database')"
DB_USER="$(env_val 'database\.default\.username')"
DB_PASS="$(env_val 'database\.default\.password')"
DB_HOST="$(env_val 'database\.default\.hostname')"

if [ -z "$DB_NAME" ]; then
  echo "Could not read database.default.database from $APP/.env" >&2
  exit 2
fi
echo "Emptying database '$DB_NAME' and reinstalling..."
# Drop every table rather than the database itself: the application's MySQL user is
# normally granted rights on one database only, and CREATE DATABASE is not among them.
mysql -h "${DB_HOST:-localhost}" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" -N -B -e "
  SET FOREIGN_KEY_CHECKS = 0;
  SELECT GROUP_CONCAT(CONCAT('\`', table_name, '\`'))
    FROM information_schema.tables WHERE table_schema = DATABASE();" \
  | while read -r tables; do
      [ -z "$tables" ] || [ "$tables" = "NULL" ] && continue
      mysql -h "${DB_HOST:-localhost}" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" \
        -e "SET FOREIGN_KEY_CHECKS = 0; DROP TABLE IF EXISTS $tables; SET FOREIGN_KEY_CHECKS = 1;"
    done || exit 1
( cd "$APP" && php schema/install.php --seed >/dev/null \
  && rm -rf writable/uploads/* writable/imports/* writable/logs/* writable/throttle/* 2>/dev/null )

fail=0
for t in ${@:-01-workspace 02-contacts 03-deals 04-activities 05-data}; do
  [ -f "$t.mjs" ] || { echo "no such suite: $t"; continue; }
  echo "=== $t"
  node "$t.mjs"
  st=$?
  [ $st -ne 0 ] && fail=1 && echo "!!! $t exited $st"
done
echo "=== done (fail=$fail)"
exit $fail

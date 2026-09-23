#!/usr/bin/env bash
# Deploys SyncCRM when the tracked branch moves. Meant to be run from cron.
#
#   */5 * * * * /home/master/applications/<app>/public_html/deploy/auto-update.sh
#
# It does nothing at all unless the remote branch has a commit the site does not
# have yet, so running it often is cheap. When there is something new it hands
# over to cloudways.sh, which compile-checks the code on this server's PHP and
# rolls back if it does not parse, so a bad commit cannot take the site down.
#
# Everything it does is confined to this application's folder.
set -uo pipefail

WEB="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRANCH="${BRANCH:-$(git -C "$WEB" rev-parse --abbrev-ref HEAD 2>/dev/null || echo main)}"
LOG="$WEB/writable/logs/deploy.log"
mkdir -p "$(dirname "$LOG")"

say() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*" >> "$LOG"; }

# One deploy at a time: a slow run must not overlap the next cron tick.
exec 9>"$WEB/writable/deploy.lock"
if ! flock -n 9; then
  exit 0
fi

if [[ ! -d "$WEB/.git" ]]; then
  say "ERROR: $WEB is not a git checkout; nothing to update."
  exit 1
fi

if ! git -C "$WEB" fetch --depth 1 origin "$BRANCH" >/dev/null 2>&1; then
  say "WARN: could not reach the remote; will try again next run."
  exit 0
fi

LOCAL="$(git -C "$WEB" rev-parse HEAD 2>/dev/null || echo none)"
REMOTE="$(git -C "$WEB" rev-parse FETCH_HEAD 2>/dev/null || echo none)"

# Nothing new: stay quiet so the log stays readable.
if [[ "$LOCAL" == "$REMOTE" || "$REMOTE" == "none" ]]; then
  exit 0
fi

say "New commit on $BRANCH: ${LOCAL:0:7} -> ${REMOTE:0:7}. Deploying."
OUT="$(BRANCH="$BRANCH" "$WEB/deploy/cloudways.sh" update 2>&1)"
STATUS=$?
echo "$OUT" | sed 's/^/    /' >> "$LOG"

if [[ $STATUS -eq 0 ]]; then
  say "Deployed ${REMOTE:0:7}."
else
  say "FAILED (exit $STATUS). The site is still serving the previous version."
fi
exit $STATUS

#!/usr/bin/env bash
# Runs from cron every minute: restores the PM2 process list after a server reboot
# and restarts the app if it is not running. Safe to run repeatedly.
export NVM_DIR="$HOME/.nvm"
# shellcheck disable=SC1090
[[ -s "$NVM_DIR/nvm.sh" ]] && source "$NVM_DIR/nvm.sh" >/dev/null
command -v pm2 >/dev/null || exit 0
if ! pm2 describe synccrm >/dev/null 2>&1; then
  pm2 resurrect >/dev/null 2>&1 || true
fi
if ! pm2 describe synccrm 2>/dev/null | grep -q "online"; then
  pm2 restart synccrm >/dev/null 2>&1 || true
fi

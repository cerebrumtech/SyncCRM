#!/usr/bin/env bash
#
# Downloads the sales hand-off export from Google Drive onto the server and checks
# that what arrived is the file we asked for.
#
#   deploy/fetch-handoff.sh <manifest> [target-dir]
#
# The manifest is three whitespace-separated columns:
#
#   <drive-file-id>   <path under the target dir>   <check>
#
# where <check> is the first line the file must start with (for a CSV), or
# '@xlsx' to check the workbook opens and to list its sheet tabs, or '-' for no
# content check beyond "not empty and not a web page".
#
# The file ids are the access key for a link-shared folder, so the manifest is
# never committed. Keep it outside the web root and delete it afterwards.
#
# Default target: ~/applications/<app>/private_html/SyncCRM-Export -- outside the
# web root, so the sales data is not reachable over HTTP.
set -euo pipefail

MANIFEST="${1:-}"
if [ -z "$MANIFEST" ] || [ ! -f "$MANIFEST" ]; then
  echo "Usage: $0 <manifest> [target-dir]" >&2
  exit 2
fi

TARGET="${2:-}"
if [ -z "$TARGET" ]; then
  APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
  TARGET="$(dirname "$APP_DIR")/private_html/SyncCRM-Export"
fi
mkdir -p "$TARGET"

COOKIES="$(mktemp)"
trap 'rm -f "$COOKIES"' EXIT

# Overridable only so the checks below can be exercised against a local server.
DRIVE_BASE="${DRIVE_BASE:-https://drive.google.com/uc}"

fetch() {
  # Drive serves an interstitial for larger files; the token comes back in a cookie.
  local id="$1" out="$2"
  curl -sSL -c "$COOKIES" -o "$out" \
    "${DRIVE_BASE}?export=download&id=${id}" || return 1
  local token
  token="$(awk '/download_warning|_warning_/ {print $NF}' "$COOKIES" | tail -1)"
  if [ -n "$token" ]; then
    curl -sSL -b "$COOKIES" -o "$out" \
      "${DRIVE_BASE}?export=download&confirm=${token}&id=${id}" || return 1
  fi
}

ok=0; bad=0
while read -r ID REL CHECK; do
  case "$ID" in ''|'#'*) continue ;; esac
  case "$REL" in /*|*..*) echo "REFUSED   $REL -- path escapes the target directory"; bad=$((bad+1)); continue ;; esac

  OUT="$TARGET/$REL"
  mkdir -p "$(dirname "$OUT")"
  if ! fetch "$ID" "$OUT"; then
    echo "FAILED    $REL -- download did not complete"; bad=$((bad+1)); continue
  fi

  SIZE="$(wc -c < "$OUT" | tr -d ' ')"
  if [ "$SIZE" -eq 0 ]; then
    echo "EMPTY     $REL"; bad=$((bad+1)); continue
  fi
  # A sign-in or quota page comes back as HTML with a 200.
  if head -c 512 "$OUT" | grep -qi '<!doctype html\|<html'; then
    echo "NOT SHARED $REL -- Drive returned a web page, not the file."
    echo "           Set the folder to 'Anyone with the link' and run this again."
    bad=$((bad+1)); continue
  fi

  case "$CHECK" in
    '-'|'')
      echo "ok        $REL  ${SIZE} bytes"; ok=$((ok+1)) ;;
    '@xlsx')
      if ! unzip -l "$OUT" >/dev/null 2>&1; then
        echo "WRONG     $REL -- not a readable workbook"; bad=$((bad+1)); continue
      fi
      TABS="$(unzip -p "$OUT" xl/workbook.xml 2>/dev/null \
              | tr '<' '\n' | sed -n 's/^sheet name="\([^"]*\)".*/\1/p')"
      N="$(printf '%s\n' "$TABS" | grep -c . || true)"
      echo "ok        $REL  ${SIZE} bytes  ${N} tabs"
      printf '%s\n' "$TABS" | sed 's/^/             tab: /'
      ok=$((ok+1)) ;;
    *)
      HEAD="$(head -1 "$OUT" | tr -d '\r')"
      # Strip a UTF-8 BOM a spreadsheet round-trip may have added.
      HEAD="${HEAD#$'\xEF\xBB\xBF'}"
      if [ "${HEAD#*$CHECK}" = "$HEAD" ]; then
        echo "WRONG SHAPE $REL -- header does not contain '$CHECK'"
        echo "             got: ${HEAD:0:90}"
        bad=$((bad+1)); continue
      fi
      ROWS="$(( $(wc -l < "$OUT") - 1 ))"
      echo "ok        $REL  ${SIZE} bytes  ${ROWS} rows"; ok=$((ok+1)) ;;
  esac
done < "$MANIFEST"

echo
echo "$ok file(s) verified, $bad problem(s). Target: $TARGET"
if [ "$bad" -gt 0 ]; then
  echo "Do not run the import until every line reads ok." >&2
  exit 1
fi
echo "Next: php schema/import-handoff.php --dir=$TARGET --dry-run"

#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
OUT="$DIST/lousy-outages.zip"
SRC="$ROOT/plugins/lousy-outages"
mkdir -p "$DIST"
rm -f "$OUT"
cd "$ROOT/plugins"
zip -r "$OUT" lousy-outages \
  -x '*/.git/*' '*/tests/*' '*/node_modules/*' '*/.env' '*/.env.*' '*/secrets/*' '*/wp-config.php' '*.bak' '*.backup' '*.tmp' '*/.DS_Store'

LISTING="$(unzip -Z -1 "$OUT")"
if echo "$LISTING" | rg '\\' >/dev/null; then
  echo "ERROR: ZIP contains backslash path separators" >&2
  exit 1
fi
if echo "$LISTING" | rg -v '^lousy-outages/' >/dev/null; then
  echo "ERROR: ZIP contains entries outside lousy-outages/ root" >&2
  exit 1
fi
for req in \
  'lousy-outages/lousy-outages.php' \
  'lousy-outages/includes/ExternalSignals.php' \
  'lousy-outages/public/shortcode.php'; do
  if ! echo "$LISTING" | rg -x "$req" >/dev/null; then
    echo "ERROR: ZIP missing required entry: $req" >&2
    exit 1
  fi
done

echo "Built and validated: $OUT"

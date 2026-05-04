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
  -x '*/.git/*' '*/tests/*' '*/node_modules/*' '*/.env*' '*/secrets/*' '*/wp-config.php' '*/backup*' '*.bak' '*~'
echo "Built: $OUT"

#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="lousy-outages"
VERSION="0.3.0"
DEST="$ROOT/build/lousy-outages-${VERSION}.zip"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$ROOT/build" "$TMP/$PLUGIN_DIR"

while IFS= read -r path; do
  case "$path" in
    "$PLUGIN_DIR/tests/"*) continue ;;
  esac
  mkdir -p "$TMP/$(dirname "$path")"
  cp "$ROOT/$path" "$TMP/$path"
done < <(cd "$ROOT" && git ls-files "$PLUGIN_DIR")

# Normalize mtimes so the archive hash is reproducible across machines.
find "$TMP/$PLUGIN_DIR" -exec touch -t 202607200000.00 {} +
rm -f "$DEST"
(
  cd "$TMP"
  find "$PLUGIN_DIR" -type f | LC_ALL=C sort | zip -X -q "$DEST" -@
)

sha256sum "$DEST"

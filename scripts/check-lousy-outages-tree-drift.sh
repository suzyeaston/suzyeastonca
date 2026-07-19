#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CANON="$ROOT/plugins/lousy-outages"
ALT="$ROOT/lousy-outages"
BUILD="$ROOT/scripts/build-lousy-outages-plugin.sh"
if ! rg -n 'SRC="\$ROOT/plugins/lousy-outages"' "$BUILD" >/dev/null; then
  echo "ERROR: build script is not using canonical plugins/lousy-outages/." >&2
  exit 1
fi
if [[ ! -d "$CANON" ]]; then
  echo "ERROR: canonical plugin tree missing: $CANON" >&2
  exit 1
fi
if [[ ! -d "$ALT" ]]; then
  echo "OK: canonical plugin tree present and no alternate tree found"
  exit 0
fi
KEYS=(
  "includes/Api.php"
  "includes/ExternalSignals.php"
  "includes/SignalCollector.php"
  "includes/Sources/SourcePack.php"
  "includes/Sources/SourceBudgetManager.php"
  "includes/Sources/ProviderFeedSource.php"
  "includes/Sources/HackerNewsChatterSource.php"
  "includes/Summary.php"
  "public/shortcode.php"
  "assets/lousy-outages.js"
  "lousy-outages.php"
)
drift=0
for f in "${KEYS[@]}"; do
  if ! cmp -s "$CANON/$f" "$ALT/$f"; then
    echo "ERROR: alternate lousy-outages/$f differs from canonical plugins/lousy-outages/$f." >&2
    drift=1
  fi
done
if [[ $drift -ne 0 ]]; then
  exit 1
fi
echo "OK: plugin trees match for deploy-critical files; canonical source is plugins/lousy-outages/."

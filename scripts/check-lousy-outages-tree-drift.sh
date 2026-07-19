#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CANON="$ROOT/lousy-outages"
ALT="$ROOT/plugins/lousy-outages"
BUILD="$ROOT/scripts/build-lousy-outages-plugin.sh"
if ! rg -n 'SRC="\$ROOT/lousy-outages"|cd "\$ROOT"' "$BUILD" >/dev/null; then
  echo "ERROR: build script is not using canonical top-level lousy-outages/." >&2
  exit 1
fi
if [[ ! -d "$CANON" ]]; then
  echo "ERROR: canonical plugin tree missing: $CANON" >&2
  exit 1
fi
if [[ ! -d "$ALT" ]]; then
  echo "OK: canonical plugin tree present and no deprecated alternate tree found"
  exit 0
fi
KEYS=(
  "includes/Api.php"
  "includes/SignalCollector.php"
  "includes/Sources/SourcePack.php"
  "includes/Sources/SourceBudgetManager.php"
  "includes/Sources/ProviderFeedSource.php"
  "includes/Sources/HackerNewsChatterSource.php"
  "lousy-outages.php"
)
drift=0
for f in "${KEYS[@]}"; do
  if ! cmp -s "$CANON/$f" "$ALT/$f"; then
    echo "WARNING: deprecated plugins/lousy-outages/$f differs from canonical lousy-outages/$f and is ignored by deployment."
    drift=1
  fi
done
if [[ $drift -ne 0 ]]; then
  echo "OK: canonical deploy path is top-level lousy-outages/; deprecated plugins/lousy-outages/ is excluded from the ZIP workflow."
else
  echo "OK: plugin trees match and deployment uses canonical top-level lousy-outages/."
fi

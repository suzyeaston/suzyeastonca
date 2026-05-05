#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CANON="$ROOT/lousy-outages"
ALT="$ROOT/plugins/lousy-outages"
if [[ ! -d "$CANON" || ! -d "$ALT" ]]; then
  echo "OK: one plugin tree present or alternate missing"; exit 0
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
status=0
for f in "${KEYS[@]}"; do
  if ! cmp -s "$CANON/$f" "$ALT/$f"; then echo "DRIFT: $f differs between canonical and deprecated tree"; status=1; fi
done
if [[ $status -ne 0 ]]; then
  echo "Canonical deploy path is top-level lousy-outages/. Do not deploy plugins/lousy-outages/." >&2
fi
exit $status

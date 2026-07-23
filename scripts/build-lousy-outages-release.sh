#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/lousy-outages"
DIST="$ROOT/dist"
ZIP="$DIST/lousy-outages.zip"
VERSION="0.4.4"
PLUGIN_HEADER="Plugin Name: Lousy"$' '"Outages"
rm -rf "$DIST/.lousy-outages-build" "$ZIP" "$ZIP.sha256" "$DIST/release-manifest.json"
mkdir -p "$DIST/.lousy-outages-build/lousy-outages" "$DIST"
rsync -a --delete \
  --exclude 'tests/' --exclude 'scripts/' --exclude 'node_modules/' --exclude '.git/' --exclude '.github/' \
  --exclude '.ea-php-cli.cache' --exclude '*recovery*' --exclude '*diagnostic*' \
  --exclude 'screenshots/' --exclude '*.local.*' --exclude '.env*' \
  "$SRC/" "$DIST/.lousy-outages-build/lousy-outages/"
( cd "$DIST/.lousy-outages-build" && find lousy-outages -type f -print | LC_ALL=C sort | zip -X -q "$ZIP" -@ )
mapfile -t entries < <(zipinfo -1 "$ZIP")
printf '%s\n' "${entries[@]}" | grep -qx 'lousy-outages/lousy-outages.php'
! printf '%s\n' "${entries[@]}" | grep -qx 'lousy-outages/lousy-outages/lousy-outages.php'
! printf '%s\n' "${entries[@]}" | grep -q '^lousy-outages-[0-9]'
count=0; tmp="$DIST/.lousy-outages-build/check"; rm -rf "$tmp"; mkdir -p "$tmp"; unzip -q "$ZIP" -d "$tmp"
while IFS= read -r -d '' f; do grep -q "$PLUGIN_HEADER" "$f" && count=$((count+1)) || true; done < <(find "$tmp" -type f -print0)
[ "$count" -eq 1 ]
for bad in '.ea-php-cli.cache' 'tests/' 'recovery' 'diagnostic' '\\'; do ! printf '%s\n' "${entries[@]}" | grep -qiF "$bad"; done
grep -Eq '^ \* Version: '"$VERSION"'$' "$tmp/lousy-outages/lousy-outages.php"
grep -Eq "define\( 'LOUSY_OUTAGES_VERSION', '$VERSION' \);" "$tmp/lousy-outages/lousy-outages.php"
sha256sum "$ZIP" | awk '{print $1"  lousy-outages.zip"}' > "$ZIP.sha256"
sha=$(awk '{print $1}' "$ZIP.sha256")
commit=$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo unknown)
ts=$(date -u +%Y-%m-%dT%H:%M:%SZ)
python3 - "$DIST/release-manifest.json" "$commit" "$VERSION" "$sha" "$ts" <<'PY'
import json,sys,subprocess
out,commit,version,sha,ts=sys.argv[1:]
entries=subprocess.check_output(['zipinfo','-1','dist/lousy-outages.zip'],text=True).splitlines()
json.dump({'git_commit_sha':commit,'plugin_version':version,'zip_sha256':sha,'build_timestamp':ts,'canonical_source_path':'lousy-outages/','files':entries},open(out,'w'),indent=2)
PY
rm -rf "$DIST/.lousy-outages-build"
echo "Built $ZIP"

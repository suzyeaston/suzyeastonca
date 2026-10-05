const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");

const ROOT = path.resolve(__dirname, "..");

test("homepage YVR radar deck uses compact sizing tokens", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "assets", "css", "home-yvr-radar-deck.css"),
    "utf8"
  );
  assert.match(source, /--yvr-radar-ring-max-h:\s*min\(34vh,\s*300px\)/);
  assert.match(source, /--yvr-radar-unit-max:\s*min\(100%,\s*320px\)/);
  assert.match(source, /--yvr-radar-shell-max:\s*min\(980px/);
});

test("homepage YVR radar deck uses side-by-side layout on desktop", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "assets", "css", "home-yvr-radar-deck.css"),
    "utf8"
  );
  assert.match(source, /@media \(min-width: 900px\)/);
  assert.match(source, /grid-template-columns:\s*minmax\(220px,\s*320px\)\s*minmax\(0,\s*1fr\)/);
  assert.match(source, /\.home-yvr-radar-deck__radar-unit[\s\S]*grid-column:\s*1/);
  assert.match(source, /\.home-yvr-radar-deck__deck[\s\S]*grid-column:\s*2/);
});

test("homepage first load does not live-resolve radar audio or bust the outage teaser", () => {
  const config = fs.readFileSync(path.join(ROOT, "inc/home-yvr-broadcaster.php"), "utf8");
  const feeds = fs.readFileSync(path.join(ROOT, "inc/home-yvr-broadcaster.php"), "utf8");
  const audio = fs.readFileSync(path.join(ROOT, "inc/home-yvr-audio-channels.php"), "utf8");
  const teaser = fs.readFileSync(path.join(ROOT, "assets/js/lousy-outages-teaser.js"), "utf8");
  const summary = fs.readFileSync(path.join(ROOT, "lousy-outages/includes/Api.php"), "utf8");
  assert.match(config, /se_broadcaster_audio_channels_for_client\(\s*false\s*\)/);
  assert.match(feeds, /se_yvr_broadcaster_feeds_v1/);
  assert.match(feeds, /se_broadcaster_feed_budget_start\(\s*5\s*\)/);
  assert.doesNotMatch(feeds, /'timeout'\s*=>\s*15/);
  assert.match(audio, /if \( is_string\( \$cached \) && \$cached !== '' \) \{\s*return \$cached;/);
  assert.doesNotMatch(teaser, /_lo_cache_bust/);
  assert.match(summary, /'teaser' === \$view/);
  assert.match(summary, /public, max-age=60/);
});

test("homepage radar uses free OpenFreeMap tiles, not a keyed basemap", () => {
  const source = fs.readFileSync(path.join(ROOT, "js", "home-hero-map.js"), "utf8");
  assert.match(source, /tiles\.openfreemap\.org\/styles\/dark/);
  assert.doesNotMatch(source, /basemaps\.cartocdn\.com/);
});

test("radar listening posts use real Salish Sea coordinates", () => {
  const php = fs.readFileSync(path.join(ROOT, "inc", "home-yvr-broadcaster.php"), "utf8");
  const audio = fs.readFileSync(path.join(ROOT, "inc", "home-yvr-audio-channels.php"), "utf8");
  assert.match(php, /function se_broadcaster_radar_sites/);
  assert.match(php, /Bush Point, Whidbey Island/);
  assert.match(php, /'lat'\s*=>\s*48\.0337/);
  assert.match(audio, /'map_lat'\s*=>\s*48\.0337/);
  assert.doesNotMatch(audio, /49\.0337/);
});

test("homepage introduces the dedicated radio app", () => {
  const page = fs.readFileSync(path.join(ROOT, "page-home.php"), "utf8");
  const feature = fs.readFileSync(path.join(ROOT, "parts/home-radio-feature.php"), "utf8");
  assert.match(page, /parts\/home-radio-feature/);
  assert.doesNotMatch(page, /data-yvr-broadcaster/);
  assert.match(feature, /home_url\(\s*'\/radio\/'\s*\)/);
});

test("theme deploy manifest includes compact radar deck CSS", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "scripts", "theme_deploy_manifest.py"),
    "utf8"
  );
  assert.match(source, /"assets\/css\/home-yvr-radar-deck\.css"/);
});

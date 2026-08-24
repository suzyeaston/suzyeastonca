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

test("theme deploy manifest includes compact radar deck CSS", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "scripts", "theme_deploy_manifest.py"),
    "utf8"
  );
  assert.match(source, /"assets\/css\/home-yvr-radar-deck\.css"/);
});

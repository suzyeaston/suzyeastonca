const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");

const ROOT = path.resolve(__dirname, "..");

test("homepage puts the appliance spotlight across the radar deck, not in the radar lane", () => {
  const home = fs.readFileSync(path.join(ROOT, "page-home.php"), "utf8");
  const part = fs.readFileSync(path.join(ROOT, "parts/home-appliance-feature.php"), "utf8");
  const radar = fs.readFileSync(path.join(ROOT, "assets/css/home-yvr-radar-deck.css"), "utf8");
  const styles = fs.readFileSync(path.join(ROOT, "assets/css/home-feature-previews.css"), "utf8");

  const pitchAt = home.indexOf("parts/home-hero-pitch");
  const applianceAt = home.indexOf("parts/home-appliance-feature");
  const stripAt = home.indexOf("parts/home-commercial-strip");
  assert.ok(pitchAt !== -1 && applianceAt > pitchAt && stripAt > applianceAt, "spotlight sits under the hero pitch");

  assert.match(part, /class="home-appliance"/);
  assert.match(part, /class="home-appliance__layout"/);
  assert.match(part, /APPLIANCE<br>LATENT SPACE/);
  assert.match(part, /Write a loop\. Bend the sound\. Watch it burn\./);

  assert.match(
    radar,
    /\.home-yvr-radar-deck > \.home-appliance,[\s\S]*grid-column:\s*1\s*\/\s*-1/
  );
  assert.match(styles, /\.home-yvr-radar-deck > \.home-appliance\s*\{[^}]*grid-column:\s*1\s*\/\s*-1/);
  assert.match(styles, /container-type:\s*inline-size/);
  assert.match(styles, /@container appliance \(max-width:\s*680px\)/);
  assert.match(styles, /@media \(max-width:\s*700px\)/);
  assert.doesNotMatch(styles, /overflow-wrap:\s*anywhere/);
  assert.match(styles, /#homepage-content \.home-appliance :is\(h2, p, a, span\)[\s\S]*overflow-wrap:\s*normal/);
});

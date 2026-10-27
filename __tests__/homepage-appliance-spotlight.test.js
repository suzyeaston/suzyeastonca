const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");

const ROOT = path.resolve(__dirname, "..");

test("homepage spotlights Meanwhile instead of the Appliance app", () => {
  const home = fs.readFileSync(path.join(ROOT, "page-home.php"), "utf8");
  const appliance = fs.readFileSync(path.join(ROOT, "page-appliance-latent-space.php"), "utf8");
  const blogStyles = fs.readFileSync(path.join(ROOT, "assets/css/blog.css"), "utf8");
  const pitchAt = home.indexOf("parts/home-hero-pitch");
  const meanwhileAt = home.indexOf("parts/home', 'mission-meanwhile");
  const stripAt = home.indexOf("parts/home-commercial-strip");

  assert.ok(pitchAt !== -1 && meanwhileAt > pitchAt && stripAt > meanwhileAt,
    "latest writing sits just below the hero pitch");
  assert.doesNotMatch(home, /get_template_part\\(\\s*'parts\\/home-appliance-feature'/,
    "Appliance feature is not called by homepage");
  assert.equal((home.match(/parts\\/home', 'mission-meanwhile/g) || []).length, 1,
    "Meanwhile appears once, as the spotlight");
  assert.match(appliance, /appliance/i, "the standalone Appliance page is retained");
  assert.match(blogStyles, /\\.home-yvr-radar-deck > \\.home-mission-card--meanwhile-spotlight/,
    "spotlight has its own responsive styling");
});

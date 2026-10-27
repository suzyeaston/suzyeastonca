const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");

const ROOT = path.resolve(__dirname, "..");

test("homepage promotes Meanwhile ahead of other previews, not as a duplicate project card", () => {
  const source = fs.readFileSync(path.join(ROOT, "page-home.php"), "utf8");
  assert.match(source, /get_template_part\\(\\s*'parts\\/home',\\s*'mission-meanwhile'\\s*\\)/);
  assert.ok(source.indexOf("mission-meanwhile") < source.indexOf("home-feature-previews"));
  assert.doesNotMatch(source, /get_template_part\\(\\s*'parts\\/home-signal-log'\\s*\\)/);
});

test("Meanwhile spotlights the live post with title, excerpt and direct reading link", () => {
  const source = fs.readFileSync(path.join(ROOT, "parts", "home-mission-meanwhile.php"), "utf8");
  assert.match(source, /se_blog_home_mission_data/);
  assert.match(source, /home-mission-card--meanwhile-spotlight/);
  assert.match(source, /NO CARRIER/);
  assert.match(source, /LATEST ENTRY/);
  assert.match(source, /post_excerpt/);
  assert.match(source, /Read the latest note/);
  assert.match(source, /All field notes/);
  assert.match(source, /aria-live="polite"/);
});

test("blog helper exposes homepage latest published post", () => {
  const source = fs.readFileSync(path.join(ROOT, "inc", "blog.php"), "utf8");
  assert.match(source, /function se_blog_home_mission_data/);
  assert.match(source, /post_status'\\s*=>\\s*'publish'/);
  assert.match(source, /NOW TRANSMITTING/);
  assert.match(source, /STANDBY/);
});

test("spotlight styles include mobile and reduced-motion support", () => {
  const source = fs.readFileSync(path.join(ROOT, "assets", "css", "blog.css"), "utf8");
  assert.match(source, /\\.home-yvr-radar-deck > \\.home-mission-card--meanwhile-spotlight/);
  assert.match(source, /max-width: 700px/);
  assert.match(source, /prefers-reduced-motion/);
});

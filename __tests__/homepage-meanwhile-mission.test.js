const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");

const ROOT = path.resolve(__dirname, "..");

test("homepage includes Meanwhile mission card but not the full posts feed", () => {
  const source = fs.readFileSync(path.join(ROOT, "page-home.php"), "utf8");
  assert.match(source, /get_template_part\(\s*'parts\/home',\s*'mission-meanwhile'\s*\)/);
  assert.doesNotMatch(source, /get_template_part\(\s*'parts\/home-signal-log'\s*\)/);
});

test("Meanwhile mission card part handles empty and live states", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "parts", "home-mission-meanwhile.php"),
    "utf8"
  );
  assert.match(source, /se_blog_home_mission_data/);
  assert.match(source, /NO CARRIER/);
  assert.match(source, /LATEST ENTRY/);
  assert.match(source, /Open channel/);
  assert.match(source, /aria-live="polite"/);
  assert.doesNotMatch(source, /post_excerpt/);
});

test("blog helper exposes homepage mission payload", () => {
  const source = fs.readFileSync(path.join(ROOT, "inc", "blog.php"), "utf8");
  assert.match(source, /function se_blog_home_mission_data/);
  assert.match(source, /NOW TRANSMITTING/);
  assert.match(source, /STANDBY/);
});

test("mission card styles live in blog.css", () => {
  const source = fs.readFileSync(path.join(ROOT, "assets", "css", "blog.css"), "utf8");
  assert.match(source, /\.home-mission-card--meanwhile/);
  assert.match(source, /prefers-reduced-motion/);
});

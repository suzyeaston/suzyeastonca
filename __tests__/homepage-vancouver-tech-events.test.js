const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");

const ROOT = path.resolve(__dirname, "..");

test("homepage surfaces Vancouver tech events teaser and mission card", () => {
  const source = fs.readFileSync(path.join(ROOT, "page-home.php"), "utf8");
  assert.match(source, /get_template_part\(\s*'parts\/home-vancouver-tech-events'\s*\)/);
  assert.match(source, /\/vancouver-tech-events\//);
  assert.match(source, /Vancouver Tech Events/);
});

test("homepage teaser part renders upcoming list and full-calendar CTA", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "parts", "home-vancouver-tech-events.php"),
    "utf8"
  );
  assert.match(source, /suzy_get_vancouver_tech_events/);
  assert.match(source, /vancouver-tech-home/);
  assert.match(source, /Open full calendar/);
  assert.match(source, /\/vancouver-tech-events\//);
});

test("projects page lists Vancouver tech events", () => {
  const source = fs.readFileSync(path.join(ROOT, "page-projects.php"), "utf8");
  assert.match(source, /Vancouver Tech Events/);
  assert.match(source, /vancouver-tech-events/);
});

test("events page template still renders the feed", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "page-vancouver-tech-events.php"),
    "utf8"
  );
  assert.match(source, /suzy_render_vancouver_tech_events_html/);
});

test("theme deploy manifest includes events page and homepage teaser", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "scripts", "theme_deploy_manifest.py"),
    "utf8"
  );
  assert.match(source, /"page-vancouver-tech-events\.php"/);
  assert.match(source, /"page-projects\.php"/);
  assert.match(source, /"parts\/home-vancouver-tech-events\.php"/);
});

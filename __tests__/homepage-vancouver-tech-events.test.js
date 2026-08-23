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
  assert.match(source, /Founding member, BC \+ AI/);
  assert.match(source, /Vancouver Tech Journal/);
  assert.match(source, /vancouver_events_member/);
  assert.doesNotMatch(source, /above the radar/);
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

test("vancouver tech events includes BC + AI and VTJ Luma calendars", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "inc", "vancouver-tech-events.php"),
    "utf8"
  );
  assert.match(source, /luma_bc_ai/);
  assert.match(source, /vancouver-ai/);
  assert.match(source, /cal-9QLMVT9CQVtX1u0/);
  assert.match(source, /luma_vtj/);
  assert.match(source, /vantechjournal/);
  assert.match(source, /cal-i2SXCQcJZBMq8NN/);
  assert.match(source, /luma_calendar/);
  assert.match(source, /suzy_fetch_vancouver_tech_events_from_luma_calendar/);
  assert.match(source, /api\.lu\.ma\/calendar\/get-items/);
});

test("vancouver tech events highlights member calendars without ranking them", () => {
  const php = fs.readFileSync(
    path.join(ROOT, "inc", "vancouver-tech-events.php"),
    "utf8"
  );
  const css = fs.readFileSync(path.join(ROOT, "style.css"), "utf8");
  assert.match(php, /Founding member, BC \+ AI/);
  assert.match(php, /member calendars/);
  assert.match(php, /vte-member/);
  assert.match(php, /'member'\s*=>\s*true/);
  assert.match(css, /\.vte-event--member/);
  assert.match(css, /\.vte-member-badge/);
  assert.doesNotMatch(php, /above the radar/);
  assert.doesNotMatch(css, /vte-radar/);
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

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const test = require("node:test");

const ROOT = path.resolve(__dirname, "..");

test("homepage surfaces Vancouver tech events teaser above Beulah radar", () => {
  const source = fs.readFileSync(path.join(ROOT, "page-home.php"), "utf8");
  assert.match(source, /get_template_part\(\s*'parts\/home-vancouver-tech-events'\s*\)/);
  assert.match(source, /\/vancouver-tech-events\//);
  assert.match(source, /Vancouver Tech Events/);

  const teaserAt = source.indexOf("parts/home-vancouver-tech-events");
  const radarAt = source.indexOf("home-yvr-radar-deck__radar-unit");
  const beulahAt = source.indexOf("BEULAH");
  assert.ok(teaserAt > -1 && radarAt > -1 && beulahAt > -1);
  assert.ok(teaserAt < radarAt, "events teaser should sit above the radar unit");
  assert.ok(teaserAt < beulahAt, "events teaser should sit above Beulah");
  assert.equal(
    (source.match(/parts\/home-vancouver-tech-events/g) || []).length,
    1,
    "teaser should only render once"
  );
});

test("homepage teaser highlights Futureproof then chronological events", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "parts", "home-vancouver-tech-events.php"),
    "utf8"
  );
  assert.match(source, /suzy_get_vancouver_tech_events/);
  assert.match(source, /vancouver-tech-home/);
  assert.match(source, /Open full calendar/);
  assert.match(source, /\/vancouver-tech-events\//);
  assert.match(source, /Futureproof Festival/);
  assert.match(source, /chronological/);
  assert.match(source, /vancouver_events_spotlight/);
  assert.doesNotMatch(source, /[Mm]ember/);
  assert.doesNotMatch(source, /Founding/);
  assert.doesNotMatch(source, /vancouver_events_member/);
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
  assert.match(source, /futureproof-festival/);
  assert.match(source, /suzy_get_vancouver_tech_spotlight_events/);
});

test("vancouver tech events spotlights Futureproof without membership bias", () => {
  const php = fs.readFileSync(
    path.join(ROOT, "inc", "vancouver-tech-events.php"),
    "utf8"
  );
  const css = fs.readFileSync(path.join(ROOT, "style.css"), "utf8");
  assert.match(php, /Futureproof Festival/);
  assert.match(php, /vte-spotlight/);
  assert.match(php, /No ranking/);
  assert.match(css, /\.vte-event--spotlight/);
  assert.match(css, /\.vte-spotlight-badge/);
  assert.doesNotMatch(php, /[Mm]ember, BC/);
  assert.doesNotMatch(php, /I.?m a member/);
  assert.doesNotMatch(php, /Founding member/);
  assert.doesNotMatch(php, /'member'\s*=>\s*true/);
  assert.doesNotMatch(php, /vte-member/);
  assert.doesNotMatch(css, /\.vte-member/);
  assert.doesNotMatch(css, /\.vte-event--member/);
});

test("theme deploy manifest includes events page and homepage teaser", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "scripts", "theme_deploy_manifest.py"),
    "utf8"
  );
  assert.match(source, /"page-vancouver-tech-events\.php"/);
  assert.match(source, /"page-projects\.php"/);
  assert.match(source, /"parts\/home-vancouver-tech-events\.php"/);
  assert.match(source, /"page-home\.php"/);
});

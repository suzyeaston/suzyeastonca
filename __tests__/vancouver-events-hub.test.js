const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const root = path.resolve(__dirname, "..");
const read = (p) => fs.readFileSync(path.join(root, p), "utf8");

test("unified calendar uses both existing data owners, not a third scraper", () => {
  const src = read("inc/vancouver-events.php");
  assert.match(src, /suzy_get_vancouver_tech_events\(\)/);
  assert.match(src, /suzy_vmc_events\( \$now \)/);
  assert.match(src, /suzy_vte_archive_listings\( \$now \)/);
  assert.match(src, /suzy_vmc_history\( \$now \)/);
  assert.match(src, /function suzy_yvr_events_normalize/);
  assert.match(src, /function suzy_yvr_events_merge/);
  assert.match(src, /function suzy_yvr_events_filter/);
  assert.match(src, /array_unique/);
  assert.doesNotMatch(src, /wp_remote_get|curl_exec|file_get_contents\s*\(/);
  assert.doesNotMatch(src, /update_option|wp_schedule_event|set_transient/);
});

test("hub is deployed and routed independently of the focused pages", () => {
  const fn = read("functions.php");
  const manifest = read("scripts/theme_deploy_manifest.py");
  const src = read("inc/vancouver-events.php");
  const seo = read("inc/seo.php");
  assert.match(fn, /require_once get_template_directory\(\) \. '\/inc\/vancouver-events\.php';/);
  for (const p of ["inc/vancouver-events.php", "page-vancouver-events.php", "assets/css/vancouver-events.css"]) {
    assert.ok(manifest.includes('"' + p + '"'), p + " must deploy");
  }
  assert.match(src, /return \$path === 'vancouver-events';/);
  assert.match(src, /pre_handle_404/);
  assert.match(src, /redirect_canonical/);
  assert.match(src, /template_include/);
  assert.match(src, /wp_enqueue_style\( 'suzy-vancouver-events'/);
  assert.match(seo, /Vancouver Events \| Tech, Music, Art & Culture/);
  assert.match(seo, /home_url\( '\/vancouver-events\/' \)/);
  assert.match(read("page-vancouver-tech-events.php"), /suzy_render_vancouver_tech_events_html/);
  assert.match(read("page-vancouver-music-culture.php"), /suzy_vmc_history\(\)/);
});

test("one accessible page supports browsing scenes, topics, dates, search and archive", () => {
  const page = read("page-vancouver-events.php");
  assert.match(page, /<h1>Vancouver Events<\/h1>/);
  assert.match(page, /aria-label="Browse upcoming or historical events"/);
  assert.match(page, /id="events"/);
  assert.match(page, /name="yve_view"/);
  for (const f of ["yve_track", "yve_topic", "yve_q", "yve_period", "yve_year", "yve_month"]) {
    assert.ok(page.includes('name="' + f + '"'), f + " filter exists");
  }
  assert.match(page, /<form action="/);
  assert.match(page, /method="get"/);
  assert.match(page, /class="yve__event-list"/);
  assert.match(page, /id="yve-lineup"/);
  assert.match(page, /id="yve-lineup-count"/);
  assert.match(page, /data-yve-save/);
  assert.match(page, /data-yve-event=/);
  assert.match(page, /data-yve-export="ics"/);
  assert.match(page, /data-yve-export="csv"/);
  assert.match(page, /esc_attr\( wp_json_encode/);

  assert.match(page, /role="status"/);
  assert.match(page, /aria-label="Event results pages"/);
  assert.match(page, /yve__histogram/);
  assert.match(page, /incomplete coverage|incomplete|missing month means/i);
  assert.match(page, /time_note/);
  assert.match(page, /esc_html\( \$event\['title'\] \)/);
  assert.match(page, /esc_url\( \$event\['url'\] \)/);
  assert.match(page, /esc_attr\( \$filters\['q'\] \)/);
  assert.match(page, /target="_blank" rel="noopener noreferrer"/);
  assert.doesNotMatch(page, /wp_remote_get\(/);
});

test("homepage and Projects navigate to one hub without removing older source pages", () => {
  const tech = read("parts/home-vancouver-tech-events.php");
  const music = read("parts/home-vancouver-music-culture.php");
  const home = read("page-home.php");
  const projects = read("page-projects.php");
  const legacyTech = read("inc/vancouver-tech-events.php");
  const legacyCulture = read("page-vancouver-music-culture.php");
  assert.match(tech, /home_url\( '\/vancouver-events\/\?yve_track=tech' \)/);
  assert.match(music, /home_url\( '\/vancouver-events\/\?yve_track=culture' \)/);
  assert.match(home, /Vancouver Events/);
  assert.match(home, /home_url\( '\/vancouver-events\/' \)/);
  assert.match(projects, /Vancouver Events \/ Unified Calendar/);
  assert.match(projects, /Seven configured automated source adapters/);
  assert.match(legacyTech, /Browse all Vancouver events/);
  assert.match(legacyCulture, /All Vancouver events/);
  assert.match(tech, /\/vancouver-tech-events\//);
  assert.match(music, /\/vancouver-music-culture\//);
});

test("filters use namespaced query keys instead of reserved WordPress query vars", () => {
  const src = read("inc/vancouver-events.php");
  const page = read("page-vancouver-events.php");
  for (const key of ["view", "track", "topic", "year", "month", "period", "q", "page"]) {
    assert.ok(src.includes("'yve_" + key + "'"), "collector reads namespaced " + key);
    assert.ok(page.includes("yve_" + key), "page links use namespaced " + key);
  }
  assert.match(src, /max\( 1, min\( 500,/);
  assert.match(src, /preg_match\( '\/\^20\\d\{2\}\$\/'/);
  assert.match(src, /strlen|substr/);
  assert.match(src, /return \$path === 'vancouver-events';/);
});

test("responsive styling is isolated to the hub, not the two original calendars", () => {
  const css = read("assets/css/vancouver-events.css");
  assert.match(css, /\.yve__event-list/);
  assert.match(css, /\.yve__tag--tech/);
  assert.match(css, /\.yve__tag--culture/);
  assert.match(css, /\.yve__filter-panel/);
  assert.match(css, /@media \(max-width: 620px\)/);
  assert.match(css, /:focus-visible/);
  assert.doesNotMatch(read("assets/css/vancouver-music-culture.css"), /\.yve__event/);
});

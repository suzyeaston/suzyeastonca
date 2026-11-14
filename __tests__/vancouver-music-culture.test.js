const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const root = path.resolve(__dirname, "..");
const read = (file) => fs.readFileSync(path.join(root, file), "utf8");

test("new culture page is bootstrapped and included in theme deploy manifest", () => {
  const fn = read("functions.php");
  const manifest = read("scripts/theme_deploy_manifest.py");
  assert.match(fn, /require_once get_template_directory\(\) \. '\/inc\/vancouver-music-culture\.php'/);
  for (const file of [
    "inc/vancouver-music-culture.php", "inc/vancouver-music-culture-source-parsers.php", "page-vancouver-music-culture.php",
    "assets/css/vancouver-music-culture.css", "page-projects.php",
    "parts/home-vancouver-tech-events.php", "parts/home-vancouver-music-culture.php", "inc/seo.php"
  ]) {
    assert.ok(manifest.includes('"' + file + '"'), file + " must deploy");
  }
});

test("page has upcoming, archive, source directory, safe links, and filters", () => {
  const template = read("page-vancouver-music-culture.php");
  for (const text of [
    "Music, Culture &amp; Curiosity", 'id="upcoming"', 'id="history"',
    'id="sources"', "vmc_category", "vmc_year", "vmc_page",
    "suzy_vmc_events()", "suzy_vmc_history()",
    "suzy_vmc_directory()", "get_header()", "get_footer()"
  ]) assert.ok(template.includes(text), text + " must be in template");
  assert.match(template, /esc_url\( \$event\['url'\] \)/);
  assert.match(template, /esc_html\( \$event\['title'\] \)/);
  assert.match(template, /not proof the event took place/);
  assert.match(template, /Months with no captured records are/);
  assert.match(template, /current_user_can\( 'manage_options' \)/);
  assert.match(template, /suzy_vmc_directory_groups\(\)/);
  assert.match(template, /vmc__source-group/);
  assert.match(template, /browsable venue and organizer links, not guaranteed automatic event feeds/);

  assert.match(template, /esc_html\( \$event\['time_note'\] \)/);
  assert.doesNotMatch(template, /wp_remote_get\(/);
});

test("collectors cover different domains, bound load, and don't silently fabricate history", () => {
  const src = read("inc/vancouver-music-culture.php");
  assert.match(src, /www\.8east\.ca\/wp-json\/tribe\/events\/v1\/events/);
  assert.match(src, /www\.roundhouse\.ca\/wp-json\/tribe\/events\/v1\/events/);
  assert.match(src, /roadrunner-red-yde9\.squarespace\.com\/new-events\?format=json/);
  assert.match(src, /luma\.com\/discover\/vancouver/);
  assert.match(src, /redgate\.tv\/tickets\//);
  assert.match(src, /upstartandcrow\.com\/wp-json\/tribe\/events\/v1\/events/);
  assert.match(src, /rhythmchanges\.ca\/gig-list-vancouver\//);
  assert.match(src, /8east\.ca\/events\/\?ical=1/);
  assert.match(src, /fallback_endpoint/);
  assert.match(src, /suzy_vmc_parse_redgate_html/);
  assert.match(src, /suzy_vmc_parse_jazz_html/);
  assert.match(src, /suzy_vmc_parse_ical/);
  assert.match(src, /last good data \(max 24h\)/);
  assert.match(src, /'time_note' => substr/);
  assert.match(src, /require_once __DIR__ \. '\/vancouver-music-culture-source-parsers\.php'/);
  assert.match(src, /function suzy_vmc_parse_tribe/);
  assert.match(src, /function suzy_vmc_parse_squarespace/);
  assert.match(src, /function suzy_vmc_dedupe/);
  assert.match(src, /update_option\( 'suzy_vmc_archive_v1', \$merged, false \)/);
  assert.match(src, /wp_schedule_event\( time\(\) \+ 60, 'hourly'/);
  assert.match(src, /empty or blocked|HTTP /);
  assert.match(src, /start > time\(\) \+ 730 \* DAY_IN_SECONDS/);
  assert.match(src, /strtolower/);
  assert.match(src, /function suzy_vmc_is_route/);
  assert.match(src, /add_filter\( 'pre_handle_404'/);
  assert.match(src, /add_filter\( 'template_include'/);
  assert.match(src, /'vancouver-music-culture'/);
});

test("projects and homepage offer discoverability, SEO has dedicated canonical", () => {
  // The Projects page now renders escaped card data from a PHP array.
  assert.match(read("page-projects.php"), /Vancouver Music (?:&amp;|&) Culture/);
  assert.match(read("page-projects.php"), /project_url\(\s*'vancouver-music-culture'\s*\)/);
  assert.match(read("page-home.php"), /parts\/home-vancouver-music-culture/);
  assert.match(read("parts/home-vancouver-music-culture.php"), /\/vancouver-music-culture\//);
  const seo = read("inc/seo.php");
  assert.match(seo, /Vancouver Music & Culture Events/);
  assert.match(seo, /home_url\( '\/vancouver-music-culture\/' \)/);
  assert.match(seo, /suzy_vmc_is_route/);
});

test("responsive culture styling is isolated from tech calendar layout", () => {
  const css = read("assets/css/vancouver-music-culture.css");
  assert.match(css, /\.vmc__events/);
  assert.match(css, /\.vmc__months/);
  assert.match(css, /\.vmc__directory-groups/);
  assert.match(css, /@media \(max-width: 640px\)/);
  assert.match(css, /:focus-visible/);
  assert.doesNotMatch(read("style.css"), /vmc__event/);
  assert.match(read("inc/vancouver-music-culture.php"), /wp_enqueue_style\( 'suzy-vancouver-music-culture'/);
});

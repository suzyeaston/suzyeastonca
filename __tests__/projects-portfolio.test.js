const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

const root = path.resolve(__dirname, "..");
const read = (file) => fs.readFileSync(path.join(root, file), "utf8");
const page = read("page-projects.php");

test("2026 portfolio has a single accessible hero and five navigable collections", () => {
  assert.equal((page.match(/<h1\b/g) || []).length, 1);
  assert.match(page, /class="projects-hero__title">Projects<\/h1>/);
  for (const id of [
    "featured-projects", "vancouver-projects", "music-ai-projects",
    "systems-projects", "experiments-projects"
  ]) {
    assert.ok(page.includes("'" + id + "'") || page.includes('id="' + id + '"'), id + " section must exist");
    assert.ok(page.includes('href="#' + id + '"'), id + " navigation must work");
  }
  assert.match(page, /aria-labelledby/);
});

test("main featured projects reflect recent Futureproof, Gastown, and outage work", () => {
  for (const title of ["SUZY//TOAST · The Appliance Latent Space", "Gastown Simulator", "Lousy Outages"]) {
    assert.ok(page.includes(title), title + " is featured");
  }
  assert.match(page, /October 29, 2026/);
  assert.match(page, /8 GB MacBook Pro/);
  assert.match(page, /Magenta RealTime 2 Small/);
  assert.match(page, /Swift Audio Unit host/);
  assert.match(page, /independently captured loops/);
  assert.match(page, /physical toaster remained a prototype/);
  assert.match(page, /Three.js\/WebGL/);
  assert.match(page, /not a surveyed digital twin/);
  assert.match(page, /incident history and lifecycle logic/);
  assert.match(page, /not represented as universally enabled/);
});

test("local culture and civic systems are current and provenance-aware", () => {
  for (const title of ["AFTERIMAGE / Vancouver", "Vancouver Tech Events", "Vancouver Music & Culture"]) {
    assert.ok(page.includes(title), title + " exists");
  }
  assert.match(page, /26 curated venue\/organizer links|Twenty-six curated venue\/organizer links/);
  assert.match(page, /Four configured automated source adapters/);
  assert.match(page, /captured listings from proof that events occurred/);
  assert.match(page, /source-backed|Source-backed/);
  assert.match(page, /MapLibre/);
});

test("music and local intelligence projects distinguish prototypes from shipping work", () => {
  for (const title of [
    "Loop Lab", "The Appliance · Browser Instrument", "SUZY//PIRATE RADIO",
    "Skywhale Airways", "MACHINE VISIONS", "SUZY//AI",
    "SUZY//WORLD", "POP//CONTEXT", "suzyeaston.ca / The Workshop"
  ]) {
    assert.ok(page.includes(title), title + " exists");
  }
  assert.match(page, /browser instrument’s latent\/neural controls are disabled/);
  assert.match(page, /public stream is not connected yet/);
  assert.match(page, /collaboratively created AI Film Club project/);
  assert.match(page, /gallery system for selected AI-assisted films/);
  assert.match(page, /stops before interpretation/);
  assert.match(page, /Private AI context is not dumped into the public database/);
  assert.match(page, /not a claim that every work in the catalog is exhibited/);
});

test("earlier experiments remain accessible but are not mistaken for current headline builds", () => {
  const featuredAt = page.indexOf("$featured_projects =");
  const sectionsAt = page.indexOf("$project_sections =");
  assert.ok(featuredAt >= 0 && sectionsAt > featuredAt);
  const featured = page.slice(featuredAt, sectionsAt);
  for (const old of ["Track Analyzer", "ASMR Lab", "Riff Generator", "Albini QA"]) {
    assert.doesNotMatch(featured, new RegExp(old), old + " should not eclipse current featured work");
    assert.ok(page.includes(old), old + " still available");
  }
});

test("project links are explicit, valid source destinations with escaped output", () => {
  for (const route of [
    "gastown-sim", "lousy-outages", "afterimage",
    "vancouver-tech-events", "vancouver-music-culture", "loop-lab", "radio",
    "appliance-latent-space", "blog"
  ]) {
    assert.ok(page.includes("'" + route + "'"), route + " internal route should be linked");
  }
  for (const repo of [
    "suzyeaston/suzy-ai", "suzyeaston/suzy-world", "suzyeaston/pop-context",
    "suzyeaston/skywhale-airways", "suzyeaston/afterimage-vancouver",
    "suzyeaston/appliance-latent-space-live"
  ]) assert.ok(page.includes("https://github.com/" + repo), repo + " public source must be linked");
  assert.match(page, /esc_url\( \$link\['url'\] \)/);
  assert.match(page, /esc_html\( \$link\['label'\] \)/);
  assert.match(page, /esc_html\( \$project\['title'\] \)/);
  assert.match(page, /esc_html\( \$project\['status'\] \)/);
  assert.match(page, /esc_attr\( \$project\['title'\]/);
  assert.match(page, /target="_blank" rel="noopener noreferrer"/);
  assert.doesNotMatch(page, /https?:\/\/(localhost|127\.0\.0\.1)/);
});

test("portfolio CSS supports readable cards and narrow screens", () => {
  const css = read("style.css");
  assert.match(css, /\.projects-card__status/);
  assert.match(css, /\.projects-section__kicker/);
  assert.match(css, /\.projects-card__actions a/);
  assert.match(css, /@media \(max-width: 700px\)/);
  assert.match(css, /overflow-wrap: anywhere/);
  const manifest = read("scripts/theme_deploy_manifest.py");
  assert.match(manifest, /"page-projects\.php"/);
  assert.match(manifest, /"style\.css"/);
});

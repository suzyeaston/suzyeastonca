const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const root = path.resolve(__dirname, "..");
const { normalizeEvent, parseSaved, escapeICSText, foldICS, makeICS, makeCSV } =
  require("../assets/js/vancouver-events-lineup.js");

const example = (overrides = {}) => ({
  id: "a".repeat(40),
  title: "4DSOUND & Jazz + Poetry",
  start: 1795129200,
  end: 1795136400,
  url: "https://luma.com/test?x=1",
  location: "Lobe, 713 E Hastings St, Vancouver",
  source: "Official organizer",
  timeNote: "",
  ...overrides
});

test("local favorites are validated and URL schemes cannot execute scripts", () => {
  const good = normalizeEvent(example());
  assert.equal(good.title, "4DSOUND & Jazz + Poetry");
  assert.ok(good.url.startsWith("https://luma.com/test"));
  assert.equal(normalizeEvent(example({ url: "javascript:alert(1)" })), null);
  assert.equal(normalizeEvent(example({ url: "data:text/html,foo" })), null);
  assert.equal(normalizeEvent(example({ id: "not-a-real-key" })), null);
  assert.equal(normalizeEvent(example({ start: "not-a-date" })), null);
  assert.equal(normalizeEvent(example({ title: "" })), null);
  assert.equal(normalizeEvent(example({ end: 1 })).end, 0);
});

test("saved lineup persists valid unique event records, with bounded local storage", () => {
  const two = [example(), example(), example({ id: "b".repeat(40), title: "Second event" })];
  assert.equal(parseSaved(JSON.stringify(two)).length, 2);
  assert.deepEqual(parseSaved("invalid JSON"), []);
  assert.deepEqual(parseSaved(JSON.stringify({ unexpected: true })), []);
  assert.equal(parseSaved(JSON.stringify(Array.from({ length: 140 }, (_, i) => example({
    id: i.toString(16).padStart(40, "0"), title: "Show " + i
  })))).length, 100);
});

test("ICS imports calendar events with UTC dates, source provenance and tentative status", () => {
  const date = new Date("2026-11-14T21:00:00Z");
  const content = makeICS([example({
    title: "Poetry, vocals; and a line\nbreak",
    location: "Artspeak, Vancouver",
    timeNote: "Doors at 7 PM; show time unconfirmed"
  })], date);
  assert.match(content, /^BEGIN:VCALENDAR\r\nVERSION:2\.0\r\n/);
  assert.match(content, /BEGIN:VEVENT\r\n/);
  assert.match(content, /UID:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa@suzyeaston\.ca/);
  assert.match(content, /DTSTAMP:20261114T210000Z/);
  assert.match(content, /DTSTART:\d{8}T\d{6}Z/);
  assert.match(content, /DTEND:\d{8}T\d{6}Z/);
  assert.match(content, /STATUS:TENTATIVE/);
  assert.match(content, /not an RSVP or ticket/);
  // iCalendar intentionally folds long lines; unfold before reading a field.
  const unfolded = content.replace(/\r\n[ \t]/g, "");
  assert.match(unfolded, /show time unconfirmed/);
  assert.match(content, /SUMMARY:Poetry\\\, vocals\\; and a line\\nbreak/);
  assert.match(content, /\r\nEND:VCALENDAR\r\n$/);
});

test("ICS doesn't invent an end time for organizer listings that only have a start", () => {
  const content = makeICS([example({ end: 0, timeNote: "Doors time; not show time" })]);
  assert.match(content, /DTSTART:/);
  assert.doesNotMatch(content, /DTEND:/);
  assert.match(content, /Doors time/);
});

test("ICS escaping and folding protect line boundaries for long international titles", () => {
  assert.equal(escapeICSText("A,B;C\nD\\E"), "A\\,B\\;C\\nD\\\\E");
  const line = "SUMMARY:" + "星🌟".repeat(70);
  const folded = foldICS(line);
  const lines = folded.split("\r\n");
  assert.ok(lines.length > 2);
  for (const [index, item] of lines.entries()) {
    assert.ok(Buffer.byteLength(item, "utf8") <= 75, "folded ICS lines obey 75 octet maximum");
    if (index > 0) assert.ok(item.startsWith(" "), "continuation begins with whitespace");
  }
});

test("CSV is spreadsheet-ready, properly quoted, and protects against formula injection", () => {
  const content = makeCSV([
    example({ title: '=HYPERLINK("https://evil.example","a")', location: "+1/2", source: "@danger" }),
    example({ id: "b".repeat(40), title: "A, B\nC", location: "The \"Bar\"" })
  ]);
  assert.ok(content.startsWith("\uFEFF"));
  assert.match(content, /"Title","Start \(UTC\)","End \(UTC\)"/);
  assert.match(content, /"'=HYPERLINK\(""https:\/\/evil\.example"",""a""\)"/);
  assert.match(content, /"'\+1\/2"/);
  assert.match(content, /"'@danger"/);
  assert.match(content, /"A, B\nC"/);
  assert.match(content, /"The ""Bar"""/);
  assert.match(content, /https:\/\/luma\.com\/test\?x=1/);
});

test("template connects each event to selection and preserves source listing links", () => {
  const tpl = fs.readFileSync(path.join(root, "page-vancouver-events.php"), "utf8");
  const php = fs.readFileSync(path.join(root, "inc/vancouver-events.php"), "utf8");
  const manifest = fs.readFileSync(path.join(root, "scripts/theme_deploy_manifest.py"), "utf8");
  const css = fs.readFileSync(path.join(root, "assets/css/vancouver-events.css"), "utf8");
  assert.match(tpl, /id="yve-lineup"/);
  assert.match(tpl, /data-yve-save/);
  assert.match(tpl, /data-yve-event="<\?php echo esc_attr\( wp_json_encode/);
  assert.match(tpl, /data-yve-export="ics"/);
  assert.match(tpl, /data-yve-export="csv"/);
  assert.match(tpl, /Saved only on this device\/browser/);
  assert.match(tpl, /No account needed/);
  assert.match(tpl, /saving doesn't reserve a spot or RSVP/);
  assert.match(tpl, /esc_url\( \$event\['url'\] \)/);
  assert.match(php, /wp_enqueue_script\( 'suzy-vancouver-events-lineup'/);
  assert.match(php, /filemtime\( \$js \)/);
  assert.match(manifest, /"assets\/js\/vancouver-events-lineup\.js"/);
  assert.match(css, /\.yve__save\[aria-pressed="true"\]/);
  assert.match(css, /\.yve__lineup/);
  assert.match(css, /\.yve__event-cabinet/);
  assert.match(css, /\.yve h1[\s\S]*"Press Start 2P"/);
  assert.match(css, /@media \(max-width: 700px\)/);
  assert.doesNotMatch(tpl, /wp_remote_get/);
});

test("calendar pagination retains namespaced query variables", () => {
  const tpl = fs.readFileSync(path.join(root, "page-vancouver-events.php"), "utf8");
  for (const key of ["yve_year", "yve_month", "yve_period", "yve_view", "yve_track", "yve_topic", "yve_q", "yve_page"]) {
    assert.ok(tpl.includes("'" + key + "'"), "pagination retains " + key);
  }
});

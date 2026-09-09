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

test("homepage teaser keeps short intro and Futureproof spotlight slot", () => {
  const source = fs.readFileSync(
    path.join(ROOT, "parts", "home-vancouver-tech-events.php"),
    "utf8"
  );
  assert.match(source, /suzy_get_vancouver_tech_events/);
  assert.match(source, /vancouver-tech-home/);
  assert.match(source, /Open full calendar/);
  assert.match(source, /\/vancouver-tech-events\//);
  assert.match(source, /Meetup tabs multiply\. One feed\./);
  assert.match(source, /vancouver_events_spotlight/);
  assert.doesNotMatch(source, /keeps them honest/);
  assert.doesNotMatch(source, /sits up front/);
  assert.doesNotMatch(source, /chronological/);
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
  assert.match(php, /Oct 29–30 at the Space Centre\./);
  assert.match(css, /\.vte-event--spotlight/);
  assert.match(css, /\.vte-spotlight-badge/);
  assert.doesNotMatch(php, /No ranking/);
  assert.doesNotMatch(php, /stays chronological/);
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

/**
 * Mirrors the PHP Futureproof identity + merge/dedupe strategy used in
 * suzy_get_vancouver_tech_events() so we can regression-test the feed
 * without booting WordPress.
 */
function isFutureproofEvent(event) {
  const url = String(event.url ?? "").toLowerCase();
  const title = String(event.title ?? "")
    .toLowerCase()
    .trim();
  if (url.includes("futureproof-festival")) {
    return true;
  }
  return /\bfutureproof\s+festival\b/.test(title);
}

function eventIdentityKey(event) {
  if (isFutureproofEvent(event)) {
    return "futureproof-festival-2026";
  }
  const title = String(event.title ?? "")
    .toLowerCase()
    .trim();
  const start = event.start ? Number(event.start) : 0;
  const location = event.location
    ? String(event.location).toLowerCase().trim()
    : "";
  if (!title) {
    return "";
  }
  return `${title}|${start}|${location}`;
}

function mergeEventRecords(preferred, fallback) {
  const merged = { ...preferred };
  for (const field of ["title", "start", "end", "location", "url", "source"]) {
    const prefEmpty =
      preferred[field] === undefined ||
      preferred[field] === null ||
      preferred[field] === "";
    if (
      prefEmpty &&
      fallback[field] !== undefined &&
      fallback[field] !== null &&
      fallback[field] !== ""
    ) {
      merged[field] = fallback[field];
    }
  }
  if (isFutureproofEvent(preferred) || isFutureproofEvent(fallback)) {
    merged.spotlight = true;
  } else if (preferred.spotlight || fallback.spotlight) {
    merged.spotlight = true;
  }
  if (!preferred.curated) {
    delete merged.curated;
  }
  return merged;
}

function dedupeVancouverTechEvents(events) {
  const marked = events.map((event) => {
    const next = { ...event };
    if (isFutureproofEvent(next)) {
      next.spotlight = true;
    } else {
      delete next.spotlight;
      delete next.member;
    }
    return next;
  });

  const seenKeys = new Map();
  const deduped = [];

  for (const event of marked) {
    const key = eventIdentityKey(event);
    if (!key) {
      continue;
    }

    if (seenKeys.has(key)) {
      const existingIndex = seenKeys.get(key);
      const existing = deduped[existingIndex];

      if (isFutureproofEvent(event) || isFutureproofEvent(existing)) {
        const existingCurated = Boolean(existing.curated);
        const incomingCurated = Boolean(event.curated);

        if (existingCurated && !incomingCurated) {
          deduped[existingIndex] = mergeEventRecords(event, existing);
        } else if (!existingCurated && incomingCurated) {
          deduped[existingIndex] = mergeEventRecords(existing, event);
        } else {
          deduped[existingIndex] = mergeEventRecords(existing, event);
        }
      } else if (event.spotlight) {
        deduped[existingIndex].spotlight = true;
      }
      continue;
    }

    seenKeys.set(key, deduped.length);
    deduped.push(event);
  }

  return deduped;
}

test("Futureproof helpers and cache v5 land in the aggregator", () => {
  const php = fs.readFileSync(
    path.join(ROOT, "inc", "vancouver-tech-events.php"),
    "utf8"
  );
  assert.match(php, /function suzy_vte_is_futureproof_event\s*\(/);
  assert.match(php, /function suzy_vte_event_identity_key\s*\(/);
  assert.match(php, /function suzy_vte_merge_event_records\s*\(/);
  assert.match(php, /futureproof-festival-2026/);
  assert.match(php, /suzy_vancouver_tech_events_cache_v5/);
  assert.doesNotMatch(php, /suzy_vancouver_tech_events_cache_v4/);
  assert.match(php, /'curated'\s*=>\s*true/);
  assert.match(
    php,
    /array_merge\s*\(\s*\$events\s*,\s*suzy_get_vancouver_tech_spotlight_events\s*\(\s*\)\s*\)/
  );
  assert.match(php, /suzy_vte_event_identity_key\s*\(\s*\$event\s*\)/);
  assert.match(php, /suzy_vte_merge_event_records\s*\(/);
});

test("full calendar excludes spotlight events from dated list", () => {
  const php = fs.readFileSync(
    path.join(ROOT, "inc", "vancouver-tech-events.php"),
    "utf8"
  );
  const renderStart = php.indexOf("function suzy_render_vancouver_tech_events_html");
  assert.ok(renderStart > -1);
  const renderChunk = php.slice(renderStart, renderStart + 4500);
  assert.match(
    renderChunk,
    /if\s*\(\s*!\s*empty\s*\(\s*\$event\['spotlight'\]\s*\)\s*\)\s*\{\s*continue;/
  );
  assert.match(renderChunk, /\$events_by_date/);
  assert.match(renderChunk, /\$spotlight_events/);
});

test("duplicate Futureproof titles collapse to one spotlight event", () => {
  const curated = {
    title: "Futureproof Festival 2026",
    start: 1793235600,
    end: 1793422800,
    location: "H.R. MacMillan Space Centre",
    url: "https://luma.com/futureproof-festival",
    source: "BC + AI Events",
    spotlight: true,
    curated: true,
  };
  const live = {
    title: "Futureproof Festival of AI",
    start: 1793235600,
    end: 1793422800,
    location: "H.R. MacMillan Space Centre",
    url: "https://luma.com/futureproof-festival",
    source: "BC + AI Events",
  };
  const other = {
    title: "TechVAN Meetup",
    start: 1792000000,
    location: "Vancouver",
    url: "https://example.com/techvan",
    source: "Meetup TechVAN",
  };

  // Live first, curated fallback second — mirrors production merge order.
  const feed = dedupeVancouverTechEvents([live, curated, other]);
  const futureproof = feed.filter((event) => isFutureproofEvent(event));

  assert.equal(futureproof.length, 1, "exactly one Futureproof event");
  assert.equal(futureproof[0].spotlight, true);
  assert.equal(futureproof[0].title, "Futureproof Festival of AI");
  assert.equal(futureproof[0].url, "https://luma.com/futureproof-festival");
  assert.equal(futureproof[0].curated, undefined);
  assert.equal(feed.length, 2);

  // Curated alone still works as fallback.
  const fallbackOnly = dedupeVancouverTechEvents([curated, other]);
  const fallbackFp = fallbackOnly.filter((event) => isFutureproofEvent(event));
  assert.equal(fallbackOnly.length, 2);
  assert.equal(fallbackFp.length, 1);
  assert.equal(fallbackFp[0].spotlight, true);
  assert.equal(fallbackFp[0].title, "Futureproof Festival 2026");

  // Different titles still share the canonical identity (not fuzzy matching).
  assert.equal(eventIdentityKey(curated), "futureproof-festival-2026");
  assert.equal(eventIdentityKey(live), "futureproof-festival-2026");
  assert.notEqual(eventIdentityKey(other), "futureproof-festival-2026");
  assert.equal(isFutureproofEvent({ title: "Futureproofing your stack" }), false);
});

test("spotlight section items are not re-listed in dated calendar buckets", () => {
  const events = [
    {
      title: "Futureproof Festival of AI",
      start: 1793235600,
      url: "https://luma.com/futureproof-festival",
      spotlight: true,
    },
    {
      title: "TechVAN Meetup",
      start: 1792000000,
      url: "https://example.com/techvan",
    },
  ];

  const spotlightEvents = events.filter((event) => event.spotlight);
  const eventsByDate = {};
  for (const event of events) {
    if (event.spotlight) {
      continue;
    }
    const dateKey = "bucket";
    if (!eventsByDate[dateKey]) {
      eventsByDate[dateKey] = [];
    }
    eventsByDate[dateKey].push(event);
  }

  assert.equal(spotlightEvents.length, 1);
  assert.equal(Object.values(eventsByDate).flat().length, 1);
  assert.equal(Object.values(eventsByDate).flat()[0].title, "TechVAN Meetup");
  assert.ok(
    !Object.values(eventsByDate)
      .flat()
      .some((event) => event.spotlight)
  );
});

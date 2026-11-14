# Vancouver tech event coverage update

Researched 2026-09-26. Base inspected: `27488d5a7099569ff3e5e13e579ee182526b46cc`.

The original aggregator had eight sources: three Meetup ICS feeds, Luma's general
Vancouver discovery page, BC + AI, Vancouver Tech Journal, T-Net, and Meetup search.
General city discovery does not guarantee coverage of an organizer's calendar.

## Additional automatic calendar sources

| Community | Organizer calendar | Coverage |
| --- | --- | --- |
| Vancouver Crypto Events | https://luma.com/yvr | DCTRL/EthVan and other Vancouver crypto/builders events |
| DC604 | https://luma.com/dc604 | DEFCON Vancouver and security meetups |
| VanCitySec | https://luma.com/vancitysec | Security community socials |
| Vancouver.dev | https://luma.com/vancouver.dev | Developer community events |
| VanTUG | https://luma.com/Vantug | Enterprise IT, cloud, Microsoft and security |
| Code Together Vancouver | https://luma.com/code-together-vancouver | Collaborative coding |
| White-Hat Security Community | https://luma.com/WhiteHatSecurityCommunityVancouver | Cybersecurity coffee chats |
| Vancouver Impact | https://luma.com/vancouverimpact | Climate tech, startups and innovation |

The VanTUG website links its calendar: https://www.vantug.com/events.
Vancouver Crypto Events currently lists Builders Night at DCTRL:
https://luma.com/builders-night-vancouver-sept-30.
These are source registrations, not promises that every source always has future events.

## Direct community links

DCTRL's own hosted-event profile is also linked:
https://luma.com/user/usr-XQ1OMFlMqL7Ajax. This catches open houses visitors may not
find on the regional crypto calendar. It is a profile link, not an automatic feed.

Vancouver Hack Space: https://vanhack.ca/events-calendar. Its official site advertises
Tuesday public nights and links a Google calendar. We link that calendar rather than
invent dated occurrences: the existing ICS parser does not implement RRULE,
EXDATE, or moved/cancelled recurrence instances. VHS dates are not auto-imported.

## Code changes

- Preserve case and punctuation in Luma slugs (notably `vancouver.dev`).
- Combine featured and regular structured event lists; deduplicate their overlap.
- Normalize Luma/Meetup event URLs for deduplication while retaining each occurrence's timestamp.
- Fill missing event fields from duplicate records.
- Respect a published external registration URL where supplied by Luma.
- Render dates and times using `America/Vancouver`, independently of WordPress settings.
- Refilter cached events; keep multiday events until their published end.
- Bump aggregate and affected parser caches so the patch takes effect on the next uncached page request.
- Correct Futureproof's displayed date range to October 28–30, consistent with its existing timestamp.
- Show organizer links even if the automatic list is empty. Admin `?vte_debug=1` reports HTTP failures.

## Validation and limitations

Run `php tests/VancouverTechEventsTest.php` and
`node --test __tests__/homepage-vancouver-tech-events.test.js`.
The behavior tests execute the actual PHP parser/rendering functions with stubbed
WordPress HTTP and cache calls, covering combined lists, malformed payloads,
slug preservation, duplicate URLs, recurrence identities, timezone boundaries,
HTML escaping, cache expiry filtering and fallback links.

The existing integration uses Luma public-page data and an undocumented public
endpoint, so upstream changes or bot protection can still interrupt imports.
Organizer pages were verified through web search, but live HTTP requests to Luma
and DCTRL from the development environment returned 403. This patch is not a claim
of successful production ingestion. After deployment, an administrator should
check `/vancouver-tech-events/?vte_debug=1` and confirm per-source results.

The existing 80-event display cap and general discovery feeds remain. The page is
broader, not an exhaustive inventory of all Vancouver events. No fictional recurring
events or old one-off events were seeded into the feed. Page/CDN caches may need
clearing if the old rendered page persists after a successful deploy.

## Deployment

Run the downloadable `update-vancouver-tech-events.sh` with Bash. It clones the
latest `main` into a separate directory, checks and applies the embedded patch,
commits the changes (including a copy of the script), and pushes normally to `main`.
The repository's existing `Deploy to Production` workflow then handles WordPress.
No credentials are embedded and no force push is used. Conflicts stop before push.
`--check` prepares the changes without committing or pushing.

If rollback is needed, revert the printed commit SHA in a clean checkout and push
that revert. Do not reset or force-push shared history.

## November 9, 2026: Eventbrite and conference gap

The source inventory above reflected September 2026. On November 9, the live
calendar did not show three verified events:

| Event | Verified occurrence (Vancouver time) | Authoritative link |
| --- | --- | --- |
| AI Builders Guild: Build, Demo & Connect | Nov 9, 6:30–9:30 pm | https://www.eventbrite.com/e/ai-builders-guild-vancouver-build-demo-connect-tickets-2002478930386 |
| DNS-OARC 47 | Nov 9–10 | https://indico.dns-oarc.net/event/58/ |
| MiningTech North America | Nov 9–10 | https://www.miningtechnorthamerica.com/ |

The Google AI Overview also put events on **November 9** that organizer /
third-party evidence places on **November 25**, notably the VTJ Critical
Minerals discussion, Cloud Native Vancouver and Vancouver Java User Group.
The VTJ discussion was already correctly listed on Nov 25 on the live page.
Do not copy dates from AI summaries into the event records.

### New automated sources and behavior

- Cloud Native Vancouver's confirmed Luma calendar:
  https://luma.com/cloudnativevan
- Eventbrite Vancouver tech discovery:
  https://www.eventbrite.ca/d/canada--vancouver/tech/
- Eventbrite Vancouver AI discovery:
  https://www.eventbrite.ca/d/canada--vancouver/artificial-intelligence/
- Each Eventbrite search result is canonicalized by its ticket ID. At most
  12 unique event detail pages per source are requested per 30-minute cache
  window. A detail page must publish a parseable start date; the code does
  not infer dates from search-card copy.
- Three verified event dates are added as short-lived fallback records.
  The existing time filter hides them automatically once they end. They
  are not recurring events or future promises.
- The full aggregate cache key is now `v7`, avoiding the earlier cached
  coverage set. Source debug continues to show HTTP and empty-source status.
- Links to the Java User Group organizer profile, DNS-OARC, Convention Centre
  and Eventbrite discovery searches remain available when scraping is blocked.

### Tests and operational caveats

Run `php -l inc/vancouver-tech-events.php`,
`php tests/VancouverTechEventsTest.php` and
`node --test __tests__/homepage-vancouver-tech-events.test.js`.
The PR adds a separate GitHub Actions validation workflow for these checks.

This is a **coverage improvement, not an exhaustive guarantee**. Search pages
may block WordPress's server or stop rendering event anchors, and JSON-LD
on detail pages can change. Admins can visit
`/vancouver-tech-events/?vte_debug=1` while logged in and inspect source
counts. In particular, if both Eventbrite sources show 403 or 0 entries,
those events will not auto-import; the linked official discovery pages are
the fallback. The production site and its CDN cache should be checked
after deployment. Correct data and timezone settings still depend on
organizers publishing accurate timestamps.

### Critical: B.C. permanent Pacific time (PCT)

In March 2026 B.C. announced that the March 8 clock change was its last;
from November 1 Vancouver no longer falls back to PST. Its local civil time
remains UTC−7 year round. An outdated `America/Vancouver` timezone
database still converts November dates at UTC−8, making some event times
show an hour too early. See:

- Province of B.C.: https://www2.gov.bc.ca/gov/content/governments/celebrating-british-columbia/daylight-saving-time
- UBC IT, Oct. 20, 2026: https://it.ubc.ca/news/october-20-2026/bc-time-zone-change-what-it-means-your-calendar-and-devices

The aggregator uses a fixed UTC−7 offset for post-March-8-2026
Vancouver event parsing and rendering, while retaining historical IANA
timezone rules for events before the final spring-forward. The PHP test
suite asserts that November 2026 01:30 UTC is 6:30 p.m. in Vancouver,
including when run on servers with outdated timezone data. Eventbrite
structured data should have an accurate UTC−7 offset for B.C. dates;
incorrect offsets from an upstream organizer are not silently rewritten.

## November 11, 2026: independent Luma creative-tech discovery

A source gap was reported for [DEFRAG: Archive of Synthetic Dreams](https://luma.com/3fkowyad),
November 18–19 at The Permanent. This public event is listed under
[Luma Vancouver AI discovery](https://luma.com/discover/vancouver/ai),
but its organizer (Ministry of Next) was not one of our subscribed Luma calendars.

- Register Luma's public Vancouver **AI** and **Tech** category pages as
  additional discovery sources. Organizer calendars remain the preferred
  stable coverage path.
- For event-card links on those pages, resolve the actual event metadata
  through Luma's public URL endpoint; only use parseable published dates.
  A bounded number of detail requests prevents an unbounded scraper loop.
- Reject non-event pages and non-Luma domains. The existing common
  aggregation stage handles canonical cross-source deduplication.
- Seed DEFRAG as a time-limited verified safety-net record (Nov 18 at 2 pm
  through Nov 19 at 11 pm, matching the official event agenda).
  This remains useful even if Luma blocks the source feed.
- Bump the aggregate cache key to v8 so new sources are checked after deployment.
- Preserve the administrator source-debug mode at
  `/vancouver-tech-events/?vte_debug=1`.

**Limits:** Public discovery results are not an exhaustive events inventory;
private, unlisted, outside-radius or newly posted events may be absent.
Luma's website and unauthenticated endpoint can change or block requests.
Confirm production HTTP status and ingested counts in administrator debug mode,
and clear WordPress/CDN HTML caches if the old page persists.

 
## November 13, 2026: persistent historical archive

The old calendar used a 30-minute transient cache and removed finished events
**before** deduplication and caching, so expired listings disappeared. The new
archive is deliberately separate from the upcoming-event cache.

- Each successful aggregate refresh now captures valid, dated, linked public
  event listings **before** the upcoming-only filter. It deduplicates using
  the calendar's existing canonical event identity and retains first-seen
  timestamps and source/verified provenance.
- A non-autoloaded WordPress option (`suzy_vte_event_archive_v1`) holds at
  most 2,500 records. Only fields needed for historical display are stored.
  Empty or blocked feeds do **not** erase previously observed listings.
- A twice-daily WordPress cron refresh keeps discovery going even when the
  calendar page is not visited. This is WP-Cron, not a guaranteed wall-clock
  service: low-traffic sites or hosts with disabled WP-Cron need a system cron.
- Past verified curated entries (Futureproof Festival, MiningTech North
  America, DNS-OARC, AI Builders Guild) serve as a small pre-installation seed
  without pretending to provide exhaustive historical backfill. Future DEFRAG
  is not shown until it actually becomes past.
- Public view: year/topic filters, twelve-month listing counts, newest-first
  list and 30-record pagination. Topic labels are heuristic title-keyword
  categories, not authoritative classifications.
- **Interpretation:** an archived listing does not prove the event happened,
  had attendees, or wasn't cancelled. Monthly counts will be heavily affected
  by which calendars are indexed and by tracking start time. Do not call
  changes in raw listing counts changes in local event *activity* without
  checking source coverage.
- Date grouping continues to use the existing Vancouver-local timezone
  helper, including pre-March-2026 historical PST rules.

QA:
1. `php -l inc/vancouver-tech-events.php` and
   `php -l inc/vancouver-tech-events-archive.php`
2. `php tests/VancouverTechEventsTest.php`
3. `node --test __tests__/homepage-vancouver-tech-events.test.js`
4. `python3 scripts/theme_deploy_manifest.py validate`
5. After merge, inspect the Archive section on
   `/vancouver-tech-events/#vte-archive`, check 2025/2026 filters and
   monthly histogram, confirm Futureproof and the November 9 events appear,
   check DEFRAG is still upcoming, then verify the next calendar refresh
   appends new events without deleting previous records.

# Vancouver Music & Culture calendar

Implementation: November 13, 2026. This is a neighbourhood-scale cultural index, not a general concert ticket marketplace. Focus: independent and experimental music, spatial audio, music technology, poetry and literary salons, philosophy, talks, artist-run spaces, and cultural workshops.

## Sources and coverage

Four independent automatic adapters are configured, each with its own error reporting: (1) 8EAST / NOW Society via The Events Calendar public WordPress JSON endpoint, (2) Roundhouse via the same event-plugin format but an independent host, (3) Lobe via its Squarespace event collection JSON, and (4) public Luma Vancouver discovery screened for cultural topics and Vancouver venue location. These JSON endpoints are adapter attempts, not guaranteed integration agreements. The linked organizers may block requests or change formats, and the admin source health view makes this explicit.

Another dozen direct-source links include Western Front, Music on Main, VPL Author Events, Pandora's Collective, Vancouver Curiosity Club, Heart of the City, Eastside Culture Crawl, The Wise, and New Forms Media Society. Their inclusion as links is not a claim that they have fully automated ingestion.

The hourly WP-Cron job refreshes sources with bounded requests and transient failure caches. Public page requests use already captured listings and a small individually verified set of dated listings, without blocking on network calls. WP-Cron is traffic-dependent; if it is disabled or traffic is absent, the remote feeds may become stale.

## Historical archive and interpretation

Saved options are suzy_vmc_latest_v1, suzy_vmc_archive_v1, and suzy_vmc_source_status_v1. They are stored non-autoloaded. The archive retains at most 1,500 normalized records, never discards history because a source feed is empty, and groups by year/start month. The earliest verified seed records are not a complete historical backfill. Empty months are marked as unknown rather than claiming zero cultural events, and the archive never implies verified attendance.

Date parsing uses Vancouver permanent UTC-7 after March 8, 2026 and historic IANA rules before then; Squarespace timestamps are milliseconds converted to Unix seconds. Discovery links require culturally relevant title keywords and Vancouver locations; remote URLs are fixed source endpoints, event links must use HTTPS, and all public fields are escaped.

## Original organizers and dated launch examples

- 8EAST Nov 14 improvisation: https://www.8east.ca/event/fall-improvisation-workshops-2026-matthew-ariaratnam/
- Roundhouse Constellation Nov 13: https://www.roundhouse.ca/event/constellation-festival/2026-11-13/
- Curiosity Club Nov 15 Tolstoy discussion: https://vancouvercuriosityclub.com/events/how-much-land-does-a-man-need-tolstoy-on-greed-secur-06350f242559-226f31b0
- Pandora's Collective Nov 17 poetry gallery: https://www.pandorascollective.com/art--poetry-in-the-gallery.html
- VPL Nov 18 Twisted Poets: https://vpl.bibliocommons.com/v2/events/69698d252c65973d00181b3f
- Lobe Nov 18 immersive listening: https://roadrunner-red-yde9.squarespace.com/new-events/mycoglossia-album-listening-session-in-4d-sound
- Western Front Nov 21 Six Turntables: https://www.westernfront.ca/events/six-turntables
- 8EAST Nov 25 Improv Karaoke: https://www.8east.ca/event/improv-karaoke-67/
- Music on Main Dec 9–11 Winter Solstice: https://www.musiconmain.ca/event/music-for-the-winter-solstice-2026/

One source discrepancy: 8EAST's Concurrence #14 calendar lists Nov 19, but a description mentions Nov 18; the verified seed follows the calendar date and should be rechecked with the organizer.

## Delivery and post-merge QA

The exact public route /vancouver-music-culture/ is served by the theme without requiring a new WordPress Page post. Other slugs are untouched. The CSS is route-scoped; the Projects page and homepage tech-calendar teaser link to this calendar. SEO title and canonical are defined in the existing meta helper.

Tests: php -l inc/vancouver-music-culture.php; php -l page-vancouver-music-culture.php; php tests/VancouverMusicCultureTest.php; node --test __tests__/vancouver-music-culture.test.js __tests__/homepage-vancouver-tech-events.test.js; python3 scripts/theme_deploy_manifest.py validate after generating existing social-card assets.

After merge, verify HTTP 200 and stylesheet, initial seed records across categories, organizer links, category/year controls, archive caveat, mobile layout, and site navigation. In admin mode, inspect source-status results after WP-Cron runs. Do not assume blocked sources produced records. Live visual QA is intentionally after merge.


## November 13: independent venue discovery audit

The first launch only linked twelve organizers, with some obvious missing corners of Vancouver's scene. Research cross-checked artist-run organizations (PAARC), organizers' own published calendars, Vancouver Magazine's December 2025 independent-venue guide, Rhythm Changes' November 2026 jazz gig list, and Upstart & Crow's live event pages.

The directory now contains **26 distinct browse-only links**, grouped across:
- Experimental audio & media: existing 8EAST, Lobe, Western Front, Music on Main, New Forms, plus VIVO Media Arts and UNIT/PITT (including its documented intimate jazz programming).
- Indie gigs & small jazz rooms: Red Gate, Green Auto, Wise Hall, Tyrant Studios, Frankie's Jazz Club, Guilt & Company, Fox Cabaret, Rogue Folk Club, plus Rhythm Changes as an **independent jazz listings calendar**, not a venue.
- Poetry and ideas: VPL, Pandora's, Curiosity Club, Upstart & Crow, Vancouver Poetry House, Artspeak and Word Vancouver.
- Broader arts programming: Roundhouse, Heart of the City and Eastside Culture Crawl.

Primary sources: https://redgate.tv/schedule/ ; https://www.vivomediaarts.com/programming ; https://www.unitpitt.ca/portfolio/caff/ ; https://upstartandcrow.com/events/ ; https://www.vancouverpoetryhouse.com/vanslam/ ; https://www.rhythmchanges.ca/gig-list-vancouver/ ; https://www.foxcabaret.com/ ; https://www.tyrantstudios.com/ . Green Auto public show discovery links to https://do604.com/venues/green-auto instead of asserting an official standalone calendar.

**Exclusion:** Massy Arts Society's homepage (https://massyarts.com/) says permanently closed. It is not added as an active gallery listing. Massy Books is a separate ongoing bookshop; no assumptions about programming at its former gallery were made.

Three individually dated, linked literary listings were seeded from Upstart & Crow's own event detail pages: Michael Foy (Nov 13, 6:30–8pm), Lisa Baldissera (Nov 26, 6:30pm; unknown end), and Gregor Craigie (Dec 1, 6:30pm; unknown end). No recurring dates or event times were invented. Upstart & Crow specifically warns that Eventbrite sometimes displays incorrect times; use its official event page for reference.

**Important distinction:** These additional directory links do NOT turn into automatic collected events. The four existing feed adapters are unchanged until endpoint reliability can be confirmed. More links improve discovery, not automatic coverage. QA should verify grouped links on mobile, direct venue destinations, and that the homepage's next-event preview and tech calendar were not changed.


## November 14, 2026: bringing venues into the actual event feed

**QA gap:** The directory has 26 venues/organizers, but only four were configured for automatic collection. Browsable venue links do not automatically create event cards.

There are now **seven separate configured sources**, with a fallback for one of the originals:

| Source | Collector | Coverage caveat |
| --- | --- | --- |
| 8EAST | Existing JSON; fallback to https://www.8east.ca/events/?ical=1 | The iCalendar content type was observed; WP-Cron still needs live QA |
| Upstart & Crow | JSON; fallback https://upstartandcrow.com/events/?ical=1 | Upcoming event list verified, JSON/ICS availability on production unconfirmed |
| Red Gate | Bounded official HTML https://redgate.tv/tickets/ | Times are **doors**, never claimed to be show starts |
| Rhythm Changes | Bounded jazz listing https://www.rhythmchanges.ca/gig-list-vancouver/ | Secondary source, known Vancouver-city venue whitelist, verify with the venue |
| Roundhouse | Existing JSON | Could block requests |
| Lobe | Existing Squarespace JSON | Format may change |
| Luma discovery | Existing citywide title/venue filter | Limited venue matching |

### Source and safety notes

Red Gate's dated organizer ticket page lists events such as GOLERS (Nov 13), EVIL SUMMER CAMP (Nov 15), Ruby Jones (Nov 18) and CODE-22 (Nov 26). The parser accepts only dated names with explicitly published door hours, resolves the year only when the stated weekday agrees with a date near collection, and puts the **doors-not-show-start** qualification visibly on the calendar and homepage preview. It does not invent show times.

The independent Rhythm Changes November listings include Tyrant Studios, Frankie's, 8EAST, Guilt & Co. and other small Vancouver rooms. The source is not the venue operator: each listing keeps attribution, notes secondary-source uncertainty and excludes suburbs and unknown locations rather than guessing.

The Upstart & Crow events list is dated and publicly browsable, including Nov 26 and December literary discussions. The two attempted structured calendar endpoints (JSON then ICS) are **not yet verified from WordPress production**. The 8EAST ICS response was observed to have text/calendar content, providing a potentially more reliable alternative to JSON.

ICS parsing uses timed entries only; cancels and untimed all-day dates are skipped. All source URLs are fixed in code with content-size and event caps. Transient failures can reuse a source's last known good result for at most 24 hours, preserving upcoming events without claiming indefinitely fresh coverage. Historical records are not deleted when feeds fail. Same-time items sharing a venue tickets URL are deduplicated using title as well as date.

**Configured is not confirmed working:** WP-Cron is traffic-dependent, and each source has a per-source count/status visible to logged-in WordPress administrators. Live checks after merge must verify actual ingestion, not just the presence of source configuration.

### Post-merge QA

Inspect the upcoming calendar, category filters and archived listings. After an hourly WP-Cron refresh, check administrator source statuses and counts for all seven sources. Verify Red Gate door-time warnings, new small-room jazz listings including Tyrant and Frankie's, Upstart literary results, source-attribution labels and absence of suburban dates. Confirm homepage preview and Vancouver Tech Events remain intact.

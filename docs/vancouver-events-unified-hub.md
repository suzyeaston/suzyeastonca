# Vancouver Events: One calendar, several scenes

## Intent
Combine the existing Vancouver Tech Events and Vancouver Music & Culture calendars at the **presentation layer**, while keeping each collector and historical archive the source of truth.

Public route: **/vancouver-events/**

The new URL is a theme-managed exact route, so it is available without creating another WordPress Page post. The original `/vancouver-tech-events/` and `/vancouver-music-culture/` routes remain working for deep source exploration and archived coverage. No forced redirects at launch.

## One shared feed

- Upcoming tech: `suzy_get_vancouver_tech_events()['events']` (its existing 30-minute cache and source behavior).
- Upcoming cultural listings: `suzy_vmc_events()` (already cached; no new network requests).
- Tech archive: `suzy_vte_archive_listings()` (persisted source-observed listings plus verified backfill).
- Culture archive: `suzy_vmc_history()` (separate persistent index plus verified seeds).
- Shared presentation adapter: `inc/vancouver-events.php`.
- Rendering: `page-vancouver-events.php` and `assets/css/vancouver-events.css`.

No data migration or new external feed adapters. This avoids breaking existing event ingestion and keeps useful historical records in place.

## Filtering design

| Control | Values | Why |
| --- | --- | --- |
| View | Upcoming, Historical archive | Same URL for the two timelines |
| Scene | Everything, Tech, Music & Culture | Broad entry point |
| Topic | Seven tech and six cultural categories | Detailed, source-aware filtering |
| When (upcoming) | All, next 7, 30, or 90 days | Practical planning windows |
| Year + month (archive) | Years that have records, all months or specific month | Event-listing history/trend exploration |
| Search | Title, venue/location, source/organizer | Venue discovery without 26-item dropdown |
| Pagination | 24 records/page | Keep mobile readable and archive bounded |

Parameters are namespaced (`yve_view`, `yve_track`, `yve_topic`, `yve_period`, `yve_year`, `yve_month`, `yve_q`, `yve_page`) to avoid clashing with WordPress's own public `page`, `year` and other query variables. Form submissions are server-rendered, keyboard accessible and work without JavaScript. Filters are combinable; bad values reset to safe defaults.

## Deduplication, provenance and boundaries

- Normalize source records only if title, timestamp and external event URL exist.
- Two appearances with **the same normalized title and exact start time** become a single card with multiple scene/topic badges and combined source attribution. This is conservative: different event titles on a shared Red Gate ticket URL remain distinct.
- Organizer-verified detail links take precedence when duplicate records disagree, but source names and category memberships are retained.
- Venue and source text stays visible. The source's explicit `time_note`, including Red Gate **doors time, not show time**, is retained.
- The archive is a record of **captured listings**, not proof that attendance or the event itself occurred. Missing months are shown as unknown rather than zero attendance.
- Tech and culture source limits and upstream blocking remain real constraints. Combining datasets does **not** mean every local event is covered. The source owners keep collecting under their own existing contracts.

## Navigation

- The homepage's Tech teaser links to `/vancouver-events/?yve_track=tech`, and its Music & Culture teaser links to `/vancouver-events/?yve_track=culture`. The original specialist source pages remain linked nearby.
- The homepage project selector has a single `Vancouver Events` entry.
- Projects includes the unified event browser alongside the underlying specialist implementations.
- Both specialist pages link back to the unified browser.
- Dedicated title, description and canonical link in `inc/seo.php`.

## QA and staged adoption

1. CI must pass PHP lint, behavior-level fixture tests, Node integration tests, existing Tech and Music & Culture suites, and production manifest validation.
2. **Before merging** review PR screenshots/markup for filters, mobile wrapping, accessible controls, visual continuity and correctness of time labels.
3. **After merging** inspect production `/vancouver-events/`, combined upcoming cards, source URLs, dates, filtering, paging and archive year/month. Confirm that filtering Tech shows the same tech records and filtering Culture shows the same cultural records as their focused pages.
4. Check a known event appearing in both sources is shown once; check two distinct shows using the same venue ticket URL remain distinct. Verify no newly created record is missing its source or time note.
5. Keep existing specialist routes public while the combined page is QA'd. Consider a later PR to simplify the two separate homepage teaser panels after visual feedback.

## Development tests

- `php -l inc/vancouver-events.php && php -l page-vancouver-events.php`
- `php tests/VancouverEventsHubTest.php`
- `node --test __tests__/vancouver-events-hub.test.js`
- Existing `Vancouver Music & Culture QA` workflow includes the new tests plus the prior regressions and theme manifest validation.

The combined layout is the proposed first iteration: an upcoming/archive switch, scene/topic filters and searchable event list. No map, RSVP workflow, tickets, account system or speculative recommendation algorithm is added in this PR.

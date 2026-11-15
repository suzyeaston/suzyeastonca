# Vancouver Events: Arcade and saved lineup (November 2026)

## Purpose
Live QA showed the combined events calendar was useful but not styled like the existing arcade site. Visitors also had no way to remember interesting events across a filter change or another visit.

## User experience
- Every event card has a Save event button, which becomes Saved when selected. Each button exposes its state via aria-pressed.
- The My saved lineup panel near the top shows saved events and a count, provides individual removal, a confirmed Clear action, and bulk export.
- Picks persist in browser localStorage under a versioned key. No WordPress accounts, server writes, analytics, RSVP, tickets or event registration. Visitors who clear browser data, use private mode, or switch devices may lose their choices. A storage-denied warning suggests exporting.
- Export Calendar (.ics) produces standards-based UTC timestamps, source and organizer URLs, any original time note, and tentative event status. Missing end times remain missing: no fabricated event durations. Saving is not an RSVP.
- Export Spreadsheet (.csv) produces quoted, UTF-8 CSV for Sheets/Excel/Numbers, including title, UTC start/end, venue, source, event URL and time note. Cells beginning with spreadsheet formulas are prefixed to prevent execution.
- Browser selection is limited to 100 event records and validated against title, timestamp, SHA1-shaped event key, and HTTP(S) URL. All saved rows use DOM textContent, not arbitrary HTML.
- The user does not need to upload a spreadsheet to get events into a calendar. Calendar apps can import the ICS file directly. CSV is optional for a personal spreadsheet.

## Visual language
Pixel headings and console labels, 8-bit-style cabinet borders, neon green/cyan/pink keylines, restrained scanlines, compact HUD strips, angular cards, distinct Tech/Culture coloring and clear button focus states. Card layout remains readable for long names and on narrow screens. Source data and filter/backend services are unchanged.

## Files
page-vancouver-events.php; assets/css/vancouver-events.css; assets/js/vancouver-events-lineup.js; inc/vancouver-events.php; scripts/theme_deploy_manifest.py; __tests__/vancouver-events-lineup.test.js; .github/workflows/vancouver-music-culture-qa.yml.

## Post-merge QA
1. Visit /vancouver-events/ and confirm zero saved and disabled exports.
2. Save one tech and one music event; reload, switch filters, paginate, check both remain saved. Same event in two feeds must count once.
3. Export ICS and import into Apple Calendar, Google Calendar, or Outlook. Compare time and location. Check door-time caveat and no invented end.
4. Export CSV, inspect Unicode, quoting, title/venue values, and event URLs in a spreadsheet.
5. Remove one and clear the rest with confirmation. Inspect the storage-disabled/private-mode state.
6. Check mobile controls, lengthy gig names, archive filtering and whether the starfield obscures text.
7. Confirm original Tech and Culture pages still work.

Cross-device synchronization and actual RSVP are deliberately not implemented.

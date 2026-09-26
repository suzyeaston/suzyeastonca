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

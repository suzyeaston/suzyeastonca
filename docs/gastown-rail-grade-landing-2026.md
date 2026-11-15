# Gastown rail-grade fidelity pass: Station East / The Landing

## Research and intent
- Waterfront Station / West Coast Express passenger platforms are west of
  the Station East lot, with the CP/CPKC N-Yard extending east.
- City of Vancouver 2022 N-Yard study identifies 20 yard tracks and a
  mainline dividing into three passenger-platform tracks.
- Station East Lot 531 at 555 West Cordova lies between historic Waterfront
  Station and The Landing, 375 Water Street.
- The user-supplied November 14 2026 views show a featureless grey apron,
  little height separation, and missing rail-side service lane / wire fence.

Sources:
- https://vancouver.ca/files/cov/2022-519-release.pdf
- https://www.translink.ca/schedules-and-maps/west-coast-express
- https://heritagevancouver.org/top10-watch-list/2021/8-555-west-cordova/
- https://dailyhive.com/vancouver/555-west-cordova-street-vancouver-the-crystal-waterfront-square-station

## Scene corrections
- Preserve existing playable surface lot (+0.19 scene metres).
- Place a non-walkable lower service alley immediately beyond its north
  boundary and behind The Landing (-0.55).
- Add independent mesh-chain-link rail fence after the alley, with corner
  points following the north and east parking lot perimeter.
- Draw two visible near-Landing rail alignments, ballast and batched
  sleepers at -1.35.
- Shift only pre-existing CPR/N-Yard railway objects and WCE passenger
  platform/train to -1.35, never the SeaBus terminal or bridge.
- Lower the enormous uniform-grey rail apron to expose the graded
  rail geometry and retain the harbour backdrop.

All measurements and road alignment are **illustrative**, not surveyed. They
are not real-world safe railway access or accessible route information.
Preserve the public lot, Waterfront building, Landing footprint and elevator,
flying controls, and the protected harbour scenic subsystem.

## QA after deployment
1. From Station East's public fence look toward The Landing. Visible order:
   parking edge, service alley, wire fence, lower ballast and tracks.
2. Pan west to the Waterfront Station WCE platforms and verify the
   train and canopy sit below the public lot.
3. Check the parking drive aisle and the original NPCs still work.
4. Walk from Cordova through the station, back to the lot and into
   The Landing/elevator, and return to the street.
5. Test SUZYFLY and the third-person camera. Inspect for clipping
   or grey artifacts and confirm no errors in the browser console.
6. Inspect the SeaBus/Canada Place sightlines, then run all CI checks.

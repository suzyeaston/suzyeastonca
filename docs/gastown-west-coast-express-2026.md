# West Coast Express at Waterfront Station: scene and QA

## Intent and geographic constraints
The screenshot reference shows the Station East surface parking fence with a blank
grey waterfront apron beyond it. The revised simulation layers an *illustrative*
Waterfront West Coast Express platform behind the existing historic station,
and three commuter tracks turning north of the Station East parking parcel.
It does not remove the parking lot, move The Landing, replace the 20-track CPR
N-Yard scene, or imply public access to railway property.

The station has a cream canopy, maroon columns, yellow tactile warning edge,
platform benches, a legible sign, and a stationary low-poly blue/yellow
F59PH-style locomotive attached to three white/navy bi-level commuter coaches.
All geometry is original, local and lightweight. Train location, fleet numbers,
platform details and rail curve are **illustrative, not surveyed or live**.

The original floating "still ticketed!" and "one star!" NPC speech sprites are
removed. Parking dialogue and character interactions are preserved.

## Reference sources
- TransLink Waterfront WCE services: https://www.translink.ca/schedules-and-maps/west-coast-express
- City of Vancouver rail yard study (WCE tracks and 20-track N-Yard):
  https://vancouver.ca/files/cov/2022-519-release.pdf
- WCE locomotive repaint: https://www.trains.com/pro/passenger/commuter-regional/vancouvers-west-coast-express-expands-service/
- User-provided aerial station/platform and fleet photography, Nov 14 2026.
No reference photography is copied into the theme.

## QA after deployment
1. Hard reload /gastown-sim/ and confirm no JS startup error; verify Suzy,
   the lot, Landing/elevator, cruise ship, Seabus, Waterfront historic frontage.
2. From the Station East fence face toward the Landing and pan toward the
   historic station. Rails, gravel, sleepers and the WCE platform should be
   legible. No tracks should intersect the parking lot.
3. Inspect the train from the rear-third-person, first-person and SUZYFLY
   camera angles. Verify two window decks, distinctive livery and canopy.
4. Approach parking NPCs and confirm the floating comic boxes are gone but
   E/interact dialogue and spoken cues remain accessible.
5. Walk around Station East and back down The Landing elevator, and check
   no camera-collision regression or visible building intersection.
6. Run npm test and the Gastown GIS QA workflow. This does not replace
   on-device browser/WebGL visual QA.

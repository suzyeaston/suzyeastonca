# Waterfront Station east arcade / Rogue / Station East lot: evidence and fidelity fix

2026-11-16. Follow-up to #809 and #810, after comparing user gameplay screenshots
with current and historical station photography and station/parking documents.

## Verified spatial relationships

1. **One Rogue restaurant covers two adjacent arched storefront bays.**
   The current reference photo shows the elongated light-filled arch passage
   **to the LEFT** of both Rogue openings when looking toward the east end
   of the concourse. They are not two separate Rogues split around a central
   West Coast Express gate. The 2018 western-wing staircase photograph looks
   east into this same architectural arrangement.
2. **Rogue is in the east wing of Waterfront Station.** Historical writing
   explicitly identifies the 2018 view as looking east, and the earlier
   lunch counter's replacement location as the eastern wing.
3. **Station East Surface Parking is beside the station to the east.**
   Cadillac Fairview's 2025 Waterfront Properties guide identifies the
   open-air lot next to The Station and specifies that its vehicular
   entrance is off West Cordova Street. The lot must not be mirrored
   geographically merely because the user is facing the left arcade.
4. **Waterfront transit entrances are distinct from Rogue.**
   TransLink's entrance-location map marks station access from West Cordova
   as well as other station access, but is *not* an indoor floorplan.

### Important confidence distinction

The photos and historical materials establish a **left arcade plus two
Rogue arches to its right**. They **do not establish** that the distant end
of the left-hand passage is an officially open, direct public exit onto
Station East Surface Parking in 2026. Therefore the simulator's handoff
from the left passage to the georeferenced side-access approach is marked
as an **illustrative game transition**, not a surveyed indoor hallway,
permitted evacuation route, accessible route, or current official entrance.

## Design changes

- The left rear arch at local x=-12 is a full opening with a short pale
  passage instead of a black wall.
- Rogue occupies the next two arches at local x=0 and x=12 as **one**
  restaurant with two storefront portals, framed beneath the heritage clock.
- The main hall no longer treats either Rogue glass storefront as a
  West Coast Express turnstile. Separate WCE circulation bends behind the
  left arcade (illustrative spur to existing platform ramp).
- A player entering the left-hand vestibule can make a clearly disclosed
  illustrative transition to the existing Station East side forecourt;
  E also works near the threshold. The lot and exterior entrance remain
  in the original georeferenced world.
- Indoor wayfinding describes the left arcade, not a misplaced Cordova
  exit; the WCE/SeaBus signs stay with their distinct transit route.
- Walking region excludes the two Rogue storefronts while allowing the
  side corridor plus WCE spur.

## Reference sources

- User-provided gameplay and station photographs (Nov 16, especially
  photos with the hall clock, adjacent Rogue portals and arched passage).
- Vancouver As It Was, "Re-Inventing CPR/Waterfront Station" (2018),
  describing the western wing staircase view looking EAST toward Rogue:
  https://vanasitwas.wordpress.com/2018/03/29/re-inventing-cpr-waterfront-station/
- VanCurious station hall photography and 1914/heritage description:
  https://vancurious.ca/waterfront-station
- Cadillac Fairview **Waterfront Properties Property Information Guide**
  (2025), under Parking / The Station East Surface Parking Lot:
  https://assets.cadillacfairview.com/m/29ef3327e7f26fac/original/Waterfront-Properties-Property-Information-Guide.pdf
- TransLink official accessible entrance map (entrances, not internal plan):
  https://www.translink.ca/-/media/translink/documents/schedules-and-maps/skytrain-accessible-entrance-maps/waterfront_station_elevator_map.pdf
- Heritage renovation archival photographs and glazing studies:
  https://srarch.net/projects/waterfront-station-renovation

## QA after deployment

1. Look east at clock: one LEFT arched walkway, two Rogue bays on its RIGHT.
2. No WCE or Canada Line sign floating above a Rogue storefront.
3. Approach Rogue doors: movement stops at glass storefront (not a train portal).
4. Enter left passage; follow the separate WCE branch and reach platform.
5. Enter the terminal end of the left passage to take the explicitly
   illustrative Station East lot transition. E also works near it.
6. Reverse: from Station East exterior re-enter its existing door and
   reconnect to the main concourse.
7. Verify compass/map remain north-aligned and lot stays east of station.
8. Verify old Cordova entrance, WCE train, roof, rail-edge, NPCs and The
   Landing journey remain functional.

## Newly located measured-plan research

Andrea Ewanchyna, *Occupying Transitional Space: An Interior Design for a
Short Stay Hotel* (University of Manitoba, 2010; Library and Archives
Canada) describes original 1914 and 2009 station main-floor plans with
key entry points, transit circulation and restaurant/retail areas
(Figures 5–6). It quotes the original waiting room as approximately
**60 ft by 150 ft**, with a **40 ft coffered ceiling**, east/west
barrel-vaulted wing corridors, and north-side transit access. This is
substantially more elongated than the simulator's approximate
44.4 m by 35.7 m hall. That deep geometry correction is **not** in
this small east-arcade PR because it would also move doors, NPCs,
camera bounds, and the WCE transition.

Source:
https://central.bac-lac.gc.ca/.item?app=Library&id=MR87182&oclc_number=1019473230&op=pdf

The thesis discusses historic plan adaptations (2009, not a current public
floorplan), and we have not verified the present-day exit's legal/public
accessibility. Preserve the confidence boundary, particularly for
gameplay transitions.

## Future fidelity

For survey-grade alignment, we need licensed indoor floorplans or on-site
photographs of **both ends of the left arcade**, ideally showing the
actual west/east door directions. If those show the side passage's public
endpoint differently, replace the illustrative transition with the
documented route, not a guessed internal shortcut.

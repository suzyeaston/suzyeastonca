# Waterfront Station / photo-fidelity follow-up (November 16, 2026)

## Why

User screenshots after PR #809 still showed dark red-brick bunker walls inside the
1914 heritage concourse, a massive blue West Coast Express canopy/sign cutting
through the room, outdoor parking instructions inside the hall, and a parking
access route that read on the wrong side relative to the photo viewpoint.

The reference photographs show a much brighter, formal hall: tall cream Ionic
columns, off-white deeply coffered ceiling, rose ornamental friezes, gold-framed
Canadian landscape panels, a round clock above the rear arches, terrazzo
mosaic floor, tall daylight windows, Rogue restaurant near the far end and a
retail/Starbucks frontage. The Cordova entrances with black domed awnings sit
toward the left **when looking back toward the street from the concourse**.

## Implemented

- Apply a true interior ivory plaster finish over the red-brick shell without
  touching the red exterior masonry, map anchor or building yaw.
- Replace dark ceiling and frieze materials with warm, light-responsive colors
  and add windows, railings, shop windows, heritage clock, and decorative
  panels based on the user's photographs and architectural records.
- Style the far left/right rear arches as warmly lit Rogue frontage instead
  of unlit black wall surfaces. The central WCE access is still usable.
- Clarify the leftward Cordova exit toward the Station East lot when facing
  back toward West Cordova; keep the **actual** lot east of the building,
  rather than reversing the city's mapped coordinates.
- Correct the exaggerated WCE roof collision with **two separate canopy
  stretches** to either side of the heritage hall. The long below-grade
  platform stays beside the staged train, but no longer projects a canopy
  and large sign into the station building.
- While the player is inside, show Waterfront Station concourse context in
  the minimap, not an outdoor 'parking lot ahead' direction.
- Add regression checks for bright plaster, wall-window / shopfront sides,
  Cordova exit guidance and canopy overlap.

## References

- User-provided Waterfront Station photos and gameplay screenshots,
  November 16, 2026, especially the cream/rose rear arcade, high clerestory
  windows and the spatial arrangement of the Cordova doors and retail.
- Soren Rasmussen Architects restoration:
  https://srarch.net/projects/waterfront-station-renovation
- City of Vancouver / heritage significance: interior Ionic columns, deep
  coffered ceiling, terrazzo, dual clocks and 1916 landscape paintings.
- TransLink station and entrance information:
  https://www.translink.ca/schedules-and-maps/skytrain
- Vancouver heritage architecture overview:
  https://www.seabc.ca/wordpress/files/notable_structures/Vancouver_Notable_Structures_IABSE_SEABC_2017.pdf

The geometry and daylight panels are original procedural art. These are
illustrative features, not copied Street View images, authentic paintings,
live tenant directory, measured interior floorplans or accessible travel
instructions.

## Visual QA after deployment

1. Reenter Cordova main doors; inspect from both ends of the hall.
2. Confirm no giant blue WCE fascia floats across the upper concourse.
3. Check warm cream walls, pink friezes, gray-blue coffer panels, painted
   landscape inserts, central clock and bright tall window silhouettes.
4. Confirm the dark exterior red brick remains unchanged.
5. Walk to the WCE transfer, platform and back; inspect roof/track clearance.
6. Leave via Cordova toward Station East parking. Confirm the leftward
   interior exit direction in the photographed viewpoint, while the actual
   east lot coordinates and side entrance still function.
7. Check the modern signage, NPCs, camera, rain, lot, Landing and gameplay.

# Waterfront Station heritage concourse + West Coast Express boarding connection
November 16, 2026 · screenshot-driven fidelity pass

## What the reference imagery shows

The screenshots provided on November 16 show two distinct spaces that should not be
flattened into one red-brick hall:

1. **Heritage CPR concourse, 601 W Cordova:** cream-painted classical columns,
   ceiling coffers and pendants, salmon/rose-toned decorative friezes, glazed
   street entrances and polished floor bands. Real people, ticket machines,
   active information panels and gate readers give the space scale.
2. **Contemporary transit interchange:** white/light roof bays and skylights,
   blue/black directional signage, wayfinding for Expo Line, SeaBus, Canada
   Line and West Coast Express; separate onward access to WCE platforms.
3. **WCE platform:** a roofed open-air commuter boarding area, yellow tactile
   boundary, maroon canopy columns and white/blue double-decker cars.

The historic ceiling and the modern boarding/transfer passages are intentionally
distinct. We did **not** copy photography or Google Maps textures.

## Implementation

- Keep the existing 1914 red-masonry exterior, Cordova entrances and Station East
  arcade; remove the *central* solid panel from the concourse's rear masonry.
- Make that central bay a real pedestrian portal, connect it by a lightweight
  illuminated sloped passage to the covered **east** end of the modeled WCE
  platform, and let the player walk either direction, without teleporting.
- Give the concourse a recognizable transit layer: colored wayfinding fascias,
  rear arcade, Compass-style reader silhouettes, vending machines, luminous
  upper glazing, flanking retail windows, more visible architectural ornament.
- Populate the public hall and covered boarding apron with deterministic
  low-poly commuter NPCs. Reuse existing animation and weather/mood filters.
- Limit the walkable boarding polygon to the **clear eastern apron**, not the
  train tracks or the existing western section of the illustrative platform
  which geometrically overlaps the heritage hall. Keep the rail yard closed.
- Make player/commuter elevations follow the sloped passage to the platform's
  -1m level; adjust collision bounds and preserve existing Station East access.

The connection is a **representational film-set route**, not certified public
access, a precise station floor plan, real Compass fare validation, an
accessible route design or live rail operations. Existing building/train
coordinate approximations remain. Exact station/track geometries need a
measured future geometry pass before treating this as a digital twin.

## Grounding and references

- Soren Rasmussen Architects' station renovation records historical arches,
  ceiling and glass elevator:
  https://srarch.net/projects/waterfront-station-renovation
- TransLink official service descriptions confirm the main Cordova entrance
  connects SkyTrain, SeaBus and West Coast Express:
  https://www.translink.ca/schedules-and-maps/skytrain
  https://www.translink.ca/schedules-and-maps/west-coast-express
- TransLink accessible entrance map (real-life route information should come
  from TransLink, **not** the game's illustrative sloped connector):
  https://www.translink.ca/-/media/translink/documents/schedules-and-maps/skytrain-accessible-entrance-maps/waterfront_station_elevator_map.pdf
- Reference photography of the hall and boarding area used for observation:
  https://www.subwaynut.com/canada/vancouver/waterfront_station/index.php
  https://structurae.net/en/structures/waterfront-station
- User-provided Google Maps Street View and gameplay captures, November 16.

## QA checklist

1. Hard reload `/gastown-sim/`: no startup JavaScript errors, no missing assets.
2. Enter through the central Cordova door and confirm cream columns,
   rose frieze, recognizable rear-wayfinding, ticket machines and passengers.
3. Walk toward the **middle** rear arch and into the illuminated passage.
   Continue onto the eastern WCE platform without pressing E or teleporting.
4. Walk back to the Cordova entrance. Verify third-person camera stays
   outside structural walls and Suzy's feet follow the transfer slope.
5. Attempt to approach the rail edge, western blocked building overlap, and
   through each side rear arch: player stays within the public walking route.
6. Confirm indoor NPCs move on hall paths, platform NPCs stay behind the
   tactile edge, and the low-graphics option still retains some commuters.
7. Recheck Station East parking, The Landing elevator/return path, and
   existing animated West Coast Express, street traffic and rain.
8. Run `npm test` and inspect the GitHub CI checks. Browser/WebGL QA remains
   necessary after deployment, particularly the ramp-to-platform sightline.

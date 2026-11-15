# Waterfront rail corridor: single topology, November 2026

## Why the previous screenshot QA failed
Three separately-authored rail systems competed for the same territory:
1. The heritage station's roughly -45 degree yaw was incorrectly applied
   to the West Coast Express line, turning its coaches and tracks toward
   the parking lot and across the freight yard.
2. Seven near-field CPR rails were projected from the short Station East
   fence's bearing, not the eastbound rail-corridor bearing.
3. The Landing-side "alley" had ungrounded coordinates (82,43) to
   (196,27), producing a long diagonal wire fence across the tracks.
   That module also added two duplicated rail alignments.
4. A single grey waterfront polygon covered everything from Z=+240
   (inside Gastown) to the harbour shore.

## Geographic controls
City of Vancouver railway hazard study:
https://vancouver.ca/files/cov/2022-519-release.pdf

TransLink Waterfront / West Coast Express:
https://www.translink.ca/schedules-and-maps/west-coast-express

555 W Cordova heritage parking lot:
https://heritagevancouver.org/top10-watch-list/2021/8-555-west-cordova/

User-supplied Google Street View photographs October 2020 and February
2022 projected through the scene's GIS origin (49.28583333, -123.11166667):
- 49.2851413, -123.1107414 → x~67.2, z~77.0
- 49.2851168, -123.1105671 → x~79.8, z~79.8
- 49.2850634, -123.1102798 → x~100.7, z~85.7

The last three points are inside the real narrow service alley immediately
along The Landing, NOT in the CPR rail corridor. Chain link should follow
its northern edge beside the red-brick utility compound. It should never
cut across the rail yard.

## Authored scene corrections
- Keep original public parking geometry, parking entrance, Waterfront
  Station, The Landing footprint, characters and elevator.
- WCE group now uses global city XY/XZ frame rather than heritage
  building yaw. Three parallel passenger tracks leave the station east.
  The low-poly train is centered on the nearest track, alongside its
  platform. Opposite-side WCE nameplates are no longer mirrored.
- CPR near-field and distant tracks both proceed east/northeast, beginning
  east of the station rather than under the lot.
- The alley module now makes a narrow route from the photographed street
  positions, a chain-link fence on the rail/utility side and a low brick
  service building, and makes ZERO duplicated railway tracks.
- The giant grey apron is confined to the industrial rail band rather
  than filling the entire city-side foreground.
- Continue the 20-track illustrative N-Yard without touching harbour
  and SeaBus components. No physics or gameplay collision changes.

The visual rail grades remain illustrative, with lot +0.19, service alley
+0.11, and rail -1.35 scene metres. These values are NOT surveyed,
not current rail operations, and do not imply pedestrian access.

## Browser QA acceptance
1. From Station East fence, look west toward the WCE platform. The train
   and canopy should be level with one another and BELOW parking grade;
   the platform should be west of the parking area, not across its centre.
2. Look toward The Landing from the parking east edge: the wall-side service
   alley, wire-mesh gate and red-brick utility shed should now be there.
3. SUZYFLY over the yard: no crisscrossed WCE tracks, no snake-like
   diagonal fence running through the tracks, and no huge grey slab.
4. Pan north: the CPR rail yard, SeaBus, Canada Place and cruise ship
   remain visible without duplicated near-field tracks.
5. Walk the lot and heritage station, interact with NPCs, then enter and
   exit The Landing via the lift, checking camera clipping and console.
6. Confirm Gastown GIS QA and production theme deployment in Actions.

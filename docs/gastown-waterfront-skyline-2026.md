# Gastown Simulator: geographically anchored waterfront skyline (November 2026)

## Why we changed it

The earlier backdrop inserted generic 50-135 m office slabs east of Water Street. Those fictional
buildings were removed in PR #756. After rendering QA, the project still lacked Vancouver-specific
buildings, while the *existing* Harbour Centre disc was incorrectly anchored to the 333 Seymour
parcel. This revision keeps the rail yard, Canada Place, SeaBus, the open Station East surface lot
and Waterfront Station, but corrects the skyline with small authored meshes.

## Architecture and geographic anchors

All geographic points use the committed civic world's `meta.gis.origin` with **+X east, -Z north**.
Coordinate points come from named historical records, project/property sources or published site
descriptions. They are anchors, **not** surveyed building footprints or height models.

| Landmark | Point (latitude, longitude) | Visual cues | Source |
| --- | --- | --- | --- |
| Harbour Centre / Vancouver Lookout | 49.284697, -123.111935 | 1977 gridded concrete shaft, 28 floors, circular observation crown and mast | https://seabc.ca/wordpress/files/notable_structures/Vancouver_Notable_Structures_IABSE_SEABC_2017.pdf ; https://harbourcentre.com/ |
| 333 Seymour | ~49.28533, -123.11246 | Southeast Cordova/Seymour corner, 17-storey stone/glass office tower | https://pci-group.com/projects/333-seymour/ |
| 320 Granville | 49.285603, -123.112846 | 2023 curved glass, staggered ribbon/silver floor trim, about 117 m, 30 storeys | https://www.kpf.com/project/320-granville ; https://mapcarta.com/W1173159900 |
| 200 Granville Square | 49.285886, -123.112977 | 1973 bronze/concrete office grid, rooftop control room, 30 storeys | https://localhistory.vpl.ca/link/buildings1036 ; https://skyscraperpage.com/b7191/vancouver/200-granville-square |
| Royal Bank Tower, 675 W Hastings | 49.285301, -123.113428 | 1931 limestone Art Deco stepped massing | https://675westhastings.com/the-building/ ; https://localhistory.vpl.ca/ |
| Sinclair Centre, 757 W Hastings | 49.285968, -123.113868 | four heritage buildings, former Post Office with 43m clock tower | https://www.sinclaircentre.com/?page_id=14 ; https://localhistory.vpl.ca/link/buildings1212 |
| 601 W Hastings | 49.28484963, -123.1128793 | 2021 25-storey glass office, uplifted plaza corner | https://pci-group.com/projects/601-west-hastings/ ; https://vancouverpublicspace.ca/inventory/601-hastings-street-at-seymour-street-princess-plaza/ |

`feature-92` becomes 333 Seymour, `feature-15` becomes 320 Granville, and `feature-16`
becomes Harbour Centre. All three retain civic parcel IDs to suppress overlapping GIS extrusions.
The four *additional* buildings are separate, original non-collidable meshes in
`js/gastown-waterfront-skyline.js`. The buildings are approximate scene representations:
do not attribute precise real dimensions, roof plans, tenancy or façade materials to this simulation.
No commercial map screenshots or stock textures ship with the theme.

### Safeguards

- World JSON, collision, mini-map, station, parking lot and rail geometry remain unchanged.
- Named scenery is independently wrapped by the hero-landmark error boundary and is not a camera collider.
- The new script is included in the production theme manifest before `functions.php` and
  registered as a WordPress dependency, preventing broken partial deployments.
- Regression tests confirm real-world projected positions and exclude station/lot/playable
  footprints, check distinctive silhouettes and a bounded mesh count, and validate the lookout
  location against the source coordinate.
- Existing tests of the landing elevator, station façade, and lot should still pass.

### Post-merge manual browser QA

1. Reload `/gastown-sim/` after deployment (hard reload / private window to clear cache).
2. At Waterfront Station on Cordova, confirm the masonry station survives, and 320 Granville
   sits west of Seymour while the blue-glass 333 Seymour block occupies its own parcel.
3. From the **Station East Lot 531** fence or `SUZYFLY`, confirm the Vancouver Lookout is
   **southeast** of the station at 555 West Hastings, not over the 333 Seymour block.
4. From a north-facing/harbour view, identify Granville Square, the stepped limestone RBC tower,
   Sinclair Centre clock tower, and 601 W Hastings as background downtown landmarks.
5. Verify no added building covers the open parking lot, 1914 station, rail tracks, cruise ship,
   SeaBus, Water Street or the North Shore sightline. If a scenic mass reads as intrusive, adjust
   only that named building's approximate massing, not the GIS anchors.
6. Check low-graphics mode, third-person camera, elevator lobby and frame rate on an 8 GB Mac.

This is a coordinate-informed visual stage, **not a measured photogrammetric city model**.

## November 15: reference-guided West Cordova / Water Street visual pass

The user compared Station East parking and the southeast-bound corridor with
July 2024 and June 2025 Street View images. The model had the correct Harbour
Centre observation tower anchor, but its long Cordova street-facing mass was
a visually blank gap. The fix adds two lightweight limestone-and-glass frontage
skins along existing City of Vancouver civic footprint feature-16 edges
(5→6 and 13→14). Sidewalk, parking lot height, lower alley, tram route,
movement and collisions are unchanged.

Additional skyline anchors, deliberately southeast of the playable corridor:

- **Woodward's W43, 128 W Cordova**, 49.28297, -123.10808: the approximately
  122-metre red-trimmed residential tower, historic brick podium and an
  illuminated-style **replica W sign on its separate steel lattice**.
  Reference: Structural Engineers Association of BC, 2017 Vancouver Notable
  Structures guide, https://www.seabc.ca/wordpress/files/notable_structures/Vancouver_Notable_Structures_IABSE_SEABC_2017.pdf
- **Dominion Building, 207 W Hastings**, 49.282902, -123.109644: the red
  and cream heritage tower and green mansard roof. Reference: Vancouver
  Public Library Local History,
  https://localhistory.vpl.ca/link/buildings1114

These are original, budgeted low-poly meshes at documented address-point
locations, not photogrammetry or exact footprint-survey reproductions.
Woodward's is not beside the Waterfront Station parking lot and may be
partially hidden by nearer buildings from the natural walking route.

### Browser QA to do after merge

1. Face south from Station East parking and confirm Harbour Centre-side
   Cordova frontage is legible, with recessed horizontal glazing rather than
   a flat grey block or floating slab.
2. Turn toward Water Street; check skyline parallax, historic streetwall,
   and Vancouver Lookout's already georeferenced circular observation crown.
3. From the Steam Clock approach, look southeast: Woodward's and Dominion
   should appear beyond the route, not next to the clock or station.
4. Confirm no new collision blockers, no shadow wedges, no missing street
   paving, and no interference with elevated parking, WCE, or the Landing.

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

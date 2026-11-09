# Gastown Waterfront fidelity: November 2026 implementation record

The project already uses +X east, -Z north, with one civic-to-scene transformation. **Do not add a second reflection.** Preserve the station, parking-lot and road anchors.

## Delivered in this initial PR

- Convert the seven modeled near-field N-Yard tracks from 24 m-spaced decorative sleepers to 0.72 m-spaced `THREE.InstancedMesh` sleepers, approximately 1,449 ties in one draw call.
- Give paired rails separate raised heads and webs so grazing light picks them out from the ballast.
- Keep the 13 additional batched tracks and west-end ladder; retain the rail-yard boundary as non-walkable.
- Add source-level regression tests for these invariants.

**Precision disclosure:** This is a rail *legibility* improvement, not a surveyed railway alignment. Existing yard geometry remains representative; the parking lot is not yet surveyed.

## Next fidelity phases (not implemented by this PR)

### 1. Station public concourse and entry
- Reference public entrances and accessibility routes against [TransLink's Waterfront entrance map](https://www.translink.ca/-/media/translink/documents/schedules-and-maps/skytrain-accessible-entrance-maps/waterfront_station_elevator_map.pdf).
- Add `js/gastown-waterfront-interior.js` for the historic public concourse with a connected doorway polygon and threshold tests. Distinguish public space from fare-paid and restricted areas.
- Refactor the existing exterior *solid wall/body mesh* at the entrance to avoid walking through the modeled facade. Integrate both walking bounds and third-person camera collision. Never claim a digital twin without a verified interior survey.

### 2. Measured architectural silhouette and Station East
- Use [City of Vancouver 2022 LiDAR](https://opendata.vancouver.ca/explore/dataset/lidar-2022/) offline to derive elevations and roof/parapet outlines. Do **not** bundle large LAS point clouds into the website.
- Use [City railway alignments](https://opendata.vancouver.ca/explore/assets/railways/) for coarse position only; they are not a track-by-track engineering survey.
- Verify Station East lot boundaries, fence, parking, accessible entrance and public right-of-way before declaring lookout coordinates exact.
- Keep rendering distant harbour scenery at reduced detail.

### 3. Water Street, Steam Clock and real audio
- Join [Vancouver storefront inventory](https://opendata.vancouver.ca/explore/assets/storefronts-inventory/) and [business licences](https://opendata.vancouver.ca/explore/dataset/business-licences/) by civic address, but do not treat parcel address points as storefront-door coordinates.
- Store facade bay spacing and current tenants in a date-stamped, reviewable frontage manifest. Flag approximations explicitly.
- Replace generic clock tones only after committing a usable local audio asset. [This Gastown Steam Clock recording](https://commons.wikimedia.org/wiki/File:Vancouver_Gastown_steam_clock_chiming.webm) is a candidate under CC BY-SA 3.0; verify author credit, adaptation attribution and the suitability of the sample before bundling.
- Record or obtain clearly licensed sounds from *Vancouver itself* for rail, bus, pedestrian, gull and rain zones. Never represent generic stock sounds as field recordings.

## Pre-merge checks

- Run `npm test` and confirm existing Gastown GIS/public-realm/harbour tests still pass.
- Confirm JavaScript parses with `node --check js/gastown-harbour-vista.js`.
- Inspect desktop Chrome/WebGL from Station East entrance, rail-yard lookout, and harbour lookout. Look for visible ballast, paired rails, dense sleepers and no railroad crossing through the accessible aisle.
- Verify the main WordPress theme deployment and absence of browser errors before calling the live site QA'd.

## Follow-up acceptance criteria

Walk from Cordova into the public historic concourse and back without snapping; distinguish real storefronts from illustrative ones; view rails from both fence viewpoints; hear *an attributed, locally served actual clock recording* only in a sensible sound zone; experience rain only when the weather state is rain.

## PR follow-up: heritage hall detail and Angel of Victory

User-supplied 2024/2025 Street View screenshots and interior photographs prompted a
visual-fidelity pass. The prior "public concourse" was a narrow, almost featureless
tube; the current model adds *procedurally authored* recessed coffer panels,
orthogonal ceiling ribs, a pink frieze, cream Ionic columns, a three-arch
terminal treatment, small clock motif and terrazzo floor bands. Central entry
stiles have been moved out of the walking gap.

An original low-poly **Angel of Victory** memorial is now east of the station
front entrance, on a granite-colored plinth with an interpretive plaque. The
sculpture shows the angel, separate raised wings, and carried fallen soldier;
it is a recognizable landmark, **not** a reproduction of the original bronze
and not a photogrammetry model. Sculpture placement is relative to the authored
station stage and still needs on-site measurement.

Grounding:
- [City of Vancouver Public Art Registry, artwork 24](https://covapp.vancouver.ca/PublicArtRegistry/ArtworkDetail.aspx?ArtworkId=24): artist Coeur de Lion MacCarthy, east of station entrance, bronze memorial.
- [Veterans Affairs Canada](https://www.veterans.gc.ca/en/remembrance/memorials/canada/winged-victory): memorial unveiled April 28, 1922.
- [SRA Architects Waterfront Station renovation](https://srarch.net/projects/waterfront-station-renovation): documented restoration of original arched openings and historic concourse.
- [Vancouver structural/heritage overview](https://www.seabc.ca/wordpress/files/notable_structures/Vancouver_Notable_Structures_IABSE_SEABC_2017.pdf): photographs and original coffered ceiling.

No Street View, commercial photography, stock model, or copyrighted third-party
texture is embedded. The hall remains representative rather than an exact
architectural plan; circulation and camera behavior require browser QA.

## Follow-up: Grand hall and Station East entry circulation

The first public hall was too narrow and shallow (about 15m × 20m). This
iteration expands the *authored* hall to 44.4m × 35.7m with an approximate
14.45m coffered ceiling, preserving the exterior's local geospatial anchor.
The east wing is hollowed at street level to let visitors walk from Station
East Lot through its side doorway into the same public hall. The side-door E
action now enters the hall directly rather than teleporting to Cordova.

The footprint and interior detailing remain **illustrative**, not measured
architectural documentation, accessible-wayfinding instructions or a verified
fire-egress plan. The east access uses the previously authored door location,
which needs a separate real-world survey. Restricted transit areas remain
non-walkable.

Sources consulted:
- [SRA Architects Waterfront Station renovation](https://srarch.net/projects/waterfront-station-renovation) describes the original restored concourse bays and glazed elevator.
- [TransLink Waterfront Station accessible entrances](https://www.translink.ca/-/media/translink/documents/schedules-and-maps/skytrain-accessible-entrance-maps/waterfront_station_elevator_map.pdf) distinguishes public station entrances from station service areas.
- [Notable Structures Vancouver (2017)](https://www.seabc.ca/wordpress/files/notable_structures/Vancouver_Notable_Structures_IABSE_SEABC_2017.pdf) depicts original hall architecture and modern renovations.

The illustrated trolley retains its grey/blue/yellow paint with small,
original locally generated `suzyeaston.ca` side advertising panels. The
advertiser panel is fictional placement, not a claim of paid transit advertising.

### Next browser QA
1. Walk continuously from Cordova through the central arch to the back of the hall.
2. Walk from Station East Lot through the glass side entrance into the hall;
   verify the E interaction also ends inside, not outside.
3. Inspect interior ceiling, upper wall geometry and third-person camera for
   occlusion and clipping from all four corners.
4. Confirm no visible solid wall still blocks the side corridor or Cordova
   doorway and the trolley text is legible from each side.

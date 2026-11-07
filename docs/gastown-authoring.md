# Gastown authoring notes

## Waterfront public realm and map alignment (November 7, 2026)

**Modeled scene:** Waterfront Station (601 W Cordova), Station East surface lot (555 W Cordova / Lot 531), then The Landing / Kelly-Douglas Warehouse (375 Water Street). The parking site remains a surface lot in this simulation; a 2026 office-tower application is **not** evidence that a building was completed.

**References:** [Heritage Vancouver, 555 W Cordova](https://heritagevancouver.org/top10-watch-list/2021/8-555-west-cordova/), [Impark Station East surface lot](https://impark.com/vancouver-tourism/), [Vancouver Heritage Foundation, The Landing](https://www.heritagesitefinder.ca/location/375-water-st-vancouver-bc), and [City development application](https://www.shapeyourcity.ca/555-w-cordova-st).

The civic map now anchors `world.landing` to City 2015 outline `object_id=6506600`. The parking polygon is explicitly **approximate**, drawn between the station frontage and The Landing. Parked vehicle positions, curb controls, door/window designs and surfaces are representational, not surveyed or live. The station model's footprint remains stylistically authored using the civic facade position and heading, not a photogrammetry scan.

**Critical geometry correction:** City local projection uses +X east and +Z north; Three.js `ShapeGeometry` / `ExtrudeGeometry` rotated by `-Math.PI / 2` requires using shape coordinates `(x, -z)` to avoid a north/south mirror. The building renderer uses `rotation.y = -yaw` when source GIS yaw is a conventional +X-to-+Z angle. For a Three.js group rotated around Y by `theta`, local `(x,z)` maps to world `(x cos theta + z sin theta, -x sin theta + z cos theta)`. Both station pedestrian paths and collision/visual geometry must share that transform.

The minimap keeps player position centered, applies **one metres-per-pixel scale** to both axes, draws its true-north cardinal labels and a measured 25 m scale bar, and shows an arrow derived from the same camera yaw as the visible first-person scene. The static legend lists only colors that the map actually draws. The station/lot/Landing/clock anchors are the only named context markers at this scale, reducing clutter.

**Mouse controls:** click the 3D world to capture the cursor; **Esc releases pointer lock**. A persistent instruction sits inside the view, and a release control is available when pointer lock is active. The tutorial repeats this explicitly.

Run `node --test __tests__/gastown-waterfront-public-realm.test.js` as well as `node --test __tests__/gastown-gis-alignment.test.js`. Test geometric alignment separately from a real Chrome/WebGL visual inspection.

## Civic GIS alignment (November 2026)

The production world is now rebuilt by `scripts/rebuild-gastown-civic-world.js` from the **cached** City of Vancouver public street centerlines and 2015 approximate building outlines. The normal `scripts/generate-gastown-world.js` pipeline delegates to that civic builder when the expected streets are present.

The authored walk follows the connected named segments `600 W CORDOVA ST`, `500 W CORDOVA ST`, and `200-300 WATER ST`. The emitted walk corridor is roughly 292 metres from the station-view start to the Water/Cambie intersection, with the Steam Clock anchored to the published WGS84 location of 49.2843841111, -123.10889. The **building footprint** and **street centerline** datasets are separate sources, so make sure they are both projected through `data/reference/route-anchors.json` before drawing or measuring geometry. In scene coordinates, +X is east and +Z is north. The minimap is north-up unless the user toggles it.

The station colonnade anchor uses City 2015 frontage outline `object_id=6563395`, and the adjacent main station block `object_id=6562725` is excluded from generic massing to prevent the hero model being hidden under black boxes. The camera is spawned on the opposite West Cordova sidewalk and faces the real frontage orientation. For testing run `node --test __tests__/gastown-gis-alignment.test.js`.

**Fidelity limits:** Open Data street centerlines are approximate rights-of-way, not pedestrian wayfinding guarantees. The 2015 building footprints have changed since capture. Street surface widths, building heights, exact entrance door dimensions, incidental street furniture and NPC movement remain stylized estimates. This project is a place-based simulation, not a certified GIS or accessible routing map. The scene does not use live positions of actual people.

Authoritative source pages:
- [City public streets](https://opendata.vancouver.ca/explore/dataset/public-streets/)
- [City building footprints 2015](https://opendata.vancouver.ca/explore/dataset/building-footprints-2015/)
- [TransLink Waterfront Station](https://www.translink.ca/translink/schedules-and-maps/skytrain)

## Waterfront Station exterior (2026 station-first scene)

The entry scene now has an original procedural model in `js/gastown-waterfront-station.js`, staged across the near-side Cordova sidewalk from the starting camera. It is a **recognizable, reference-led interpretation**, not a survey-accurate 3D scan or verified GIS footprint. Do not advertise exact dimensions or precise spatial placement.

Reference-led characteristics include the original CPR red-brick Beaux-Arts facade, cream Ionic columns and their capitals, projecting stone cornices, upper sash windows, arched wing glazing, the incised railway name, three modern black dome awnings reading THE STATION, and glazed main entrance doors. The entrance doors are the street-level portals. Actual fare gates are indoors and are **not** depicted as an outdoor fence.

The model uses original procedural brick texture painting. The production position is now georeferenced against the cached civic facade footprint; the fallback spawn-relative staging is retained only for legacy synthetic scenes. No third-party reference photos are copied into runtime assets. Its `anchorFor(world)` stages the facade facing the existing world spawn, while `arrivalYaw(world)` and `arrivalPitch()` initially point the player at the doors and inscription. `isWalkableForecourt()` lets the player walk up to the entrance without opening the interiors; it stops at the doorway so the station is not a ghost wall. The model owns eight short, reversible commuter patrols in the forecourt, fed into the existing NPC walking/stride system; low-graphics mode limits that population.

For the next fidelity pass, replace the temporary local-stage coordinate offset with verified building footprints and an exterior survey/photo-matched view. The current long Water Street corridor remains unchanged.

Visual/reference sources:

- [Cadillac Fairview, The Station](https://www.cadillacfairview.com/office/properties/the-station/): street-side facade and canopies.
- [TransLink, station map](https://www.translink.ca/translink/schedules-and-maps/skytrain): station entrance and transit connections.
- [SRA Architects, Waterfront Station renovation](https://srarch.net/projects/waterfront-station-renovation): historic entrance glazing and restoration notes.


These notes cover lightweight world authoring for the Gastown First-Person Simulator.

## Props

Add lightweight street clutter in `assets/world/gastown-water-street.json` under `world.props[]`.

Use this shape:

```json
{ "id": "example-prop", "kind": "trash_bag", "x": 0, "z": 0, "y": 0, "yaw": 0, "scale": 1 }
```

Supported starter `kind` values are:

- `trash_bag`
- `cardboard_box`
- `newspaper_box`

The simulator batches each kind with `InstancedMesh` for fewer draw calls.

## NPCs

Add simple NPCs in `world.npcs[]`.

Use this shape:

```json
{
  "id": "example-npc",
  "role": "pedestrian",
  "patrol": [
    { "x": 0, "z": 0 },
    { "x": 4, "z": 0 }
  ],
  "interactRadius": 2,
  "dialogId": "example_dialog"
}
```

`role: "pedestrian"` expects 2 to 4 `patrol` points and will loop through them. `guide` and `busker` can use `idleSpot` for stationary placement.

Dialog text lives in `assets/dialog/gastown.json`. Match an NPC's `dialogId` to a key in that file.

## Textures

Ground textures load from:

- `assets/textures/cobblestone/`
- `assets/textures/concrete-slabs/`

Each texture set uses local `albedo`, `normal`, `roughness`, and `ao` maps.

## Civic data world build

To refresh cropped City of Vancouver civic exports and rebuild the simulator world JSON in one step, run:

```sh
npm run build:gastown-world
```

The build-time pipeline queries the City of Vancouver Opendatasoft Explore API v2.1, caches corridor-sized exports under `data/cov/`, writes `data/cov/_manifest.json`, and regenerates `assets/world/gastown-water-street.json` without calling external APIs from the browser.

Set `COV_INCLUDE_BUSINESS_LICENCES=true` if you also want the optional `business-licences.json` cache refreshed during the build.

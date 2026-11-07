# Gastown authoring notes

## Waterfront Station exterior (2026 station-first scene)

The entry scene now has an original procedural model in `js/gastown-waterfront-station.js`, staged across the near-side Cordova sidewalk from the starting camera. It is a **recognizable, reference-led interpretation**, not a survey-accurate 3D scan or verified GIS footprint. Do not advertise exact dimensions or precise spatial placement.

Reference-led characteristics include the original CPR red-brick Beaux-Arts facade, cream Ionic columns and their capitals, projecting stone cornices, upper sash windows, arched wing glazing, the incised railway name, three modern black dome awnings reading THE STATION, and glazed main entrance doors. The entrance doors are the street-level portals. Actual fare gates are indoors and are **not** depicted as an outdoor fence.

The model deliberately uses original procedural brick texture painting. No third-party reference photos are copied into runtime assets. Its `anchorFor(world)` stages the facade facing the existing world spawn, while `arrivalYaw(world)` and `arrivalPitch()` initially point the player at the doors and inscription. `isWalkableForecourt()` lets the player walk up to the entrance without opening the interiors; it stops at the doorway so the station is not a ghost wall. The model owns eight short, reversible commuter patrols in the forecourt, fed into the existing NPC walking/stride system; low-graphics mode limits that population.

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

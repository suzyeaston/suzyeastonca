# Gastown authoring notes

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

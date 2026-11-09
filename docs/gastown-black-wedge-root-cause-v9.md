# Gastown v9: the black wedges were upright road decals

## Root cause

The world JSON has `streetscape.surfaceBands: []`, so `addGround()` generates dark route-aligned fallback pavement and wheel-track meshes along the entire 17-point Water/Cordova route.

Every mesh begins life as a flat `THREE.PlaneGeometry`. The prior code used:

```js
paver.rotation.x = -Math.PI / 2;
paver.rotation.y = band.yaw || 0;
```

Three.js `Euler` defaults to `XYZ` order, which applies the Y turn *before* the X flattening. For diagonally oriented streets, those dark asphalt rectangles acquire a sideways normal and stand partially upright. Viewed in perspective, their sides become huge black/charcoal diagonal wedges crossing footpaths, buildings, skyline and Suzy's portrait shots. Camera occlusion work in v7/v8 could never correct source geometry.

## Fix

Apply the pavement transform with explicit `YXZ` order, flattening around X first and then rotating around world Y. Preserve texture, colors, opacity, existing road/decal sizing and world data. Tag the meshes `Street-level surface band: ...` so subsequent diagnostics can identify them.

## Automated regression

`node --test __tests__/gastown-ground-plane-rotation.test.js`

Covers all real street headings and ±90° headings. Exercises the actual runtime rotation expression and confirms the plane normal points straight up. Demonstrates that the old default `XYZ` transform would tilt the plane. Also checks the real world relies on fallback surface bands and that the JavaScript still parses.

## Browser QA after merge

1. Starting at Waterfront Station, turn toward the Station East parking lot. The yellow clearance gantry, cars, and station frontage should be visible without black fins between them.
2. In Back / Follow, Face / Front and first-person, walk alongside Waterfront Station and The Landing.
3. Walk east on W Cordova and Water Street toward the Steam Clock. Verify no black planes occlude storefronts or the sky.
4. Look back west near the Steam Clock; compare against the 18:14 screenshots.
5. Check that the asphalt overlays still render *on the road*, not hovering over it, and that the cruise ship and SeaBus remain visible.

No live browser rendering was performed as part of this source-only pass. Visual QA is intentionally deferred until after merge.

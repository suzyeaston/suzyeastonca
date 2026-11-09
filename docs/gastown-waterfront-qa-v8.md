# Waterfront Station QA: camera and east-side access (v8)

Reference: November 8 screenshot review and the **Gastown Simulator Waterfront QA – Remaining Issues** visual board shared in the project conversation.

## Implemented

1. **Back/Follow snap to overhead:** lower and cap the camera's vertical angle, preserve and ease the active shoulder, and avoid the old rapid left/right candidate flip near walls.
2. **Front-facing black occluder:** check nearby opaque building faces from much closer to Suzy instead of ignoring anything within 72 cm. Ignore glazing/paving/decorative details as blockers. Shorten the boom before the lens gets buried in a masonry shell.
3. **Parking-lot side access:** add an identifiable east-side station glass entrance, stone surround, awning, marked pedestrian apron, and an exterior walking connector toward the Cordova frontage. Press **E** near the side doorway to move to the modeled front entrance.
4. **Geography:** reuse the existing civic-world station and Station East lot anchors; do not use copied Google Street View textures or pretend the station interior is modeled.

## QA after deploy

- At 601 W Cordova, face north and south, then switch repeatedly between **Face / Front** and **Back / Follow** while walking beside the station. Suzy's head and orange checkerboard Vans bag should stay within a normal third-person frame without bouncing overhead.
- Walk alongside the nearby glass/stone building massing. Watch for any remaining dark diagonal planes across the front-view camera, and capture angle, position and mode if they persist.
- Approach the station's east side from Station East parking. The entrance should have a recognisable doorway and pedestrian paving. **E** near the side access should link to the Cordova entrance without trapping the walker in a building wall.
- Confirm the lot, rail yard, Inlet, SeaBus and Canada Place sightline continue to render. Run first-person movement and map zoom as a separate regression check.

**Limitations:** this is a stylized model, not an architectural survey. The east-side connector leads to the exterior front entry; no station interior or real-world live routing is claimed. Browser visual review is deliberately post-merge.

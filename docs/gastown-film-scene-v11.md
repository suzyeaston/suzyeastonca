# Gastown film set v11: from concept to playable world

This work advances the **interactive** Waterfront Station / Station East film set. The polished
concept render is a visual target, **not** a production 3D screenshot or a GIS survey.

## Shipped in this pass
- Four low-poly moving road cars, two existing stylized Route 50 buses, and one slowly
  moving car in the open parking aisle. Motion is scripted, not live traffic data.
- Existing lot NPCs, high-five encounters, Suzy's smile and heritage facades remain.
- Procedurally generated, self-contained background traffic/waterfront air,
  gull calls and a distant low horn. No new remote recordings, API calls or
  unknown-language voice clips. Starts after a user gesture and can be muted.
- NPC speech only selects an English installed OS voice, preferring en-CA.
  If none is available, visual interactions still work in silence.
- Remove the generic SkyTrain-rumble substitution for the commuter mood and
  distant transit zone; retain the verified bundled rain fallback.
- New regression coverage for animated vehicles and audio controls.

## Scene boundaries and QA
- Current pedestrian/vehicle paths are **illustrative**. They do not assert
  right-of-way, transit timetables or verified public parking access.
- This is a local browser world, not a copy of Google Maps/Street View.
- A static concept image cannot replace live geometry, camera behavior or
  operating ambience without breaking the simulator's filming workflow.
- The image's high-detail heritage station, flowers, foliage, bus destinations,
  wet pavement and photoreal lighting are future art-direction tasks.

After deployment: hard refresh, visit the Waterfront/Station East camera;
rotate toward West Cordova and look for animated buses and road cars, then
watch the single slow car enter the lot. Click the scene to unlock sound;
toggle “Vancouver ambience” and listen for a quiet traffic bed and intermittent
gulls. Interact with “nice chat” or “high five?” walkers. Test full-screen,
map zoom and 8GB Mac performance. Verify that sound *stops* when muted.

**References already in the simulator:** City of Vancouver open-data world build,
Waterfront Station and Station East lot documentation, Heritage Vancouver's
555 W Cordova listing, and Kelly-Douglas Warehouse / The Landing sources.

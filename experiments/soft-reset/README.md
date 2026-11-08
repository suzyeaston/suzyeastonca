# SOFT RESET
### A 60-second film in a city that learns to make art, not war.

**Film Club challenge:** *Make love, not war* (working interpretation; confirm the exact theme in the club WhatsApp).  
**Screening:** Thursday, November 12, 2026.  
**Submission target:** MP4, <= 60 seconds. The published Film Club rule says Monday at midnight before the event: November 9. Check directly if an extension has been announced.

## One-sentence story

In the Station East parking lot above the working Vancouver waterfront, a musician watches an AI-accelerated future turn the city into a war machine. A human note interrupts the cycle. The machine does not disappear: it learns another use for its intelligence.

**Not a remake of Grimes' "Accelerando":** use its artistic questions, not its recording, choreography, imagery, or distinctive shots. Original composition and visuals.

## One location. Five transformations.

| Time | Act | Visual | Audio |
| --- | --- | --- | --- |
| 00–10 | The world | Station East parking lot; chain-link fence, rail yard, ships, rain | field ambience, sparse human motif |
| 10–24 | The machine wakes | cranes become articulated objects, ships split into mechanical geometry, clouds flicker | Magenta pulse rises, mechanical percussion |
| 24–38 | War | impossible industrial silhouette, shadow fleets, red-black storm; do not show actual harm to identifiable people | layered tension, distortion, accelerating percussion |
| 38–48 | A human frequency | hard cut to Suzy in frame, voice or guitar; animation stops at the first note | silence, breath, a single live note |
| 48–60 | The harbour learns to sing | the same cranes turn into instruments; mechanical parts move together; light grows from rubble | Magenta changes harmony in response to the human motif; finish unresolved |

The love ending is **behavioral**, not merely a palette swap: machines cooperate instead of attack, objects generate art rather than damage, a human is still directing the exchange.

## Reuse the actual code

**World:** `page-gastown-sim.php`, `js/gastown-sim.js`, `js/gastown-waterfront-places.js`, `js/gastown-harbour-vista.js`, `assets/world/gastown-water-street.json`.

- The built-in **Fence lookout** is the camera seed. Don't add a new geospatial anchor.
- The existing harbour is procedural and contains a representative vessel, not a live ship schedule.
- Three.js transforms should be separate overlays/groups. Don't corrupt the geographic source or turn the speculative sci-fi overlays into a factual city claim.
- **First vertical slice:** one locked camera, 5 world-state snapshots, 60-second score. No player movement or NPC systems needed for the film.
- Keep the main website untouched until a browser QA check and review. This branch is development-only.

**Scene timing:** `cues.js` exports `sample(seconds, {bass,mids,highs})`. The return values are continuous 0..1 controls for future renderer bindings. No model inference, sound output, rendering, and **no connection to the live simulator yet** are claimed.

Suggested adapter: a `filmDirector` attached after `addHeroLandmarks(world)` (the runtime's initialization builds the working scene); call `sample(time, energy)` just before `renderer.render(scene, camera)`. Scene changes should be reversible and activate only in an explicit film mode. Drive all visual animation from the requested film time for deterministic retakes.

## Sound plan: human composer + local Magenta

Use the **Magenta RealTime 2 Small** app or AU plugin in Logic at 48 kHz on Apple Silicon. It is already a playable local instrument, not something the website needs to host.

1. Record a 60-second **original** human motif (voice, guitar, bass or keyboard).
2. Play MIDI steering into Magenta and print two or three **audio takes**, rather than requiring live inference while Three.js renders on an 8GB machine.
3. Export `human.wav`, `magenta.wav`, `ambience.wav`, and a quick `mix.wav` from Logic or your established AU host. Keep a backup.
4. Cue the hard silence at 38 seconds and the first voice at 41 seconds. Let the final harmony start as a response, not as a triumphal soundtrack.
5. Use Web Audio FFT or precomputed envelope data to map bass to mechanical motion, mids to sky instability and highs to bloom. Don't mistake FFT response for Magenta generating visuals.

No need to build a second music app on deadline day. After delivery, build the separate **Accelerando-inspired original instrument** around the same semantic cue protocol and reuse the existing local Magenta host.

## Put Suzy in the world

Capture 2–3 short, clean-background performances on a phone:
- side profile at the fence, or equivalent isolated performer plate;
- one deliberate medium shot performing a note;
- one extreme close-up of a hand, mouth, or instrument.

Composite these over screen-recorded simulator footage (mask/key in your editor). **Use your actual recorded likeness and performance**, not an AI recreation of Grimes and not a guessed synthetic Suzy identity.

## First task, before anything else

Open the existing Gastown simulator and select **Fence lookout**. Verify the shot can see some meaningful combination of fence, rail yard, harbour and sky. Record a **10-second clean plate at 1080p**, preferably with the minimap/HUD hidden or cropped for the final edit.

If this baseline isn't compelling, fix the camera/framing first. Don't build a second skyline until we have an original shot worth transforming.

## Test the cue math

```bash
node --test __tests__/gastown-film-cues.test.js
```

## Deliverable checks

- 60 seconds or less, one MP4, playable locally.
- Starts in an identifiable Gastown waterfront environment.
- Human performance and original audio carry the emotional turn.
- AI generated music clearly has a defined, documented role.
- Films the **same space** in five states, rather than relying on random disconnected AI clips.
- Maintains separate notes/credits for human performance, original code, Magenta MRT2 model and other actual generation/compositing tools.

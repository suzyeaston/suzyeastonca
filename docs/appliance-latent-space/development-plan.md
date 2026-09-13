# Development plan

Each phase has to be shippable and tested before the next one starts. Nothing here is a schedule.

## Phase 1 — the control language

Define the vocabulary and prove it resolves, with no page and no sound.

1. `control-map.json`, `js/appliance-control-map.js`, these documents, and their tests. **← current step**
2. `page-appliance-latent-space.php` and `assets/css/appliance-latent-space.css`. Static markup, inert controls, bidirectional parity test against the JSON.
3. `functions.php` enqueue and `se_appliance_control_map()`, `THEME_FILES` entries in `scripts/theme_deploy_manifest.py`, and a page line in `scripts/setup-local-wp.sh`.
4. `js/appliance-latent-space.js`: pointer and keyboard input resolved through the control map into control events, shown in a status readout. Still silent.
5. A Web Audio test-tone engine behind the control-event path. Oscillators and gain only.
6. Add `/appliance-latent-space/` to `criticalPages` in `tests/e2e/site-smoke.spec.js`.

Exit condition: the browser instrument makes controllable sound from both pointer and keyboard, with no new failures in the test suite. A `/projects/` link goes in at that point, not before.

## Phase 2 — Web MIDI

Feature-detect `navigator.requestMIDIAccess`, translate incoming messages into the same control events, and degrade silently when the browser or the permission says no. `resolveMidiBinding` is already tested, so the adapter stays thin.

## Phase 3 — the appliance

Source a toaster. Map its real dials, switches and sensors onto the existing control ids by filling in the optional `hardware` block. Firmware presents it as a class-compliant USB MIDI device so it needs no driver and no browser exception.

## Phase 4 — the laptop system

The performance and audio rig that the toaster plays. Out of scope for the repository until it has something to version.

## Phase 5 — the latent space

Model inference, and the point at which `neural_mix` stops being aspirational. Nothing about it is decided.

## Standing constraints

- No frontend framework, no CDN dependencies, native Web Audio only.
- No microphone capture and no uploads. Everything stays in the browser.
- Existing production functionality stays untouched. No unrelated refactors, including to Loop Lab.
- Any new file under a deployable path must be added to `THEME_FILES` in the same commit.

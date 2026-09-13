# The Appliance Latent Space

A public creative-technology project inside the `suzyeastonca` theme.

The finished piece is three things sharing one control vocabulary: a repurposed toaster acting as a USB MIDI instrument, a laptop performance and audio system, and a browser instrument at `/appliance-latent-space/`. The physical appliance and the browser have to speak the same language, so the language is being defined first.

## Status

Phase 1, in progress. There is no toaster yet.

What exists:

- `assets/data/appliance-latent-space/control-map.json` — the shared control vocabulary
- `js/appliance-control-map.js` — validation and binding resolution, no DOM, no audio
- `__tests__/appliance-latent-space.test.js` — automated tests for both

What does not exist yet: the page template, the audio engine, Web MIDI, any hardware, any model.

## Documents

- [architecture.md](architecture.md) — how the parts fit and what is deliberately absent
- [control-map.md](control-map.md) — the control vocabulary and its schema
- [development-plan.md](development-plan.md) — phases and their exit conditions
- [build-log.md](build-log.md) — dated entries, newest last

## Running the tests

```bash
node --test __tests__/appliance-latent-space.test.js
```

The full suite is `npm test`. It has pre-existing failures unrelated to this project, and it mutates tracked files under `data/cov/` and `assets/world/` as a side effect, so run the file above during development and check the full suite against its known baseline before committing.

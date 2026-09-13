# Architecture

## The idea in one paragraph

A control is a logical name, not a knob. `browning` is a control. The dial on the toaster, the slider in the browser, the `b` key and MIDI CC 21 are all *bindings to* `browning`. Every binding produces the same event, and only that event reaches the audio engine. If that holds, the toaster and the browser are the same instrument with different front ends.

## Layers

```text
input sources          binding resolution         state + audio
-------------          ------------------         -------------
pointer  ─┐
keyboard ─┼─────────▶  appliance-control-map.js  ─────▶  control event  ─────▶  audio engine
Web MIDI ─┤            (pure, testable)                  { id, kind, group,
appliance ┘                                                value, source, at }
```

`js/appliance-control-map.js` is deliberately free of DOM and audio. It parses and validates the map, resolves a keypress or a MIDI message to a control, clamps values, and builds control events. That is why hardware firmware can mirror it later, and why the contract is unit-testable in Node with no browser.

Everything that needs a browser lives outside it.

## Files

| Path | Role |
| --- | --- |
| `assets/data/appliance-latent-space/control-map.json` | Source of truth for the vocabulary |
| `js/appliance-control-map.js` | Validation and binding resolution (UMD, pure) |
| `__tests__/appliance-latent-space.test.js` | Automated tests |
| `page-appliance-latent-space.php` | Page template and control surface markup |
| `assets/css/appliance-latent-space.css` | Page styles, scoped under `.appliance-page` |
| `js/appliance-latent-space.js` | DOM wiring and Web Audio engine (not built yet) |

## The control surface

Controls are native form elements, not styled divs. Continuous controls are `<input type="range">` with `min`, `max`, `step` and `value` taken from the map. Momentary and toggle controls are `<button type="button">`, and toggles carry `aria-pressed`. Groups are `<fieldset>` with a `<legend>`, so the four groupings are real structure rather than headings that look like structure.

Every interactive element carries `data-control="<id>"` and nothing else does — readouts use `data-control-readout` instead. That keeps the attribute unambiguous as the thing JavaScript binds to, and lets the tests treat it as the template's claim about which controls exist. The parity tests run that claim against the JSON in both directions, so a control cannot be added to one without the other.

Keyboard hints are shown twice on purpose: a visible `<kbd>` chip that is `aria-hidden`, and the same key named in the hint text that `aria-describedby` points at. Screen readers get it once, sighted players get the chip. The space binding renders as `SPACE`, never as a literal space.

All CSS is scoped under `.appliance-page`, including the bare-element and pseudo-element rules that style the sliders. A test walks every selector in the stylesheet and fails on any that could reach the rest of the site.

## How the map reaches the browser

PHP reads `control-map.json`, decodes it, and hands the parsed map to the page inline with `wp_localize_script`, the same way `se_get_asmr_visual_registry()` feeds ASMR Lab. No runtime fetch, no loading state, no failure path — the instrument is playable on first paint.

`wp_localize_script` casts top-level scalars to strings, so the map is nested one level down inside the localized object. Numbers inside the nested structure keep their types.

## Conventions this follows

- UMD module wrapper matching `js/loop-lab.js` and `js/gastown-dialog.js`: CommonJS export for tests, global for the browser. No ES modules, no bundler.
- Template-gated, `filemtime`-versioned `wp_enqueue_*` in `functions.php`, matching `se_enqueue_loop_lab_assets()`.
- Tests run on Node's built-in runner over `__tests__/**/*.test.js`. Pure functions get unit tests; templates and sources get read from disk and asserted against.
- Runtime files must be listed in `THEME_FILES` in `scripts/theme_deploy_manifest.py`, or the production deploy job fails on any push to `main` that touches them.

## Deliberately absent

No React, Vue, or any other frontend framework. No Tone.js, no Three.js, no CDN dependencies. No microphone capture. No neural inference, no RAVE, no model of any kind. No hardware firmware. Native Web Audio only.

The name promises a latent space. Phase 1 delivers the control surface for one. Page copy should not imply a model is running, because none is.

# Build log

Dated entries, newest last.

## 2026-09-13 — 000 // Before the toaster

There is no toaster. That is the point of this entry.

The piece needs a physical appliance, a laptop system and a browser instrument to behave like one instrument. The usual way to get that wrong is to buy the appliance first, wire whatever dials it happens to have, and then bolt a browser version onto the shape of somebody else's product decisions from 1997. The vocabulary ends up describing a toaster instead of describing music.

So the software and control language get defined first, while nothing physical exists to bias them.

The vocabulary is semantic: `browning`, `latent_x`, `latent_y`, `neural_mix`, `memory`, `destruction`, `plunge`, `capture`, `freeze`, `kill`. Ten controls, each naming a musical behaviour. No crumb tray. `browning` survives from the appliance world because it is genuinely the right word for how hard a signal is cooked, not because a toaster has that dial.

Hardware metadata is optional and currently unset on every control. When the appliance is sourced, its dials and switches bind to these ids. The ids do not move.

Committed in this entry: the control map, the pure resolution module, these documents and their tests. No page, no audio, no MIDI, no model.

## 2026-09-13 — 001 // Ten controls, nothing behind them

The control surface exists at `page-appliance-latent-space.php`. Ten controls in four fieldsets, and not one of them does anything.

They are native elements. Sliders are `<input type="range">`, gestures are `<button>`, groups are `<fieldset>` and `<legend>`. Nothing is a div pretending to be a knob. That decision is cheap now and expensive later: keyboard access, focus order and screen reader semantics all arrive for free, and step 4 gets to wire behaviour onto controls that already work rather than rebuilding them.

The slider `min`, `max`, `step` and `value` are copied from the control map, and a test fails if they drift from it. Same for which controls exist, which group they sit in, and which key they claim. The template and the JSON cannot disagree quietly.

The page says what it is: control surface only, no audio engine, no model running. A test bans the page from ever claiming a model *is* running while still letting it say one isn't.

Still no sound. That is next.

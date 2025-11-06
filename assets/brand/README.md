# Brand Assets

These files power the arcade-inspired site logo:

- `suzanne-logo.svg` – the primary neon badge used in the header.
- `brand-logo.css` – scanline/flicker effects and responsive sizing. Loaded globally via `functions.php`.
- `scripts/export-logo.mjs` – optional helper script (run locally) for exporting PNG variants.

To swap logos in templates, wrap the SVG (or an `<img>` referencing it) in an element with the `brand-logo` class so the effects apply. Reduced-motion users automatically get a static version without animation.

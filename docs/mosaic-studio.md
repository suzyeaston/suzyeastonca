# MOSAIC / production deployment on suzyeaston.ca

MOSAIC is a separately maintained [public GitHub repository](https://github.com/suzyeaston/mosaic-studio).
The WordPress theme keeps a **vendored snapshot** of seven source files in `assets/mosaic/`
so users can use the studio at `https://www.suzyeaston.ca/mosaic/` without GitHub Pages, another
host, or downloading any project zip. The route is rendered through `page-mosaic.php`.

## Update source files
1. Review the latest tested `main` revision in `suzyeaston/mosaic-studio`.
2. Copy `index.html`, `styles.css`, `core.js`, `effects.js`, `patterns.js`, `instruments.js`, and `app.js` into `assets/mosaic/`.
3. Run `node --test __tests__/mosaic-integration.test.js`, `php -l page-mosaic.php`,
   and `python3 scripts/theme_deploy_manifest.py validate`; merge the PR only after QA.
4. Theme deployment copies these assets along with the page. Check that GitHub Actions succeeded
   and verify the production URL from a real browser.

## Permissions, privacy, and recording limitations
- Camera/mic need HTTPS or localhost and browser permission. The page provides a
  fullscreen URL as a fallback if embedded capture is blocked.
- The iframe is **same-origin** to the WordPress site; it uses `allow="camera; microphone; autoplay"`.
- The recorder uses local IndexedDB for takes, with media in the visitor's browser,
  and does not upload media to WordPress. Browser storage may be evicted.
- This is a browser prototype, not a real-time distributed audio workstation; device-specific
  tests for sync, export and browsers must accompany public QA.
- Audio/video saved at a different origin (e.g. github.io or localhost) do not automatically
  migrate to suzyeaston.ca. Download copies of important takes before switching origins.

## Visual effects / social sharing (2026-11)
- The vendored `effects.js` module applies full-grid effects to both the Play All preview canvas and local composite export.
- The WordPress copy appends a static `?v=fx2` query to internal assets to invalidate old cached front-end files; update on each future release.
- Social sharing uses a user-initiated browser-native file share when available. Otherwise the exported video is downloaded and the user manually uploads it. No social OAuth tokens or remote media uploads.
- Saving edits/FX changes clears any previous export's share-ready state so stale media isn't shared by mistake.
- The `mosaic-studio` repo remains the source of truth, with this WordPress theme serving the published snapshot.

## Fast grid controls (2026-11)
- Click an empty tile's + to select and start the normal camera/microphone/optional count-in flow immediately.
- Tap the actively recording tile to Stop & Keep without scrolling; sidebar controls still work.
- Every saved take has an × in its top-left. It is a separate button (not nested inside another button), confirming and deleting only the indexed take and its IndexedDB record.
- Internal assets use `?v=tiles3` to invalidate caches. Future vendor updates should increment the version.
- QA: macOS Chrome/Safari; confirm × does not trigger the recording button and works for 2×2 and 3×3 layouts. Ensure short takes still loop as before.

## MP4-first social export (2026-11)
- Prefer a genuine H.264/AAC MP4 composite from the visitor's MediaRecorder, then generic MP4, then WebM. Camera/microphone source takes are unchanged.
- The downloaded filename extension always reflects the actual MediaRecorder.mimeType. Renaming WebM to MP4 is deliberately prohibited.
- The format note explains whether MP4 is supported, and fallbacks explain how to get a social-ready copy using another current browser or a local conversion.
- Internal asset URLs bump to `?v=mp4-4` to avoid stale cached JavaScript at the site URL.
- Test on macOS Chrome and Safari with sound, video playback, and Instagram/YouTube upload. This update does not add server or WebAssembly transcoding; true MP4 availability depends on the visitor's browser encoder.

## SUZY//AI crossover, Stage 1 (2026-11)
- The self-hosted app now includes `assets/mosaic/instruments.js`. Its Web Audio instrument voices and independent-loop approach adapt from SUZY//AI Film Club browser audio and TOASTER loop-rack design, without Magenta or network APIs.
- Three playable sounds (Keys, Bass and Pad), an eight-note button/keyboard control surface, and up to 12 separately muted, mixed and removable event layers.
- Compact note events are saved to browser localStorage. Existing camera/mic takes stay in IndexedDB. The shared Web Audio instrument bus is added to composite export and regular playback; audio-only instrument arrangements export with the unfilled video grid.
- Browser transport scheduling is best-effort, not DAW sample-accurate. Real-device Chrome/Safari listening and sound-in-MP4 QA are still needed.
- Internal static assets now use `?v=instrument5` to invalidate old CSS/JS caches. Bump this on each release.
- Source attribution and the original license notes are in the `suzyeaston/mosaic-studio` repository's `NOTICE`.
- Future Stage 2: audio-reactive visualizer layered over video or video-free graphics. No visualizer or model generation is claimed for this PR.

## Ready-made musical loops and rhythm sequencer (2026-11, v0.5)
- Original beat-note and instrument-phrase patterns are contained in self-hosted `assets/mosaic/patterns.js`, loaded before `instruments.js`. All sound is synthesized locally in Web Audio; no samples or model weights.
- Six ready-made loops: three drum rhythms, plus a bassline, key arpeggio and pad harmonic pattern. Each can be created as a standalone 1-, 2-, or 4-bar track from the existing BPM.
- The collapsible 16-step beat editor toggles kick/snare/hat. Adding its pattern stores and loops it just like an instrument layer.
- Each new pattern layer keeps the BPM in effect at creation; already committed and previously recorded layers are NOT silently time-stretched when the count-in BPM slider changes. Use a consistent BPM while layering.
- Patterns loop through the existing instrument bus, which participates in playback, video overdub monitoring, and browser-local MP4/WebM export. Existing stored layers and video takes are preserved.
- Integration regressions verify preset IDs, bar seconds, beat events, and browser asset references. Manual browser audio QA remains necessary.
- Cache busting uses `?v=loops6`. Future revisions should increment the query string across the self-hosted assets.

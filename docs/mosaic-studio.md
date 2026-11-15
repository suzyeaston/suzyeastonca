# MOSAIC / production deployment on suzyeaston.ca

MOSAIC is a separately maintained [public GitHub repository](https://github.com/suzyeaston/mosaic-studio).
The WordPress theme keeps a **vendored snapshot** of eleven source files in `assets/mosaic/`
so users can use the studio at `https://www.suzyeaston.ca/mosaic/` without GitHub Pages, another
host, or downloading any project zip. The route is rendered through `page-mosaic.php`.

## Update source files
1. Review the latest tested `main` revision in `suzyeaston/mosaic-studio`.
2. Copy `index.html`, `styles.css`, `core.js`, `effects.js`, `patterns.js`, `soundbank.js`, `instruments.js`, `timeline.js`, `scenes.js`, `audio-loops.js`, and `app.js` into `assets/mosaic/`.
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

## Local sound bank and video/audio timeline (2026-11, v0.6)
- Better original Web Audio sounds in self-hosted `assets/mosaic/soundbank.js`: warm Silk Pad replaces the harsh previous Soft Orbit sawtooth patch; two local timbres each for Keys, Bass, Pad and drum kit.
- Visitors can preview **one bar** using Hear Loop/Hear Beat before committing an independent musical clip. This output-only preview never reaches the export bus; selecting Add commits a loop as before. Browser-local source recordings remain unchanged.
- The audio/video arrangement timeline is modelled in `assets/mosaic/timeline.js` and displayed under the video grid. It shows repeating takes, instrument note-event marks and the master playback position, with inline mute and music-layer removal. It's a track visualization, not a video/audio clip editing DAW.
- Save per-layer sound patch IDs with locally stored note events; older saved layers default to a valid warm patch.
- `?v=sound7` cache busts all script/style assets, including the new modules. The theme deploy manifest explicitly uploads soundbank.js before instruments.js and timeline.js before app.js.
- User device QA still needed for sound quality, mobile preview, recording, preserving local clips, and real-time MP4 export.

## Live-looper synchronization and compact musician mixer (2026-11, v0.7)
- The musical transport uses a common AudioContext clock and a 30ms scheduling interval; the visual timeline uses the same clock as the instrument engine. Previously async backing-media startup happened **after** the new camera recorder started, making the recorded take lag.
- The camera now starts after playback of existing material has started; when instrument loops exist, Stop & Keep continues until the next musical bar at the arrangement BPM, then records a bar-aligned looping length. Discard remains immediate.
- New hand-played notes are time-stamped against the main transport clock and grouped to whole bars. Live-added instrument clips arm on a future beat boundary outside the lookahead window. Their start offsets are preserved in localStorage and shown in the timeline.
- The instrument rack has **Loop Music / Stop Loops** controls and autoplays the first added loop, even without any video. Hear Loop/Hear Beat repeat until stopped, but never enter the export bus until the user explicitly adds the loop.
- The site interface is denser, darker and focused on performance. The video grid occupies less vertical space, live mixer controls are prominent, and the second set of per-layer sound controls is tucked into expandable Sound Mixer details.
- Internal asset URLs now use `?v=sync8` to avoid stale JS/CSS. All files are still self-hosted and uploaded through the existing theme manifest, with no account, model or cloud audio dependency.
- **Limitations:** The shared-clock changes reduce typical phase error but cannot guarantee sample-accurate recording across MediaRecorder, browser video codecs and different hardware. Audio/video playback and MP4 export still need real device QA.

## Non-destructive performance scenes and audio overdubs (2026-11, v0.8)
- The WordPress-hosted studio now includes self-hosted `assets/mosaic/scenes.js` and `assets/mosaic/audio-loops.js`. The first is a saved musician-owned mix snapshot deck with Verse, Chorus and Bridge; the second records independent microphone phrases.
- A three-scene deck stores mute/volume snapshots of the original video, synthesized notes and microphone lanes. Scenes are cued on the next bar while playing, or selected immediately when stopped. Save Current Mix persists the snapshot locally without re-recording clips.
- **Video timeline mute no longer rebuilds the grid or stops the live audio**. Existing recordings retain their original blobs.
- Audio-only overdubs use 1, 2, or 4 bar microphone recordings without occupying a video tile. Saved clips are encoded as MediaRecorder audio, decoded into Web Audio, scheduled per musical bar to avoid independent HTMLAudioElement drift, and mixed into the export destination. A dedicated IndexedDB `audioLoops` store is added via a schema v1 → v2 migration, preserving existing IndexedDB `clips`.
- Asset cache versions `?v=scenes9`; deploy manifest explicitly includes the two modules. Site integration QA verifies scene cues, stored mic loops and exported audio.
- Limitations: the scene deck changes live playback but is **not yet a recorded song-arrangement exporter**. The standard video export reflects the active scene/mix, not all live scene switches. Browser microphone latency, decoding and video rendering still require device-level QA.

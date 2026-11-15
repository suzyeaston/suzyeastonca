# MOSAIC / production deployment on suzyeaston.ca

MOSAIC is a separately maintained [public GitHub repository](https://github.com/suzyeaston/mosaic-studio).
The WordPress theme keeps a **vendored snapshot** of five source files in `assets/mosaic/`
so users can use the studio at `https://www.suzyeaston.ca/mosaic/` without GitHub Pages, another
host, or downloading any project zip. The route is rendered through `page-mosaic.php`.

## Update source files
1. Review the latest tested `main` revision in `suzyeaston/mosaic-studio`.
2. Copy `index.html`, `styles.css`, `core.js`, `effects.js`, and `app.js` into `assets/mosaic/`.
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

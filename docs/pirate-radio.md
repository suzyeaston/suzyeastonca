# Suzy Pirate Radio: independent station (November 2026)

The homepage feature and existing \`/radio/\` page are a receiver for **Suzy Pirate Radio**, not a directory of other people's stations. No tracks, recordings, community stations, maps, or simulated broadcasts are auto-played.

## What is real now

- \`radio-station/\` already includes the original Node single-source MP3 relay, an authenticated Python/FFmpeg microphone/file transmitter, a local receiver, tests and a Caddy HTTPS deployment example.
- The WordPress home card and \`/radio/\` page now share a listener that plays the actual configured public MP3 stream on a user click.
- The relay's \`/status.json\` controls the ON AIR / OFF AIR indicator and listener count. If status is unreachable, the UI says it **cannot verify** transmitter state.
- With no public stream URL configured, the Listen button stays disabled. There is no fake live feed.
- Admin paths, settings and the existing \`/radio/\` slug remain stable. The old published YVR directory is no longer rendered. Existing field recording posts/options are not deleted.

## Publish the transmitter

1. Deploy \`radio-station/\` to a persistent host **separately** from WordPress, following [the relay README](../radio-station/README.md). This has not been done by the site update.
2. Set up a radio subdomain pointing to that host, obtain HTTPS through Caddy, and verify \`https://YOUR-HOST/status.json\` reports \`{"online":false,"listeners":0}\` while offline.
3. In WordPress → **Settings → Pirate Radio**, set the public stream URL to \`https://YOUR-HOST/live.mp3\` and the public status URL to \`https://YOUR-HOST/status.json\`. Only public URLs belong here; never put the source token in WordPress or JavaScript.
4. Use the private source token on your Mac with \`transmit.py\` for a live show. Verify status turns online, press Listen on the public page from another device/network, and then stop transmitting to check offline behavior.
5. Purge WordPress/CDN caches to publish changed settings or templates.

The existing production workflow deploys **WordPress theme files only** on a push to \`main\`. GitHub does not provide live shell access, DNS changes, public stream server deployment, or source secrets via these theme commits. The internet-only station is not an FM/AM transmitter.

## Testing

- \`node --test __tests__/pirate-radio-static.test.js\`
- \`node tests/radio-player.e2e.cjs\` after installing Playwright Chromium
- \`node --test radio-station/server.test.mjs\` (FFmpeg exercises the full MP3 path)

A green browser test uses a mocked transmitter status, **not** a real public broadcast. Always run an actual live source-to-listener test before declaring the station on air.

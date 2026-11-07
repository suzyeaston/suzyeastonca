# Gastown simulator audio assets

The outdoor simulator uses **only real sound files verified on the deployed WordPress theme filesystem**. Files in this folder are optional. If a file is missing, the client must not try to fetch it or guess an alternative extension.

`functions.php` builds the `seGastownSim.audioSources` allowlist from readable files. The browser's `setupAudio()` loads only those returned URLs through Howler. Existing theme assets provide two optional fallbacks:

- `beds/commuter_mix` and `zones/distant_transit` use `assets/audio/yvr/skytrain-rumble.mp3` when a bespoke Gastown file is unavailable.
- `weather/rain_pavement` uses `assets/audio/yvr/rain-loop.mp3` when a bespoke file is unavailable.
- Missing night, nightlife, eerie, thunder, or other zone sounds are silent until recordings exist. The WebGL walk, weather graphics, NPCs and synthesized Steam Clock sounds continue functioning.

## Add original ambience

Optional supported stems (prefer `.mp3`, then `.ogg`):

```
assets/audio/gastown/beds/quiet_night.mp3
assets/audio/gastown/beds/commuter_mix.mp3
assets/audio/gastown/beds/nightlife_hum.mp3
assets/audio/gastown/beds/eerie_drones.mp3
assets/audio/gastown/weather/rain_pavement.mp3
assets/audio/gastown/weather/thunder_roll.mp3
assets/audio/gastown/zones/distant_transit.mp3
assets/audio/gastown/zones/steam_clock_mech.mp3
assets/audio/gastown/zones/street_crowd.mp3
```

Add new media to `scripts/theme_deploy_manifest.py` alongside the other deployed assets, and verify it exists on the production theme **before** trying to load it. Prefer original or properly licensed sound. Never replace missing files with dummy MP3s: silent, explicitly optional audio is better than perpetual 404 requests.

The browser-side regression is `node --test __tests__/gastown-audio-assets.test.js`.

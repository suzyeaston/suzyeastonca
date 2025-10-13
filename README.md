# Suzy's Retro Arcade – v3.0
A custom WordPress theme powering [suzyeaston.ca](https://suzyeaston.ca), complete with games, music tools and a dash of civic spirit.

## What's Included
- Retro pixel-art inspired design with neon CRT vibes
- **Canucks Puck Bash** – an 80s style hockey game built with HTML5 canvas
- **Riff Generator 8000** for instant rock, punk, metal or jazz riffs
- **Track Analyzer** for quick MP3 vibe checks using OpenAI
- Album reviews of classic jazz and rock discovered during Suzy's HMV days
- **Albini Q&A** widget that channels legendary producer Steve Albini via OpenAI
- Music releases, livestream schedules and a "Now Listening" section featuring a static YouTube embed
- Downtown Eastside advocacy section and notes on Suzy's possible 2026 city council run
- Custom REST endpoints for Canucks news and betting odds
- **Lousy Outages** arcade‑style status board with homepage teaser
- "Buy Me a Coffee" buttons to support Suzy's work
- Mobile friendly with drag & tap controls

## Lousy Outages refresh
The neon dashboard now streams live provider data from `/api/outages` on load and every five minutes (override with the `OUTAGES_POLL_MS` env var or the `lousy_outages_interval` option in wp-admin). Each request fan-outs to the official status APIs with 10s timeouts, caches the merged JSON in a transient for ~90 seconds and then renders per-incident drawers with start time, latest update/ETA and impact badges. Albini-style snark is generated client-side with speech synth support when enabled, and the homepage teaser reuses the same copy: “Check if your favourite services are up. Insert coin to refresh.”

The legacy REST endpoint at `/wp-json/lousy-outages/v1/status` still works and returns the expanded payload, while the new `/api/outages` edge route emits uncached JSON with `Cache-Control: no-store`. WordPress cron keeps polling in the background—run `wp cron event run lousy_outages_poll` if you need to warm the datastore manually.

The status arcade now opens with an "at-a-glance" headline that calls out any degraded providers, links straight to the custom RSS feed at `/outages/feed/` and highlights the alerts inbox (`suzanneeaston@gmail.com`) so you can wire it into whatever mail filters you prefer. Unknown telemetry also surfaces in the banner, making it obvious when a provider's API stops responding.

## Get alerts on your phone
- **RSS**: Subscribe to https://<your-site>/outages/feed in any mobile RSS app (NetNewsWire, Reeder, Feedly). You’ll get a push/badge when incidents publish.
- **SMS (optional)**: Enter your Twilio SID/Auth/From and your phone under Settings → Lousy Outages. Use “Send Test SMS” to verify.

## Track Analyzer
Uploads are sent to OpenAI's Whisper and GPT‑4 APIs for a quick analysis of your
MP3. Results appear with a fun retro overlay and clear loading indicators. For
production use, consider exposing this feature through a custom REST endpoint
and caching results in case OpenAI is unavailable.

## About Suzy Easton
Suzy is a Vancouver-based musician, technologist and all-around creative builder. She toured Canada playing bass, recorded with Steve Albini and once appeared on MuchMusic. These days she writes album reviews of jazz and rock classics found during her time at HMV (2007–2012). Inspired by the single "A Little Louder" airing on CiTR, she's crafting new hard rock demos. Suzy loves hockey, punk rock, 8-bit aesthetics and fixing things that break in production, and she's considering a run for Vancouver City Council in 2026.

## License
MIT License. Play nice.

# Suzy Pirate Radio

Our own small internet-radio transmitter and relay. One authorised source sends
128 kbps MP3; many listeners hear the same live programme. Built from Node's HTTP
server, a Python source client and FFmpeg's audio encoder. No hosted-radio account
is needed. This is an internet station, not an FM/AM or amateur-radio transmitter.

## First show on your Mac

Install Node 22+, Python 3 and FFmpeg. If FFmpeg is missing: `brew install ffmpeg`.
From the checked-out repository:

```bash
bash radio-station/setup-local.sh
cd radio-station
node --env-file=.env server.mjs
```

Leave that running. In another terminal, enter the same `radio-station` directory:

```bash
python3 transmit.py --list-devices
python3 transmit.py --mic 0
```

Replace `0` with the **audio device index** for your Focusrite or microphone from
the device list. macOS may ask Terminal for microphone permission. Audio input
begins only when you run this command; Ctrl-C stops it. The relay does not record.
On Linux use an ALSA device name, for example `--mic default`.

Or send an original recording or music file you have permission to broadcast:

```bash
python3 transmit.py --file /path/to/your-original-recording.wav
```

Open <http://127.0.0.1:8788/> and press **Tune in**. Keep headphones on while testing
a microphone to avoid feedback. To broadcast Logic output, route the intended
mix to a loopback/input device and choose that device explicitly. Nothing here
captures system audio automatically. File mode is one file per show, with no
AutoDJ, playlist scheduler or automatic restart.

`setup-local.sh` creates a random source token in a private `.env`, preserves it
on reruns, and never prints it. `.env` is ignored by git and the Docker build.
The token is read from `.env` or the environment; it is never passed in FFmpeg's
arguments, URLs or browser JavaScript. The relay binds to loopback by default.

## Connect it to suzyeaston.ca

The WordPress player is ready to consume the stream, but a public HTTPS relay
has to exist first. A visitor's localhost is their own device, not your Mac.

For an always-on station, use a dedicated server that supports Docker or a
persistent Node process. Ordinary WordPress FTP access cannot run this service.
This repository's existing theme deployment intentionally does **not** launch
Docker, edit DNS, open ports or deploy `radio-station/` onto the WordPress host.

A dedicated-host deployment example is included:

1. Copy this directory onto the new host and run `bash setup-local.sh` (requires
   local Node/Python), or create `.env` with a random `SOURCE_TOKEN` of at least
   32 characters using your server's secret-management process.
2. Add `RADIO_DOMAIN=radio.your-domain.example` to that private `.env`. Replace
   the example with your actual hostname; point its DNS at this host.
3. Ensure ports 80 and 443 are available. Do not run this Compose file on an
   existing WordPress server already using those ports.
4. Run `docker compose config --quiet`, then `docker compose up -d --build`.
   Caddy terminates HTTPS and forwards streaming responses immediately.
   HTTP/1 full-duplex is enabled for the source authentication handshake.
   Avoid CDN/proxy caching or buffering of `/source` and `/live.mp3`.
5. Keep the matching source token in your Mac's private `radio-station/.env`.
   Run `python3 transmit.py --server https://YOUR-RADIO-HOST --mic 0`.
6. In WordPress **Settings → YVR Radio**, enter:
   - Community stream URL: `https://YOUR-RADIO-HOST/live.mp3`
   - Relay status URL: `https://YOUR-RADIO-HOST/status.json`
7. Purge the radio page cache. The station appears as **Suzy Pirate Radio**.
   Test listening from a different network before inviting listeners.

The browser shows transmitter status separately from local playback status.
“Transmitter online” means the relay is receiving bytes; it cannot prove that
your mic contains audible sound. Offline listening returns HTTP 503 and the
player offers Retry. Press it after the show starts.

## Protocol and limits

| Endpoint | Purpose |
| --- | --- |
| `GET /` | Minimal local test receiver |
| `GET /status.json` | Public online state and listener count; no source token |
| `POST /source` | Authenticated, chunked `audio/mpeg`; `Authorization: Bearer …` |
| `GET /live.mp3` | Public live MP3; available only while a source sends audio |

- One source at a time. A second gets 409; wrong authentication gets 401.
- Default maximum 30 listeners, configurable with `MAX_LISTENERS`.
- A 20-second source idle timeout releases the source slot and ends listeners.
- Slow listeners are disconnected once their queued bytes exceed 256 KiB.
- No rebroadcasting of third-party scanner/station feeds, uploads, file archives,
  listener accounts, public mic button, call-ins, WebRTC or RF transmission.
- Experimental small-station software. Run a real-duration soak test before a
  scheduled show. Docker/TLS deployment and Mac mic permissions require a test
  on the chosen host/hardware. Browsers introduce latency; this is not an intercom.
- 30 listeners at 128 kbps use about 3.84 Mbps / 1.73 GB per hour before overhead.
- Node/Caddy image tags are major-version tags; resolve to reviewed digests for
  a reproducible production deployment. Keep the host and runtime patched.

Tests: `node --test radio-station/server.test.mjs` from the repository root.
The FFmpeg integration test creates a temporary test tone, transmits it through
our actual Python client, receives the relay output and decodes it. That tone
is never added to the public Vancouver sound catalogue.

Next: scheduled original sets, source reconnect, a show manifest, explicit live
location metadata, and a separately moderated low-latency voice room.

Technical references:
- https://nodejs.org/api/http.html
- https://ffmpeg.org/ffmpeg-devices.html (AVFoundation and ALSA)
- https://caddyserver.com/docs/caddyfile/directives/reverse_proxy
- https://caddyserver.com/docs/caddyfile/options#enable_full_duplex

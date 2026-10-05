# YVR Radio v2 — the map returns; our transmitter begins

## Changes

The first radio update removed the map and retained unrelated recorded loops.
This release restores geographic listening and removes all those loops from
the app. The default view contains Vancouver-area sources; Salish Sea expands
to the Washington hydrophones, labelled with their actual locations.

MapLibre / OpenFreeMap pins select the same receiver as the station list.
Sources at nearby coordinates share a chooser. Search/category filters also
filter the pins. A source's location text distinguishes an approximate studio
area, broad listening area or recording location; these are not RF coverage
maps or live tracking coordinates. Unverified BC wildfire entries whose old
coordinates all pointed to the same place are excluded from this directory.
The community station has no invented position: it remains in the list until
we implement explicitly configured broadcast-location metadata.

## Real Vancouver audio

One real Vancouver archive recording is bundled: Joshua May’s Gastown steam
clock recording dated 2012-08-30, extracted to MP3 from the Commons video under
CC BY-SA 3.0. It has source, credit, capture date and licence links in the player.
See [recording attribution](vancouver-audio-attribution.md). It is not presented
as Suzy’s recording or a live feed. The release also adds an admin-managed
collection for the recordings we actually make:

1. Upload your audio in WordPress **Media** and note its attachment ID from the
   edit URL (`post=123`).
2. Open **Vancouver recordings → Add a Vancouver recording**.
3. Give it a title, audio attachment ID, place, latitude/longitude, capture date,
   recordist/permission credit and permission confirmation. Publish.
4. Purge the radio page cache. It appears under **Vancouver recordings** and as
   a star pin at its capture location, with date and credit in the receiver.

Validation requires a real WordPress audio attachment, valid date, complete
provenance, permission confirmation and coordinates inside a conservative Metro
Vancouver bounding box (49.0–49.5, −123.5–−122.3). Incomplete records stay off the
public map even if their post is published. Files play once rather than looping
by default. Public field recording locations should be places you're comfortable
publishing; do not use a private residential position.

Possible first captures: Jericho waves, a SkyTrain journey, a public street
soundscape or your own performance. The software does not invent our future recordings
or claim we have already captured them. Old attributed sample assets remain
in source history but are not offered by the radio app.

## Our internet broadcast

See [`../radio-station/README.md`](../radio-station/README.md) for a working local
relay, macOS/Linux microphone transmitter, original-file transmitter, local
receiver, tests and a dedicated-host HTTPS deployment example. WordPress gets
a new optional status URL field for separate on-air / listener-count reporting.
This is a tested local prototype, not a running public station yet.

## Fixes to v1 checks

The previous browser test assumed that Chromium could not play HLS natively.
It now explicitly sets browser capability in the unsupported scenario and also
tests the native-supported path. The real browser run also exposed malformed
category `<option>` markup; that is fixed. Browser screenshots are retained as
CI artifacts. The earlier successful deployment did not prove those controls.

## Deployment and rollback

The PR uses the existing production theme workflow. New PHP dependencies and
map JS are in the explicit deployment manifest. No radio server, DNS changes,
paid resources or source credentials are deployed automatically.

After merge/deploy: open `/radio/`, check Vancouver pins, choose CiTR via its pin,
filter by community radio, switch to Salish Sea, and test on a phone. If old code
persists, purge page/CDN cache. Publish one real field recording to check the
admin-to-map path. The map depends on MapLibre CDN, OpenFreeMap tiles and WebGL;
if unavailable, the station list remains usable.

Revert this release commit to return to v1's directory. New field recording
posts/media and station settings remain in the database; nothing deletes them.
Stop a separately deployed relay with Ctrl-C or `docker compose down`.

## Verification / scope

Local: actual MP3 path, auth rejection, source locking, listener limits, idle
cleanup, bounded output queues; PHP parser and JS/Python syntax; deployment
manifest; receiver browser tests and MapLibre pin/region/search/mobile checks. Real
OpenFreeMap tiles also loaded in the local browser preview. WordPress runtime/admin interactions need
post-deployment verification. The public HTTPS server is not provisioned by this
PR. Original live radio feeds remain provider-owned; scanner/hydrophone buttons
open provider players rather than transmitting or recording their feeds.

Location references checked:
- https://www.citr.ca/contact/ (UBC campus; approximate campus pin)
- https://coopradio.org/contact/ (370 Columbia Street)
- https://www.cjsf.ca/contents/contact-us (SFU Burnaby SUB 1420)
- https://live.orcasound.net/listen/bush-point
- https://live.orcasound.net/listen/mast-center
- https://openfreemap.org/quick_start/

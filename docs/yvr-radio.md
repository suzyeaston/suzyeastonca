# YVR Radio — implementation and rollout

> Current update: see [YVR Radio v2](yvr-radio-v2.md) for the restored map, real
> Vancouver recording collection and working local internet broadcast stack.
> The v1 notes below are historical; its recorded loops and map removal have
> been superseded.

Prepared 5 October 2026 for suzyeaston/suzyeastonca.

## What was actually wrong

The production broadcaster JavaScript matched the repository when inspected.
`isAudioPlaying()` only checks `!paused && !ended`; that can be true while audio
is buffering. The static homepage “on the air” label, decorative bars, native
0:00 duration and independent loading labels can contradict each other.
The old scanner fallback can silently change marine/fire channels to aviation.
The catalog also describes a Tokyo train recording as SkyTrain and a recorded
steam whistle as a ferry horn. These are recorded loops, not live local feeds.

## This release

- Publishes `/radio/` once after the theme deploy, unless the slug already exists.
- Replaces the large homepage player/map with an explicit YVR Radio launch card.
- One receiver: Connecting, Buffering, Playing, Paused, Stopped, Ended or Failed.
- A 20-second connection/buffering timeout; explicit retry; no silent fallbacks.
- Search, categories, browser-local favourites, volume and shareable station URLs.
- CiTR AAC stream; CFRO and CJSF official player links; existing CBC/CKNW streams.
- Scanner and hydrophone entries open provider players, clearly labelled. These
  are directory entries, not verified in-page streams. No provider scraping.
- Recorded loops retain source links and credits with corrected descriptions.
- Community stream URL and optional public room URL in Settings → YVR Radio.
- Native WordPress message board: site login, all new messages moderated,
  newest 30 approved messages public. No new accounts or memberships are created.

This first release replaces the old interactive map with a usable directory;
it does not implement a new map, live chat, voice calls, recording, song metadata,
RF transmitting, a broadcast server or a DCTRL-branded service. The original
radar JS/CSS/catalog remain in the repository for future map work.

## Run the provided bash script on your Mac

`bash ~/Downloads/upgrade-yvr-radio.sh --push`

Requires git, Python 3 and GitHub CLI (`gh auth login`), plus git commit name/email.
The script makes a new isolated checkout under `~/Projects`, applies the bundled
patch, validates the deployment manifest and creates a branch and pull request.
It does not merge or deploy. Without `--push`, it prepares the local commit only.
The patch contains the actual implementation: no AI or external generated patch
is downloaded at runtime. It aborts if it cannot safely apply to current main.

Review and merge the PR when ready. The existing **Deploy to Production** workflow
uploads theme files. It still needs the existing `LOUSY_SSH_*` repository secrets.
No password should be pasted into chat, the script or a commit.

After deployment:
1. Open WordPress once to trigger the one-time page creation, then `/radio/`.
2. If `/radio/` already existed, its content/template is not changed. In Pages,
   edit it and select **YVR Radio** yourself, or resolve the slug collision first.
3. Check the page is published and the homepage card appears. Purge host/CDN
   cache if the old layout persists; asset versions use file modification times.
4. On desktop and your phone, test CiTR, a recorded loop and a provider link.
   Test blocked/offline playback, Stop, switching channels, volume 0 and Retry.
5. Test a message with a non-admin site account, approve it under Comments, then
   confirm it is visible signed out. If comments are disabled globally or by a
   plugin, enable comments for this page. This release follows site login policy.
6. Set the community HTTPS *listener* URL only after the server exists. Browser
   HLS playback needs appropriate CORS headers; native AAC/MP3 support varies.
   Do not put source credentials in the listener URL. Purge page cache after
   changing settings so the public station list refreshes.

“Playing” reports media playback events, not a signal-level measurement. Quiet
scanner periods are normal; the app does not use fake level meters. This page
stops audio on navigation. Favourites stay in the current browser, not an account.
CiTR and CKNW returned HTTP 200 audio and CBC returned HTTP 200 HLS playlist
data in the preparation check. This is not an audible playback verification.
Future feed reachability is not guaranteed; links provide recovery paths.

## Our own station, in stages

**Now:** WordPress hosts the directory, player and moderated return messages.
Use your original music, conversations and experiments for a first show.
No private Telegram membership information or invite links are embedded.

**Local proof of concept:** Run Icecast on a laptop or a separate development
machine, connect a source client such as Mixxx or BUTT, and test from another
LAN machine. A localhost URL refers to each listener's own device, so do not
put it into the public WordPress configuration. A public HTTPS site will not
reliably play an HTTP LAN feed. Use a local test page until TLS/routing exists.

**Public station:** A dedicated streaming host or VPS runs Icecast/AzuraCast.
Your Focusrite/mic/DAW feeds a source client; the browser receives the public
HTTPS listener mount. WordPress handles the website. AzuraCast adds AutoDJ,
playlists, schedules and streamer accounts. Its documented minimum is Docker,
2 GB RAM and 20 GB storage. FTP/file-manager access cannot establish whether
shared hosting supports persistent services, inbound ports or Docker. Check
those hosting capabilities before choosing same-server deployment. Don't run
an AzuraCast installer over the existing WordPress host without that assessment.
At 128 kbps, 20 listeners consume roughly 2.56 Mbps of outbound audio payload
(about 1.15 GB/hour), before overhead: streaming bandwidth is a separate budget.

**Talk back:** Keep the message board for asynchronous requests and show ideas.
A future WebRTC room can add low-latency voice, push-to-talk, host moderation,
explicit microphone permission and guest admission. Icecast is one-to-many;
adding a microphone button alone does not make it a two-way voice system.

**Next product work:** Accurate station-location map (not invented coverage),
feed health history, show schedules in Vancouver time, now-playing metadata,
opt-in listening parties, community submissions and an original-sounds archive.
Any provider audio stays at its source unless redistribution is authorised.

## Rollback

Revert this release commit through a new PR and merge it to redeploy the previous
homepage. Because deployment does not delete new files, remove the generated
radio page from public view in WordPress (set Draft), and remove its template
selection if needed. The new PHP module is no longer loaded after revert.
Settings, favourites and comments are not deleted by a code rollback. Keep them
until you decide whether to reuse them. The deploy script also backs up touched
server files; see `docs/deploy-production.md`.

## Sources checked

- https://player.citr.ca/ — official AAC URL, https://live.citr.ca/live.aac
- https://coopradio.org/ — official CFRO station and player
- https://www.cjsf.ca/ — official CJSF site links to /streaming
- https://www.azuracast.com/docs/getting-started/requirements/
- https://www.icecast.org/docs/icecast-latest/basic_setup/

## Validation limits

JavaScript syntax, PHP parsing and deploy-manifest consistency are checked during
preparation. 15 DOM/media-state assertions passed with simulated events. Browser visual checks
could not run here because the Chromium download failed; a Playwright check is
included in the PR workflow. These tests exercise state changes;
they do not prove all external feeds are audible. Production WordPress creation,
moderation plugins, server permissions and an actual broadcast require the
post-deploy checks above. No live website changes were made while preparing.

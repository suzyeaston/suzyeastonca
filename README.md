# Suzy's Retro Arcade – v4.0
A custom WordPress theme powering [suzyeaston.ca](https://suzyeaston.ca) — part retro arcade, part creative-tech lab, part Vancouver build-in-public experiment.

Current highlights:
- **Gastown First-Person Simulator**, a live Vancouver prototype evolving in public
- **Track Analyzer** for MP3 vibe checks
- **Lousy Outages**, an arcade-style status board
- **Albini Q&A** / "What Would Steve Do" style experiments
- **ASMR Lab**, the earlier audio/visual prototype that helped inspire the Gastown simulator and is now under major redevelopment

The repo is open source, the process is visible, and the goal is to keep building playful, useful, and strange little things that invite people in. We rise together.

## What's Included
- Custom WordPress theme with neon CRT/pixel styling and arcade energy
- Homepage + project templates for live creative-tech experiments
- Gastown simulator page and supporting systems for rapid iteration
- Track Analyzer workflow for AI-assisted feedback on uploaded MP3s
- Lousy Outages dashboard and teaser components
- Experimental voice/tool pages, including Albini Q&A style workflows
- Legacy + rebuilding ASMR Lab experience (kept online while redevelopment continues)

## Current flagship / live experiments
### Gastown First-Person Simulator
The current flagship prototype. A first-person Vancouver corridor build focused on Waterfront Station → Water Street → Steam Clock, with ongoing updates to atmosphere, navigation, and scene detail. It changes often because it is built in public.

### Albini Q&A and related experiments
"What Would Steve Do" style tools are still part of the ecosystem: quote-grounded prompts, commentary workflows, and playful interfaces that test how voice, archives, and creative tooling can coexist.

## Lousy Outages
**Lousy Outages** remains active as the arcade-style status board.

- `/api/outages` provides live provider pulls with `Cache-Control: no-store`
- `/wp-json/lousy-outages/v1/status` remains available for legacy consumers
- Polling cadence can be tuned with `OUTAGES_POLL_MS` or the `lousy_outages_interval` option
- Background refresh can be warmed manually with `wp cron event run lousy_outages_poll`

It is intentionally practical but still fun: command-line vibes, alert hooks, and very online reliability energy.

## Track Analyzer
**Track Analyzer** is still in rotation for MP3 uploads and quick AI-assisted mix feedback. It is designed as a fast creative checkpoint for musicians who want signal, not fluff.

## ASMR Lab status
**ASMR Lab** is an earlier audio/visual experiment that got weird enough to help inspire the Gastown simulator.

It still matters, it is still in the repo, and parts are still playable — but it is currently under major redevelopment.

## Gastown world build pipeline
To refresh the cropped City of Vancouver civic exports and rebuild the simulator world JSON in one step, run `npm run build:gastown-world`. This build-time pipeline queries the City of Vancouver Opendatasoft Explore API v2.1, caches corridor-sized exports under `data/cov/`, writes `data/cov/_manifest.json`, and then regenerates `assets/world/gastown-water-street.json` without calling external APIs from the browser. Set `COV_INCLUDE_BUSINESS_LICENCES=true` if you also want the optional `business-licences.json` cache refreshed during the build.

## Open source / build in public
This repo is open source: <https://github.com/suzyeaston/suzyeastonca>

Expect frequent updates, rough edges, and visible iteration. If something looks off right after a deploy, hard refresh, clear cache, or pop into incognito and try again.

Community, collaboration, and shared momentum are core to the project. If you want to contribute ideas, report bugs, or remix experiments, you are welcome here.

## About Suzy Easton
Suzy Easton is a Vancouver-based musician and creative technologist working at the intersection of music, software, civic curiosity, and internet-era storytelling. Touring, recording, and production culture still inform the build style: direct, experimental, and always in motion.

## License
MIT License. Build kindly.

## Gastown props + NPC authoring notes
- Add lightweight street clutter in `assets/world/gastown-water-street.json` under `world.props[]` using `{ id, kind, x, z, y?, yaw, scale }`.
- Supported starter `kind` values are `trash_bag`, `cardboard_box`, and `newspaper_box`; the simulator batches each kind with `InstancedMesh` for fewer draw calls.
- Add simple NPCs in `world.npcs[]` using `{ id, role, patrol?, idleSpot?, interactRadius, dialogId }`.
- `role: "pedestrian"` expects 2–4 `patrol` points and will loop through them; `guide` and `busker` can use `idleSpot` for stationary placement.
- Dialog text lives in `assets/dialog/gastown.json`; match an NPC's `dialogId` to a key in that file.
- Ground textures load from `assets/textures/cobblestone/` and `assets/textures/concrete-slabs/` using local `albedo`, `normal`, `roughness`, and `ao` maps.

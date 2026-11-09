# suzyeaston.ca

**music // systems // experiments**

This is the code behind [suzyeaston.ca](https://suzyeaston.ca/): a custom WordPress site, a service-status product, a browser-built Vancouver, music tools, publishing automation, and a growing collection of experiments. Not a résumé in repository form. The machinery is the point.

Built in Vancouver, BC, on the shared, unceded, ancestral territories of the xʷməθkʷəy̓əm (Musqueam), Sḵwx̱wú7mesh Úxwumixw (Squamish Nation), and səlilwətaɬ (Tsleil-Waututh) Nations.

> **Scope:** This repository holds the WordPress theme, the Lousy Outages plugin, selected browser applications, tooling, and source content. It is not a full WordPress database export, a copy of every service behind the site, or a guarantee that each experiment is currently deployed.

## Enter the site

| Experience | What lives here |
| --- | --- |
| [Gastown Simulator](https://suzyeaston.ca/gastown-sim/) | Walkable, filmable, stylized Vancouver built with Three.js and civic-data references |
| [Lousy Outages](https://suzyeaston.ca/lousy-outages/) | WordPress-native monitoring, incident intelligence, status feeds, and alerts |
| [Meanwhile](https://suzyeaston.ca/blog/) | Writing and performance notes, with Git-to-WordPress publishing |
| [Suzy Pirate Radio](https://suzyeaston.ca/radio/) | A real-stream receiver for a separately hosted independent internet station |
| [AFTERIMAGE / Vancouver](https://suzyeaston.ca/afterimage/) | WordPress entry point for a separately hosted creative-work atlas |
| [MACHINE VISIONS](https://suzyeaston.ca/ai-art/) | AI-assisted moving-image exhibition and publishing framework |
| [Vancouver Tech Events](https://suzyeaston.ca/vancouver-tech-events/) | Local event aggregation and presentation |
| [Loop Lab](https://suzyeaston.ca/loop-lab/) | Browser recording, overdubbing, arrangement, and WAV mix export |
| [Music / Records](https://suzyeaston.ca/music-releases/) | Music releases and listening |
| [Projects](https://suzyeaston.ca/projects/) | The wider selection, including Track Analyzer, ASMR Lab, and arcade experiments |

## Systems currently represented in this repo

### Gastown Simulator

The most rapidly evolving part of the site is an interactive **Waterfront Station / Station East / Water Street** world. It is a browser-based film set, not Google Street View or a surveyed digital twin.

- Three.js scene, third-person Suzy avatar, walking/running/flight controls, camera and photo tools
- Waterfront Station public concourse, Station East parking/rail-yard viewpoints, harbour panorama, heritage streetscapes, Steam Clock, traffic, and weather
- NPC encounters and a **fictional** visit to the Multimodal Media Lab at The Landing
- Selected City of Vancouver open-data inputs, generated world geometry, procedural and optional file-backed audio, and regression tests
- Distinct renderings of physical landmarks and deliberately invented interactions. Geometry, access, dialogue, traffic, and audio are not authoritative real-world conditions.

Start with [the simulator template](page-gastown-sim.php), [its browser runtime](js/gastown-sim.js), [authoring notes](docs/gastown-authoring.md), and [film-scene implementation notes](docs/gastown-film-scene-v11.md). The supporting scripts and tests matter just as much as the scenery.

### Lousy Outages

The standalone [Lousy Outages plugin](lousy-outages/) powers a status console for third-party AI, cloud, and developer services. Its code covers provider ingestion, incident lifecycles, status/history surfaces, RSS, email notifications, diagnostics, and account/entitlement foundations. A feature existing in code does **not** imply that every paid or notification flow is enabled in production.

Public REST entry point:

~~~http
GET /wp-json/lousy-outages/v1/status
~~~

See [plugin documentation](lousy-outages/README.md) and [release/deployment notes](docs/lousy-outages-release-and-cleanup.md). Do not commit subscriber records, live credentials, or private customer data.

### Meanwhile: posts from source control

Writing lives in [content/meanwhile/](content/meanwhile/). A dedicated GitHub Actions workflow validates opted-in Markdown posts and their media, then imports them into WordPress over configured SSH access. This is **separate from the theme/plugin deployment**. Drafts do not publish simply because they are committed.

Read [the publishing guide](content/meanwhile/README.md). The WordPress database and Media Library remain the published content store.

### Suzy Pirate Radio

[radio-station/](radio-station/) contains an independent Node MP3 relay, a Python/FFmpeg source transmitter, tests, and a Docker/Caddy hosting example. The WordPress homepage and [radio receiver](template-yvr-radio.php) listen to a configured public stream and report transmitter status; they do not fabricate an on-air broadcast.

**Important:** the radio server is *not* launched by the WordPress deploy. It needs its own persistent host, HTTPS endpoint, source token, and real end-to-end test. See [radio-station/README.md](radio-station/README.md) and [the integration notes](docs/pirate-radio.md).

### AFTERIMAGE, MACHINE VISIONS, and the other rooms

- **AFTERIMAGE / Vancouver:** [page-afterimage.php](page-afterimage.php) integrates a separately hosted atlas through a constrained iframe/message bridge. The atlas application itself is not implemented in this repository.
- **MACHINE VISIONS:** [page-ai-art.php](page-ai-art.php), a catalog/schema and [import CLI](scripts/import-ai-art-bundle.js) provide the public gallery layer. Only entries marked published should appear; the presence of a gallery is not evidence of finished works. See [docs/ai-art.md](docs/ai-art.md).
- **Music tools:** Loop Lab, Track Analyzer, riff experiments, ASMR Lab, and music releases live alongside the main theme.
- **Other interfaces:** Vancouver Tech Events, the retro arcade/Pacific Power Play, and recording-culture experiments remain part of the site.

## Where the code lives

~~~text
page-*.php, functions.php, inc/, parts/   WordPress templates, routing, integrations
assets/, js/                           Browser runtimes, styles, world and media assets
lousy-outages/                        Standalone WordPress monitoring plugin
radio-station/                        Separate streaming relay and source transmitter
content/meanwhile/                    Versioned posts and publishing media
data/, scripts/                       Civic-data, asset, import, and deployment tooling
schemas/                              Publish/data contracts
__tests__/, tests/                    Node regression checks and browser tests
.github/workflows/                   CI, production deploy, and publishing actions
src/, astro.config.mjs                Ancillary Astro/front-end scaffold
docs/                                 Architecture, QA, and operations notes
~~~

The **production site is WordPress/PHP**, not an Astro deployment. The Astro build is a separate front-end scaffold. Some older page templates remain in the tree for compatibility; notably, the former consulting and shop routes now redirect rather than serving active storefronts.

## Develop and test

**Requirements:** Node/npm; Docker Compose for local WordPress; Playwright browser installation for end-to-end tests. Some data/audio workflows have additional requirements described in their own docs.

~~~sh
npm install
npm test                 # Node regression suite
npm run build            # ancillary Astro build, NOT a WordPress deploy
~~~

Run a local WordPress instance with the theme and Lousy Outages plugin mounted from this checkout:

~~~sh
npm run local:start
npm run local:setup
# Site: http://localhost:8080
npx playwright install chromium
npm run test:e2e
npm run local:stop
~~~

The local environment uses WordPress, MariaDB, Docker Compose, and WP-CLI. See [docs/local-wordpress.md](docs/local-wordpress.md) for setup, credentials, and reset instructions. Never reuse its demo credentials on a real host.

To refresh the generated Gastown civic world, use `npm run build:gastown-world` (which synchronizes external City of Vancouver inputs). For the station relay, run `node --test radio-station/server.test.mjs` and follow its own setup guide; the full audio path needs FFmpeg.

## What ships, and what doesn't

| Workflow | Responsibility |
| --- | --- |
| [Deploy to Production](.github/workflows/deploy-production.yml) | On `main`, deploy changed WordPress theme and/or plugin paths over SSH/SFTP using an explicit theme manifest; supports manual dispatch |
| [Publish Meanwhile](.github/workflows/publish-meanwhile.yml) | Validate source posts and import ready Markdown/media into WordPress on trusted `main` runs |
| [Gastown GIS QA](.github/workflows/gastown-gis-qa.yml) | Validate simulator syntax, scene behavior, world alignment, and related regressions on relevant changes |
| [Radio Check](.github/workflows/radio-check.yml) | Radio-specific checks; **not** a radio-server deployment |
| [Release Lousy Outages](.github/workflows/release-lousy-outages.yml) | Plugin release workflow; separate from the radio relay |

Production uploads are **path-filtered**. Merging documentation alone does not replace the live WordPress files. GitHub Actions does not deploy the external AFTERIMAGE app, start the radio transmitter, or automatically publish unpublished artwork. Refer to [docs/deploy-production.md](docs/deploy-production.md) and the individual subsystem guides before changing production behavior.

## Working principles

Build it, test it, admit what's illustrative, and keep the weirdness where it belongs.

- Do not present generated Gastown traffic, viewpoints, voices, weather, or geometry as verified real-time Vancouver conditions.
- Do not describe a radio transmitter as live unless the separate source and host are actually on air.
- Do not invent published films, shipped paid features, working alerts, or deployment success from code presence alone.
- Keep credentials and personal/subscriber information out of commits; see [SECURITY.md](SECURITY.md).

## Licence

The repository includes an [MIT Licence](LICENSE) for the software. Check provenance and any separately stated terms before reusing third-party datasets, photographs, recordings, or other media.

Made in Vancouver. Still in progress.

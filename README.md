# Suzy Easton

*Practical AI, strange interfaces, and useful systems built in Vancouver.*

This repository powers [suzyeaston.ca](https://suzyeaston.ca), the public creative-technology home of Suzy Easton.

It is part portfolio, part workshop, and part open notebook. The work moves between AI strategy, cloud operations, automation, civic data, music technology, interactive storytelling, and the occasional interface that looks like it escaped from an arcade cabinet.

Some projects are polished. Some are mid-mutation. All of them have survived contact with reality.

## Doors in

- [Live site](https://suzyeaston.ca)
- [Lousy Outages](https://suzyeaston.ca/lousy-outages/)
- [Gastown Simulator](https://suzyeaston.ca/gastown-sim/)
- [Track Analyzer](https://suzyeaston.ca/suzys-track-analyzer/)
- [Work with Suzy](https://suzyeaston.ca/work-with-suzy/)

## What this is

This is a public lab for building things in plain sight.

The repository contains selected experiments, production code, prototypes, documentation, and the visible remains of ideas being tested properly rather than discussed forever.

It also shows how Suzy works: start with an unclear problem, find the useful part, build a working system, test it, listen to what breaks, and improve it without sanding off all the personality.

The code is public where that makes sense. Client work, private infrastructure, subscriber data, and future commercial layers live elsewhere.

## Featured work

### Lousy Outages

**Lousy Outages** is independent outage intelligence for AI, cloud, developer, and creative tools.

It watches official provider sources, groups related notices into clearer incidents, and turns status-page language into information people and businesses can use. The public dashboard is built for the awkward space between “all systems operational” and an entire group chat quietly confirming that nothing works.

Current public work includes:

- source-backed provider monitoring
- incident grouping and lifecycle tracking
- public status and history views
- RSS and notification foundations
- email-alert infrastructure
- community-signal experiments
- provider and incident diagnostics

Future commercial layers may include personalised monitoring, higher-signal alerting, private operational views, and custom provider coverage.

Subscriber information, customer systems, and private infrastructure are not included in this repository.

Developer entry point:

```text
GET /wp-json/lousy-outages/v1/status
```

Implementation and deployment notes live in [`lousy-outages/README.md`](lousy-outages/README.md).

### Gastown First-Person Simulator

Gastown Simulator is an interactive Vancouver experiment built around civic data, browser-based world building, route logic, atmosphere, and public-place storytelling.

The current world focuses on the corridor around Waterfront Station, Water Street, and the Steam Clock. It combines generated world data, local textures, dialogue, sound, navigation systems, and selected City of Vancouver datasets.

It is not trying to reproduce the city perfectly. It is exploring what happens when maps, memory, public data, and a slightly haunted sense of place share the same browser window.

The repository includes:

- the WordPress page template
- simulator JavaScript
- generated world data
- dialogue and audio systems
- local texture references
- civic-data build tooling
- props and NPC authoring structures

Detailed authoring notes live in [`docs/gastown-authoring.md`](docs/gastown-authoring.md).

### Track Analyzer

Track Analyzer is an AI-assisted feedback tool for musicians working with uploaded MP3s.

It offers practical notes on feel, lyrics, structure, arrangement, and creative direction. The goal is not to replace judgement or taste. It is to help a musician hear the next decision more clearly.

That approach runs through much of this repository: AI works best here as a collaborator, checkpoint, research tool, or second set of ears.

### Other experiments

Other projects and archives include:

- Albini Q&A and recording-culture tools
- ASMR Lab
- Loop Lab
- riff and music-generation experiments
- audiovisual sketches
- Vancouver civic-data pieces
- Suzy’s Retro Arcade interface system

Not every experiment needs the same size billboard. Some are active, some are resting, and some are waiting for the right strange afternoon.

## Consulting and collaboration

Suzy is available for selected consulting, prototyping, and collaboration involving:

- practical AI strategy and implementation
- AI workflows and automation
- internal tools and product prototypes
- cloud and service-status intelligence
- creative-technology systems
- music and multimedia tools
- technical discovery and architecture
- troubleshooting complicated systems that have acquired folklore

The work is especially suited to organizations and creative teams that need to move from a broad idea to something testable, understandable, and useful.

This repository shows the process:

- find the real problem
- reduce the ambiguity
- design the system
- build the prototype
- test the failure paths
- improve it in public where appropriate
- keep the result accessible and human

For consulting, collaboration, or useful strange ideas, visit the [Work with Suzy](https://suzyeaston.ca/work-with-suzy/) page or use the contact options on the live site.

## Technical foundations

The stack is practical, flexible, and mostly uninterested in technological fashion shows.

Core technologies include:

- WordPress and PHP
- JavaScript
- REST APIs
- Node tooling
- scheduled jobs and automation
- cloud and provider-status integrations
- open-data pipelines
- responsive interface design
- accessibility testing
- Docker-based local QA tooling

There is also Astro tooling for selected front-end build and preview workflows.

The custom WordPress theme and Lousy Outages plugin remain the main production shape of the site.

Built in Vancouver, where the rain provides free resilience testing.

## Running the project

This is primarily a production WordPress theme and public development repository. It includes Docker-based local QA support, but it is not packaged as a universal one-command product installation.

Install Node dependencies:

```sh
npm install
```

Run the JavaScript test suite:

```sh
npm test
```

Run the Astro build:

```sh
npm run build
```

Start and prepare the local WordPress QA environment:

```sh
npm run local:start
npm run local:setup
```

Stop the local environment:

```sh
npm run local:stop
```

Refresh the Gastown civic-data world build:

```sh
npm run build:gastown-world
```

The local WordPress environment uses Docker Compose, WordPress, MariaDB, and WP-CLI. The theme and Lousy Outages plugin are mounted directly from this repository for testing.

## Project documentation

Specialised notes live outside the main README so this page can remain an introduction rather than becoming a basement filing cabinet.

- [`docs/gastown-authoring.md`](docs/gastown-authoring.md), props, NPCs, textures, and civic-data world builds
- [`lousy-outages/README.md`](lousy-outages/README.md), monitoring, notifications, feeds, QA, and deployment
- [`assets/audio/gastown/README.md`](assets/audio/gastown/README.md), Gastown audio assets
- [`assets/brand/README.md`](assets/brand/README.md), brand assets and usage notes

## Open source and commercial development

This repository contains selected public experiments and portfolio work.

It is a community resource, a professional proof point, and a place for ideas that benefit from being developed in the open.

That does not mean every future layer belongs here.

Commercial services, consulting systems, customer implementations, subscriber infrastructure, and proprietary components may be developed in private repositories. Client work and private infrastructure are not included here.

Public code remains governed by its applicable licence. Contributions and issue reports are welcome where they fit the public project, but this repository should not be read as a promise that all related work will always remain open.

## About Suzy

Suzy Easton is a Vancouver-based AI strategist, solutions engineer, musician, and creative technologist.

Her background spans music performance and recording, technical support, QA, IT operations, cloud systems, automation, and software development. That mix shapes the work: direct, iterative, technically grounded, and suspicious of unnecessary gloss.

Suzy’s Retro Arcade is still part of the house style. It is not the whole job description.

## Licence

Public code in this repository is available under the MIT Licence where applicable.

See [`LICENSE`](LICENSE) for the full licence text.

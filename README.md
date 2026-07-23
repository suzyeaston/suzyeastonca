# Suzy Easton

This is the public creative-technology portfolio and laboratory of Suzy Easton, a Vancouver-based AI strategist, solutions engineer, creative technologist, musician, and product builder. It powers [suzyeaston.ca](https://suzyeaston.ca), documents selected experiments, and shows how practical systems can still have a pulse.

Useful doors in:

- [Live site](https://suzyeaston.ca)
- [Lousy Outages](https://suzyeaston.ca/lousy-outages/)
- [Gastown Simulator](https://suzyeaston.ca/gastown-sim/)
- [Track Analyzer](https://suzyeaston.ca/suzys-track-analyzer/)
- [Work with Suzy](https://suzyeaston.ca/work-with-suzy/)

## What this is

This repository is a public lab, a portfolio, and a build-in-public record. It brings together practical AI, cloud and IT operations, automation, civic data, music technology, interactive storytelling, and experimental interfaces.

It is also evidence of professional capability: taking ambiguous ideas, turning them into working systems, testing them, improving them, and leaving enough of the process visible that other people can understand the decisions. Some projects are polished. Some are mid-mutation. All of them are real.

## Featured work

### Lousy Outages

Lousy Outages is independent outage intelligence for AI, cloud, developer, and creative tools. It watches provider status sources, groups incidents into a public dashboard, and translates reliability noise into explanations humans can act on.

The public work includes source-backed provider monitoring, incident grouping, public status display, RSS/feed foundations, community-signal experiments, and email-alert infrastructure. It is built for the space between a vendor saying “all systems operational” and everyone in the room quietly losing faith in the internet.

Future commercial layers may include personalised or premium monitoring, higher-signal alerting, and private operational views. Subscriber details, client systems, and private infrastructure are not part of this public repository.

Useful developer entry point: `GET /wp-json/lousy-outages/v1/status`. More implementation notes live in [`lousy-outages/README.md`](lousy-outages/README.md).

### Gastown First-Person Simulator

Gastown Simulator is an interactive Vancouver corridor experiment built around civic/open-data pipelines, browser-world authoring, route logic, and public-place storytelling. It is less a finished game than a working question: what happens when local data, street atmosphere, and playful interface design share the same map?

The repository includes the WordPress page template, JavaScript simulator code, generated world data, local texture references, dialog data, and build scripts for refreshing selected City of Vancouver data. Detailed props, NPC, texture, and civic-data authoring notes have moved to [`docs/gastown-authoring.md`](docs/gastown-authoring.md).

### Track Analyzer

Track Analyzer is a musician-focused AI-assisted feedback tool for uploaded MP3s. The point is fast, practical notes on feel, lyrics, structure, and arrangement, not mystic robot judgement from a chrome-plated oracle.

It reflects a larger theme in this repo: AI is most useful when it helps a human make the next creative or technical decision with more clarity.

### Other experiments

Not every experiment needs the same size billboard. Selected archive and side projects include Albini Q&A-related recording prompts, ASMR Lab, Loop Lab, riff-generation tools, music pages, audiovisual sketches, Vancouver data pieces, and Suzy’s Retro Arcade styling system.

The experiments change often. Software has declined to become a finished medium.

## Consulting and collaboration

Suzy is available for selected consulting and collaboration involving:

- practical AI strategy and implementation
- AI workflow and automation design
- prototypes and internal tools
- cloud and service-status intelligence
- creative-technology products
- music and multimedia systems
- technical discovery, architecture, and troubleshooting

This repository demonstrates how she works: find a useful problem, translate ambiguity into a system, build the prototype, test it, improve it in public, and preserve personality and accessibility while doing the serious parts properly.

For consulting, collaboration, or useful strange ideas, start at [Work with Suzy](https://suzyeaston.ca/work-with-suzy/) or email through the contact options on the site.

## Technical foundations

The stack is intentionally practical rather than precious. The public site is a custom WordPress theme with PHP templates, JavaScript interfaces, REST APIs, Node tooling, automation scripts, scheduled jobs, cloud/status integrations, open-data pipelines, and responsive, accessible interface work.

There is also Astro tooling in the repository for front-end build and preview workflows. The WordPress theme and the Lousy Outages plugin remain the main production shape.

Built in Vancouver, where the weather provides free resilience testing.

## Running the project

This is primarily a production WordPress theme and public development repository, with Docker-based local QA support rather than a universal one-command product install.

Install Node dependencies when needed:

```sh
npm install
```

Run available JavaScript tests:

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

The local WordPress setup uses Docker Compose, WordPress, MariaDB, WP-CLI, this theme mounted as `suzyeastonca`, and the `lousy-outages` plugin mounted from this repository.

## Project documentation

Specialised notes live outside the main README so this page can introduce the work without becoming a basement filing cabinet.

- [`docs/gastown-authoring.md`](docs/gastown-authoring.md), props, NPCs, textures, and civic-data world builds
- [`lousy-outages/README.md`](lousy-outages/README.md), provider monitoring, notifications, feeds, QA, and deployment notes
- [`assets/audio/gastown/README.md`](assets/audio/gastown/README.md), Gastown audio assets
- [`assets/brand/README.md`](assets/brand/README.md), brand asset notes

## Open source and commercial development

This repository contains selected public experiments and portfolio work. It is a community resource, a professional proof point, and a visible lab for ideas Suzy is willing to develop in public.

That does not mean every future layer belongs here. Commercial services, customer implementations, consulting systems, subscriber infrastructure, and proprietary components may live in private repositories. Client work and private infrastructure are not included here.

Public code remains governed by its applicable licence. Contributions and issue reports are welcome where they fit the public project, but the public repository should not be read as a promise that all related work will remain open forever.

## About Suzy

Suzy Easton is a Vancouver-based musician and technologist working across AI strategy, solutions engineering, product prototypes, music systems, civic curiosity, and experimental web interfaces. Her background in recording, performance, production culture, support, QA, operations, and software gives the work its shape: direct, iterative, technical, and allergic to unnecessary gloss.

Suzy’s Retro Arcade is still part of the house style. It is not the whole job description.

## Licence

The public code in this repository is available under the MIT Licence where applicable. See [`LICENSE`](LICENSE) for the licence text.

---
title: "Everything Works Except for Art"
slug: everything-works-except-for-art
series: "Meanwhile / November 2026 / Film Club Transmission"
status: pending-wordpress-import
category: "film + moving image"
excerpt: "I built a miniature Vancouver, tried to score it with my AI instrument, fought the audio pipeline, and accidentally submitted a film about debugging."
featured_image: gastown-night-simulator.jpg
---

*Meanwhile / November 13, 2026 / Film Club Transmission*

Last night, November 12, a little film of mine screened at BC + AI Film Club in Gastown. I wasn't in the room to introduce it, so here's the missing artist statement.

I built a version of Vancouver in my browser, tried to give it an original AI-assisted score, lost a fairly spectacular argument with my own software, and sent the result to Mayumi just before the deadline.

The brief was **make art not war**. By the end, I was mostly at war with my audio routing.

Naturally.

<!-- PHOTO: gastown-night-simulator.jpg | A pale-haired avatar with a red checkered backpack faces a moody, stylized Vancouver street at night. | Development capture from Gastown Simulator. The city has its own weather system and absolutely no obligation to behave. -->

## 01 / Building a city to get lost in

The starting point was [Gastown Simulator](https://suzyeaston.ca/gastown-sim/), a playable, filmable little Vancouver I've been building in Three.js. Waterfront Station, the rail yards, Water Street, the harbour, the Steam Clock, and a fictional trip into The Landing's sixth-floor creative universe.

I used city data and real-world references, then kept rebuilding the parts that felt wrong. It's not a surveyed digital twin. It's a city assembled from geography, memory, code, weather, tiny arguments about street direction, and the occasional deeply cursed low-poly pedestrian.

My character has a checkered Vans backpack. She walks, runs, and sometimes flies. I wanted her to move through a place I know, then let the place become something else.

<!-- PHOTO: simulator-under-construction.jpg | The developing Gastown Simulator shows an angular interior and low-poly characters, with the minimap and navigation controls visible. | Behind the scenes: a perfectly normal day inside the city's unfinished geometry. -->

## 02 / The soundtrack that kept escaping

The other half was music. After [Futureproof](https://www.suzyeaston.ca/2026/11/02/the-toaster-was-a-decoy/), I'd kept working on SUZY//TOAST, the little performance instrument I built around Google's locally running Magenta RealTime model. The idea wasn't to type *make a soundtrack* and collect the result. I wanted to **play** the film.

For Film Club I was experimenting with a piece called *Pyramid Afterimage*: E minor moving through E7, A minor and back to E, around 100 BPM, with piano, low cello/harmonium colours, synthetic textures and a much less obedient electronic pulse. Grimes' *Accelerando* had been in my head, not as a track to copy, but as permission to let the city accelerate.

That was the plan, anyway.

<!-- PHOTO: afterimage-audio-controls.jpg | The PYRAMID AFTERIMAGE performance scene in SUZY//TOAST, showing blend, instrument, and film-sound design controls. | A soundtrack concept built as an instrument. The controls were more confident than the audio. -->

The model, browser, native audio host, recording path and headphones all had slightly different ideas about where sound was supposed to go. Prompts changed. Instruments refused to sound like their descriptions. Sometimes the native rhythm worked while the AI instruments were silent. Screen capture added another layer of negotiation.

Software status: ready.

Musician status: can anyone hear this thing?

<!-- PHOTO: film-club-capture-ui.jpg | SUZY//TOAST running locally in a browser, with the Film Club recording panel and screen/audio capture controls visible. | The recording interface was part of the experiment. It did not make the experiment predictable. -->

## 03 / The cursor is the director

Eventually I stopped trying to rescue the entire ambitious version and assembled what I had in iMovie. The submitted file was called *Make Art Not War.mp4*. It was rushed, weird, darker than planned, and much closer to a psychological horror film about debugging AI than the little electronic opera I'd imagined.

And yes, **the mouse cursor is visible in the film**.

That wasn't intentional. At first I was embarrassed. Then I realised it's probably the most revealing part. There's a person operating this world. I'm not pretending that the city made itself, or that the avatar is an autonomous artist. The cursor is me playing, directing, testing, interrupting. A tiny, extremely unglamorous hand on the controls.

That's the part of AI filmmaking I care about. Not the machine producing an immaculate image on command. The human deciding what to keep when the image, the music and the machine all disagree.

## 04 / An accidental artist statement

I sent Mayumi the film with a note about broken audio, a pile of prompt revisions and an unstable Vancouver simulation. She replied, “haha - I love it!! Thanks Suzy :)” Which is a generous response to a transmission sent from the edge of the deadline.

I missed the screening, but I like that this tiny thing got to exist outside my laptop. It wasn't the film I set out to make. It's still a film I made.

I don't think *make art not war* has to mean making something gentle or tidy. For me it meant taking tools that are often discussed in terms of acceleration, control and power, and using them to build a city, play a strange instrument, and make something personal instead.

The AI didn't direct the film. I did. Sometimes badly. Sometimes by accident. With an exceptionally persistent mouse cursor.

Everything works except for art.

And then, occasionally, art is what survives. 🖤

*These are development screenshots from the simulator and music software, not verified stills from the final submitted MP4. The [Gastown Simulator](https://suzyeaston.ca/gastown-sim/) and [SUZY//AI source](https://github.com/suzyeaston/suzy-ai) are open to explore.*
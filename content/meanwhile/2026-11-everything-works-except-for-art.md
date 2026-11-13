---
title: "Make Art Not War: The November 2026 BC + AI Film Club Prompt Challenge"
slug: make-art-not-war-november-2026-bc-ai-film-club-prompt-challenge
series: "Meanwhile / November 2026 / Film Club Transmission"
status: publish
published_at: "2026-11-13 12:00:00"
category: "film + moving image"
excerpt: "The November 2026 BC + AI Film Club prompt challenge, an evolving Gastown Simulator, a generative instrument, and a film made inside a playable Vancouver."
featured_image: gastown-night-simulator.jpg
---

*Meanwhile / November 13, 2026 / Film Club Transmission*


On November 12, a film I made screened at BC + AI Film Club. I wasn't there to introduce it, and there is a fair amount of context missing from the finished video.

For November, the Film Club prompt challenge was **Make Art Not War**.

I had this idea of filming inside a version of Vancouver I'd been slowly building, off and on, since March, and performing an electronic soundtrack to go with it.

That was the beginning. What eventually made it into the room was something else.

<!-- PHOTO: gastown-night-simulator.jpg | A stylized scene from Gastown Simulator showing a pale-haired player avatar with a checkered backpack in Vancouver at night. | A development image from Gastown Simulator, the existing world I used as a location for the film. -->

## 01 / film club, before the film

I went to my first BC + AI Film Club meetup in January. Filmmakers, animators, musicians, people experimenting with systems and images and sound. I remember going home thinking, *I found my people.*

My first little film for one of the prompt challenges was [*Rain City Roll Reserve*](https://www.youtube.com/watch?v=FrjuKGj91Pw), a tiny toilet-paper commercial that screened at the Planetarium in February. Which is a fairly good introduction to the kinds of things that can happen when artists start experimenting with these tools.

<!-- PHOTO: rain-city-roll-reserve.jpg | Film still from Rain City Roll Reserve, showing performers on the Vancouver waterfront and the commercial title across the frame. | *Rain City Roll Reserve*, my February Film Club submission. [Watch on YouTube](https://www.youtube.com/watch?v=FrjuKGj91Pw). -->

Then came the ASMR film challenge. I started thinking about sound design, procedural visuals, and how much control I actually wanted over the result. Rather than relying on a sequence of generated clips, I began building my own web platform, ASMR Lab, to experiment with those relationships. The challenge turned into an accidental hackathon.

In March I talked with Mayumi about what I'd been making.

For June's **Retro** prompt challenge, there was also [*I AM NOMAD*](https://skywhaleairways.com/), the film Kris Krüg and I are credited on, set inside *Skywhale Airways*, a psychedelic airport for time travellers. My contribution was wonderfully small: I opened [a pull request](https://github.com/WalksWithASwagger/skywhale-airways/pull/78) to change a single pixel in the airport artwork by one unit of blue. Kris later changed the surrounding WebGL sky by that same one unit. One bag, one body, one pixel. I love how a detail almost too small to see can still belong to the whole piece.

In September I submitted *Neon Gods*, a very different little horror film for the prompt challenge of the same name.

<!-- PHOTO: neon-gods.jpg | Still from Neon Gods showing animated mythological figures composited into a lived-in room. | *Neon Gods*, my September Film Club experiment. -->

That September prompt also gave us [*The Sky Smoked Back*](https://lightningstrike.org/), a very trippy 56-second short Kris Krüg made after the August 22 lightning storm. It features AI versions of both of us sheltering on a Vancouver porch, with the sky doing something otherworldly overhead. Kris made the film, asked to use my likeness, and credited me as *with Suzy Easton*. It's surreal seeing a version of yourself inside someone else's film, especially when we're each interpreting the same prompt in completely different ways.

By November I was back with another experiment, this time with a world I could physically navigate on my computer.

I like this about Film Club. It isn't just about showing a finished piece. There are people willing to talk about what you tried, what broke, what you learned, and what might be possible next.

<!-- PHOTO: film-club-michelle-diamond.jpg | A lively group of six people laughing together, with microphones being shared at a creative gathering. | A moment from the BC + AI creative community. Photograph by Michelle Diamond. -->

## 02 / a corridor i keep returning to

[Gastown Simulator](https://suzyeaston.ca/gastown-sim/) was already its own project long before the November film challenge.

I started it in March, coming out of those earlier experiments with Vancouver scenes and sound. It's been an on-and-off process of trying to reconstruct the corridor from Waterfront Station into Gastown: the station itself, West Cordova, the rail yards, the harbour, Water Street, the Steam Clock, the buildings and the way they sit against one another.

I've walked through that part of Vancouver for years. I know what it feels like when the station opens onto the street, when the railway yard comes into view, when the harbour appears between buildings. The street directions and the position of landmarks matter because they're part of how I know the place.

I used City of Vancouver geographic data and visual references, but I wasn't trying to make a perfect survey or a photorealistic replica. I wanted to be able to *enter* the place. A version of the city that was recognizable, but still strange enough to become something else.

There's a little avatar of me in there now, with a checkered Vans backpack. She can walk, run and fly. There are weather and lighting changes, other characters, and an imagined route into The Landing, where Film Club meets.

The Film Club challenge didn't create the simulator. It gave me a new reason to go back inside it, this time with a camera.

<!-- PHOTO: simulator-under-construction.jpg | The developing Gastown Simulator shows low-poly characters, an interior scene and navigation controls. | A world under construction, built and revised over months rather than generated in one pass. -->

## 03 / yes, it's running in a browser

The technical part is interesting because the film wasn't really *made in HTML*, although the world lives on a webpage.

The WordPress site serves a PHP page template with HTML and CSS for the interface. Inside it is an HTML canvas. JavaScript and **Three.js** construct a 3D scene and use **WebGL** to render it live. The camera, avatar movement, buildings, street surfaces, weather and lighting are part of an interactive program, not a set of finished video frames.

There's another layer behind that. Node.js scripts take geographic information from City of Vancouver open-data exports, including street geometry and building footprints, and turn it into a more compact JSON world. Then I add and revise things that data alone can't give me: architectural details, perspectives, people, sound, the view from the parking lot across the tracks.

So it's part civic geography, part hand-built simulation, part memory. The raw data can tell me where a street goes. It can't tell me why I keep looking toward the harbour from a particular corner.

For the film, I could move the camera through that live world, experiment with third-person views and the Suzy avatar, and record what happened on screen. I used OBS in the process and assembled the final video in iMovie.

The film is an MP4. The *location* is executable software.

I find that distinction kind of beautiful.

## 04 / the soundtrack was supposed to be played

The other half of this was music.

At [Futureproof](https://www.suzyeaston.ca/2026/11/02/the-toaster-was-a-decoy/) in October, I performed with a small AI-assisted instrument I'd been building around Google's Magenta RealTime model. I kept developing the idea afterward in SUZY//AI / SUZY//TOAST.

I'm interested in a system I can perform *with*. I've always loved looping, building a piece layer by layer, playing something back and responding to it. That sense of performance is part of what I love about Grimes and Imogen Heap, too. Not pressing a button and receiving a complete piece of music, but shaping prompts, harmonies, loops and textures in real time, listening and responding.

For the Film Club experiment I worked on a score called *Pyramid Afterimage*: piano, cello, harmonium, low electronic textures and an E minor / E7 / A minor harmonic movement. I was trying to find something that could sit between a familiar city and a stranger, accelerating version of it.

<!-- PHOTO: afterimage-audio-controls.jpg | The Pyramid Afterimage scene in the SUZY//TOAST interface, with audio and instrument controls visible. | A generative soundtrack designed as a performance, not just a prompt. -->

I also experimented with ways the simulator itself could react to music, using browser audio analysis and a timed film sequence. That's part of the development story, not a claim that every idea survived into the final cut.

And the sound was much harder to capture than I'd expected.

At one point I could hear the native rhythm but not the AI instruments. Audio routing, browser output, loop timing, recording, prompt revisions: I was working through all of it while also trying to make a film.

Being the musician and the engineer is useful until you realize you can keep solving technical problems indefinitely and never actually finish the art.

<!-- PHOTO: film-club-capture-ui.jpg | SUZY//TOAST running in a browser with its film recording and audio controls on screen. | The audio and recording tools were still evolving while I was trying to use them. -->

## 05 / the cursor is still there

I finally cut together what I had and sent Mayumi [*Make Art Not War.mp4*](https://www.youtube.com/watch?v=SKXYeTfUeOs).

The film ended up darker and stranger than the little electronic opera I'd pictured. There's something unsettling about moving through a city you recognize as a simulated version of yourself. It started feeling less like a music video and more like psychological science fiction.

And I accidentally recorded the mouse cursor into the film.

lol.

At first I wanted it gone. But I kept thinking about the fact that there's a person operating this world. Choosing where to go, what to look at, when to move, when to stop. Making mistakes. Making decisions.

It's not some grand statement I planted there on purpose. It's just evidence of how the thing was made, and I kind of like that.

I've been thinking about AI and art a lot. Not just what the models can produce, but what happens to the person using them. Where does taste live? What happens to improvisation when a system can generate its own material? What do we remember when we reconstruct a place from data?

I don't have a neat answer. I do know that none of this removed the need to listen, look, edit, change my mind, or decide that an imperfect moment was worth keeping.

The [film](https://www.youtube.com/watch?v=SKXYeTfUeOs) screened in Gastown, in the part of Vancouver I'd been rebuilding on my computer. I wasn't there to see it, which is a strange ending to a project about trying to be present inside a place.

But it was in the room.

And the city is still there, running in the browser.

♡ 

*This post includes development images from Gastown Simulator and SUZY//TOAST, stills from earlier film experiments, and a community photograph by Michelle Diamond. The simulator images are not verified frames from the submitted video.*

*Watch [Make Art Not War](https://www.youtube.com/watch?v=SKXYeTfUeOs) · Explore [Gastown Simulator](https://suzyeaston.ca/gastown-sim/) · [SUZY//AI source](https://github.com/suzyeaston/suzy-ai)*

<?php
/**
 * Template Name: Bio Page
 *
 * An editorial introduction, not a services page.
 * The project index and technical work remain at /projects/.
 */
wp_enqueue_style(
    'se-bio-story',
    get_template_directory_uri() . '/assets/css/bio-story.css',
    array('main-styles'),
    filemtime( get_template_directory() . '/assets/css/bio-story.css' )
);
get_header();

$bio_images = get_template_directory_uri() . '/assets/brand/';
?>
<main id="main-content" class="se-story" aria-labelledby="se-story-title">
    <div class="se-story__shell">
        <header class="se-story__hero">
            <div class="se-story__hero-copy">
                <p class="se-story__eyebrow">ABOUT / A WORK IN PROGRESS</p>
                <h1 id="se-story-title">Hi, I’m Suzy.</h1>
                <p class="se-story__lead">I make music, untangle systems, and build little worlds to see what happens inside them.</p>
                <p>For a long time I thought the musician and the engineer were two separate versions of me. Lately I’ve realised they’ve been having the same conversation all along.</p>
                <p class="se-story__small">Vancouver, BC · Musician &amp; creative technologist · Usually making something</p>
            </div>
            <figure class="se-story__photo se-story__photo--hero">
                <img src="<?php echo esc_url( $bio_images . 'bio-red-toque.jpg' ); ?>" alt="Suzy smiling outdoors in Vancouver, wearing a red knit toque and clear-framed glasses." width="228" height="480" fetchpriority="high" decoding="async">
                <figcaption>Between one thing and the next, in Vancouver.</figcaption>
            </figure>
        </header>

        <nav class="se-story__contents" aria-label="On this page">
            <a href="#music-first">Music first</a>
            <a href="#other-instrument">The other instrument</a>
            <a href="#same-conversation">Where they meet</a>
            <a href="#vancouver">Vancouver</a>
            <a href="#still-making">Still making</a>
        </nav>

        <section id="music-first" class="se-story__chapter" aria-labelledby="music-first-title">
            <p class="se-story__number">01 / BEFORE THE CODE</p>
            <h2 id="music-first-title">Music was the first language.</h2>
            <div class="se-story__prose">
                <p>I grew up in Vancouver playing piano, and eventually found my way to bass, jazz, composition, recording studios, and bands. I played in Seafoam and Minto (formerly The Smokes), toured across Canada, appeared on MuchMusic, and made a record with Steve Albini in Chicago.</p>
                <p>I loved being onstage, but I was just as fascinated by everything behind the sound: the arrangement, the signal path, the tiny decision that changes an entire performance. I studied recording arts and music technology because I wanted to know how it worked, not just how it felt.</p>
                <p>Improvisation never really left me. The best part is still listening closely enough to know when to change direction.</p>
            </div>
            <p class="se-story__aside">From the archive: <a href="<?php echo esc_url( 'https://www.straight.com/article-244417/minto-finally-gets-serious' ); ?>" target="_blank" rel="noopener noreferrer">Minto in the Georgia Straight</a> · <a href="<?php echo esc_url( home_url( '/music-releases/' ) ); ?>">the records</a></p>
        </section>

        <section id="other-instrument" class="se-story__chapter se-story__chapter--rule" aria-labelledby="other-instrument-title">
            <p class="se-story__number">02 / ANOTHER KIND OF INSTRUMENT</p>
            <h2 id="other-instrument-title">Then I got very good at finding what broke.</h2>
            <div class="se-story__prose">
                <p>Technology crept in through recording gear, websites, software, and curiosity. Over the years I ended up working in quality assurance, support engineering, IT operations, automation, identity, security, and cloud systems. I spent a lot of time looking at the part of a system nobody notices until it stops working.</p>
                <p>I like a difficult technical problem. I also like the moment when someone who has been staring at an error for hours finally understands what happened. That's more satisfying to me than a shiny demo.</p>
                <p>Eventually I was building AI systems too. And I kept wondering what would happen if I brought all that engineering back to the thing that started it: making music with other intelligences in the room.</p>
            </div>
        </section>

        <section id="same-conversation" class="se-story__chapter se-story__chapter--feature" aria-labelledby="same-conversation-title">
            <p class="se-story__number">03 / THE TWO HALVES MEET</p>
            <h2 id="same-conversation-title">An orchestra in 8 GB.</h2>
            <div class="se-story__prose">
                <p>In 2026 I started building my own instruments again. Just this time they were made of code, local models, microphones, loops, and occasionally terrible timing bugs. I called one of those experiments SUZY//AI.</p>
                <p>At Futureproof in Vancouver I performed <em>The Appliance Latent Space</em>, improvising with my voice, live looping and generative sound on a little MacBook Pro with 8 GB of memory. It was imperfect, unpredictable, and recognisably a performance. I wasn't asking software to be the musician. I wanted something I could play.</p>
                <p>That distinction matters to me. I’m interested in AI tools that an artist can shape, interrupt, refuse, or change their mind about. Consent and control belong inside the instrument, not in the fine print.</p>
                <p>I’ve also been making short films and experiments through the BC + AI Film Club. Music, moving images, game worlds, software: I don't think they have to live in separate drawers anymore.</p>
            </div>
            <div class="se-story__thread">
                <span>Follow the thread</span>
                <a href="<?php echo esc_url( 'https://github.com/suzyeaston/suzy-ai' ); ?>" target="_blank" rel="noopener noreferrer">SUZY//AI source</a>
                <a href="<?php echo esc_url( home_url( '/loop-lab/' ) ); ?>">Loop Lab</a>
                <a href="<?php echo esc_url( home_url( '/appliance-latent-space/' ) ); ?>">The Appliance</a>
            </div>
        </section>

        <section id="vancouver" class="se-story__chapter se-story__split" aria-labelledby="vancouver-title">
            <div>
                <p class="se-story__number">04 / THE CITY GETS INTO EVERYTHING</p>
                <h2 id="vancouver-title">Vancouver keeps finding its way into the code.</h2>
                <div class="se-story__prose">
                    <p>I'm from here. I pay attention to the places between the obvious landmarks: small music rooms, train tracks, odd corners of Gastown, rain on the street, the way a neighbourhood changes while you're looking at it.</p>
                    <p>That's how a playable Gastown, a calendar for local music and technology, an atlas of creative work, and an outage dashboard all ended up living on the same website. They aren't a master plan. They're things I noticed, became curious about, and started making.</p>
                    <p>Some are useful. Some are wonderfully unnecessary. Most are unfinished in the best sense.</p>
                </div>
                <p class="se-story__aside"><a href="<?php echo esc_url( home_url( '/gastown-sim/' ) ); ?>">Walk through Gastown</a> · <a href="<?php echo esc_url( home_url( '/vancouver-events/' ) ); ?>">Find a local event</a> · <a href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>">Check the signals</a></p>
            </div>
            <figure class="se-story__photo se-story__photo--street">
                <img src="<?php echo esc_url( $bio_images . 'bio-coffee.jpg' ); ?>" alt="Suzy smiling outside with a takeaway coffee and autumn trees in the background." width="228" height="480" loading="lazy" decoding="async">
                <figcaption>Small walks, new ideas. The city gets into everything.</figcaption>
            </figure>
        </section>

        <section id="still-making" class="se-story__chapter se-story__chapter--rule" aria-labelledby="still-making-title">
            <div>
                <p class="se-story__number">05 / RIGHT NOW</p>
                <h2 id="still-making-title">Still figuring it out. Still making things.</h2>
                <div class="se-story__prose">
                    <p>This year has involved rebuilding in more ways than one. I don't want to turn every detour into a personal brand lesson. Some things are hard, some things are funny, and a lot of life happens while you're trying to make the next thing work.</p>
                    <p>These days my work moves between sound, software, research, and whatever else catches my attention. I put experiments online with their rough edges showing. The website is less a finished portfolio than an open notebook of things I've tried and things I'm still curious about.</p>
                    <p>I still think like a bassist: listen, leave space, make a decision, and be ready when the arrangement changes.</p>
                </div>
            </div>
        </section>

        <aside class="se-story__music-note" aria-labelledby="se-story-music-note-title">
            <p class="se-story__number">P.S. / MORE MUSIC</p>
            <h2 id="se-story-music-note-title">More recordings soon.</h2>
            <p>If you’d like to help me keep recording, picking up something I’ve already released on Bandcamp is a lovely way to do it. More music is on its way. Thanks for listening. ♡</p>
            <a href="<?php echo esc_url( 'https://suzyeaston.bandcamp.com/' ); ?>" target="_blank" rel="noopener noreferrer">Listen or buy a recording on Bandcamp</a>
        </aside>

        <footer class="se-story__ending">
            <p>If you arrived here through a song, a little bit of code, or a very specific Vancouver rabbit hole: hi. It’s all the same person.</p>
            <div class="se-story__links" aria-label="Keep exploring">
                <a href="<?php echo esc_url( home_url( '/projects/' ) ); ?>">The things I'm building</a>
                <a href="<?php echo esc_url( home_url( '/music-releases/' ) ); ?>">The music</a>
                <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">Field notes</a>
                <a href="<?php echo esc_url( 'https://github.com/suzyeaston' ); ?>" target="_blank" rel="noopener noreferrer">GitHub</a>
            </div>
        </footer>
    </div>
</main>
<?php get_footer(); ?>

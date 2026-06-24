<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content" class="home-layout">

    <section class="home-hero hero hero-section crt-block" aria-labelledby="home-hero-title">
        <div class="hero-grid">
            <div class="hero-main">
                <p class="hero-eyebrow pixel-font"><?php echo esc_html( 'Vancouver · AI Strategist · Creative Technologist · Bass' ); ?></p>
                <h1 id="home-hero-title" class="hero-core-headline"><?php echo esc_html( 'Day: AI infrastructure.' ); ?><br><?php echo esc_html( 'Night: bass and weird tools.' ); ?></h1>
                <p class="hero-copy"><?php echo esc_html( 'AI strategy and solutions engineering at Quercus IT. Formerly a touring bassist — national tours, MuchMusic, recorded with Steve Albini in Chicago. Still releasing music. Still building weird tools.' ); ?></p>
                <div class="home-cta-row hero-cta-group">
                    <a href="<?php echo esc_url( home_url( '/projects/' ) ); ?>" class="pixel-button hero-primary-cta"><?php echo esc_html( 'Explore Projects' ); ?></a>
                    <a href="#music" class="pixel-button hero-secondary-cta"><?php echo esc_html( 'Hear the Music' ); ?></a>
                    <button class="pixel-button hero-secondary-cta" type="button" data-contact-trigger aria-haspopup="dialog" aria-controls="contact-suzy-modal"><?php echo esc_html( 'Contact' ); ?></button>
                </div>
            </div>
            <aside class="hero-side hero-photo-card" aria-label="Hero portrait">
                <div class="hero-photo-frame">
                    <a class="hero-photo-link hero-photo-link-block" href="<?php echo esc_url( home_url( '/bio/' ) ); ?>">
                        <img class="hero-photo" src="<?php echo esc_url( home_url( '/wp-content/uploads/2026/01/IMG_9003.jpg' ) ); ?>" alt="<?php echo esc_attr( 'Suzy Easton' ); ?>" loading="lazy" decoding="async">
                    </a>
                </div>
                <p class="hero-photo-caption pixel-font"><a class="hero-photo-link" href="<?php echo esc_url( home_url( '/bio/' ) ); ?>"><?php echo esc_html( 'Vancouver, BC / Read bio →' ); ?></a></p>
                <?php get_template_part( 'parts/lousy-outages-teaser' ); ?>
            </aside>
        </div>
    </section>

    <section id="music" class="music-world crt-block" aria-labelledby="music-world-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( '// NIGHT SHIFT //' ); ?></p>
        <h2 id="music-world-title" class="pixel-font"><?php echo esc_html( 'Bass. Records. That one time in Chicago.' ); ?></h2>
        <p><?php echo esc_html( 'National touring bassist. Minto and The Smokes. Canada-wide, MuchMusic, recorded with Steve Albini in Chicago. Still releasing — somewhere between Sade, Grimes, and Metric.' ); ?></p>
        <div class="home-music-player">
            <?php
            // Drop in your Bandcamp embed iframe here — replace ALBUM_ID with the numeric ID from your Bandcamp album URL.
            // Example: <iframe style="border:0;width:100%;height:120px;" src="https://bandcamp.com/EmbeddedPlayer/album=ALBUM_ID/size=large/bgcol=000000/linkcol=39ff14/tracklist=false/artwork=small/transparent=true/" seamless></iframe>
            ?>
            <div class="home-cta-row">
                <a class="pixel-button" href="https://suzyeaston.bandcamp.com" target="_blank" rel="noopener noreferrer"><?php echo esc_html( 'Listen on Bandcamp' ); ?></a>
                <a class="pixel-button" href="<?php echo esc_url( home_url( '/music-releases/' ) ); ?>"><?php echo esc_html( 'Music Releases' ); ?></a>
            </div>
        </div>
    </section>

    <section class="home-terminal-strip crt-block" aria-label="Identity readout">
        <div class="home-terminal-readout" role="presentation">
            <p>
                <span class="home-terminal-key"><?php echo esc_html( 'OPERATOR' ); ?></span>
                <span class="home-terminal-dots" aria-hidden="true"></span>
                <span class="home-terminal-val"><?php echo esc_html( 'Suzy Easton' ); ?></span>
            </p>
            <p>
                <span class="home-terminal-key"><?php echo esc_html( 'LOCATION' ); ?></span>
                <span class="home-terminal-dots" aria-hidden="true"></span>
                <span class="home-terminal-val"><?php echo esc_html( 'Vancouver, BC' ); ?></span>
            </p>
            <p>
                <span class="home-terminal-key"><?php echo esc_html( 'DAY JOB' ); ?></span>
                <span class="home-terminal-dots" aria-hidden="true"></span>
                <span class="home-terminal-val"><?php echo esc_html( 'AI Strategist & Solutions Eng. · Quercus IT' ); ?></span>
            </p>
            <p>
                <span class="home-terminal-key"><?php echo esc_html( 'NIGHT JOB' ); ?></span>
                <span class="home-terminal-dots" aria-hidden="true"></span>
                <span class="home-terminal-val"><?php echo esc_html( 'Bass. Bandcamp. Tools that shouldn\'t work but do.' ); ?></span>
            </p>
            <p>
                <span class="home-terminal-key"><?php echo esc_html( 'TOURING' ); ?></span>
                <span class="home-terminal-dots" aria-hidden="true"></span>
                <span class="home-terminal-val"><?php echo esc_html( 'Canada-wide · MuchMusic · Steve Albini in Chicago' ); ?></span>
            </p>
            <p class="home-terminal-status">
                <span class="home-status-tag"><?php echo esc_html( 'EMPLOYED' ); ?></span>
                <span class="home-status-tag"><?php echo esc_html( 'BUILDING' ); ?></span>
                <span class="home-status-tag"><?php echo esc_html( 'RELEASING' ); ?></span>
            </p>
        </div>
        <div class="home-cta-row">
            <button class="pixel-button" type="button" data-contact-trigger aria-haspopup="dialog" aria-controls="contact-suzy-modal"><?php echo esc_html( 'Contact' ); ?></button>
            <a class="pixel-button" href="<?php echo esc_url( home_url( '/bio/' ) ); ?>"><?php echo esc_html( 'Full bio' ); ?></a>
        </div>
    </section>

    <section class="home-project-grid crt-block" aria-labelledby="selected-projects-title">
        <p class="home-section-kicker pixel-font"><?php echo esc_html( '// SELECT YOUR BUILD //' ); ?></p>
        <h2 id="selected-projects-title" class="pixel-font"><?php echo esc_html( 'Projects' ); ?></h2>
        <div class="selected-work__grid">
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html( 'Lousy Outages' ); ?></h3>
                <p><?php echo esc_html( 'Official status pages lie. This one doesn\'t.' ); ?></p>
                <a class="pixel-button" href="<?php echo esc_url( home_url( '/lousy-outages/' ) ); ?>"><?php echo esc_html( 'Open' ); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html( 'Gastown Simulator' ); ?></h3>
                <p><?php echo esc_html( 'Vancouver in a browser. Maps, routes, civic data.' ); ?></p>
                <a class="pixel-button" href="<?php echo esc_url( home_url( '/page-gastown-sim/' ) ); ?>"><?php echo esc_html( 'Explore' ); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html( 'Track Analyzer' ); ?></h3>
                <p><?php echo esc_html( 'Rough mix goes in. Notes come out.' ); ?></p>
                <a class="pixel-button" href="<?php echo esc_url( home_url( '/suzys-track-analyzer/' ) ); ?>"><?php echo esc_html( 'Try it' ); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html( 'What Would Steve Do' ); ?></h3>
                <p><?php echo esc_html( 'AI Albini-isms for when your mix sounds wrong.' ); ?></p>
                <a class="pixel-button" href="<?php echo esc_url( home_url( '/albini-qa/' ) ); ?>"><?php echo esc_html( 'Ask Steve' ); ?></a>
            </article>
            <article class="home-project-card selected-work__card">
                <h3 class="pixel-font"><?php echo esc_html( 'ASMR Lab' ); ?></h3>
                <p><?php echo esc_html( 'Audio/visual experiments. Probably fine.' ); ?></p>
                <a class="pixel-button" href="<?php echo esc_url( home_url( '/asmr-lab/' ) ); ?>"><?php echo esc_html( 'Enter' ); ?></a>
            </article>
        </div>
        <div class="home-cta-row home-project-grid__all">
            <a class="pixel-button" href="<?php echo esc_url( home_url( '/projects/' ) ); ?>"><?php echo esc_html( 'All projects →' ); ?></a>
        </div>
    </section>

</main>

<?php get_footer(); ?>

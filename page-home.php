<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
    <div class="hero-section">
        <h1 class="pixel-font">Hi, I&rsquo;m Suzy Easton.</h1>
        <p class="tagline">I&rsquo;m a musician, technologist, and creative builder based in Vancouver.<br>I&rsquo;ve toured across Canada, recorded with Steve Albini, and I&rsquo;m currently releasing new music while building QA systems and interactive tools online.<br>I&rsquo;m also active in local advocacy work—and exploring a run for Vancouver City Council in 2026.</p>
        <p class="arcade-subtext">Insert coin to explore</p>
        <div class="puck-icon">🏒</div>

        <div class="button-cluster">
            <div class="button-group">
                <h3 class="group-title">🎵 Listen</h3>
                <div class="group-buttons">
                    <a href="https://suzyeaston.bandcamp.com" class="pixel-button" target="_blank">Bandcamp</a>
                    <a href="/podcast" class="pixel-button">Podcast: Easy Living</a>
                </div>
            </div>

            <div class="button-group">
                <h3 class="group-title">🛠 Play / Build</h3>
                <div class="group-buttons">
                    <a href="/riff-generator" class="pixel-button">Riff Generator</a>
                    <a href="/arcade" class="pixel-button">Canucks Game</a>
                </div>
            </div>

            <div class="button-group">
                <h3 class="group-title">📺 Watch</h3>
                <div class="group-buttons">
                    <a href="/social-media" class="pixel-button">Livestream</a>
                    <a href="/music-releases" class="pixel-button">Upcoming Events</a>
                </div>
            </div>

            <div class="button-group">
                <h3 class="group-title">📖 Read</h3>
                <div class="group-buttons">
                    <a href="/bio" class="pixel-button">About Suzy</a>
                    <a href="/albini-qa" class="pixel-button">Albini Q&A</a>
                </div>
            </div>
        </div>

        <!-- Bio Panel -->
        <div class="bio-panel">
            <div class="bio-content">
                <p>Ex-touring bassist turned web-weirdness maker and accidental tenant organizer.</p>
                <p>I mix music, code, and advocacy to build tools that blur the line between art, community, and tech.</p>
                <p>Based in Gastown.</p>
            </div>
        </div>
    </div>

    <section class="featured-content">
        <div class="featured-item">
            <h2 class="pixel-font">What's New</h2>
            <div class="news-grid">
                <div class="news-item">
                    <h3>Live Stream Schedule</h3>
                    <p>Join me every Friday at 8PM PST</p>
                    <a href="/social-media" class="more-button">Watch Live</a>
                </div>
            </div>
        </div>

        <div class="featured-item">
            <h2 class="pixel-font">Latest Podcast</h2>
            <p class="pixel-font">"Easy Living with Suzy Easton" - Stories, insights, and laughter about life in Vancouver</p>
            <?php
            $latest_podcast = new WP_Query(array(
                'posts_per_page' => 1,
                'post_type' => 'podcast'
            ));
            
            if ($latest_podcast->have_posts()) :
                while ($latest_podcast->have_posts()) :
                    $latest_podcast->the_post();
                    ?>
                    <div class="podcast-preview">
                        <h3><?php the_title(); ?></h3>
                        <?php the_excerpt(); ?>
                        <a href="<?php the_permalink(); ?>" class="more-button">Listen Now</a>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
            endif;
            ?>
        </div>

    </section>

    <section class="now-listening">
        <h2 class="pixel-font">Now Listening</h2>
        <div id="now-listening-widget"></div>
    </section>

    <section class="advocacy-section">
        <h2 class="pixel-font">DTES & City Council Advocacy</h2>
        <p>I'm working with neighbours in the Downtown Eastside to keep vulnerable residents housed and heard. I'm also laying the groundwork for a 2026 City Council campaign focused on housing justice.</p>
        <div class="group-buttons">
            <a href="/advocacy" class="pixel-button">Learn More</a>
            <a href="/contact" class="pixel-button">Get Involved</a>
            <a href="https://www.carnegiehousingproject.ca/events" class="pixel-button" target="_blank">See Local Housing Events</a>
        </div>
    </section>


    <section class="support-section">
        <h2 class="pixel-font">Support My Creative Journey</h2>
        <p class="pixel-font">If you'd like to support my creative and technical endeavors, consider buying me a coffee on <a href="https://www.buymeacoffee.com/wi0amge" target="_blank" class="support-link">Buy Me a Coffee</a></p>
        <p class="pixel-font">For collaborations or just to chat, reach out at <a href="mailto:suzyeaston@icloud.com" class="support-link">suzyeaston@icloud.com</a></p>
    </section>
</main>

<?php get_footer(); ?>

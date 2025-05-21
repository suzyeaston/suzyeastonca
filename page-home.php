<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
    <div class="hero-section">
        <h1 class="pixel-font">Suzy Easton</h1>
        <p class="tagline">Vancouver musician, Canucks fan, and creative technologist who builds weird musical stuff on the internet.</p>
        
        <div class="cta-buttons">
            <a href="https://suzyeaston.bandcamp.com" class="action-button" target="_blank">🎧 Listen on Bandcamp</a>
            <a href="/arcade" class="action-button">🎮 Play Canucks Game</a>
            <a href="/riff-generator" class="action-button">🎸 Riff Generator</a>
            <a href="/podcast" class="action-button">🎙️ Podcast: Easy Living</a>
            <a href="/social-media" class="action-button">📡 Watch Livestream</a>
        </div>

        <!-- Bio Panel -->
        <div class="bio-panel">
            <div class="bio-content">
                <p>Former touring bassist turned creative builder.</p>
                <p>I write music, make tools, break things on purpose, and try to make tech feel human.</p>
                <p>Based in Gastown.</p>
            </div>
        </div>
    </div>

    <!-- What's New Section -->
    <section class="whats-new-section">
        <h2 class="pixel-font">In the Wild</h2>
        <div class="news-grid">
            <div class="news-item">
                <h3>Live Jams</h3>
                <p>Every Friday @ 8PM PST</p>
                <a href="/social-media" class="more-button">Join the Stream</a>
            </div>
        </div>
    </section>

    <section class="featured-content">
        <div class="featured-item">
            <h2 class="pixel-font">What's New</h2>
            <div class="news-grid">
                <div class="news-item">
                    <h3>Featured in Vancouver Weekly</h3>
                    <p>"Suzy Easton's retro arcade brings Vancouver's music scene to life"</p>
                    <a href="#" class="more-button">Read More</a>
                </div>
                <div class="news-item">
                    <h3>New Album Release</h3>
                    <p>Pixel Punk Dreams - Available now on all platforms</p>
                    <a href="https://suzyeaston.bandcamp.com" class="more-button" target="_blank">Listen Now</a>
                </div>
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

        <div class="featured-item">
            <h2 class="pixel-font">Tech & Music Projects</h2>
            <ul class="project-list">
                <li>Live video jam sessions</li>
                <li>New music releases</li>
                <li>Comedic documentary filmmaking about life in Vancouver</li>
            </ul>
        </div>
    </section>

    <section class="newsletter">
        <h2 class="pixel-font">Join the Pixel Punk Squad</h2>
        <p class="pixel-font">Get exclusive updates, new music drops, and secret arcade codes!</p>
        <?php echo do_shortcode('[newsletter_form]'); ?>
    </section>

    <section class="support-section">
        <h2 class="pixel-font">Support My Creative Journey</h2>
        <p class="pixel-font">If you'd like to support my creative and technical endeavors, consider buying me a coffee on <a href="https://www.buymeacoffee.com/wi0amge" target="_blank" class="support-link">Buy Me a Coffee</a></p>
        <p class="pixel-font">For collaborations or just to chat, reach out at <a href="mailto:suzyeaston@icloud.com" class="support-link">suzyeaston@icloud.com</a></p>
    </section>
</main>

<?php get_footer(); ?>

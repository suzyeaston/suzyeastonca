<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
    <div class="hero-section">
        <h1 class="pixel-font">Suzy Easton</h1>
        <p class="tagline">Vancouver Musician • Tech Pro • Pixel Punk</p>
        
        <div class="cta-buttons">
            <a href="https://suzyeaston.bandcamp.com" class="action-button" target="_blank">🎧 Listen on Bandcamp</a>
            <a href="/riff-generator" class="action-button">🕹️ Riff Generator</a>
            <a href="/arcade" class="action-button">🎮 Play Arcade Games</a>
            <a href="/podcast" class="action-button">🎙️ Podcast</a>
        </div>
    </div>

    <section class="featured-content">
        <div class="featured-item">
            <h2 class="pixel-font">Latest Music</h2>
            <div class="music-grid">
                <?php
                $latest_posts = new WP_Query(array(
                    'posts_per_page' => 3,
                    'post_type' => 'post'
                ));
                
                if ($latest_posts->have_posts()) :
                    while ($latest_posts->have_posts()) :
                        $latest_posts->the_post();
                        ?>
                        <div class="music-item">
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

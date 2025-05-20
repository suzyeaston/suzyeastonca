<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
    <div class="hero-section">
        <h1 class="pixel-font">Suzy Easton</h1>
        <p class="tagline">Vancouver Musician • Tech Pro • Pixel Punk</p>
        
        <div class="bio-section">
            <p class="pixel-font">Hello! I'm Suzy Easton, a 42-year-old, Vancouver-born musician and tech enthusiast. My musical journey began with childhood piano lessons, leading to open mic performances in my teens. After studying classical music and recording arts, I joined the psychedelic rock band Seafoam, delved into the punk scene, and eventually played bass for rock band The Smokes/Minto, touring across Canada and recording in Chicago with the legendary Steve Albini (Pixies/Nirvana).</p>
            
            <p class="pixel-font">Alongside music, I ventured into technology, building a successful career in IT, QA, WebDev, Software Development and software operations, self-taught, building experience through contracting. It's been awesome, I've worked with companies like Electronic Arts, IBM, Western Forest Products, ADP Canada, Crisis Intervention & Suicide Prevention Centre of BC, WineDirect, and Alida.</p>
        </div>
        
        <div class="cta-buttons">
            <a href="/support" class="action-button">Support the Artist</a>
            <a href="/riff-generator" class="action-button">Generate a Riff</a>
            <a href="/arcade" class="action-button">Play Arcade Games</a>
            <a href="/podcast" class="action-button">Listen to Podcast</a>
        </div>
    </div>

    <section class="featured-content">
        <div class="featured-item">
            <h2 class="pixel-font">Latest Music</h2>
            <?php
            $latest_post = new WP_Query(array(
                'posts_per_page' => 1,
                'post_type' => 'post'
            ));
            
            if ($latest_post->have_posts()) :
                while ($latest_post->have_posts()) :
                    $latest_post->the_post();
                    ?>
                    <div class="music-preview">
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

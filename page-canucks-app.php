<?php
/*
Template Name: Canucks App
*/
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <div class="canucks-twitter-feed">
            <h2>Real-Time Canucks News</h2>
            <!-- This blockquote embed is how Twitter recommends you embed a timeline -->
            <blockquote class="twitter-timeline" data-width="600" data-height="600" data-theme="dark" 
                href="https://twitter.com/officialsuzye?ref_src=twsrc%5Etfw">
                Tweets by officialsuzye
            </blockquote>
            <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
        </div>

        <?php
          // This outputs the Canucks scoreboard from the shortcode
          echo do_shortcode('[canucks_scoreboard]');
        ?>
        
    </main><!-- #main -->
</div><!-- #primary -->

<?php get_footer(); ?>


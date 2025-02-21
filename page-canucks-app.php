<?php
/*
Template Name: Canucks App
*/
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <!-- X (Twitter) Feed (may require you to be logged in, or for your account to be public) -->
        <div class="canucks-twitter-feed">
            <h2>Real-Time Canucks News</h2>
            <a class="twitter-timeline"
               href="https://twitter.com/officialsuzye?ref_src=twsrc%5Etfw">
               Tweets from Suzy Easton, not sure if this will work lol
            </a>
            <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
        </div>

        <!-- Now the combined scoreboard + news feed -->
        <?php echo do_shortcode('[canucks_app]'); ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php get_footer(); ?>



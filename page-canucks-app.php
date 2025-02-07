<?php
/*
Template Name: Canucks App
*/
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php
          // This outputs the Canucks scoreboard
          echo do_shortcode('[canucks_scoreboard]');
        ?>
    </main><!-- #main -->
</div><!-- #primary -->

<?php get_footer(); ?>

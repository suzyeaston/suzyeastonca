<?php
/**
 * Template Name: Canucks App
 *
 * This page template displays the Canucks schedule using the canucks_scoreboard shortcode.
 */
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php echo do_shortcode('[canucks_scoreboard]'); ?>
    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();

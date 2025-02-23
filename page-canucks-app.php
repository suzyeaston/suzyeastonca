<?php
/*
Template Name: Canucks App
*/
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <!-- Display the combined Canucks App (News at top, followed by Schedule) -->
        <?php echo do_shortcode('[canucks_app]'); ?>
    </main><!-- #main -->
</div><!-- #primary -->

<?php get_footer(); ?>




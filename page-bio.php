<?php
/*
Template Name: Bio Page
*/
get_header();
?>

<main id="main-content">
    <section class="page-content">
        <?php
        while ( have_posts() ) : the_post();
        ?>
        <div id="bio-container" class="scrolling-text"><?php the_content(); ?></div>
        <?php
        endwhile;
        ?>
    </section>
</main>

<?php get_footer(); ?>

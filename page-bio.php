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
            echo '<div class="bio-content">' . get_the_content() . '</div>';
        endwhile;
        ?>
    </section>
</main>

<?php get_footer(); ?>

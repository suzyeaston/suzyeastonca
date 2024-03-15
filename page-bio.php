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
            the_content();
        endwhile;
        ?>
    </section>
</main>

<?php
get_footer();
?>

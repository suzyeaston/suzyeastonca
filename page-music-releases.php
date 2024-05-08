<?php
/*
Template Name: Music Releases Page
*/

get_header();
?>
<main id="main-content">
    <header class="standard-header">
        Music Releases
    </header>
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

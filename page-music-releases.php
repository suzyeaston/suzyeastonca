<?php
/*
Template Name: Music Releases Page
*/

get_header();
?>
<main id="main-content">
    <header class="page-header">
        <h1 class="glowing-header">Music Releases</h1>
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

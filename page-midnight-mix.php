<?php
/*
Template Name: Midnight Mix Page
*/

get_header();
?>
<main id="main-content">
    <header class="standard-header">
        Midnight Mix with Suzy Easton: Live Stream Music Sessions
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

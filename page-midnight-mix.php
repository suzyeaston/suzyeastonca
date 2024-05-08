<?php
/*
Template Name: Midnight Mix Page
*/

get_header();
?>
<main id="main-content">
    <header class="page-header">
        <h1 class="glowing-header">Midnight Mix with Suzy Easton: Live Stream Music Sessions</h1>
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

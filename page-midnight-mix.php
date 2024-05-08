<?php
/*
Template Name: Midnight Mix Page
*/

get_header();
?>
<main id="main-content">
    <header id="retro-game-header">
        <div id="stacked-nerd-title">Midnight Mix with Suzy Easton</div>
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

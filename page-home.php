<?php
/*
Template Name: Home Page
*/

get_header();
?>
<main id="main-content">
    <header id="retro-game-header">
        <div id="stacked-nerd-title" class="glowing-text">Stacked Nerd</div>
        <img src="https://suzyeaston.ca/wp-content/uploads/2024/03/suzy2.jpeg" alt="Suzy Easton" class="animated-image">
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

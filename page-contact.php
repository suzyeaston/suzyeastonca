<?php
/*
Template Name: Contact Page
*/

get_header();
?>
<main id="main-content">
    <header id="retro-game-header">
        <div id="stacked-nerd-title">Ways to Contact Suzy</div>
    </header>
    <section class="page-content">
        <p>Email: <a href="mailto:info@suzyeaston.ca">info@suzyeaston.ca</a></p>
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

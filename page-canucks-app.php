<?php
/*
Template Name: Canucks App
*/
get_header();
?>

<main id="main-content">
    <header id="canucks-app-header">
        <h1 class="glowing-text">Canucks Retro Stats App</h1>
    </header>

    <section id="canucks-content">
        <?php
        // Display the Canucks plugin shortcode here
        echo do_shortcode('[canucks_scoreboard]');
        ?>
    </section>
</main>

<style>
    #canucks-app-header {
        text-align: center;
        font-family: 'Press Start 2P', cursive;
        color: #00FF00;
        margin: 20px;
        text-shadow: 0 0 10px #00FF00, 0 0 20px #FF0000;
    }

    #canucks-content {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        background-color: #000;
        border: 3px solid #00FF00;
        color: #00FF00;
    }
</style>

<?php get_footer(); ?>

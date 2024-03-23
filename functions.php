<?php

function retro_game_music_theme_scripts() {
    
    wp_enqueue_style('retro-font', 'https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
    wp_enqueue_style('main-styles', get_stylesheet_uri());
    
    wp_enqueue_script('piano-script', get_template_directory_uri() . '/js/piano-script.js', array(), '1.0.1', true);

    if (is_front_page()) {
        wp_enqueue_script('game-init', get_template_directory_uri() . '/js/game-init.js', array(), '1.0.0', true);
    }
}
add_action('wp_enqueue_scripts', 'retro_game_music_theme_scripts');

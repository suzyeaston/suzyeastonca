<?php
/**
 * The header for our Retro Arcade theme
 *
 * Displays all of the <head> section and opening <body> tag,
 * plus our full‑screen starfield canvas.
 *
 * @package SuzysMusicTheme
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <link rel="profile" href="http://gmpg.org/xfn/11">

  <title>Suzy's Retro Arcade - Canucks Puck Bash Game</title>
  <meta name="description" content="Play a retro pixel hockey game starring the Vancouver Canucks. Shoot, score, and hear the Simple Minds goal horn. Built by Suzy Easton.">
  <meta name="keywords" content="Canucks hockey arcade, retro pixel games, 80s hockey, simple minds hockey song, Vancouver indie dev, free hockey games, nostalgic arcade, suzyeaston.ca, Canucks Puck Bash">
  <meta property="og:title" content="Canucks Puck Bash - Retro Hockey Arcade">
  <meta property="og:description" content="Shoot, score, and hear 'Don't You Forget About Me' in this 80s-style hockey arcade game.">
  <meta property="og:image" content="https://suzyeaston.ca/arcade/og-image.png">
  <meta property="og:url" content="https://suzyeaston.ca/arcade/">

  <!-- Main stylesheet -->
  <link rel="stylesheet" href="<?php bloginfo( 'stylesheet_url' ); ?>">

  <!-- Retro arcade font -->
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">

  <meta name="viewport" content="width=device-width,initial-scale=1">
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php 
  // Fires immediately after the opening <body> tag for plugins/themes
  if ( function_exists( 'wp_body_open' ) ) {
    wp_body_open();
  }
?>

<!-- Full‑screen moving starfield background -->
<canvas id="starfield"></canvas>

<?php
  // You can drop in your site header/branding or navigation here if you like,
  // or let individual page templates handle their own <header> blocks.
?>

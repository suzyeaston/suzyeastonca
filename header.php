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

  <title><?php wp_title( '|', true, 'right' ); bloginfo( 'name' ); ?></title>

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

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

  <?php
    global $wp;
    $site_name   = 'Suzy Easton';
    $default_img = 'https://suzyeaston.ca/arcade/og-image.png';

    if ( is_front_page() ) {
      $meta_title = 'Suzy Easton – Vancouver Musician & Creative Technologist';
      $meta_desc  = 'Home base for Vancouver, BC, Canada musician Suzy Easton. Explore music reviews, rock demos, retro games and AI-powered tools.';
      $meta_img   = $default_img;
    } elseif ( is_page_template( 'page-track-analyzer.php' ) ) {
      $meta_title = "Suzy's Track Analyzer – AI Vibe Checker for Musicians";
      $meta_desc  = 'Upload an MP3 and get a quick vibe check powered by AI—perfect for indie producers and music tech fans.';
      $meta_img   = $default_img;
    } elseif ( is_page_template( 'page-arcade.php' ) ) {
      $meta_title = 'Canucks Puck Bash - Retro Hockey Arcade';
      $meta_desc  = "Shoot, score, and hear 'Don't You Forget About Me' in this 80s-style hockey arcade game.";
      $meta_img   = $default_img;
    } else {
      $meta_title = wp_title( '|', false, 'right' ) . $site_name;
      $meta_desc  = get_bloginfo( 'description' );
      $meta_img   = $default_img;
    }
    $meta_url = home_url( add_query_arg( [], $wp->request ) );
  ?>
  <title><?php echo esc_html( $meta_title ); ?></title>
  <meta name="description" content="<?php echo esc_attr( $meta_desc ); ?>">
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?php echo esc_attr( $meta_title ); ?>">
  <meta property="og:description" content="<?php echo esc_attr( $meta_desc ); ?>">
  <meta property="og:image" content="<?php echo esc_url( $meta_img ); ?>">
  <meta property="og:url" content="<?php echo esc_url( $meta_url ); ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo esc_attr( $meta_title ); ?>">
  <meta name="twitter:description" content="<?php echo esc_attr( $meta_desc ); ?>">
  <meta name="twitter:image" content="<?php echo esc_url( $meta_img ); ?>">
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Person",
    "name": "Suzy Easton",
    "url": "https://www.suzyeaston.ca",
    "jobTitle": "Musician and Creative Technologist",
    "address": {
      "@type": "PostalAddress",
      "addressLocality": "Vancouver",
      "addressCountry": "CA"
    },
    "sameAs": [
      "https://suzyeaston.bandcamp.com",
      "https://soundcloud.com/suzyeaston",
      "https://instagram.com/suzyeaston",
      "https://youtube.com/@suzyeaston"
    ]
  }
  </script>

  <!-- Main stylesheet -->
  <link rel="stylesheet" href="<?php bloginfo( 'stylesheet_url' ); ?>">

  <!-- Retro arcade font -->
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php
  // Fires immediately after the opening <body> tag for plugins/themes
  if ( function_exists( 'wp_body_open' ) ) {
    wp_body_open();
  }
?>

<a href="/" class="home-link" aria-label="Home">🏠 Home</a>
<?php get_template_part('parts/bmc-button'); ?>

<!-- Full‑screen moving starfield background -->
<canvas id="starfield" role="img" aria-label="Animated starfield background"></canvas>

<?php
  // You can drop in your site header/branding or navigation here if you like,
  // or let individual page templates handle their own <header> blocks.
?>

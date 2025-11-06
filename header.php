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

    $meta_keywords = 'Suzy Easton, musician, creative technologist';

    if ( is_front_page() ) {
      $meta_title = 'Suzy Easton – Vancouver Musician & Creative Technologist';
      $meta_desc  = 'Suzanne (Suzy) Easton shares indie music spotlights, outage stories, retro-inspired tools, and Vancouver-based creative tech experiments.';
      $meta_keywords = 'Suzy Easton, Suzanne Easton, Vancouver musician, creative technologist, Lousy Outages, indie music, retro arcade website, outage analysis';
      $meta_img   = $default_img;
    } elseif ( is_page_template( 'page-track-analyzer.php' ) ) {
      $meta_title = "Suzy's Track Analyzer – AI Vibe Checker for Musicians";
      $meta_desc  = 'Upload an MP3 and get a quick vibe check powered by AI—perfect for indie producers and music tech fans.';
      $meta_keywords = 'track analyzer, music AI tool, Suzy Easton, mix feedback';
      $meta_img   = $default_img;
    } elseif ( is_page_template( 'page-arcade.php' ) ) {
      $meta_title = 'Canucks Puck Bash - Retro Hockey Arcade';
      $meta_desc  = "Shoot, score, and hear 'Don't You Forget About Me' in this 80s-style hockey arcade game.";
      $meta_keywords = 'Canucks arcade game, retro hockey game, Suzy Easton arcade';
      $meta_img   = $default_img;
    } else {
      $meta_title = wp_title( '|', false, 'right' ) . $site_name;
      $meta_desc  = get_bloginfo( 'description' );
      $meta_keywords = 'Suzy Easton, musician, creative technologist, Vancouver artist';
      $meta_img   = $default_img;
    }
    $meta_url = home_url( add_query_arg( [], $wp->request ) );
    $structured_data = [
      '@context' => 'https://schema.org',
      '@graph'   => [
        [
          '@type' => 'WebSite',
          'name' => $site_name,
          'url'  => home_url( '/' ),
          'description' => $meta_desc,
          'inLanguage'  => get_bloginfo( 'language' ),
          'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => home_url( '/?s={search_term_string}' ),
            'query-input' => 'required name=search_term_string',
          ],
        ],
        [
          '@type' => 'Person',
          'name' => 'Suzy Easton',
          'url'  => 'https://www.suzyeaston.ca',
          'jobTitle' => 'Musician and Creative Technologist',
          'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Vancouver',
            'addressCountry'  => 'CA',
          ],
          'sameAs' => [
            'https://suzyeaston.bandcamp.com',
            'https://soundcloud.com/suzyeaston',
            'https://instagram.com/suzyeaston',
            'https://youtube.com/@suzyeaston',
          ],
        ],
      ],
    ];
  ?>
  <title><?php echo esc_html( $meta_title ); ?></title>
  <meta name="description" content="<?php echo esc_attr( $meta_desc ); ?>">
  <meta name="keywords" content="<?php echo esc_attr( $meta_keywords ); ?>">
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
  <?php echo wp_json_encode( $structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); ?>
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

<header class="main-header">
  <a class="brand-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Home">
    <img
      src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/brand/suzanne-logo.svg' ); ?>"
      alt="Suzanne (Suzy) Easton"
      width="420" height="150"
      decoding="async" loading="eager" />
  </a>
  <?php get_template_part( 'parts/bmc-button' ); ?>
</header>

<!-- Full‑screen moving starfield background -->
<canvas id="starfield" role="img" aria-label="Animated starfield background"></canvas>

<?php
  // You can drop in your site header/branding or navigation here if you like,
  // or let individual page templates handle their own <header> blocks.
?>

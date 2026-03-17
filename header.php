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
      $meta_title = 'Suzy Easton | Vancouver Musician + Creative Technologist Building in Public';
      $meta_desc  = 'Suzy Easton is a Vancouver musician and creative technologist sharing public prototypes, AI experiments, music tools, and creative-tech lab notes in the open.';
      $meta_keywords = 'Suzy Easton, Vancouver creative technologist, musician and technologist, public prototypes, AI experiments, music tools, build in public, open source projects';
      $meta_img   = $default_img;
    } elseif ( is_page_template( 'page-asmr-lab.php' ) ) {
      $meta_title = 'ASMR Lab – experimental predecessor now under major redevelopment';
      $meta_desc  = 'ASMR Lab is Suzy\'s earlier audio/visual prototype that inspired the Gastown simulator and is now being rebuilt in public.';
      $meta_keywords = 'ASMR Lab, Gastown simulator predecessor, creative tech prototype, Suzy Easton';
      $meta_img   = $default_img;
    } elseif ( is_page_template( 'page-lousy-outages.php' ) ) {
      $meta_title = 'Lousy Outages – Retro outage dashboard for modern chaos';
      $meta_desc  = 'A retro terminal-style dashboard that tracks popular services, highlights incidents, and can send alerts when things go sideways.';
      $meta_keywords = 'lousy outages status dashboard, outage tracker, retro status board';
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
    } elseif ( is_page_template( 'page-coffee-for-builders.php' ) ) {
      $meta_title = 'Coffee for Builders in Vancouver | Suzy Easton';
      $meta_desc  = 'Coffee chats in Vancouver for people building things—tech, music, civic projects, and sports takes. Low-key, public, and intentional.';
      $meta_keywords = 'coffee chats Vancouver, builders, Suzy Easton, tech, music, civic projects, sports takes';
      $meta_img   = $default_img;
    } else {
      $meta_title = wp_title( '|', false, 'right' ) . $site_name;
      $meta_desc  = get_bloginfo( 'description' );
      $meta_keywords = 'Suzy Easton, musician, creative technologist, Vancouver artist';
      $meta_img   = $default_img;
    }
    if ( is_singular() ) {
      $meta_url = get_permalink();
    } else {
      $meta_url = home_url( add_query_arg( [], $wp->request ) );
    }
    $home_profile_graph = [];
    if ( is_front_page() ) {
      $home_profile_graph[] = [
        '@type' => 'ProfilePage',
        'name' => 'Suzy Easton',
        'url' => home_url( '/' ),
        'description' => $meta_desc,
        'mainEntity' => [
          '@type' => 'Person',
          'name' => 'Suzy Easton',
          'jobTitle' => 'Musician and Creative Technologist',
          'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Vancouver',
            'addressCountry' => 'CA',
          ],
          'sameAs' => [
            'https://suzyeaston.bandcamp.com',
            'https://soundcloud.com/suzyeaston',
            'https://instagram.com/suzyeaston',
            'https://youtube.com/@suzyeaston',
          ],
        ],
      ];
    }

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

    if ( ! empty( $home_profile_graph ) ) {
      $structured_data['@graph'] = array_merge( $structured_data['@graph'], $home_profile_graph );
    }
  ?>
  <title><?php echo esc_html( $meta_title ); ?></title>
  <meta name="description" content="<?php echo esc_attr( $meta_desc ); ?>">
  <meta name="keywords" content="<?php echo esc_attr( $meta_keywords ); ?>">
  <link rel="canonical" href="<?php echo esc_url( $meta_url ); ?>">
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
  <!-- Header wordmark (compact bar) -->
  <div class="se-header-branding">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="se-header-wordmark">
      <span class="se-header-name-main">SUZANNE</span>
      <span class="se-header-name-nick">(SUZY)</span>
      <span class="se-header-name-last">EASTON</span>
    </a>
  </div>
  <div class="header-actions">
    <button class="pixel-button header-contact-trigger"
            type="button"
            data-contact-trigger
            aria-haspopup="dialog"
            aria-controls="contact-suzy-modal"
            aria-label="Contact Suzy">
      CONTACT SUZY
    </button>
  </div>
</header>

<div class="se-contact-modal" id="contact-suzy-modal" data-contact-modal hidden>
  <div class="se-contact-modal__overlay" data-contact-close tabindex="-1"></div>
  <div class="se-contact-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="se-contact-title" aria-describedby="se-contact-copy">
    <div class="se-contact-modal__top">
      <button type="button" class="se-contact-modal__close" data-contact-close aria-label="Close contact form">✕</button>
      <h2 id="se-contact-title" class="pixel-font">Contact Suzy</h2>
      <p id="se-contact-copy" class="se-contact-modal__copy">Yo, what’s up? Drop a note and Suzy will get back to you.</p>
      <p class="se-contact-modal__audio-status" data-contact-audio-status aria-live="polite">Narrator loading…</p>
    </div>

    <div class="se-contact-modal__body">
      <form class="se-contact-form" data-contact-form data-endpoint="<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>" novalidate>
        <input type="hidden" name="action" value="se_contact_suzy">
        <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'se_contact_suzy' ) ); ?>">

        <label for="se-contact-name">Name</label>
        <input id="se-contact-name" name="name" type="text" autocomplete="name" required>

        <label for="se-contact-email">Email</label>
        <input id="se-contact-email" name="email" type="email" autocomplete="email" required>

        <label for="se-contact-message">Message</label>
        <textarea id="se-contact-message" name="message" rows="5" required></textarea>

        <label for="se-contact-chaos">Type “yowhatsup” so I know you’re not a chaos bot.</label>
        <input id="se-contact-chaos" name="chaos_check" type="text" autocapitalize="off" autocomplete="off" spellcheck="false" required>

        <p class="se-contact-form__status" data-contact-status aria-live="polite"></p>

        <div class="se-contact-form__actions">
          <button type="submit" class="pixel-button">Send message</button>
        </div>
      </form>

      <div class="se-contact-success" data-contact-success hidden>
        <p class="se-contact-success__headline">Message received. Suzy will get back to you soon.</p>
        <p>Want to fuel the weird little upgrades? <a href="https://buymeacoffee.com/wi0amge" target="_blank" rel="noopener noreferrer">Buy Suzy a coffee</a> or <a href="<?php echo esc_url( home_url( '/' ) ); ?>">share the site</a>.</p>
      </div>
    </div>

    <div class="se-contact-modal__footer">
      <p>Want to fuel the weird little upgrades? Buy Suzy a coffee or share the site.</p>
    </div>
  </div>
</div>

<!-- Hero banner lives in page templates (e.g., homepage hero); this header stays compact above it -->
<!-- Full‑screen moving starfield background -->
<canvas id="starfield" role="img" aria-label="Animated starfield background"></canvas>

<?php
  // You can drop in your site header/branding or navigation here if you like,
  // or let individual page templates handle their own <header> blocks.
?>

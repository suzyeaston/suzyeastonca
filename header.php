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
    $page_meta       = se_page_meta();
    $meta_title      = $page_meta['title'];
    $meta_desc       = $page_meta['description'];
    $meta_keywords   = $page_meta['keywords'];
    $meta_img        = $page_meta['image'];
    $meta_url        = $page_meta['url'];
    $structured_data = se_page_structured_data();
    $og_type         = function_exists( 'se_page_og_type' ) ? se_page_og_type() : 'website';
  ?>
  <title><?php echo esc_html( $meta_title ); ?></title>
  <meta name="description" content="<?php echo esc_attr( $meta_desc ); ?>">
  <meta name="keywords" content="<?php echo esc_attr( $meta_keywords ); ?>">
  <link rel="canonical" href="<?php echo esc_url( $meta_url ); ?>">
  <meta property="og:type" content="<?php echo esc_attr( $og_type ); ?>">
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
      <span class="se-header-name-main">SUZY</span>
      <span class="se-header-name-last">EASTON</span>
    </a>
  </div>
  <div class="header-actions">
    <nav class="se-header-nav" aria-label="Site actions">
      <a class="se-header-nav__link se-header-nav__link--shop" href="<?php echo esc_url( se_preserve_utm_url( home_url( '/shop/' ) ) ); ?>" data-hire-cta data-hire-cta-label="header_shop">SHOP</a>
      <a class="se-header-nav__link se-header-nav__link--blog" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">MEANWHILE</a>
      <a class="se-header-nav__link" href="<?php echo esc_url( se_preserve_utm_url( home_url( '/work-with-suzy/' ) ) ); ?>" data-hire-cta data-hire-cta-label="header_hire">HIRE SUZY</a>
    </nav>
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
      <p id="se-contact-copy" class="se-contact-modal__copy">Send a note about work, projects, music, prototypes, or the weird issue nobody can reproduce.</p>
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

        <label for="se-contact-chaos">Type “suzylab” so I know you’re not a chaos bot.</label>
        <input id="se-contact-chaos" name="chaos_check" type="text" autocapitalize="off" autocomplete="off" spellcheck="false" required>

        <p class="se-contact-form__status" data-contact-status aria-live="polite"></p>

        <div class="se-contact-form__actions">
          <button type="submit" class="pixel-button">Send message</button>
        </div>
      </form>

      <div class="se-contact-success" data-contact-success hidden>
        <p class="se-contact-success__headline">Message received. I’ll get back to you soon.</p>
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
<canvas id="starfield" role="img" aria-label="Starfield background"></canvas>

<?php
  // You can drop in your site header/branding or navigation here if you like,
  // or let individual page templates handle their own <header> blocks.
?>

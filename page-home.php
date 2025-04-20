<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">

  <!-- Hero / Logo -->
  <section class="hero">
    <h1 id="stacked-nerd-title" class="glowing-text">Stacked Nerd</h1>
    <img
      src="https://www.suzyeaston.ca/wp-content/uploads/2025/04/suzyquattro.jpg"
      srcset="
        https://www.suzyeaston.ca/wp-content/uploads/2025/04/suzyquattro.jpg 1x,
        https://www.suzyeaston.ca/wp-content/uploads/2025/04/suzyquattro.jpg 2x
      "
      alt="Suzy Quattro"
      class="hero-img"
    />
  </section>

  <!-- Retro Grid Menu -->
  <section id="menu-container">
    <?php
      $items = [
        ['Home',          home_url('/')],
        ['About',         home_url('/bio')],
        ['Podcast',       home_url('/easy-living-with-suzy-easton')],
        // this will now launch your Snake game
        ['Arcade',        home_url('/the-midnight-mix')],
        ['Canucks App',   home_url('/canucks-app')],
        ['Bandcamp Releases', home_url('/music-releases')],
        ['Social Media',  home_url('/social-media')],
        ['Contact',       home_url('/contact')],
        ['Albini Q&A',    home_url('/albini-qa')],
      ];

      foreach ( $items as $item ) :
        // build classes
        $classes = 'menu-item';
        if ( $item[0] === 'Arcade' ) {
          $classes .= ' play-menu';
        }
        if ( $item[0] === 'Albini Q&A' ) {
          $classes .= ' albini-menu';
        }
    ?>
      <div
        class="<?php echo esc_attr( $classes ); ?>"
        onclick="location.href='<?php echo esc_url( $item[1] ); ?>'"
      >
        <?php echo esc_html( $item[0] ); ?>
      </div>
    <?php endforeach; ?>
  </section>

</main>

<?php get_footer(); ?>

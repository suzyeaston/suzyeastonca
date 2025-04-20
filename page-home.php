<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">

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

  <section id="menu-container">
    <?php
      $items = [
        ['Home',    home_url('/')],
        ['About',   home_url('/bio')],
        ['Podcast', home_url('/easy-living-with-suzy-easton')],
        ['Live Music', home_url('/the-midnight-mix')],
        ['Canucks App', home_url('/canucks-app')],
        ['Bandcamp Releases', home_url('/music-releases')],
        ['Social Media', home_url('/social-media')],
        ['Contact', home_url('/contact')],
        ['Albini Q&A', home_url('/albini-qa')],
      ];
      foreach ($items as $item) : 
        $active_class = $item[0] === 'Albini Q&A' ? ' albini-menu' : '';
    ?>
      <div
        class="menu-item<?php echo $active_class; ?>"
        onclick="location.href='<?php echo esc_url($item[1]); ?>'"
      >
        <?php echo esc_html($item[0]); ?>
      </div>
    <?php endforeach; ?>
  </section>

</main>

<?php get_footer(); ?>


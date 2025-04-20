<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">

  <section class="hero">
    <h1 id="stacked-nerd-title" class="glowing-text">Stacked Nerd</h1>
    <div class="hero-image">
      <img
        src="<?php echo get_template_directory_uri(); ?>/assets/suzy2.jpeg"
        srcset="<?php echo get_template_directory_uri(); ?>/assets/suzy2.jpeg 1x,
                <?php echo get_template_directory_uri(); ?>/assets/suzy2@2x.jpeg 2x"
        alt="Suzy Easton"
      />
    </div>
  </section>

  <section id="menu-container">
    <?php
      $items = [
        ['Home',      home_url('/')],
        ['About',     home_url('/bio')],
        ['Podcast',   home_url('/easy-living-with-suzy-easton')],
        ['Live Music',home_url('/the-midnight-mix')],
        ['Canucks App',home_url('/canucks-app')],
        ['Bandcamp Releases',home_url('/music-releases')],
        ['Social Media',home_url('/social-media')],
        ['Contact',   home_url('/contact')],
        ['Albini Q&A',home_url('/albini-qa')],
      ];
      foreach($items as $item): ?>
        <div class="menu-item<?php echo $item[0]==='Albini Q&A' ? ' albini-menu' : ''?>"
             onclick="location.href='<?php echo esc_url($item[1]); ?>'">
          <?php echo esc_html($item[0]); ?>
        </div>
    <?php endforeach; ?>
  </section>

</main>

<?php get_footer(); ?>

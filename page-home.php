<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
  <header id="retro-game-header">
    <div id="stacked-nerd-title" class="glowing-text">Stacked Nerd</div>
    <img src="<?php echo get_template_directory_uri(); ?>/assets/suzy2.jpeg"
         alt="Suzy Easton" class="animated-image">
  </header>

  <section id="menu-container">
    <div class="menu-item" onclick="location.href='<?php echo home_url(); ?>'">
      Home
    </div>
    <div class="menu-item" onclick="location.href='<?php echo home_url('/bio'); ?>'">
      About
    </div>
    <div class="menu-item" onclick="location.href='<?php echo home_url('/easy-living-with-suzy-easton'); ?>'">
      Podcast - Easy Living
    </div>
    <div class="menu-item" onclick="location.href='<?php echo home_url('/the-midnight-mix'); ?>'">
      Live Music
    </div>
    <div class="menu-item" onclick="location.href='<?php echo home_url('/canucks-app'); ?>'">
      Canucks App
    </div>
    <div class="menu-item" onclick="location.href='<?php echo home_url('/music-releases'); ?>'">
      Bandcamp Releases
    </div>
    <div class="menu-item" onclick="location.href='<?php echo home_url('/social-media'); ?>'">
      Social Media
    </div>
    <div class="menu-item" onclick="location.href='<?php echo home_url('/contact'); ?>'">
      Contact
    </div>

    <!-- New Albini link -->
    <div class="menu-item albini-menu" onclick="location.href='<?php echo home_url('/albini-qa'); ?>'">
      Albini Q&A
    </div>
  </section>
</main>

<?php get_footer(); ?>

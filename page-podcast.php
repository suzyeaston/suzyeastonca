<?php
/**
 * Template Name: Podcast Page
 * Description: A dedicated page template for "Easy Living with Suzy Easton" podcast.
 */
get_header();
?>

<main id="main-content">

  <!-- HERO / HEADER -->
  <section id="retro-game-header" class="podcast-header">
    <h1 id="stacked-nerd-title" class="glowing-text">Easy Living with Suzy Easton</h1>
    <p class="podcast-subtitle">▶ Now Playing</p>
  </section>

  <!-- PAGE CONTENT -->
  <section class="page-content">
    <div class="bio-content">
      <p>
        Welcome, brave traveler of the internet, to <strong>Easy Living with Suzy Easton</strong>.  
        I’m Suzy: Vancouver‑born musician, tech, and full‑time cheese enthusiast.
      </p>

      <p>
        I used to rock out on bass across Canada, nearly freeze my fingers off on prairie tours, 
        and pop up on MuchMusic (when that was still a thing). Now, I work in tech.
      </p>

      <p>
        On this podcast, I share comedic stories about navigating Vancouver’s weird and wonderful quirks, 
        plus behind‑the‑scenes glimpses of my musical adventures.
      </p>
    </div>

    <!-- EMBED PODBEAN PLAYER -->
    <div class="music-embeds">
      <iframe 
        src="https://easylivingwithsuzyeaston.podbean.com/e/episode-embed/12345/" 
        height="200" width="100%" scrolling="no" frameborder="0"
        allow="autoplay; encrypted-media"
      ></iframe>
    </div>

    <div class="bio-content">
      <p>
        Intrigued? Click “▶ Play” above to dive in, or 
        <a href="https://easylivingwithsuzyeaston.podbean.com/" target="_blank">
          visit our Podbean page
        </a> for every episode.
      </p>
      <p>
        Thanks for stopping by
      </p>
    </div>
  </section>

</main>

<?php get_footer(); ?>

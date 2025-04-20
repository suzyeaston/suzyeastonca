<?php
/**
 * Template Name: Podcast Page
 * Description: A dedicated page template for "Easy Living with Suzy Easton" podcast.
 */
get_header();
?>

<main id="main-content">

  <!-- Retro Header -->
  <section id="retro-game-header" class="podcast-header">
    <h1 id="stacked-nerd-title" class="glowing-text">
      Easy Living with Suzy Easton
    </h1>
    <p class="podcast-subtitle">▶ Episode 1: Suzy’s Guide to Vancouver’s April 5th By‑Election</p>
    <p class="podcast-meta">
      Released March 10, 2025 &middot; 13 views on iHeart &middot; Hosted on PodBean
    </p>
  </section>

  <!-- Episode Description -->
  <section class="page-content">
    <div class="bio-content">
      <p>
        In this inaugural episode, I delve into Vancouver’s upcoming by‑election (April 5, 2025), 
        filling seats vacated by Christine Boyle and Adriane Carr. Expect my usual unfiltered take on 
        city politics—and maybe a bad bass pun or two.
      </p>
    </div>
  </section>

  <!-- Embeds Section -->
  <section class="embeds-container">

    <!-- PodBean Player -->
    <div class="embed-panel">
      <h2 class="panel-title">▶ Listen on PodBean</h2>
      <div class="music-embeds">
        <iframe 
          src="https://easylivingwithsuzyeaston.podbean.com/e/suzy-guide-to-vancouvers-april-5th-cutting-by-election/embed/"
          height="200" width="100%" scrolling="no" frameborder="0"
          allow="autoplay; encrypted-media"
        ></iframe>
      </div>
    </div>

    <!-- iHeart Widget -->
    <div class="embed-panel">
      <h2 class="panel-title">▶ Listen on iHeartRadio</h2>
      <div class="music-embeds">
        <iframe 
          src="https://www.iheart.com/podcast/1333-easy-living-with-suzy-eas-269998818/embed/player" 
          height="200" width="100%" frameborder="0" allow="autoplay; encrypted-media"
        ></iframe>
      </div>
    </div>

    <!-- YouTube Video -->
    <div class="embed-panel">
      <h2 class="panel-title">▶ Watch on YouTube</h2>
      <div class="music-embeds">
        <iframe 
          width="100%" height="315"
          src="https://www.youtube.com/embed/VeE9DX6IQCY?si=8Pj25LbSNdh5fHax"
          title="YouTube video player"
          frameborder="0" 
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
          allowfullscreen
        ></iframe>
      </div>
    </div>

    <!-- Bandcamp Track -->
    <div class="embed-panel">
      <h2 class="panel-title">▶ Stream on Bandcamp</h2>
      <div class="music-embeds">
        <iframe 
          style="border:0; width:100%; height:120px;" 
          src="https://bandcamp.com/EmbeddedPlayer/track=TRACK_ID/size=large/bgcol=000000/linkcol=00ff00/transparent=true/" 
          seamless>
          <a href="https://suzyeaston.bandcamp.com/track/your-track-slug">Your Track Name</a>
        </iframe>
      </div>
    </div>

    <!-- RSS Feed Link -->
    <div class="embed-panel rss-panel">
      <h2 class="panel-title">▶ RSS Feed</h2>
      <p>
        <a href="https://rss.com/podcasts/easylivingwithsuzyeaston/" target="_blank">
          Subscribe via RSS
        </a>
      </p>
    </div>

  </section>

</main>

<?php get_footer(); ?>

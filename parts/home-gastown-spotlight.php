<?php
/**
 * Homepage Gastown Simulator spotlight. Lightweight static SVG, no embedded
 * WebGL, location permission, tracking request or third-party map scripts.
 */
$gastown_url = home_url( '/gastown-sim/' );
?>
<section class="home-gastown-spotlight" aria-labelledby="home-gastown-spotlight-title">
  <div class="home-gastown-spotlight__copy">
    <p class="home-gastown-spotlight__eyebrow pixel-font">GASTOWN / FIELD SIMULATION <span aria-hidden="true">✦</span></p>
    <h2 id="home-gastown-spotlight-title">GASTOWN<br><span>SIMULATOR.</span></h2>
    <p class="home-gastown-spotlight__lead">The best view is through the gap.</p>
    <p class="home-gastown-spotlight__detail">Stand beside Waterfront Station at 555 W Cordova. The Landing is to the east, Station East’s open parking lot frames the harbour, and beyond it are Canada Place, a cruise ship, and the North Shore. Then step into the playable streets and walk to the Steam Clock.</p>
    <div class="home-gastown-spotlight__tags" aria-label="Simulator features">
      <span>City GIS geometry</span>
      <span>3D street explorer</span>
      <span>Station East lookout</span>
      <span>Browser-based</span>
    </div>
    <div class="home-gastown-spotlight__actions">
      <a class="home-gastown-spotlight__play" href="<?php echo esc_url( $gastown_url ); ?>">
        <span aria-hidden="true">▶</span> ENTER GASTOWN
      </a>
      <p>Desktop experience · keyboard + mouse</p>
    </div>
  </div>
  <figure class="home-gastown-spotlight__art" aria-labelledby="home-gastown-art-caption">
    <img
      src="<?php echo esc_url( get_template_directory_uri() . '/assets/illustrations/gastown-station-east-lookout.svg' ); ?>"
      width="1000"
      height="580"
      decoding="async"
      alt="Artist's street-level view of Station East: Waterfront Station on the left, The Landing heritage warehouse on the right, cars in the open-air parking lot, and a cruise ship at Canada Place against Burrard Inlet and the North Shore mountains."
    />
    <figcaption id="home-gastown-art-caption">555 W CORDOVA / STATION EAST LOOKOUT <span>ILLUSTRATED VIEW · SHIP REPRESENTATIVE</span></figcaption>
  </figure>
</section>

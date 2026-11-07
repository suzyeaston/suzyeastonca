<?php
/**
 * Homepage Gastown Simulator spotlight. Lightweight static SVG, no embedded
 * WebGL, location permission, tracking request or third-party map scripts.
 */
$gastown_url = home_url( '/gastown-sim/' );
?>
<section class="home-gastown-spotlight" aria-labelledby="home-gastown-spotlight-title">
  <div class="home-gastown-spotlight__copy">
    <p class="home-gastown-spotlight__eyebrow pixel-font">FEATURED WORLD / VANCOUVER, BC <span aria-hidden="true">✦</span></p>
    <h2 id="home-gastown-spotlight-title">THE CITY IS<br><span>PLAYABLE.</span></h2>
    <p class="home-gastown-spotlight__lead">A little piece of Gastown, rebuilt for wandering.</p>
    <p class="home-gastown-spotlight__detail">Start at Waterfront Station. Walk West Cordova and Water Street toward the Steam Clock. Follow the real street geometry, encounter animated locals, and look north toward Canada Place and Burrard Inlet.</p>
    <div class="home-gastown-spotlight__tags" aria-label="Simulator features">
      <span>City GIS geometry</span>
      <span>3D street explorer</span>
      <span>Harbour view</span>
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
    <svg viewBox="0 0 700 390" role="img" aria-labelledby="home-gastown-map-title home-gastown-map-desc" preserveAspectRatio="xMidYMid meet">
      <title id="home-gastown-map-title">Gastown, Waterfront Station and Burrard Inlet</title>
      <desc id="home-gastown-map-desc">Original illustrative schematic of the North Shore mountains, Canada Place sails, and a walking route from Waterfront Station toward the Gastown Steam Clock. Not a turn-by-turn map.</desc>
      <defs>
        <linearGradient id="gastown-harbour-sky" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0" stop-color="#183948"/>
          <stop offset=".72" stop-color="#0e2734"/>
          <stop offset="1" stop-color="#09161e"/>
        </linearGradient>
        <linearGradient id="gastown-inlet-water" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0" stop-color="#16596d"/>
          <stop offset="1" stop-color="#0a2638"/>
        </linearGradient>
      </defs>
      <rect width="700" height="390" rx="12" fill="#06131c"/>
      <path d="M0 0 H700 V190 H0Z" fill="url(#gastown-harbour-sky)"/>
      <path d="M0 157 L50 121 90 137 135 105 160 115 215 73 238 92 266 58 292 79 319 46 354 85 395 62 430 87 480 53 526 90 557 70 604 103 635 86 700 128 V205 H0Z" fill="#6c95a4" opacity=".78"/>
      <path d="M0 179 L38 150 105 164 167 130 220 147 275 112 314 130 360 111 403 146 453 119 530 159 589 128 645 155 700 147 V217 H0Z" fill="#224f62"/>
      <path d="M212 77 L220 82 228 79 M311 49 L319 46 329 58 M471 59 L480 53 493 64" stroke="#d6e9e9" stroke-width="4" fill="none" stroke-linecap="round"/>
      <path d="M0 210 Q270 189 700 210 V280 H0Z" fill="url(#gastown-inlet-water)"/>
      <g stroke="#6fbbc5" stroke-width="2" opacity=".35">
        <path d="M48 227 h83 M158 239 h80 M288 218 h95 M429 241 h98 M553 220 h97 M90 262 h130 M344 253 h140"/>
      </g>
      <g transform="translate(318 185)" aria-hidden="true">
        <path d="M-72 28 L70 28 85 40 -95 40Z" fill="#c2dee4"/>
        <path d="M-70 17 L64 17 70 28 -72 28Z" fill="#406879"/>
        <path d="M-66 9 L-34 -51 -19 11Z M-30 9 L2 -65 16 11Z M7 9 L32 -53 45 11Z M40 9 L63 -39 74 11Z" fill="#f2f5f0" stroke="#bfd2db" stroke-width="2"/>
        <path d="M-84 48 L93 48" stroke="#67d9d5" stroke-width="2" opacity=".8"/>
      </g>
      <path d="M0 268 L700 268 V390 H0Z" fill="#0b1e26"/>
      <g stroke="#254b58" stroke-width="1" opacity=".65">
        <path d="M0 300 H700 M0 332 H700 M0 364 H700"/>
        <path d="M35 268 V390 M100 268 V390 M165 268 V390 M230 268 V390 M295 268 V390 M360 268 V390 M425 268 V390 M490 268 V390 M555 268 V390 M620 268 V390"/>
      </g>
      <path d="M76 342 C160 339 184 352 265 352 S395 323 458 320 S565 299 635 295" fill="none" stroke="#28343b" stroke-width="28" stroke-linecap="round"/>
      <path d="M76 342 C160 339 184 352 265 352 S395 323 458 320 S565 299 635 295" fill="none" stroke="#f4d478" stroke-width="4" stroke-dasharray="4 11" stroke-linecap="round"/>
      <circle cx="85" cy="343" r="13" fill="#0b2432" stroke="#91e0f3" stroke-width="3"/>
      <circle cx="85" cy="343" r="5" fill="#c7f2ff"/>
      <circle cx="635" cy="295" r="12" fill="#0b2432" stroke="#f9c66a" stroke-width="3"/>
      <circle cx="635" cy="295" r="5" fill="#f9c66a"/>
      <g fill="#f3f2e7" font-family="ui-monospace, Menlo, monospace" font-size="13" font-weight="700">
        <text x="44" y="316">WATERFRONT</text>
        <text x="480" y="277">STEAM CLOCK</text>
        <text x="24" y="35" fill="#c3f4ec" font-size="11" letter-spacing="2">NORTH SHORE / BURRARD INLET</text>
        <text x="403" y="183" font-size="11" fill="#edf8f4">CANADA PLACE</text>
      </g>
      <g transform="translate(636 34)" stroke="#b6e5df" stroke-width="2" fill="none">
        <path d="M0 31 V0 M-5 7 L0 0 5 7"/>
        <text x="0" y="-8" fill="#b6e5df" stroke="none" text-anchor="middle" font-size="12">N</text>
      </g>
    </svg>
    <figcaption id="home-gastown-art-caption">WATERFRONT → WATER ST → STEAM CLOCK <span>SCHEMATIC / NOT TO SCALE</span></figcaption>
  </figure>
</section>

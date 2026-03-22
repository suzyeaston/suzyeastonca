<?php
/*
Template Name: Gastown Simulator
*/

get_header();
?>
<main id="primary" class="content-area gastown-sim-page">
  <section id="gastown-sim-app" class="gastown-sim-shell">
    <header class="gastown-sim-header">
      <p class="gastown-sim-kicker">Prototype route: Waterfront Station → Water Street → Steam Clock</p>
      <h1>Gastown First-Person Simulator</h1>
      <p class="gastown-sim-intro">A stylized, geographically grounded Gastown walk through a focused Water Street slice with the Steam Clock staged at an intersection plaza. Click into the scene, look around, and walk the route.</p>
    </header>

    <div class="gastown-controls" role="group" aria-label="Simulator controls">
      <button type="button" class="pixel-button secondary" data-action="pause">Pause</button>
      <button type="button" class="pixel-button secondary" data-action="reset">Reset to route start</button>
      <label>
        Time of day
        <select name="time-of-day">
          <option value="morning" selected>Morning</option>
          <option value="afternoon">Afternoon</option>
          <option value="dusk">Dusk</option>
          <option value="night">Night</option>
        </select>
      </label>
      <label>
        Weather
        <select name="weather">
          <option value="clear" selected>Clear</option>
          <option value="drizzle">Drizzle</option>
          <option value="rain">Rain</option>
          <option value="thunderstorm">Thunderstorm</option>
          <option value="fog">Fog</option>
        </select>
      </label>
      <label>
        Mood
        <select name="mood">
          <option value="calm" selected>Calm</option>
          <option value="commuter">Commuter</option>
          <option value="lively">Lively</option>
          <option value="eerie">Eerie</option>
        </select>
      </label>
      <button type="button" class="pixel-button tiny secondary" data-action="debug-toggle">Toggle debug</button>
    </div>

    <section class="gastown-help" aria-label="Simulator controls guide">
      <h2>Quick controls</h2>
      <ul>
        <li><strong>Click scene</strong> to enter look mode</li>
        <li><strong>Mouse</strong> = look around</li>
        <li><strong>W A S D</strong> = move / strafe</li>
        <li><strong>Arrow keys</strong> = move forward/back + turn left/right</li>
        <li><strong>Mouse wheel</strong> = look up/down (while focused/in play mode)</li>
        <li><strong>Ctrl + ↑ / Ctrl + ↓</strong> = precise look up/down steps</li>
        <li><strong>Esc</strong> = release pointer / pause</li>
      </ul>
    </section>

    <p class="gastown-status" data-sim-status aria-live="polite">Loading simulator...</p>
    <p class="gastown-pointer-status" data-sim-pointer-status aria-live="polite">Pointer unlocked.</p>
    <p class="gastown-landmark" data-sim-landmark aria-live="polite">Nearest landmark: Station threshold</p>
    <p class="gastown-route-segment" data-sim-route-segment aria-live="polite">Route segment: Waterfront Station Threshold</p>
    <p class="gastown-interact-prompt" data-sim-interact-prompt aria-live="polite" hidden></p>

    <div class="gastown-sim-canvas" data-sim-canvas tabindex="-1">
      <aside class="gastown-minimap" aria-label="Route navigator minimap">
        <div class="gastown-minimap-toolbar" role="group" aria-label="Minimap zoom controls">
          <button type="button" class="gastown-minimap-zoom" data-action="minimap-zoom-in" aria-label="Zoom in minimap">+</button>
          <button type="button" class="gastown-minimap-zoom" data-action="minimap-zoom-out" aria-label="Zoom out minimap">−</button>
        </div>
        <canvas data-sim-minimap width="220" height="220"></canvas>
        <ul class="gastown-minimap-legend" aria-label="Minimap legend">
          <li><span class="dot player"></span> Player</li>
          <li><span class="dot station"></span> Station</li>
          <li><span class="dot steam"></span> Steam Clock</li>
          <li><span class="dot nearest"></span> Nearest landmark</li>
        </ul>
        <p class="gastown-minimap-label" data-sim-minimap-landmark>Nearest: Waterfront Station Threshold</p>
      </aside>
      <pre class="gastown-route-debug-overlay" data-route-debug-overlay hidden></pre>
    </div>

    <section class="gastown-debug" data-debug-panel hidden>
      <h2>Debug notes</h2>
      <ul>
        <li>W/S + Arrow Up/Down move forward/back; A/D strafe</li>
        <li>Arrow Left/Right turns the player heading</li>
        <li>Mouse look while pointer is locked</li>
        <li>Wheel and Ctrl+ArrowUp/Down also adjust pitch while focused/in play mode</li>
        <li>Walk bounds clamp keeps the player in the playable corridor slice</li>
        <li>Route debug can also be enabled with <code>?gastownDebug=1</code></li>
        <li>Audio zones: station rumble, steam clock core, split-building nightlife edge</li>
      </ul>
    </section>

    <!-- Template-ready teaser for homepage placement later.
      In progress: Gastown first-person simulator.
      Frequent updates are expected; hard refresh / clear cache may be needed after releases.
    -->
    <p class="gastown-teaser-snippet" hidden>
      In progress: our Gastown first-person simulator is evolving quickly. We ship frequent updates, so if a change looks odd, please hard refresh and clear cache.
    </p>

    <footer class="gastown-attribution" aria-live="polite">
      <p>Contains information licensed under the Open Government Licence – Vancouver.</p>
      <p data-gastown-osm-attribution hidden>Map data © OpenStreetMap contributors.</p>
    </footer>

    <div class="gastown-dialog-modal" data-dialog-modal role="dialog" aria-modal="true" aria-hidden="true" hidden>
      <div class="gastown-dialog-panel">
        <div class="gastown-dialog-header">
          <h2 data-dialog-title>Gastown guide</h2>
          <button type="button" class="pixel-button tiny secondary" data-dialog-close>Close</button>
        </div>
        <div class="gastown-dialog-body" data-dialog-body></div>
        <div class="gastown-dialog-actions">
          <div class="gastown-dialog-actions-dynamic" data-dialog-actions-dynamic aria-live="polite"></div>
          <button type="button" class="pixel-button secondary" data-dialog-close data-dialog-close-fallback>Back to walk</button>
        </div>
      </div>
    </div>
  </section>
</main>
<?php get_footer(); ?>

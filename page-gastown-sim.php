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
      <p class="gastown-sim-intro">A stylized, geographically grounded Gastown walk through one focused corridor slice. Click into the scene, look around, and walk the route.</p>
    </header>

    <div class="gastown-controls" role="group" aria-label="Simulator controls">
      <button type="button" class="pixel-button secondary" data-action="pause">Pause</button>
      <button type="button" class="pixel-button secondary" data-action="reset">Reset to route start</button>
      <label>
        Time of day
        <select name="time-of-day">
          <option value="morning" selected>Morning</option>
          <option value="afternoon">Afternoon</option>
          <option value="evening">Evening</option>
          <option value="night">Night</option>
        </select>
      </label>
      <label>
        Weather
        <select name="weather">
          <option value="clear">Clear</option>
          <option value="rain" selected>Rain</option>
          <option value="fog">Fog</option>
        </select>
      </label>
      <label>
        Mood
        <select name="mood">
          <option value="quiet">Quiet</option>
          <option value="eerie" selected>Eerie</option>
          <option value="nightlife">Nightlife</option>
          <option value="commuter">Commuter</option>
        </select>
      </label>
      <button type="button" class="pixel-button tiny secondary" data-action="debug-toggle">Toggle debug</button>
    </div>

    <section class="gastown-help" aria-label="Simulator controls guide">
      <h2>Quick controls</h2>
      <ul>
        <li><strong>Click scene</strong> to enter look mode</li>
        <li><strong>Mouse</strong> = look around</li>
        <li><strong>W A S D / arrow keys</strong> = move</li>
        <li><strong>Esc</strong> = release pointer / pause</li>
      </ul>
    </section>

    <p class="gastown-status" data-sim-status aria-live="polite">Loading simulator...</p>
    <p class="gastown-pointer-status" data-sim-pointer-status aria-live="polite">Pointer unlocked.</p>
    <p class="gastown-landmark" data-sim-landmark aria-live="polite">Nearest landmark: Station threshold</p>
    <p class="gastown-route-segment" data-sim-route-segment aria-live="polite">Route segment: Waterfront Station Threshold</p>

    <div class="gastown-sim-canvas" data-sim-canvas>
      <aside class="gastown-minimap" aria-label="Route navigator minimap">
        <canvas data-sim-minimap width="220" height="220"></canvas>
        <p class="gastown-minimap-label" data-sim-minimap-landmark>Nearest: Waterfront Station Threshold</p>
      </aside>
      <pre class="gastown-route-debug-overlay" data-route-debug-overlay hidden></pre>
    </div>

    <section class="gastown-debug" data-debug-panel hidden>
      <h2>Debug notes</h2>
      <ul>
        <li>WASD or arrow keys for movement</li>
        <li>Mouse look while pointer is locked</li>
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
  </section>
</main>
<?php get_footer(); ?>

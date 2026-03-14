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
      <p class="gastown-sim-intro">A stylized, geographically grounded night walk through one focused Gastown slice. Click Start, lock the pointer, and walk the corridor.</p>
    </header>

    <div class="gastown-controls" role="group" aria-label="Simulator controls">
      <button type="button" class="pixel-button" data-action="start">Start</button>
      <button type="button" class="pixel-button secondary" data-action="pause">Pause</button>
      <button type="button" class="pixel-button secondary" data-action="reset">Reset to route start</button>
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

    <p class="gastown-status" data-sim-status aria-live="polite">Loading simulator...</p>
    <p class="gastown-landmark" data-sim-landmark aria-live="polite">Nearest landmark: Station threshold</p>

    <div class="gastown-sim-canvas" data-sim-canvas></div>

    <section class="gastown-debug" data-debug-panel hidden>
      <h2>Debug notes</h2>
      <ul>
        <li>WASD or arrow keys for movement</li>
        <li>Mouse look while pointer is locked</li>
        <li>Corridor clamp keeps player in playable route slice</li>
        <li>Audio zones: station rumble, steam clock core, split-building nightlife edge</li>
      </ul>
    </section>
  </section>
</main>
<?php get_footer(); ?>

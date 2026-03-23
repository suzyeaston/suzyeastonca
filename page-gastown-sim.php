<?php
/*
Template Name: Gastown Simulator
*/

get_header();
?>
<main id="primary" class="content-area gastown-sim-page">
  <section id="gastown-sim-app" class="gastown-sim-shell">
    <header class="gastown-sim-header">
      <p class="gastown-sim-kicker">Lantern route: Waterfront Station → Water Street → Steam Clock</p>
      <h1>Walk into Gastown after the station doors fall quiet.</h1>
      <p class="gastown-sim-intro">A first-person heritage promenade through brick facades, paving glimmer, and the Steam Clock plaza. Step into the street, follow the landmarks, and let the neighborhood reveal itself before the controls do.</p>
    </header>

    <div class="gastown-stage-directions" role="group" aria-label="Primary simulator controls">
      <div class="gastown-stage-actions">
        <button type="button" class="pixel-button secondary" data-action="pause">Pause walk</button>
        <button type="button" class="pixel-button secondary" data-action="reset" aria-label="Reset to the start of the route">Return to station</button>
        <button type="button" class="pixel-button secondary" data-action="tutorial-open" aria-label="Open the controls tutorial overlay">How to roam</button>
      </div>
      <p class="gastown-stage-note">Click into the scene to enter look mode. The route, quest beats, and nearby encounters will surface in the heads-up display as you explore.</p>
    </div>

    <details class="gastown-world-menu">
      <summary>World settings &amp; diagnostics</summary>
      <div class="gastown-world-menu-grid">
        <section class="gastown-controls" aria-label="Simulator world settings">
          <h2>Scene direction</h2>
          <div class="gastown-controls-grid">
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
            <label class="gastown-checkbox-row">
              <input type="checkbox" name="low-graphics" data-setting="low-graphics" aria-label="Enable low graphics mode">
              Low graphics mode
            </label>
            <label class="gastown-checkbox-row">
              <input type="checkbox" name="reopen-tutorial" data-setting="reopen-tutorial" aria-label="Show the tutorial overlay again">
              Show tutorial on next load
            </label>
          </div>
        </section>

        <section class="gastown-help" aria-label="Simulator controls guide">
          <h2>Traveler's notes</h2>
          <ul>
            <li><strong>Click scene</strong> to enter look mode</li>
            <li><strong>Mouse</strong> to look around the block</li>
            <li><strong>W A S D</strong> to move and strafe</li>
            <li><strong>Arrow keys</strong> for walk/turn fallback controls</li>
            <li><strong>Mouse wheel</strong> or <strong>Ctrl + ↑ / Ctrl + ↓</strong> for pitch adjustments</li>
            <li><strong>Esc</strong> to release pointer and pause</li>
            <li><strong>M, M</strong> quickly to shift minimap orientation</li>
          </ul>
        </section>

        <section class="gastown-debug-meta" aria-label="Simulator diagnostic information">
          <h2>Diagnostics</h2>
          <p class="gastown-world-status" data-sim-world-status aria-live="polite">World data status: checking build provenance…</p>
          <p class="gastown-pointer-status" data-sim-pointer-status aria-live="polite">Pointer unlocked.</p>
          <button type="button" class="pixel-button tiny secondary" data-action="debug-toggle" aria-label="Toggle the debug notes panel">Toggle route diagnostics</button>
        </section>
      </div>
    </details>

    <section class="gastown-tutorial-overlay" data-tutorial-overlay role="dialog" aria-modal="true" aria-labelledby="gastown-tutorial-title" aria-describedby="gastown-tutorial-copy" hidden>
      <div class="gastown-dialog-panel">
        <div class="gastown-dialog-header">
          <h2 id="gastown-tutorial-title">Welcome to the Gastown Simulator</h2>
          <button type="button" class="pixel-button tiny secondary" data-action="tutorial-close" aria-label="Close tutorial overlay">Close</button>
        </div>
        <div class="gastown-dialog-body" id="gastown-tutorial-copy">
          <p>Click the scene to lock the pointer, use the mouse to look around, and move with W A S D or the arrow keys.</p>
          <p>Press E or click on nearby characters to talk. Press M M quickly to toggle the minimap between north-up and heading-up orientation.</p>
          <p>Screen reader note: status updates, landmark callouts, and quest progress are announced below the simulator.</p>
        </div>
        <div class="gastown-dialog-actions">
          <button type="button" class="pixel-button secondary" data-action="tutorial-start" aria-label="Start the simulator tutorial">Start tutorial</button>
          <button type="button" class="pixel-button secondary" data-action="tutorial-close">Skip for now</button>
        </div>
      </div>
    </section>
    <div class="gastown-sim-canvas" data-sim-canvas tabindex="-1">
      <div class="gastown-hud gastown-hud--top">
        <section class="gastown-hud-card gastown-hud-card--scene" aria-label="Current scene status">
          <p class="gastown-status" data-sim-status aria-live="polite">Loading simulator...</p>
          <div class="gastown-hud-subgrid">
            <p class="gastown-landmark" data-sim-landmark aria-live="polite">Nearest landmark: Station threshold</p>
            <p class="gastown-route-segment" data-sim-route-segment aria-live="polite">Route segment: Waterfront Station Threshold</p>
          </div>
        </section>
        <section class="gastown-hud-card gastown-hud-card--quest" aria-label="Quest status">
          <p class="gastown-hud-eyebrow">Current thread</p>
          <p class="gastown-quest-status" data-sim-quest-status aria-live="polite">Scavenger hunt: inactive.</p>
        </section>
      </div>

      <aside class="gastown-minimap" aria-label="Route navigator minimap">
        <div class="gastown-minimap-toolbar" role="group" aria-label="Minimap controls">
          <button type="button" class="gastown-minimap-mode" data-action="minimap-mode-toggle" aria-pressed="false" aria-label="Switch minimap to heading-up mode">North up</button>
          <button type="button" class="gastown-minimap-zoom" data-action="minimap-zoom-in" aria-label="Zoom in minimap">+</button>
          <button type="button" class="gastown-minimap-zoom" data-action="minimap-zoom-out" aria-label="Zoom out minimap">−</button>
        </div>
        <p class="gastown-minimap-mode-status" data-sim-minimap-mode-status aria-live="polite">North-up charting. The avenue is drawn to geographic north while guidance remains relative to your body.</p>
        <p class="gastown-minimap-tooltip" data-sim-minimap-tooltip aria-live="polite">Minimap cue: press M twice quickly to switch between cartographer and traveler orientation.</p>
        <p class="gastown-minimap-context" data-sim-minimap-context aria-live="polite"><strong>Facing</strong> north<br><strong>Nearest beacon</strong> Waterfront Station threshold — ahead</p>
        <canvas data-sim-minimap width="220" height="220"></canvas>
        <div class="gastown-minimap-compass" data-sim-compass aria-live="polite">Heading: north</div>
        <ul class="gastown-minimap-legend" data-sim-minimap-legend aria-label="Minimap legend">
          <li>You</li>
          <li>Route line</li>
          <li>Sidewalk / plaza</li>
          <li>Road</li>
          <li>Landmark</li>
          <li>Guidance callout</li>
          <li>Collectibles</li>
        </ul>
        <p class="gastown-minimap-label" data-sim-minimap-landmark>Nearest landmark: Waterfront Station threshold — ahead</p>
      </aside>

      <div class="gastown-hud gastown-hud--bottom">
        <p class="gastown-interact-prompt" data-sim-interact-prompt aria-live="polite" hidden></p>
      </div>

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

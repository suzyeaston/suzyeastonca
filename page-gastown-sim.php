<?php
/*
Template Name: Gastown Simulator
*/

get_header();
?>
<main id="primary" class="content-area gastown-sim-page">
  <section id="gastown-sim-app" class="gastown-sim-shell">
    <header class="gastown-sim-header">
      <p class="gastown-sim-kicker">Waterfront Station → Water Street → Steam Clock</p>
      <h1>Gastown Simulator</h1>
      <p class="gastown-sim-intro">Name your walker, click in, and follow the first bit of street energy.</p>
      <p class="gastown-sim-platform-note">Desktop only right now — mobile support is not available yet.</p>
    </header>

    <details class="gastown-controls-drawer">
      <summary>Settings + help</summary>
      <div class="gastown-controls" role="group" aria-label="Simulator controls">
        <button type="button" class="pixel-button secondary" data-action="pause">Pause</button>
        <button type="button" class="pixel-button secondary" data-action="reset" aria-label="Reset to the start of the route">Reset</button>
        <button type="button" class="pixel-button secondary" data-action="tutorial-open" aria-label="Open the controls tutorial overlay">Help</button>
        <button type="button" class="pixel-button secondary" data-action="rename-walker" aria-label="Rename your walker">Rename</button>
        <label>
          Time
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
          Street mood
          <select name="mood">
            <option value="calm" selected>Calm</option>
            <option value="commuter">Commuter</option>
            <option value="lively">Lively</option>
            <option value="eerie">Eerie</option>
          </select>
        </label>
        <label>
          <input type="checkbox" name="low-graphics" data-setting="low-graphics" aria-label="Enable low graphics mode">
          Low graphics
        </label>
        <label>
          <input type="checkbox" name="reopen-tutorial" data-setting="reopen-tutorial" aria-label="Show the tutorial overlay again">
          Show help on load
        </label>
        <button type="button" class="pixel-button tiny secondary" data-action="debug-toggle" aria-label="Toggle the debug notes panel">Debug</button>
      </div>

      <section class="gastown-help" aria-label="Simulator controls guide">
        <h2>Controls</h2>
        <ul>
          <li><strong>Click</strong> to step into the street</li>
          <li><strong>Mouse</strong> look</li>
          <li><strong>W A S D</strong> move</li>
          <li><strong>Arrow keys</strong> move / turn</li>
          <li><strong>E / click</strong> talk or log</li>
          <li><strong>Esc</strong> close / release</li>
        </ul>
        <p class="gastown-help-note">Startup welcome voice is AI-generated when available. If OpenAI voice or music-spec generation fails, the walk still starts and local fallback audio can play.</p>
      </section>
    </details>

    <section class="gastown-tutorial-overlay" data-tutorial-overlay role="dialog" aria-modal="true" aria-labelledby="gastown-tutorial-title" aria-describedby="gastown-tutorial-copy" hidden>
      <div class="gastown-dialog-panel">
        <div class="gastown-dialog-header">
          <h2 id="gastown-tutorial-title">On the street</h2>
          <button type="button" class="pixel-button tiny secondary" data-action="tutorial-close" aria-label="Close tutorial overlay">Close</button>
        </div>
        <div class="gastown-dialog-body" id="gastown-tutorial-copy">
          <p>Name your walker if you want, then click in and move.</p>
          <p>You will hear and see the route wake up quickly: locals, visitors, storefronts, and the pull toward the Steam Clock.</p>
          <p>AI-generated voice is disclosed here in help, not in the live HUD. If it fails, dialog text stays on screen.</p>
        </div>
        <div class="gastown-dialog-actions">
          <button type="button" class="pixel-button secondary" data-action="tutorial-start" aria-label="Start the simulator tutorial">Start walk</button>
          <button type="button" class="pixel-button secondary" data-action="tutorial-close">Skip</button>
        </div>
      </div>
    </section>

    <section class="gastown-name-overlay" data-name-overlay role="dialog" aria-modal="true" aria-labelledby="gastown-name-title" hidden>
      <div class="gastown-dialog-panel gastown-name-panel">
        <div class="gastown-dialog-header">
          <h2 id="gastown-name-title">Name your walker</h2>
        </div>
        <div class="gastown-dialog-body">
          <p>Optional. Keep it short. A quick AI-generated welcome may play after this.</p>
          <label class="gastown-name-field">
            <span>Walker name</span>
            <input type="text" maxlength="24" autocomplete="nickname" data-walker-name-input placeholder="SUZY">
          </label>
        </div>
        <div class="gastown-dialog-actions">
          <button type="button" class="pixel-button secondary" data-action="walker-start">Start</button>
          <button type="button" class="pixel-button secondary" data-action="walker-skip">Skip</button>
        </div>
      </div>
    </section>

    <section class="gastown-hud" aria-label="Exploration HUD">
      <div class="gastown-hud-identity">
        <p class="gastown-hud-name" data-walker-name-display>WALKER · 0% mapped</p>
      </div>
      <div class="gastown-hud-route">
        <p class="gastown-expedition-value" data-sim-route-score aria-live="polite">0% mapped</p>
      </div>
      <p class="gastown-hud-subline" data-sim-status aria-live="polite">Band ahead.</p>
      <p class="gastown-interact-prompt" data-sim-interact-prompt aria-live="polite" hidden></p>
    </section>

    <div class="gastown-sim-canvas" data-sim-canvas tabindex="-1">
      <aside class="gastown-minimap" aria-label="Exploration minimap">
        <div class="gastown-minimap-toolbar" role="group" aria-label="Minimap controls">
          <button type="button" class="gastown-minimap-mode" data-action="minimap-mode-toggle" aria-pressed="false" aria-label="Switch minimap to heading-up mode">North up</button>
          <button type="button" class="gastown-minimap-zoom" data-action="minimap-zoom-in" aria-label="Zoom in minimap">+</button>
          <button type="button" class="gastown-minimap-zoom" data-action="minimap-zoom-out" aria-label="Zoom out minimap">−</button>
        </div>
        <p class="gastown-minimap-tooltip" data-sim-minimap-tooltip aria-live="polite">Steam Clock ahead.</p>
        <canvas data-sim-minimap width="220" height="220"></canvas>
        
        <p class="gastown-minimap-label" data-sim-minimap-landmark>Waterfront Station threshold</p>
      </aside>
      <pre class="gastown-route-debug-overlay" data-route-debug-overlay hidden></pre>
      <div class="gastown-live-strip">
        <p class="gastown-landmark" data-sim-landmark aria-live="polite">Band ahead</p>
        
      </div>
    </div>

    <details class="gastown-log-drawer">
      <summary>Route notes</summary>
      <div class="gastown-meta-strip">
        <p class="gastown-world-status" data-sim-world-status aria-live="polite">World data status: checking build provenance…</p>
      </div>

    </details>

    <section class="gastown-debug" data-debug-panel hidden>
      <h2>Debug notes</h2>
      <ul>
        <li>W/S + Arrow Up/Down move forward/back; A/D strafe</li>
        <li>Arrow Left/Right turns the player heading</li>
        <li>Mouse look while pointer is locked</li>
        <li>Wheel and Ctrl+ArrowUp/Down also adjust pitch while focused/in play mode</li>
        <li>Walk bounds clamp keeps the player in the playable corridor slice</li>
        <li>Route debug can also be enabled with <code>?gastownDebug=1</code></li>
        <li>Audio zones: station rumble, sax busker, steam clock core, nightlife edge</li>
      </ul>
    </section>

    <footer class="gastown-attribution" aria-live="polite">
      <p>Contains information licensed under the Open Government Licence – Vancouver.</p>
      <p data-gastown-osm-attribution hidden>Map data © OpenStreetMap contributors.</p>
    </footer>

    <div class="gastown-dialog-modal" data-dialog-modal role="dialog" aria-modal="true" aria-hidden="true" hidden>
      <div class="gastown-dialog-panel">
        <div class="gastown-dialog-header">
          <h2 data-dialog-title>Gastown local</h2>
          <button type="button" class="pixel-button tiny secondary" data-dialog-close>Close</button>
        </div>
        <div class="gastown-dialog-body" data-dialog-body></div>
        <div class="gastown-dialog-actions">
          <div class="gastown-dialog-audio-status" data-dialog-audio-status aria-live="polite"></div>
          <div class="gastown-dialog-actions-dynamic" data-dialog-actions-dynamic aria-live="polite"></div>
          <button type="button" class="pixel-button secondary" data-dialog-close data-dialog-close-fallback>Back to walk</button>
        </div>
      </div>
    </div>
  </section>
</main>
<?php get_footer(); ?>

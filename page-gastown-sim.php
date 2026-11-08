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
      <p class="gastown-sim-intro">Step into the Gastown working corridor: click in, move, and explore Water Street toward the Steam Clock.</p>
      <p class="gastown-sim-platform-note">Desktop only right now — mobile support is not available yet.</p>
    </header>

    <details class="gastown-controls-drawer">
      <summary>Settings + help</summary>
      <div class="gastown-controls" role="group" aria-label="Simulator controls">
        <button type="button" class="pixel-button secondary" data-action="pause">Pause</button>
        <button type="button" class="pixel-button secondary" data-action="reset" aria-label="Reset to the start of the route">Reset</button>
        <button type="button" class="pixel-button secondary" data-action="tutorial-open" aria-label="Open the controls tutorial overlay">Help</button>
        <span class="gastown-character-chip" aria-label="Current character is Suzy">CHARACTER · SUZY</span>
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
          <li><strong>W / ↑</strong> hold to run (Shift still works)</li>
          <li><strong>A S D + arrows</strong> move / turn</li>
          <li><strong>E / click</strong> interact</li>
          <li><strong>B / See Suzy</strong> switch between first-person and behind-Suzy; <strong>Face / Front</strong> and <strong>Back / Follow</strong> choose the film camera side</li>
          <li><strong>Entrance / Fence / Diagonal</strong> jump between safe Station East viewpoints</li>
          <li><strong>Esc</strong> releases your mouse so you can click browser controls, settings, or leave the simulator</li>
          <li><strong>Harbour view</strong> frames the North Shore and Canada Place from the Cordova sidewalk (representative ship, not live berth status)</li>
        </ul>
        <p class="gastown-help-note">This simulator runs on a deterministic local world build. If optional audio or dialog extras fail, the walk continues.</p>
      </section>
    </details>

    <section class="gastown-tutorial-overlay" data-tutorial-overlay role="dialog" aria-modal="true" aria-labelledby="gastown-tutorial-title" aria-describedby="gastown-tutorial-copy" hidden>
      <div class="gastown-dialog-panel">
        <div class="gastown-dialog-header">
          <h2 id="gastown-tutorial-title">On the street</h2>
          <button type="button" class="pixel-button tiny secondary" data-action="tutorial-close" aria-label="Close tutorial overlay">Close</button>
        </div>
        <div class="gastown-dialog-body" id="gastown-tutorial-copy">
          <p>You're Suzy. This is your playable Vancouver. Click in and move.</p>
          <p>The corridor is tuned as a focused two-block walk: Waterfront threshold, Water Street rhythm, Steam Clock corner, and Maple Tree Square edge.</p>
          <p>Click the 3D scene to capture your mouse. <strong>Press Esc at any time to release it</strong> and use the page normally. W/A/S/D moves, mouse looks around, E interacts.</p>
        </div>
        <div class="gastown-dialog-actions">
          <button type="button" class="pixel-button secondary" data-action="tutorial-start" aria-label="Start the simulator tutorial">Start walk</button>
          <button type="button" class="pixel-button secondary" data-action="tutorial-close">Skip</button>
        </div>
      </div>
    </section>

    <section class="gastown-hud" aria-label="Exploration HUD">
      <div class="gastown-hud-identity">
        <p class="gastown-hud-name" data-walker-name-display>SUZY · 0% mapped</p>
      </div>
      <div class="gastown-hud-route">
        <p class="gastown-expedition-value" data-sim-route-score aria-live="polite">0% mapped</p>
      </div>
      <p class="gastown-hud-subline" data-sim-status aria-live="polite">Gastown working corridor ready.</p>
      <p class="gastown-interact-prompt" data-sim-interact-prompt aria-live="polite" hidden></p>
    </section>

    <div class="gastown-sim-canvas" data-sim-canvas tabindex="-1">
      <button
        type="button"
        class="gastown-fullscreen-toggle"
        data-action="sim-fullscreen"
        aria-label="Enter fullscreen mode"
        aria-pressed="false"
        title="Enter fullscreen"
      >⛶</button>
      <div class="gastown-pointer-help" data-sim-pointer-help role="status" aria-live="polite">
        <span class="gastown-pointer-help-label" data-sim-pointer-hint>Click the scene to look around · W A S D move</span>
        <span class="gastown-pointer-help-escape">ESC = RELEASE MOUSE</span>
        <button type="button" data-action="sim-release-pointer" class="gastown-release-pointer" aria-label="Release simulator mouse pointer">Release mouse</button>
      </div>
      <aside class="gastown-minimap" aria-label="Exploration minimap">
        <div class="gastown-minimap-toolbar" role="group" aria-label="Minimap controls">
          <button type="button" class="gastown-minimap-mode" data-action="minimap-mode-toggle" aria-pressed="false" aria-label="Switch minimap to heading-up mode">North up</button>
          <button type="button" class="gastown-minimap-mode" data-action="harbour-lookout" aria-label="Go to the Station East parking lot fence lookout, facing the working rail yard, cranes and Burrard Inlet">Fence lookout</button>
          <button type="button" class="gastown-minimap-mode" data-action="lot-entrance-view" aria-label="Station East driveway and entrance camera">Entrance</button>
          <button type="button" class="gastown-minimap-mode" data-action="lot-diagonal-view" aria-label="Station East oblique camera from the Waterfront Station sidewalk">Diagonal</button>
          <button type="button" class="gastown-minimap-mode gastown-view-suzy" data-action="suzy-view" aria-label="See Suzy in third-person view" aria-pressed="false">See Suzy</button>
          <button type="button" class="gastown-minimap-mode gastown-view-front" data-action="suzy-front-view" aria-label="Film Suzy from the front and show her face" aria-pressed="false">Face / Front</button>
          <button type="button" class="gastown-minimap-mode gastown-view-back" data-action="suzy-back-view" aria-label="Follow Suzy from behind and show her checkerboard backpack" aria-pressed="false">Back / Follow</button>
          <button type="button" class="gastown-minimap-zoom" data-action="minimap-zoom-in" aria-label="Zoom in minimap">+</button>
          <button type="button" class="gastown-minimap-zoom" data-action="minimap-zoom-out" aria-label="Zoom out minimap">−</button>
        </div>
        <p class="gastown-minimap-tooltip" data-sim-minimap-tooltip aria-live="polite">Steam Clock corridor ahead.</p>
        <p class="gastown-minimap-compass" data-sim-compass aria-live="off">Facing NE · 045°</p>
        <canvas data-sim-minimap width="240" height="240" aria-label="North-referenced local map with the player and facing arrow"></canvas>
        <ul class="gastown-minimap-legend" data-sim-minimap-legend aria-label="Map legend">
          <li><span class="dot player"></span>You + facing</li>
          <li><span class="dot route"></span>Walking route</li>
          <li><span class="dot station"></span>Waterfront</li>
          <li><span class="dot parking"></span>Station East lot</li>
          <li><span class="dot landing"></span>The Landing</li>
          <li><span class="dot steam"></span>Steam Clock</li>
        </ul>
        <p class="gastown-map-distance" data-sim-map-distance>Each map square is to scale</p>
        <p class="gastown-minimap-label" data-sim-minimap-landmark>Waterfront Station threshold</p>
      </aside>
      <pre class="gastown-route-debug-overlay" data-route-debug-overlay hidden></pre>
      <div class="gastown-live-strip">
        <p class="gastown-landmark" data-sim-landmark aria-live="polite">Water Street working corridor</p>
        
      </div>
    </div>

    <details class="gastown-log-drawer">
      <summary>Route notes</summary>
      <div class="gastown-meta-strip">
        <p class="gastown-world-status" data-sim-world-status aria-live="polite">World data status: loading Gastown working corridor build…</p>
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

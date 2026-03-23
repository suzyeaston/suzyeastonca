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
      <p class="gastown-sim-intro">A stylized, geographically grounded Gastown walk built for wandering, noticing, and letting the block answer back. Start loose, move with the street, and see what the corridor decides to reveal.</p>
    </header>

    <div class="gastown-controls" role="group" aria-label="Simulator controls">
      <button type="button" class="pixel-button secondary" data-action="pause">Pause</button>
      <button type="button" class="pixel-button secondary" data-action="reset" aria-label="Reset to the start of the route">Reset to route start</button>
      <button type="button" class="pixel-button secondary" data-action="tutorial-open" aria-label="Open the controls tutorial overlay">Tutorial</button>
      <button type="button" class="pixel-button secondary" data-action="rename-walker" aria-label="Rename your walker">Rename walker</button>
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
      <label>
        <input type="checkbox" name="low-graphics" data-setting="low-graphics" aria-label="Enable low graphics mode">
        Low graphics mode
      </label>
      <label>
        <input type="checkbox" name="reopen-tutorial" data-setting="reopen-tutorial" aria-label="Show the tutorial overlay again">
        Show tutorial on next load
      </label>
      <button type="button" class="pixel-button tiny secondary" data-action="debug-toggle" aria-label="Toggle the debug notes panel">Toggle debug</button>
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
        <li><strong>E / click</strong> = talk or log something nearby</li>
        <li><strong>Esc</strong> = release pointer / pause</li>
      </ul>
    </section>

    <section class="gastown-tutorial-overlay" data-tutorial-overlay role="dialog" aria-modal="true" aria-labelledby="gastown-tutorial-title" aria-describedby="gastown-tutorial-copy" hidden>
      <div class="gastown-dialog-panel">
        <div class="gastown-dialog-header">
          <h2 id="gastown-tutorial-title">Welcome to the Gastown Simulator</h2>
          <button type="button" class="pixel-button tiny secondary" data-action="tutorial-close" aria-label="Close tutorial overlay">Close</button>
        </div>
        <div class="gastown-dialog-body" id="gastown-tutorial-copy">
          <p>Click the scene to lock the pointer, use the mouse to look around, and move with W A S D or the arrow keys.</p>
          <p>Press E or click when a nearby local or street detail is in reach. You do not need to pick a route style first; the walk learns from what you linger on.</p>
          <p>NPC voices use AI-generated speech when available. If audio or speech fails, the conversation stays on screen and the sim keeps moving.</p>
          <p>Screen reader note: status updates, landmark callouts, and journal updates are announced below the simulator.</p>
        </div>
        <div class="gastown-dialog-actions">
          <button type="button" class="pixel-button secondary" data-action="tutorial-start" aria-label="Start the simulator tutorial">Start tutorial</button>
          <button type="button" class="pixel-button secondary" data-action="tutorial-close">Skip for now</button>
        </div>
      </div>
    </section>

    <section class="gastown-name-overlay" data-name-overlay role="dialog" aria-modal="true" aria-labelledby="gastown-name-title" hidden>
      <div class="gastown-dialog-panel gastown-name-panel">
        <div class="gastown-dialog-header">
          <h2 id="gastown-name-title">Name your walker</h2>
        </div>
        <div class="gastown-dialog-body">
          <p>Give your walker a street name, or skip and keep it simple.</p>
          <label class="gastown-name-field">
            <span>Walker name</span>
            <input type="text" maxlength="24" autocomplete="nickname" data-walker-name-input placeholder="Walker">
          </label>
        </div>
        <div class="gastown-dialog-actions">
          <button type="button" class="pixel-button secondary" data-action="walker-start">Start</button>
          <button type="button" class="pixel-button secondary" data-action="walker-skip">Skip</button>
        </div>
      </div>
    </section>

    <section class="gastown-hud" aria-label="Exploration HUD">
      <div class="gastown-hud-top">
        <div class="gastown-hud-identity">
          <p class="gastown-hud-kicker">ON THE STREET</p>
          <p class="gastown-hud-name" data-walker-name-display>Walker</p>
          <p class="gastown-hud-subline">Get your bearings and see what the street gives you.</p>
        </div>
        <div class="gastown-hud-statuses">
          <p class="gastown-status" data-sim-status aria-live="polite">Loading simulator...</p>
          <p class="gastown-quest-status" data-sim-quest-status aria-live="polite">Street details log: inactive.</p>
          <p class="gastown-pointer-status" data-sim-pointer-status aria-live="polite">Pointer unlocked.</p>
          <p class="gastown-interact-prompt" data-sim-interact-prompt aria-live="polite" hidden></p>
        </div>
        <div class="gastown-hud-route">
          <p class="gastown-expedition-label">Route completion</p>
          <p class="gastown-expedition-value" data-sim-route-score aria-live="polite">0% of the corridor surveyed.</p>
          <p class="gastown-hud-disclosure">AI voice talkback is optional and may stay silent if your browser or the API says no.</p>
        </div>
      </div>

      <div class="gastown-hud-support">
        <div class="gastown-hud-card gastown-hud-card--prompt">
          <p class="gastown-expedition-label">Current drift</p>
          <p class="gastown-expedition-value" data-sim-objective aria-live="polite">Get your bearings and see what the street gives you.</p>
          <p class="gastown-expedition-value gastown-hud-next-step" data-sim-next-step aria-live="polite">Look around, move a little, and follow what feels alive.</p>
        </div>
        <div class="gastown-hud-card gastown-hud-card--journal">
          <p class="gastown-expedition-label">Journal</p>
          <ul class="gastown-expedition-list" data-sim-journal aria-live="polite">
            <li>Arrive at the station threshold and get your bearings.</li>
          </ul>
        </div>
        <div class="gastown-hud-card gastown-hud-card--details">
          <p class="gastown-expedition-label">Street details log</p>
          <ul class="gastown-expedition-list" data-sim-collectibles-log aria-live="polite">
            <li>Newspaper box — not logged</li>
            <li>Historic plaque — not logged</li>
            <li>Painted brick panel — not logged</li>
          </ul>
        </div>
      </div>
    </section>

    <div class="gastown-sim-canvas" data-sim-canvas tabindex="-1">
      <aside class="gastown-minimap" aria-label="Exploration minimap">
        <div class="gastown-minimap-toolbar" role="group" aria-label="Minimap controls">
          <button type="button" class="gastown-minimap-mode" data-action="minimap-mode-toggle" aria-pressed="false" aria-label="Switch minimap to heading-up mode">North up</button>
          <button type="button" class="gastown-minimap-zoom" data-action="minimap-zoom-in" aria-label="Zoom in minimap">+</button>
          <button type="button" class="gastown-minimap-zoom" data-action="minimap-zoom-out" aria-label="Zoom out minimap">−</button>
        </div>
        <p class="gastown-minimap-mode-status" data-sim-minimap-mode-status aria-live="polite">Map mode: North-up — the top of the map is geographic north. Guidance: landmark callouts stay player-relative.</p>
        <p class="gastown-minimap-tooltip" data-sim-minimap-tooltip aria-live="polite">Tip: press M twice quickly to switch between north-up and heading-up map modes.</p>
        <p class="gastown-minimap-context" data-sim-minimap-context aria-live="polite"><strong>Now facing:</strong> north<br><strong>Nearest landmark:</strong> Waterfront Station threshold — ahead</p>
        <canvas data-sim-minimap width="220" height="220"></canvas>
        <div class="gastown-minimap-compass" data-sim-compass aria-live="polite">Heading: north</div>
        <ul class="gastown-minimap-legend" data-sim-minimap-legend aria-label="Minimap legend">
          <li>You</li>
          <li>Route line</li>
          <li>Sidewalk / plaza</li>
          <li>Road</li>
          <li>Landmark</li>
          <li>Ambient hint</li>
          <li>Observations</li>
        </ul>
        <p class="gastown-minimap-label" data-sim-minimap-landmark>Nearest landmark: Waterfront Station threshold — ahead</p>
      </aside>
      <pre class="gastown-route-debug-overlay" data-route-debug-overlay hidden></pre>
    </div>

    <div class="gastown-meta-strip">
      <p class="gastown-world-status" data-sim-world-status aria-live="polite">World data status: checking build provenance…</p>
      <p class="gastown-landmark" data-sim-landmark aria-live="polite">Nearest landmark: Station threshold</p>
      <p class="gastown-route-segment" data-sim-route-segment aria-live="polite">Route segment: Waterfront Station Threshold</p>
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

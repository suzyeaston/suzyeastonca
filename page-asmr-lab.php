<?php
/*
Template Name: ASMR Lab
*/

get_header();

$gastown_page     = get_page_by_path( 'page-gastown-sim' );
$gastown_page_url = $gastown_page ? get_permalink( $gastown_page ) : home_url( '/page-gastown-sim/' );
?>
<main id="primary" class="content-area asmr-lab-page">
  <section class="asmr-lab-shell" id="asmr-lab-app">
    <header class="asmr-lab-header">
      <p class="asmr-kicker">Retro-futurist rebuild chamber // archive specimen</p>
      <h1 class="asmr-title">ASMR Lab: Rebuild in Progress</h1>
      <p class="asmr-intro">Experimental predecessor to the Gastown simulator. Currently in major reconstruction with unstable modules and dramatic lighting.</p>
    </header>

    <section class="asmr-rebuild-chamber" aria-labelledby="asmr-rebuild-title">
      <p class="asmr-alert">⚠ Prototype unstable // rebuild chamber online</p>
      <h2 id="asmr-rebuild-title">ASMR Lab: Under Major Development</h2>
      <p>This started as a weird audio/visual experiment, survived too many iterations, and eventually got strange enough to help inspire the Gastown simulator.</p>
      <p>The code survived. The interface is in the shop. Rebuild in progress.</p>
      <div class="asmr-status-grid" role="list" aria-label="Rebuild status">
        <p role="listitem"><strong>Status:</strong> major rebuild</p>
        <p role="listitem"><strong>Stability:</strong> intentionally chaotic</p>
        <p role="listitem"><strong>Mutation level:</strong> acceptable</p>
      </div>
      <div class="asmr-lab-voice-panel">
        <button type="button" id="asmr-lab-voice-trigger" class="pixel-button">Activate lab narrator</button>
        <p id="asmr-lab-voice-status" class="asmr-lab-voice-status" aria-live="polite">Silent mode engaged. Narrator stays quiet until requested.</p>
      </div>
      <div class="asmr-cta-row">
        <a class="pixel-button" href="<?php echo esc_url( $gastown_page_url ); ?>">Enter Gastown prototype</a>
        <a class="pixel-button secondary" href="https://github.com/suzyeaston/suzyeastonca" target="_blank" rel="noopener noreferrer">View code on GitHub</a>
      </div>
    </section>

    <details class="asmr-legacy-console">
      <summary>Open legacy ASMR Lab console (experimental predecessor)</summary>
    <section class="asmr-lab-primer" aria-labelledby="asmr-lab-primer-title">
      <h2 id="asmr-lab-primer-title">What this prototype now performs</h2>
      <ul>
        <li><strong>Compose mode</strong> for a single hardcoded route preset: <code>gastown_water_street_walk</code>.</li>
        <li><strong>Explore mode</strong> with connected scene nodes and left/center/right look bias per node.</li>
        <li><strong>Record mode</strong> aligned to your active route traversal sequence.</li>
        <li><strong>CRT-styled playback + export</strong> via synchronized browser audio/visual engines.</li>
      </ul>
    </section>

    <form id="asmr-lab-form" class="asmr-lab-form" novalidate>
      <section class="asmr-route-console" aria-labelledby="asmr-route-title">
        <h2 id="asmr-route-title">Gastown Route Console</h2>
        <p class="asmr-route-intro">Preset: <strong>gastown_water_street_walk</strong> (Waterfront Station threshold → Water Street corridor → Steam Clock approach → split / angled-building node).</p>
        <div class="asmr-route-mode-switch" role="group" aria-label="ASMR Lab mode">
          <button type="button" class="pixel-button secondary asmr-mode-chip is-active" data-asmr-mode="compose">Compose</button>
          <button type="button" class="pixel-button secondary asmr-mode-chip" data-asmr-mode="explore">Explore</button>
          <button type="button" class="pixel-button secondary asmr-mode-chip" data-asmr-mode="record">Record</button>
        </div>

        <div class="asmr-route-nav" aria-live="polite">
          <button type="button" id="asmr-node-prev" class="pixel-button secondary">◀ Prev node</button>
          <div class="asmr-route-node-meta">
            <p id="asmr-route-node-order" class="asmr-route-node-order">Node 1 / 4</p>
            <h3 id="asmr-route-node-title">Station Threshold</h3>
            <p id="asmr-route-node-id" class="asmr-route-node-id">station_threshold</p>
            <p id="asmr-route-node-transition" class="asmr-route-node-transition"></p>
          </div>
          <button type="button" id="asmr-node-next" class="pixel-button secondary">Next node ▶</button>
        </div>

        <div class="asmr-look-controls" role="group" aria-label="Look bias">
          <span class="asmr-look-label">Look bias:</span>
          <button type="button" class="pixel-button tiny secondary asmr-look-chip" data-look-bias="left">Look left</button>
          <button type="button" class="pixel-button tiny secondary asmr-look-chip is-active" data-look-bias="center">Look center</button>
          <button type="button" class="pixel-button tiny secondary asmr-look-chip" data-look-bias="right">Look right</button>
        </div>

        <input type="hidden" name="route_preset" value="gastown_water_street_walk" />
        <input type="hidden" name="active_node" value="station_threshold" />
        <input type="hidden" name="look_bias" value="center" />
      </section>

      <div class="asmr-grid">
        <label>Duration (10-30 sec)<input type="number" name="duration" min="10" max="30" value="20" required /></label>
      </div>

      <details class="asmr-advanced-fields">
        <summary>Motif inspector (advanced override)</summary>

      <fieldset class="asmr-layer-grid asmr-layer-groups">
        <legend>Sound Layers</legend>
        <div class="asmr-motif-group">
          <h3>Core Modules</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="audio_layers[]" value="rain_ambience" /> rain ambience bed</label>
            <label><input type="checkbox" name="audio_layers[]" value="ocean_waves" /> ocean waves bed</label>
            <label><input type="checkbox" name="audio_layers[]" value="wind_gust" /> wind gust</label>
            <label><input type="checkbox" name="audio_layers[]" value="footsteps" /> footsteps</label>
            <label><input type="checkbox" name="audio_layers[]" value="skytrain_pass" /> SkyTrain pass</label>
            <label><input type="checkbox" name="audio_layers[]" value="seabus_horn" /> SeaBus horn</label>
            <label><input type="checkbox" name="audio_layers[]" value="crowd_murmur" /> crowd murmur</label>
            <label><input type="checkbox" name="audio_layers[]" value="steam_clock" /> steam clock</label>
            <label><input type="checkbox" name="audio_layers[]" value="bike_bell" /> bike bell</label>
            <label><input type="checkbox" name="audio_layers[]" value="crosswalk_chirp" /> crosswalk chirp</label>
            <label><input type="checkbox" name="audio_layers[]" value="gastown_clock_whistle" /> Gastown clock toot</label>
            <label><input type="checkbox" name="audio_layers[]" value="church_bells" /> church bells</label>
            <label><input type="checkbox" name="audio_layers[]" value="harbour_noon_horn" /> harbour noon horn</label>
            <label><input type="checkbox" name="audio_layers[]" value="nine_oclock_gun" /> 9 O'Clock Gun boom</label>
          </div>
        </div>
        <details class="asmr-motif-more">
          <summary>More modules</summary>
          <div class="asmr-motif-group">
            <div class="asmr-motif-grid">
              <label><input type="checkbox" name="audio_layers[]" value="gulls_distant" /> distant gulls</label>
              <label><input type="checkbox" name="audio_layers[]" value="compass_tap" /> compass tap</label>
              <label><input type="checkbox" name="audio_layers[]" value="skateboard_roll" /> skateboard roll</label>
              <label><input type="checkbox" name="audio_layers[]" value="siren_distant" /> distant siren</label>
              <label><input type="checkbox" name="audio_layers[]" value="laughter_burst" /> laughter burst</label>
              <label><input type="checkbox" name="audio_layers[]" value="bus_pass" /> bus pass</label>
              <label><input type="checkbox" name="audio_layers[]" value="car_horn_short" /> car horn short</label>
            </div>
          </div>
        </details>
      </fieldset>

      <fieldset class="asmr-layer-grid asmr-layer-groups">
        <legend>Visual Motifs</legend>
        <div class="asmr-motif-group">
          <h3>Core Modules</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="visual_layers[]" value="rain_streaks" /> rain streaks</label>
            <label><input type="checkbox" name="visual_layers[]" value="snow_drift" /> snow drift</label>
            <label><input type="checkbox" name="visual_layers[]" value="harbor_mist" /> harbor mist</label>
            <label><input type="checkbox" name="visual_layers[]" value="gastown_scene" /> Gastown scene</label>
            <label><input type="checkbox" name="visual_layers[]" value="granville_scene" /> Granville scene</label>
            <label><input type="checkbox" name="visual_layers[]" value="north_shore_scene" /> North Shore scene</label>
            <label><input type="checkbox" name="visual_layers[]" value="waterfront_scene" /> Waterfront scene</label>
            <label><input type="checkbox" name="visual_layers[]" value="science_world_dome" /> Science World dome</label>
            <label><input type="checkbox" name="visual_layers[]" value="chinatown_gate" /> Chinatown gate</label>
            <label><input type="checkbox" name="visual_layers[]" value="lions_gate_bridge" /> Lions Gate Bridge</label>
            <label><input type="checkbox" name="visual_layers[]" value="planetarium_dome" /> Planetarium dome</label>
            <label><input type="checkbox" name="visual_layers[]" value="starfield_projection" /> starfield projection</label>
            <label><input type="checkbox" name="visual_layers[]" value="puddle_reflections" /> puddle reflections</label>
            <label><input type="checkbox" name="visual_layers[]" value="neon_sign_flicker" /> neon sign flicker</label>
            <label><input type="checkbox" name="visual_layers[]" value="scanline_field" /> scanline field</label>
            <label><input type="checkbox" name="visual_layers[]" value="skytrain_pass_visual" /> SkyTrain pass visual</label>
            <label><input type="checkbox" name="visual_layers[]" value="ocean_surface_shimmer" /> ocean surface shimmer</label>
            <label><input type="checkbox" name="visual_layers[]" value="seabus_silhouette" /> SeaBus silhouette</label>
          </div>
        </div>
        <details class="asmr-motif-more">
          <summary>More modules</summary>
          <div class="asmr-motif-group">
            <div class="asmr-motif-grid">
              <label><input type="checkbox" name="visual_layers[]" value="clear_cold_shimmer" /> clear cold shimmer</label>
              <label><input type="checkbox" name="visual_layers[]" value="gastown_clock_silhouette" /> Gastown clock</label>
              <label><input type="checkbox" name="visual_layers[]" value="english_bay_inukshuk" /> English Bay inukshuk</label>
              <label><input type="checkbox" name="visual_layers[]" value="maritime_museum_sailroof" /> Maritime Museum sail roof</label>
              <label><input type="checkbox" name="visual_layers[]" value="bc_place_dome" /> BC Place dome</label>
              <label><input type="checkbox" name="visual_layers[]" value="port_cranes" /> Port cranes</label>
              <label><input type="checkbox" name="visual_layers[]" value="constellation_lines" /> constellation lines</label>
              <label><input type="checkbox" name="visual_layers[]" value="canada_place_sails" /> Canada Place sails</label>
              <label><input type="checkbox" name="visual_layers[]" value="glitch_flash" data-layer-group="modifier" /> glitch flash</label>
              <label><input type="checkbox" name="visual_layers[]" value="signal_bars" data-layer-group="modifier" /> signal bars</label>
              <label><input type="checkbox" name="visual_layers[]" value="chromatic_veil" data-layer-group="modifier" /> chromatic veil</label>
              <label><input type="checkbox" name="visual_layers[]" value="brick_wall_parallax" data-layer-group="modifier" /> brick wall parallax</label>
              <label><input type="checkbox" name="visual_layers[]" value="streetlamp_halo_row" data-layer-group="modifier" /> streetlamp halos</label>
              <label><input type="checkbox" name="visual_layers[]" value="bus_pass_visual" data-layer-group="modifier" /> bus pass visual</label>
              <label><input type="checkbox" name="visual_layers[]" value="cobblestone_perspective" data-layer-group="modifier" /> cobblestones</label>
              <label><input type="checkbox" name="visual_layers[]" value="skytrain_track" data-layer-group="modifier" /> SkyTrain track</label>
            </div>
          </div>
        </details>
      </fieldset>

      <label class="asmr-link-toggle"><input type="checkbox" name="link_av" /> Link sound + visual for selected motifs</label>
      </details>
      <div class="asmr-actions">
        <button type="submit" class="pixel-button">Generate Route Package</button>
        <button type="button" id="asmr-qa-preset" class="pixel-button secondary">Load Route Preset</button>
        <button type="button" id="asmr-sound-only" class="pixel-button secondary" disabled>Regenerate Route Audio</button>
      </div>
      <p id="asmr-status" class="asmr-status" role="status" aria-live="polite"></p>
      <p id="asmr-debug-status" class="asmr-status asmr-debug-status" aria-live="polite"></p>
      <p id="asmr-error" class="asmr-error" role="alert" hidden></p>
    </form>

    <section class="asmr-audio-controls" aria-label="Sound preview controls">
      <label class="asmr-mode-label">Playback
        <select id="asmr-playback-mode">
          <option value="audiovisual" selected>audio + visual</option>
          <option value="sound">sound only</option>
        </select>
      </label>
      <button type="button" id="asmr-preview" class="pixel-button" disabled>Preview</button>
      <button type="button" id="asmr-stop" class="pixel-button secondary" disabled>Stop</button>
      <button type="button" id="asmr-export" class="pixel-button secondary" disabled>Export WAV</button>
      <button type="button" id="asmr-export-video" class="pixel-button secondary" disabled>Export Video 1080p</button>
      <span id="asmr-video-support" class="asmr-video-support" aria-live="polite"></span>
      <p id="asmr-audio-feedback" class="asmr-audio-feedback" aria-live="polite"></p>
    </section>



    <details class="asmr-advanced-panel" id="asmr-advanced-panel">
      <summary>Advanced / Inspect</summary>
      <div class="asmr-advanced-intro">Power-user diagnostics, motif inspectors, and render debugging tools.</div>

      <div class="asmr-inspector-shortcuts">
        <button type="button" id="asmr-inspect-selected-visuals" class="pixel-button secondary">Inspect selected visuals</button>
        <button type="button" id="asmr-inspect-selected-sounds" class="pixel-button secondary">Inspect selected sounds</button>
      </div>

      <details class="asmr-debug-inspectors" id="asmr-debug-inspectors">
        <summary>Debug Inspectors</summary>
        <div class="asmr-debug-actions">
          <button type="button" id="asmr-preview-current-hero" class="pixel-button secondary">Preview current hero visuals</button>
          <label class="asmr-debug-toggle"><input type="checkbox" id="asmr-debug-provenance-toggle" /> Show render provenance overlay</label>
        </div>
        <div class="asmr-inspector-tabs" role="tablist" aria-label="Debug inspector tabs">
          <button type="button" class="asmr-inspector-tab is-active" data-tab="visual" role="tab" aria-selected="true">Visual Inspector</button>
          <button type="button" class="asmr-inspector-tab" data-tab="sound" role="tab" aria-selected="false">Sound Inspector</button>
        </div>

      <section class="asmr-inspector-panel is-active" data-panel="visual" role="tabpanel">
        <div class="asmr-inspector-layout">
          <div class="asmr-inspector-list">
            <label for="asmr-visual-inspector-search" class="asmr-inspector-search-label">Search visual motifs</label>
            <input id="asmr-visual-inspector-search" type="search" placeholder="Search by id, label, category..." />
            <div id="asmr-visual-atlas-grid" class="asmr-visual-atlas-grid"></div>
          </div>
          <aside class="asmr-inspector-preview" aria-live="polite">
            <h3 id="asmr-visual-preview-title">Visual Preview</h3>
            <p id="asmr-visual-inspector-meta" class="asmr-inspector-meta">Hover or focus a motif card to preview.</p>
            <canvas id="asmr-visual-debug-canvas" width="480" height="270"></canvas>
            <div class="asmr-inspector-preview-actions">
              <button type="button" id="asmr-pin-visual-preview" class="pixel-button tiny secondary" aria-pressed="false">Pin preview</button>
              <button type="button" id="asmr-stop-visual-preview" class="pixel-button tiny secondary">Stop</button>
              <button type="button" id="asmr-clear-visual-preview" class="pixel-button tiny secondary">Clear preview</button>
            </div>
          </aside>
        </div>
      </section>

        <section class="asmr-inspector-panel" data-panel="sound" role="tabpanel" hidden>
        <div class="asmr-inspector-layout">
          <div class="asmr-inspector-list">
            <label for="asmr-sound-inspector-search" class="asmr-inspector-search-label">Search sound engines</label>
            <input id="asmr-sound-inspector-search" type="search" placeholder="Search by id, label, category..." />
            <div id="asmr-sound-inspector-grid" class="asmr-sound-inspector-grid"></div>
          </div>
          <aside class="asmr-inspector-preview" aria-live="polite">
            <h3>Sound Preview</h3>
            <p id="asmr-sound-inspector-meta" class="asmr-inspector-meta">Select a sound engine and click Preview sound.</p>
            <p id="asmr-sound-inspector-status" class="asmr-inspector-status">stopped</p>
            <div class="asmr-inspector-preview-actions">
              <button type="button" id="asmr-stop-sound-preview" class="pixel-button tiny secondary">Stop</button>
            </div>
          </aside>
        </div>
        </section>
      </details>
    </details>

    <section class="asmr-visual-preview" aria-label="Visual preview canvas">
      <canvas id="asmr-visual-canvas" width="960" height="540"></canvas>
    </section>

    <section id="asmr-results" class="asmr-results" hidden>
      <header class="asmr-results-header">
        <h2>Generation Results</h2>
        <div class="asmr-results-tabs" role="tablist" aria-label="Generation result sections">
          <button type="button" class="asmr-results-tab is-active" data-results-tab="overview" role="tab" aria-selected="true">Overview</button>
          <button type="button" class="asmr-results-tab" data-results-tab="timeline" role="tab" aria-selected="false">Timeline</button>
          <button type="button" class="asmr-results-tab" data-results-tab="json" role="tab" aria-selected="false">JSON</button>
        </div>
      </header>

      <section class="asmr-results-panel is-active" data-results-panel="overview" role="tabpanel">
        <article class="asmr-card asmr-card-brief"><h3>Creative Brief</h3><p id="asmr-concept"></p><p id="asmr-edit-notes"></p><p id="asmr-presentation"></p></article>
        <article class="asmr-card"><h3>Story Beats</h3><ol id="asmr-story-beats"></ol></article>
        <article class="asmr-card"><h3>Style Tags</h3><ul id="asmr-video-prompts"></ul></article>
      </section>

      <section class="asmr-results-panel" data-results-panel="timeline" role="tabpanel" hidden>
        <article class="asmr-card"><h3>Sync Points</h3><ol id="asmr-beats"></ol></article>
        <article class="asmr-card"><h3>Active Plan</h3><ul id="asmr-active-plan"></ul></article>
        <article class="asmr-card"><h3>Plan → Visual Summary</h3><ul id="asmr-plan-visual-summary"></ul></article>
      </section>

      <section class="asmr-results-panel" data-results-panel="json" role="tabpanel" hidden>
        <article class="asmr-card asmr-card-json"><h3>Timeline Package JSON</h3><pre id="asmr-sound-json"></pre></article>
      </section>
    </details>

    </section>
  </section>
</main>
<?php get_footer(); ?>

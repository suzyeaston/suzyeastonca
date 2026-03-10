<?php
/*
Template Name: ASMR Lab
*/

get_header();
?>
<main id="primary" class="content-area asmr-lab-page">
  <section class="asmr-lab-shell" id="asmr-lab-app">
    <header class="asmr-lab-header">
      <p class="asmr-kicker">Retro-futurist creative control room</p>
      <h1 class="asmr-title">ASMR Lab</h1>
      <p class="asmr-intro">Compose short procedural sensory micro-films from a prompt. The lab outputs a strict audiovisual score you can perform directly in the browser.</p>
    </header>

    <section class="asmr-lab-primer" aria-labelledby="asmr-lab-primer-title">
      <h2 id="asmr-lab-primer-title">What ASMR Lab now performs</h2>
      <ul>
        <li><strong>Strict timeline JSON</strong> with synchronized audio + visual events.</li>
        <li><strong>Procedural sound synthesis</strong> using browser-generated textures.</li>
        <li><strong>Reactive CRT-inspired visuals</strong> driven by the same shared clock.</li>
        <li><strong>Preview, stop, WAV export, and 1080p video export</strong> without leaving the control room.</li>
      </ul>
    </section>

    <form id="asmr-lab-form" class="asmr-lab-form" novalidate>
      <div class="asmr-grid">
        <label>Duration (10-30 sec)<input type="number" name="duration" min="10" max="30" value="20" required /></label>
      </div>

      <fieldset class="asmr-layer-grid asmr-layer-groups">
        <legend>Sound Layers</legend>
        <div class="asmr-motif-group">
          <h3>Core Modules</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="audio_layers[]" value="rain_ambience" data-layer-group="bed" /> rain ambience bed</label>
            <label><input type="checkbox" name="audio_layers[]" value="ocean_waves" data-layer-group="bed" checked /> ocean waves bed</label>
            <label><input type="checkbox" name="audio_layers[]" value="wind_gust" data-layer-group="bed" /> wind gust</label>
            <label><input type="checkbox" name="audio_layers[]" value="footsteps" data-layer-group="movement" /> footsteps</label>
            <label><input type="checkbox" name="audio_layers[]" value="skytrain_pass" data-layer-group="movement" /> SkyTrain pass</label>
            <label><input type="checkbox" name="audio_layers[]" value="seabus_horn" data-layer-group="movement" checked /> SeaBus horn</label>
            <label><input type="checkbox" name="audio_layers[]" value="crowd_murmur" data-layer-group="accent" /> crowd murmur</label>
            <label><input type="checkbox" name="audio_layers[]" value="steam_clock" data-layer-group="accent" /> steam clock</label>
            <label><input type="checkbox" name="audio_layers[]" value="bike_bell" data-layer-group="accent" /> bike bell</label>
            <label><input type="checkbox" name="audio_layers[]" value="crosswalk_chirp" data-layer-group="accent" /> crosswalk chirp</label>
          </div>
        </div>
        <details class="asmr-motif-more">
          <summary>More modules</summary>
          <div class="asmr-motif-group">
            <div class="asmr-motif-grid">
              <label><input type="checkbox" name="audio_layers[]" value="gulls_distant" data-layer-group="accent" /> distant gulls</label>
              <label><input type="checkbox" name="audio_layers[]" value="compass_tap" data-layer-group="accent" /> compass tap</label>
              <label><input type="checkbox" name="audio_layers[]" value="skateboard_roll" data-layer-group="movement" /> skateboard roll</label>
              <label><input type="checkbox" name="audio_layers[]" value="siren_distant" data-layer-group="accent" /> distant siren</label>
              <label><input type="checkbox" name="audio_layers[]" value="laughter_burst" data-layer-group="accent" /> laughter burst</label>
              <label><input type="checkbox" name="audio_layers[]" value="bus_pass" data-layer-group="movement" /> bus pass</label>
              <label><input type="checkbox" name="audio_layers[]" value="car_horn_short" data-layer-group="accent" /> car horn short</label>
            </div>
          </div>
        </details>
      </fieldset>

      <fieldset class="asmr-layer-grid asmr-layer-groups">
        <legend>Visual Motifs</legend>
        <div class="asmr-motif-group">
          <h3>Core Modules</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="visual_layers[]" value="rain_streaks" data-layer-group="atmosphere" /> rain streaks</label>
            <label><input type="checkbox" name="visual_layers[]" value="snow_drift" data-layer-group="atmosphere" /> snow drift</label>
            <label><input type="checkbox" name="visual_layers[]" value="harbor_mist" data-layer-group="atmosphere" checked /> harbor mist</label>
            <label><input type="checkbox" name="visual_layers[]" value="gastown_scene" data-layer-group="scene" /> Gastown scene</label>
            <label><input type="checkbox" name="visual_layers[]" value="granville_scene" data-layer-group="scene" /> Granville scene</label>
            <label><input type="checkbox" name="visual_layers[]" value="north_shore_scene" data-layer-group="scene" /> North Shore scene</label>
            <label><input type="checkbox" name="visual_layers[]" value="waterfront_scene" data-layer-group="scene" checked /> Waterfront scene</label>
            <label><input type="checkbox" name="visual_layers[]" value="science_world_dome" data-layer-group="landmark" /> Science World dome</label>
            <label><input type="checkbox" name="visual_layers[]" value="chinatown_gate" data-layer-group="landmark" /> Chinatown gate</label>
            <label><input type="checkbox" name="visual_layers[]" value="lions_gate_bridge" data-layer-group="landmark" checked /> Lions Gate Bridge</label>
            <label><input type="checkbox" name="visual_layers[]" value="puddle_reflections" data-layer-group="modifier" /> puddle reflections</label>
            <label><input type="checkbox" name="visual_layers[]" value="neon_sign_flicker" data-layer-group="modifier" /> neon sign flicker</label>
            <label><input type="checkbox" name="visual_layers[]" value="scanline_field" data-layer-group="modifier" /> scanline field</label>
            <label><input type="checkbox" name="visual_layers[]" value="skytrain_pass_visual" data-layer-group="modifier" /> SkyTrain pass visual</label>
            <label><input type="checkbox" name="visual_layers[]" value="ocean_surface_shimmer" data-layer-group="modifier" checked /> ocean surface shimmer</label>
            <label><input type="checkbox" name="visual_layers[]" value="seabus_silhouette" data-layer-group="modifier" checked /> SeaBus silhouette</label>
          </div>
        </div>
        <details class="asmr-motif-more">
          <summary>More modules</summary>
          <div class="asmr-motif-group">
            <div class="asmr-motif-grid">
              <label><input type="checkbox" name="visual_layers[]" value="clear_cold_shimmer" data-layer-group="atmosphere" /> clear cold shimmer</label>
              <label><input type="checkbox" name="visual_layers[]" value="gastown_clock_silhouette" data-layer-group="landmark" /> Gastown clock</label>
              <label><input type="checkbox" name="visual_layers[]" value="english_bay_inukshuk" data-layer-group="landmark" /> English Bay inukshuk</label>
              <label><input type="checkbox" name="visual_layers[]" value="maritime_museum_sailroof" data-layer-group="landmark" /> Maritime Museum sail roof</label>
              <label><input type="checkbox" name="visual_layers[]" value="bc_place_dome" data-layer-group="landmark" /> BC Place dome</label>
              <label><input type="checkbox" name="visual_layers[]" value="port_cranes" data-layer-group="landmark" /> Port cranes</label>
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

      <label class="asmr-link-toggle"><input type="checkbox" name="link_av" checked /> Link sound + visual for selected motifs</label>
      <p class="asmr-link-tip">Tip: Fewer modules = clearer scenes. Try 1 scene + 1 atmosphere + 1 signature.</p>
      <div class="asmr-actions">
        <button type="submit" class="pixel-button">Generate ASMR Package</button>
        <button type="button" id="asmr-qa-preset" class="pixel-button secondary">Load QA Preset</button>
        <button type="button" id="asmr-sound-only" class="pixel-button secondary" disabled>Regenerate Sound Only</button>
      </div>
      <p id="asmr-status" class="asmr-status" role="status" aria-live="polite"></p>
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

    <section class="asmr-visual-preview" aria-label="Visual preview canvas">
      <canvas id="asmr-visual-canvas" width="960" height="540"></canvas>
    </section>

    <section id="asmr-results" class="asmr-results" hidden>
      <article class="asmr-card"><h2>Concept</h2><p id="asmr-concept"></p></article>
      <article class="asmr-card"><h2>Sync Points</h2><ol id="asmr-beats"></ol></article>
      <article class="asmr-card"><h2>Style Tags</h2><ul id="asmr-video-prompts"></ul></article>
      <article class="asmr-card"><h2>Edit Rhythm</h2><p id="asmr-edit-notes"></p></article>
      <article class="asmr-card"><h2>Presentation Note</h2><p id="asmr-presentation"></p></article>
      <article class="asmr-card"><h2>Timeline JSON</h2>
        <details>
          <summary>View JSON package</summary>
          <pre id="asmr-sound-json"></pre>
        </details>
      </article>
    </section>
  </section>
</main>
<?php get_footer(); ?>

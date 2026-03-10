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
        <label class="asmr-inline-label">Voice Style<input type="text" name="voice_style" maxlength="80" placeholder="composed machine whisper" /></label>
        <div class="asmr-motif-group">
          <h3>Core City Layers</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="audio_layers[]" value="footsteps" checked /> footsteps</label>
            <label><input type="checkbox" name="audio_layers[]" value="rain_ambience" /> rain ambience bed</label>
            <label><input type="checkbox" name="audio_layers[]" value="wind_gust" /> wind gust</label>
            <label><input type="checkbox" name="audio_layers[]" value="crowd_murmur" /> crowd murmur</label>
            <label><input type="checkbox" name="audio_layers[]" value="laughter_burst" /> laughter burst</label>
            <label><input type="checkbox" name="audio_layers[]" value="skytrain_pass" /> SkyTrain pass</label>
            <label><input type="checkbox" name="audio_layers[]" value="bus_pass" /> bus pass</label>
            <label><input type="checkbox" name="audio_layers[]" value="car_horn_short" /> car horn short</label>
            <label><input type="checkbox" name="audio_layers[]" value="steam_clock" checked /> steam clock</label>
          </div>
        </div>
        <div class="asmr-motif-group">
          <h3>Vancouver Details</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="audio_layers[]" value="seabus_horn" /> SeaBus horn</label>
            <label><input type="checkbox" name="audio_layers[]" value="gulls_distant" /> distant gulls</label>
            <label><input type="checkbox" name="audio_layers[]" value="crosswalk_chirp" /> crosswalk chirp</label>
            <label><input type="checkbox" name="audio_layers[]" value="compass_tap" /> compass tap</label>
            <label><input type="checkbox" name="audio_layers[]" value="bike_bell" /> bike bell</label>
            <label><input type="checkbox" name="audio_layers[]" value="skateboard_roll" /> skateboard roll</label>
            <label><input type="checkbox" name="audio_layers[]" value="siren_distant" /> distant siren</label>
          </div>
        </div>
      </fieldset>

      <fieldset class="asmr-layer-grid asmr-layer-groups">
        <legend>Visual Motifs</legend>
        <div class="asmr-motif-group">
          <h3>Atmosphere</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="visual_layers[]" value="rain_streaks" /> rain streaks</label>
            <label><input type="checkbox" name="visual_layers[]" value="snow_drift" /> snow drift</label>
            <label><input type="checkbox" name="visual_layers[]" value="harbor_mist" /> harbor mist</label>
            <label><input type="checkbox" name="visual_layers[]" value="clear_cold_shimmer" /> clear cold shimmer</label>
          </div>
        </div>
        <div class="asmr-motif-group">
          <h3>Scenes</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="visual_layers[]" value="gastown_scene" /> Gastown scene</label>
            <label><input type="checkbox" name="visual_layers[]" value="granville_scene" checked /> Granville scene</label>
            <label><input type="checkbox" name="visual_layers[]" value="north_shore_scene" /> North Shore scene</label>
          </div>
        </div>
        <div class="asmr-motif-group">
          <h3>Landmarks</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="visual_layers[]" value="gastown_clock_silhouette" /> Gastown clock</label>
            <label><input type="checkbox" name="visual_layers[]" value="science_world_dome" /> Science World dome</label>
            <label><input type="checkbox" name="visual_layers[]" value="chinatown_gate" /> Chinatown gate</label>
            <label><input type="checkbox" name="visual_layers[]" value="english_bay_inukshuk" /> English Bay inukshuk</label>
            <label><input type="checkbox" name="visual_layers[]" value="maritime_museum_sailroof" /> Maritime Museum sail roof</label>
            <label><input type="checkbox" name="visual_layers[]" value="lions_gate_bridge" /> Lions Gate Bridge</label>
            <label><input type="checkbox" name="visual_layers[]" value="bc_place_dome" /> BC Place dome</label>
            <label><input type="checkbox" name="visual_layers[]" value="port_cranes" /> Port cranes</label>
          </div>
        </div>
        <div class="asmr-motif-group">
          <h3>Street Texture</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="visual_layers[]" value="cobblestone_perspective" /> cobblestones</label>
            <label><input type="checkbox" name="visual_layers[]" value="brick_wall_parallax" /> brick wall parallax</label>
            <label><input type="checkbox" name="visual_layers[]" value="puddle_reflections" /> puddle reflections</label>
            <label><input type="checkbox" name="visual_layers[]" value="streetlamp_halo_row" /> streetlamp halos</label>
          </div>
        </div>
        <div class="asmr-motif-group">
          <h3>Transit + CRT</h3>
          <div class="asmr-motif-grid">
            <label><input type="checkbox" name="visual_layers[]" value="skytrain_track" /> SkyTrain track</label>
            <label><input type="checkbox" name="visual_layers[]" value="skytrain_pass_visual" checked /> SkyTrain pass visual</label>
            <label><input type="checkbox" name="visual_layers[]" value="bus_pass_visual" /> bus pass visual</label>
            <label><input type="checkbox" name="visual_layers[]" value="scanline_field" /> scanline field</label>
            <label><input type="checkbox" name="visual_layers[]" value="glitch_flash" /> glitch flash</label>
            <label><input type="checkbox" name="visual_layers[]" value="signal_bars" /> signal bars</label>
            <label><input type="checkbox" name="visual_layers[]" value="chromatic_veil" /> chromatic veil</label>
            <label><input type="checkbox" name="visual_layers[]" value="neon_sign_flicker" checked /> neon sign flicker</label>
          </div>
        </div>
      </fieldset>

      <label class="asmr-link-toggle"><input type="checkbox" name="link_av" checked /> Link sound + visual for selected motifs</label>

      <details class="asmr-advanced-fields">
        <summary>Advanced / Freeform Mode (optional override)</summary>
        <div class="asmr-grid">
          <label>Concept<input type="text" name="concept" maxlength="140" placeholder="Retro monolith waking up" /></label>
          <label>Object<input type="text" name="object" maxlength="80" placeholder="glass relay core" /></label>
          <label>Setting<input type="text" name="setting" maxlength="120" placeholder="midnight signal chamber" /></label>
          <label>Mood<input type="text" name="mood" maxlength="80" placeholder="tight tension then bloom" /></label>
          <label>Creative Goal<textarea name="creative_goal" rows="2" maxlength="260" placeholder="Favor tactile pulses and a clear terminal reveal."></textarea></label>
        </div>
      </details>
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

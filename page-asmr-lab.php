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
        <label>Location
          <select name="location">
            <option value="gastown" selected>Gastown Steam Clock</option>
            <option value="granville">Granville Street</option>
            <option value="north_shore">North Shore Mountains</option>
          </select>
        </label>
        <label>Weather
          <select name="weather">
            <option value="snow" selected>Snow</option>
            <option value="rain">Rain</option>
            <option value="fog">Fog</option>
            <option value="clear_cold">Clear Cold</option>
          </select>
        </label>
        <label>Duration (10-30 sec)<input type="number" name="duration" min="10" max="30" value="20" required /></label>
        <label>Voice Style<input type="text" name="voice_style" maxlength="80" placeholder="composed machine whisper" /></label>
        <label>Weirdness (1-10)<input type="number" name="weirdness" min="1" max="10" value="6" required /></label>
      </div>

      <fieldset class="asmr-foley-grid">
        <legend>Foley Layers</legend>
        <label><input type="checkbox" name="foley[]" value="footsteps" checked /> footsteps</label>
        <label><input type="checkbox" name="foley[]" value="rain" /> rain</label>
        <label><input type="checkbox" name="foley[]" value="chatter" /> chatter</label>
        <label><input type="checkbox" name="foley[]" value="laughter" /> laughter</label>
        <label><input type="checkbox" name="foley[]" value="skytrain" /> SkyTrain</label>
        <label><input type="checkbox" name="foley[]" value="bus" /> bus</label>
        <label><input type="checkbox" name="foley[]" value="car_horn" /> car horn</label>
        <label><input type="checkbox" name="foley[]" value="steam_clock" checked /> Gastown steam clock</label>
      </fieldset>

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

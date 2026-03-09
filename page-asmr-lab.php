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
      <p class="asmr-intro">Describe a concept and let AI generate a sensory ad concept, tactical beat sheet, tactile 8-bit foley recipe, and model-ready video prompts.</p>
    </header>

    <form id="asmr-lab-form" class="asmr-lab-form" novalidate>
      <div class="asmr-grid">
        <label>Concept<input type="text" name="concept" required maxlength="140" placeholder="Glitchy tea ritual launch" /></label>
        <label>Object<input type="text" name="object" required maxlength="80" placeholder="ceramic mug" /></label>
        <label>Setting<input type="text" name="setting" required maxlength="120" placeholder="rainy apartment at dawn" /></label>
        <label>Mood<input type="text" name="mood" required maxlength="80" placeholder="soft tension + wonder" /></label>
        <label>Duration (15-30 sec)<input type="number" name="duration" min="15" max="30" value="20" required /></label>
        <label>Voice Style<input type="text" name="voice_style" maxlength="80" placeholder="minimal poetic narrator" /></label>
        <label>Weirdness (1-10)<input type="number" name="weirdness" min="1" max="10" value="6" required /></label>
        <label>Creative Goal<textarea name="creative_goal" rows="2" maxlength="260" placeholder="Make ordinary textures feel cinematic."></textarea></label>
      </div>
      <div class="asmr-actions">
        <button type="submit" class="pixel-button">Generate ASMR Package</button>
        <button type="button" id="asmr-sound-only" class="pixel-button secondary" disabled>Regenerate Sound Only</button>
      </div>
      <p id="asmr-status" class="asmr-status" role="status" aria-live="polite"></p>
      <p id="asmr-error" class="asmr-error" role="alert" hidden></p>
    </form>

    <section class="asmr-audio-controls" aria-label="Sound preview controls">
      <button type="button" id="asmr-preview" class="pixel-button" disabled>Preview Sound</button>
      <button type="button" id="asmr-stop" class="pixel-button secondary" disabled>Stop</button>
      <button type="button" id="asmr-export" class="pixel-button secondary" disabled>Export WAV</button>
      <p id="asmr-audio-feedback" class="asmr-audio-feedback" aria-live="polite"></p>
    </section>

    <section id="asmr-results" class="asmr-results" hidden>
      <article class="asmr-card"><h2>Concept</h2><p id="asmr-concept"></p></article>
      <article class="asmr-card"><h2>Beat Sheet</h2><ol id="asmr-beats"></ol></article>
      <article class="asmr-card"><h2>Video Prompts</h2><button type="button" id="asmr-copy-prompts" class="pixel-button tiny">Copy prompts</button><ul id="asmr-video-prompts"></ul></article>
      <article class="asmr-card"><h2>Edit Notes</h2><p id="asmr-edit-notes"></p></article>
      <article class="asmr-card"><h2>Presentation Note</h2><p id="asmr-presentation"></p></article>
      <article class="asmr-card"><h2>Sound Recipe</h2>
        <details>
          <summary>View JSON recipe</summary>
          <pre id="asmr-sound-json"></pre>
        </details>
      </article>
    </section>
  </section>
</main>
<?php get_footer(); ?>

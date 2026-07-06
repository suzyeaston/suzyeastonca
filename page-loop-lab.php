<?php
/*
Template Name: Loop Lab
*/

get_header();
?>
<main id="primary" class="loop-lab-page content-area">
  <section class="loop-lab-shell" id="loop-lab-app" aria-labelledby="loop-lab-title">
    <header class="loop-lab-hero">
      <p class="loop-lab-kicker pixel-font">experimental tape machine // no prompt box</p>
      <h1 id="loop-lab-title" class="loop-lab-title pixel-font">Loop Lab</h1>
      <p class="loop-lab-intro">make a noise. keep the good bit. ruin it carefully.</p>
    </header>

    <section class="loop-lab-machine" aria-label="Browser loop recorder">
      <div class="loop-lab-reel" aria-hidden="true">
        <span></span><span></span>
      </div>

      <div class="loop-lab-status-panel" aria-live="polite">
        <p class="loop-lab-state-label pixel-font">loop state</p>
        <p class="loop-lab-state pixel-font" data-loop-state>READY TO PLAY</p>
        <p class="loop-lab-clock" data-loop-clock>00.0s</p>
      </div>

      <div class="loop-lab-controls" role="group" aria-label="Recording controls">
        <button type="button" class="loop-lab-record" data-loop-record>record first loop</button>
        <button type="button" class="pixel-button secondary" data-loop-stop disabled>stop</button>
        <button type="button" class="pixel-button secondary" data-loop-reset disabled>restart</button>
      </div>

      <p class="loop-lab-message" data-loop-message role="status" aria-live="polite">first move: make sound into the mic.</p>

      <div class="loop-lab-meter" aria-label="Loop progress">
        <span data-loop-progress></span>
      </div>
    </section>

    <section class="loop-lab-layers" aria-labelledby="loop-layers-title">
      <div class="loop-lab-section-head">
        <p class="loop-lab-kicker pixel-font">tracks</p>
        <h2 id="loop-layers-title" class="pixel-font">layers on the deck</h2>
      </div>
      <div class="loop-lab-empty" data-loop-empty>no loop yet. the machine is waiting.</div>
      <ol class="loop-lab-layer-list" data-loop-layers></ol>
    </section>

    <aside class="loop-lab-note" aria-label="Prototype notes">
      <p><strong>prototype note:</strong> this records local browser audio only. no upload. no AI costume.</p>
      <p>overdubs play while you record. browsers do not guarantee studio-tight sync, and speaker bleed can sneak into the mic. headphones help.</p>
    </aside>
  </section>
</main>
<?php get_footer(); ?>

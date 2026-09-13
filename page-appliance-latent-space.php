<?php
/*
Template Name: Appliance Latent Space
*/

get_header();
?>
<main id="primary" class="appliance-page content-area">
  <section class="appliance-shell" id="appliance-latent-space-app" data-instrument="appliance-latent-space" aria-labelledby="appliance-title">
    <header class="appliance-hero">
      <p class="appliance-kicker pixel-font">control surface // no appliance attached</p>
      <h1 id="appliance-title" class="appliance-title pixel-font">The Appliance Latent Space</h1>
      <p class="appliance-intro">one instrument, three bodies: a toaster, a laptop, and this page. the toaster doesn&rsquo;t exist yet. the vocabulary does.</p>
    </header>

    <section class="appliance-status" aria-label="Prototype status">
      <p class="appliance-status__label pixel-font">state</p>
      <p class="appliance-state pixel-font" data-appliance-state>CONTROL SURFACE ONLY</p>
      <p class="appliance-status__note">ten controls, no audio engine behind them yet. no model is running. nothing here makes a sound.</p>
    </section>

    <div class="appliance-rack">
      <fieldset class="appliance-group" data-control-group="tone">
        <legend class="appliance-group__legend pixel-font">tone</legend>
        <p class="appliance-group__note">how cooked.</p>

        <div class="appliance-control appliance-control--continuous">
          <div class="appliance-control__head">
            <label class="appliance-control__name pixel-font" for="appliance-control-browning">browning</label>
            <kbd class="appliance-control__key" aria-hidden="true">B</kbd>
          </div>
          <input class="appliance-control__range" type="range" id="appliance-control-browning" data-control="browning" min="0" max="1" step="0.05" value="0.35" aria-describedby="appliance-hint-browning">
          <output class="appliance-control__readout" for="appliance-control-browning" data-control-readout="browning">0.35</output>
          <p class="appliance-control__hint" id="appliance-hint-browning">how hard the signal is cooked. key B steps up, shift steps down.</p>
        </div>

        <div class="appliance-control appliance-control--continuous">
          <div class="appliance-control__head">
            <label class="appliance-control__name pixel-font" for="appliance-control-destruction">destruction</label>
            <kbd class="appliance-control__key" aria-hidden="true">D</kbd>
          </div>
          <input class="appliance-control__range" type="range" id="appliance-control-destruction" data-control="destruction" min="0" max="1" step="0.05" value="0" aria-describedby="appliance-hint-destruction">
          <output class="appliance-control__readout" for="appliance-control-destruction" data-control-readout="destruction">0.00</output>
          <p class="appliance-control__hint" id="appliance-hint-destruction">controlled damage. key D steps up, shift steps down.</p>
        </div>
      </fieldset>

      <fieldset class="appliance-group" data-control-group="latent">
        <legend class="appliance-group__legend pixel-font">latent</legend>
        <p class="appliance-group__note">where in the space.</p>

        <div class="appliance-control appliance-control--continuous">
          <div class="appliance-control__head">
            <label class="appliance-control__name pixel-font" for="appliance-control-latent_x">latent x</label>
            <kbd class="appliance-control__key" aria-hidden="true">X</kbd>
          </div>
          <input class="appliance-control__range" type="range" id="appliance-control-latent_x" data-control="latent_x" min="0" max="1" step="0.05" value="0.5" aria-describedby="appliance-hint-latent_x">
          <output class="appliance-control__readout" for="appliance-control-latent_x" data-control-readout="latent_x">0.50</output>
          <p class="appliance-control__hint" id="appliance-hint-latent_x">first axis of the space. moves timbre sideways. key X steps up, shift steps down.</p>
        </div>

        <div class="appliance-control appliance-control--continuous">
          <div class="appliance-control__head">
            <label class="appliance-control__name pixel-font" for="appliance-control-latent_y">latent y</label>
            <kbd class="appliance-control__key" aria-hidden="true">Y</kbd>
          </div>
          <input class="appliance-control__range" type="range" id="appliance-control-latent_y" data-control="latent_y" min="0" max="1" step="0.05" value="0.5" aria-describedby="appliance-hint-latent_y">
          <output class="appliance-control__readout" for="appliance-control-latent_y" data-control-readout="latent_y">0.50</output>
          <p class="appliance-control__hint" id="appliance-hint-latent_y">second axis. moves it up and down. key Y steps up, shift steps down.</p>
        </div>

        <div class="appliance-control appliance-control--continuous">
          <div class="appliance-control__head">
            <label class="appliance-control__name pixel-font" for="appliance-control-neural_mix">neural mix</label>
            <kbd class="appliance-control__key" aria-hidden="true">N</kbd>
          </div>
          <input class="appliance-control__range" type="range" id="appliance-control-neural_mix" data-control="neural_mix" min="0" max="1" step="0.05" value="0" aria-describedby="appliance-hint-neural_mix">
          <output class="appliance-control__readout" for="appliance-control-neural_mix" data-control-readout="neural_mix">0.00</output>
          <p class="appliance-control__hint" id="appliance-hint-neural_mix">dry to wet. nothing neural behind it yet. key N steps up, shift steps down.</p>
        </div>
      </fieldset>

      <fieldset class="appliance-group" data-control-group="time">
        <legend class="appliance-group__legend pixel-font">time</legend>
        <p class="appliance-group__note">what survives.</p>

        <div class="appliance-control appliance-control--continuous">
          <div class="appliance-control__head">
            <label class="appliance-control__name pixel-font" for="appliance-control-memory">memory</label>
            <kbd class="appliance-control__key" aria-hidden="true">M</kbd>
          </div>
          <input class="appliance-control__range" type="range" id="appliance-control-memory" data-control="memory" min="0" max="1" step="0.05" value="0.25" aria-describedby="appliance-hint-memory">
          <output class="appliance-control__readout" for="appliance-control-memory" data-control-readout="memory">0.25</output>
          <p class="appliance-control__hint" id="appliance-hint-memory">how much of the last thing survives. key M steps up, shift steps down.</p>
        </div>

        <div class="appliance-control appliance-control--toggle">
          <div class="appliance-control__head">
            <span class="appliance-control__name pixel-font">freeze</span>
            <kbd class="appliance-control__key" aria-hidden="true">F</kbd>
          </div>
          <button type="button" class="appliance-control__button" data-control="freeze" aria-pressed="false" aria-describedby="appliance-hint-freeze">freeze</button>
          <p class="appliance-control__hint" id="appliance-hint-freeze">hold the space still. key F toggles.</p>
        </div>
      </fieldset>

      <fieldset class="appliance-group" data-control-group="gesture">
        <legend class="appliance-group__legend pixel-font">gesture</legend>
        <p class="appliance-group__note">what you do to it.</p>

        <div class="appliance-control appliance-control--momentary">
          <div class="appliance-control__head">
            <span class="appliance-control__name pixel-font">plunge</span>
            <kbd class="appliance-control__key" aria-hidden="true">SPACE</kbd>
          </div>
          <button type="button" class="appliance-control__button appliance-control__button--lever" data-control="plunge" aria-describedby="appliance-hint-plunge">plunge</button>
          <p class="appliance-control__hint" id="appliance-hint-plunge">push the lever. something starts. key SPACE.</p>
        </div>

        <div class="appliance-control appliance-control--momentary">
          <div class="appliance-control__head">
            <span class="appliance-control__name pixel-font">capture</span>
            <kbd class="appliance-control__key" aria-hidden="true">C</kbd>
          </div>
          <button type="button" class="appliance-control__button" data-control="capture" aria-describedby="appliance-hint-capture">capture</button>
          <p class="appliance-control__hint" id="appliance-hint-capture">keep the current position. key C.</p>
        </div>

        <div class="appliance-control appliance-control--momentary">
          <div class="appliance-control__head">
            <span class="appliance-control__name pixel-font">kill</span>
            <kbd class="appliance-control__key" aria-hidden="true">K</kbd>
          </div>
          <button type="button" class="appliance-control__button appliance-control__button--danger" data-control="kill" aria-describedby="appliance-hint-kill">kill</button>
          <p class="appliance-control__hint" id="appliance-hint-kill">everything stops. key K.</p>
        </div>
      </fieldset>
    </div>

    <aside class="appliance-note" aria-label="Prototype notes">
      <p><strong>prototype note:</strong> these are real controls with nothing behind them. no audio engine, no MIDI, no model.</p>
      <p>&ldquo;neural mix&rdquo; is named for where this is going, not for what it does today.</p>
      <p>the same ten ids will drive the appliance when there is an appliance. the dials bind to the names. the names don&rsquo;t move.</p>
    </aside>
  </section>
</main>
<?php get_footer(); ?>

<?php
$se_stream = se_radio_url( get_option( 'se_radio_stream', '' ) );
$se_status = se_radio_url( get_option( 'se_radio_status', '' ) );
?>
<section class="sp-radio sp-radio--feature" data-pirate-radio data-stream-url="<?php echo esc_attr( $se_stream ); ?>" data-status-url="<?php echo esc_attr( $se_status ); ?>" aria-labelledby="sp-home-title">
  <div class="sp-radio__body">
    <p class="sp-radio__eyebrow">SUZY EASTON / INDEPENDENT INTERNET RADIO / 001</p>
    <h2 id="sp-home-title">PIRATE RADIO<span>Broadcasting from the in-between.</span></h2>
    <p class="sp-radio__intro">Original sounds, improvised transmissions, strange little experiments. One station, operated by Suzy, wherever the studio happens to be.</p>
    <div class="sp-radio__signal" data-radio-status role="status" aria-live="polite"><span class="sp-radio__dot" aria-hidden="true"></span><span data-radio-status-text>CHECKING SIGNAL</span></div>
    <div class="sp-radio__controls">
      <button class="sp-radio__button" type="button" data-radio-play disabled>Listen live</button>
      <button class="sp-radio__button sp-radio__button--secondary" type="button" data-radio-stop disabled>Stop</button>
      <a class="sp-radio__link" href="<?php echo esc_url( home_url( '/radio/' ) ); ?>">Enter the station ↗</a>
    </div>
    <p class="sp-radio__playback" data-radio-playback role="status">No audio starts automatically.</p>
    <audio data-radio-audio preload="none" playsinline></audio>
  </div>
  <div class="sp-radio__art" aria-hidden="true">
    <div class="sp-radio__orbit"><span>SE//</span><strong>RADIO</strong><small>NO FIXED FREQUENCY</small></div>
    <div class="sp-radio__spectrum"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
  </div>
</section>

<?php
/* Template Name: Suzy Pirate Radio */
get_header();
$se_stream = se_radio_url( get_option( 'se_radio_stream', '' ) );
$se_status = se_radio_url( get_option( 'se_radio_status', '' ) );
?>
<main class="sp-radio-page" id="pirate-radio">
  <a class="sp-radio-page__back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← RETURN TO SUZY'S WORLD</a>
  <section class="sp-radio sp-radio--full" data-pirate-radio data-stream-url="<?php echo esc_attr( $se_stream ); ?>" data-status-url="<?php echo esc_attr( $se_status ); ?>" aria-labelledby="sp-station-title">
    <div class="sp-radio__body">
      <p class="sp-radio__eyebrow">SE // 001 · INTERNET TRANSMISSION · VANCOUVER & ELSEWHERE</p>
      <h1 id="sp-station-title">SUZY<span>// PIRATE RADIO</span></h1>
      <p class="sp-radio__intro">No fixed studio. No algorithm deciding what comes next. Just a little independent station for original music, live improvisation, field recordings and whatever grows between the wires.</p>
      <div class="sp-radio__signal" data-radio-status role="status" aria-live="polite"><span class="sp-radio__dot" aria-hidden="true"></span><span data-radio-status-text>CHECKING SIGNAL</span></div>
      <p class="sp-radio__station-message" data-radio-message>Waiting for transmitter information.</p>
      <div class="sp-radio__controls">
        <button class="sp-radio__button" type="button" data-radio-play disabled>Listen live</button>
        <button class="sp-radio__button sp-radio__button--secondary" type="button" data-radio-stop disabled>Stop</button>
      </div>
      <div class="sp-radio__volume">
        <label for="sp-station-volume">YOUR VOLUME</label>
        <input id="sp-station-volume" type="range" data-radio-volume min="0" max="1" step=".05" value=".8">
        <output data-radio-volume-value for="sp-station-volume">80%</output>
      </div>
      <p class="sp-radio__playback" data-radio-playback role="status">Playback is stopped. Press Listen when a signal is available.</p>
      <p class="sp-radio__listeners" data-radio-listeners></p>
      <audio data-radio-audio preload="none" playsinline></audio>
      <noscript><p>JavaScript is needed for the receiver. <?php if ( $se_stream ) : ?><a href="<?php echo esc_url( $se_stream ); ?>">Open the public stream directly</a>.<?php else : ?>The public stream hasn't been configured yet.<?php endif; ?></p></noscript>
    </div>
    <div class="sp-radio__art" aria-hidden="true">
      <div class="sp-radio__orbit"><span>SE//</span><strong>RADIO</strong><small>INDEPENDENT SIGNAL</small></div>
      <div class="sp-radio__spectrum"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
      <p class="sp-radio__art-caption">YOUR RECEIVER IS READY.<br>THE REST IS IMPROVISATION.</p>
    </div>
  </section>
  <section class="sp-radio-page__note" aria-labelledby="sp-note-heading">
    <div><p class="sp-radio__eyebrow">ABOUT THIS FREQUENCY</p><h2 id="sp-note-heading">An independent signal, not a radio directory.</h2></div>
    <div><p>This is Suzy's own internet station. The music and conversations will change; the invitation stays the same: tune in when a broadcast is happening. When the transmitter is offline, nothing fake plays in its place.</p>
    <p>Internet radio only. No FM broadcast, commercial station affiliation or automatic rebroadcast of other people's feeds.</p>
    <?php if ( current_user_can( 'manage_options' ) ) : ?><p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=se-radio' ) ); ?>">Configure the public stream ↗</a></p><?php endif; ?></div>
  </section>
</main>
<?php get_footer(); ?>

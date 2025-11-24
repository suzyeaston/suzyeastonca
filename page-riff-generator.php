<?php
/*
Template Name: Riff Generator
*/
get_header();
?>

<div id="primary" class="content-area">
  <main id="main" class="site-main">
    <section id="riff-app" class="riff-generator"></section>
    <div class="riff-output">
      <h3 class="pixel-font">Your Riff:</h3>
      <p id="riff-text"></p>
      <audio id="riff-audio" controls style="display:none;"></audio>
      <p id="riff-status" class="pixel-font" style="display:none;"></p>
    </div>
  </main>
</div>

<script src="https://unpkg.com/tone@14.7.77/build/Tone.js"></script>
<script src="https://unpkg.com/tonejs-instruments@1.0.25/dist/tonejs-instruments.min.js"></script>
<script>
  window.seRiffConfig = {
    tipEndpoint: '<?php echo esc_url( rest_url( 'se/v1/riff-tip' ) ); ?>',
    nonce: '<?php echo wp_create_nonce( 'wp_rest' ); ?>'
  };
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.3.4/dist/vue.global.prod.js"></script>
<script src="<?php echo get_template_directory_uri(); ?>/js/riff-generator.js"></script>
<?php get_footer(); ?>

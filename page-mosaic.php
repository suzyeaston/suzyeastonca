<?php
/**
 * Template Name: MOSAIC / Multitrack Video Studio
 * Description: Public local-first video studio embedded from self-hosted theme assets.
 *
 * No WordPress page creation required; functions.php routes /mosaic/ here.
 */
get_header();
$studio_relative = '/assets/mosaic/index.html';
$studio_file = get_template_directory() . $studio_relative;
$studio_url = get_template_directory_uri() . $studio_relative;
$studio_available = is_file( $studio_file );
if ( $studio_available ) {
    $studio_url = add_query_arg( 'v', (string) filemtime( $studio_file ), $studio_url );
}
?>
<main id="main-content" class="mosaic-page">
  <style>
    .mosaic-page{background:#11131f;color:#f7f1ea;min-height:900px;padding-bottom:16px}
    .mosaic-page .mosaic-intro{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:20px max(22px,4vw);background:#1b1d2c;border-bottom:1px solid #39394b;font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
    .mosaic-page .mosaic-intro strong{font-size:14px;letter-spacing:.09em;color:#c0adff}
    .mosaic-page .mosaic-intro p{font-size:12px;color:#d5ccd9;margin:7px 0 0}
    .mosaic-page .mosaic-intro a,.mosaic-page .mosaic-note a{color:#a9f1d6;text-decoration:underline;text-underline-offset:4px}
    .mosaic-page iframe{display:block;width:100%;height:1320px;min-height:720px;margin:0 auto;max-width:1500px;border:0;background:#11131f}
    .mosaic-page .mosaic-note{padding:16px max(22px,4vw);max-width:1000px;margin:0 auto;font-size:13px;line-height:1.6;color:#d3cbdc}
    .mosaic-page .mosaic-error{min-height:300px;padding:50px 22px;text-align:center}
    @media(max-width:850px){.mosaic-page .mosaic-intro{flex-direction:column;align-items:flex-start}.mosaic-page iframe{height:1900px}}
  </style>
  <div class="mosaic-intro">
    <div>
      <strong>✳ MOSAIC / FREE MULTITRACK VIDEO STUDIO</strong>
      <p>Make a performance with yourself. Camera, microphone, and recordings stay in your browser.</p>
    </div>
    <?php if ( $studio_available ) : ?>
      <a href="<?php echo esc_url( $studio_url ); ?>" target="_blank" rel="noopener noreferrer">OPEN STUDIO FULLSCREEN ↗</a>
    <?php endif; ?>
  </div>
  <?php if ( $studio_available ) : ?>
    <iframe id="mosaic-studio-frame" title="MOSAIC: multitrack video and audio studio" src="<?php echo esc_url( $studio_url ); ?>" loading="eager" allow="camera; microphone; autoplay"></iframe>
    <div class="mosaic-note">
      <p>If camera or microphone access is blocked here, <a href="<?php echo esc_url( $studio_url ); ?>" target="_blank" rel="noopener noreferrer">open the studio directly</a>. Give your browser permission and wear headphones when overdubbing. Export takes you want to preserve. There is no server upload or account.</p>
    </div>
  <?php else : ?>
    <div class="mosaic-error" role="status">
      <h2>Studio not available yet</h2>
      <p>The web application files are still being deployed. Please check back shortly.</p>
    </div>
  <?php endif; ?>
</main>
<?php get_footer(); ?>

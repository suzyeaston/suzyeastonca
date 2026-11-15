<?php
/**
 * Template Name: MOSAIC / Multitrack Video Studio
 * Description: Free local-first browser-based multiframe video recording.
 * Virtual route /mosaic/ in functions.php; no WordPress page creation needed.
 * Backend is GitHub Pages. Publish that app and QA devices BEFORE merging.
 */
get_header();
?>
<main id="main-content" class="mosaic-page">
  <style>
    .mosaic-page{background:#10121b;color:#f5f0e7;min-height:950px;padding:0 0 25px}
    .mosaic-page .mosaic-intro{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:21px max(22px,4vw);background:#181b29;border-bottom:1px solid #3b3c4e;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px}
    .mosaic-page .mosaic-intro strong{color:#baa6ff;letter-spacing:.1em}
    .mosaic-page .mosaic-intro p{color:#cbc4d6;font-size:12px;margin:8px 0 0}
    .mosaic-page .mosaic-intro a{color:#9bdfc9;text-decoration:underline;text-underline-offset:4px;white-space:nowrap}
    .mosaic-page iframe{border:0;display:block;width:100%;height:1350px;max-width:1550px;margin:0 auto;background:#10121b}
    .mosaic-page .mosaic-fallback{max-width:950px;margin:0 auto;padding:13px 24px;color:#bbb2c8;font-size:13px;line-height:1.65}
    .mosaic-page .mosaic-fallback a{color:#9bdfc9;text-decoration:underline}
    @media(max-width:760px){.mosaic-page .mosaic-intro{flex-direction:column;align-items:flex-start}.mosaic-page iframe{height:1850px}}
  </style>
  <div class="mosaic-intro">
    <div><strong>✳ MOSAIC / FREE MULTITRACK VIDEO STUDIO</strong><p>Record camera or audio takes. Your tracks stay in this browser, not on our server.</p></div>
    <a href="https://suzyeaston.github.io/mosaic-studio/" target="_blank" rel="noopener noreferrer">OPEN STUDIO IN NEW TAB ↗</a>
  </div>
  <iframe
    title="MOSAIC: multitrack video and audio studio"
    src="https://suzyeaston.github.io/mosaic-studio/"
    loading="eager"
    allow="camera; microphone; autoplay"
    referrerpolicy="no-referrer"
  ></iframe>
  <div class="mosaic-fallback">
    <p>Camera or microphone permission blocked in this embedded view? <a href="https://suzyeaston.github.io/mosaic-studio/" target="_blank" rel="noopener noreferrer">Open the studio directly</a> for the most reliable recording experience. Recording and export happen locally; use headphones and download work you want to keep.</p>
  </div>
</main>
<?php get_footer(); ?>

<?php
/**
 * Template Name: AFTERIMAGE / Vancouver
 * Description: Experimental creative-work atlas, hosted separately on GitHub Pages.
 *
 * Routed automatically at /afterimage/ by functions.php. No database page required.
 * The frame contains public job signals only. Personal applications stay local.
 */
get_header();
?>
<main id="main-content" class="afterimage-page">
  <style>
    .afterimage-page{background:#090d16;color:#ecf4f4;min-height:850px}
    .afterimage-page .afterimage-intro{padding:18px max(20px,4vw);display:flex;justify-content:space-between;gap:20px;align-items:center;font-family:monospace;font-size:12px;color:#a4ffc9;letter-spacing:.07em}
    .afterimage-page .afterimage-intro a{color:#fff;text-decoration:underline}
    .afterimage-page iframe{border:0;display:block;width:100%;height:min(1000px,calc(100vh - 90px));min-height:740px;background:#080c15}
    @media(max-width:760px){.afterimage-page .afterimage-intro{flex-direction:column;align-items:flex-start}.afterimage-page iframe{height:1250px;min-height:1250px}}
  </style>
  <div class="afterimage-intro"><span>✳ AFTERIMAGE / VANCOUVER · CREATIVE WORK ATLAS</span><a href="https://suzyeaston.github.io/afterimage-vancouver/" target="_blank" rel="noopener noreferrer">OPEN FULLSCREEN ↗</a></div>
  <iframe title="AFTERIMAGE Vancouver: interactive map of creative employers and recent jobs" loading="eager" referrerpolicy="strict-origin-when-cross-origin" src="https://suzyeaston.github.io/afterimage-vancouver/"></iframe>
  <noscript><p><a href="https://suzyeaston.github.io/afterimage-vancouver/">Open the interactive atlas</a> (JavaScript required).</p></noscript>
</main>
<?php get_footer(); ?>

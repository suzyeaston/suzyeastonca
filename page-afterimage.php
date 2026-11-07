<?php
/**
 * Template Name: AFTERIMAGE / Vancouver
 * Description: Experimental creative-work atlas, hosted separately on GitHub Pages.
 *
 * Routed automatically at /afterimage/ by functions.php. No database page required.
 * The frame contains public research signals only. Personal applications stay local.
 */
get_header();
?>
<main id="main-content" class="afterimage-page">
  <style>
    .afterimage-page{background:#090d16;color:#ecf4f4;min-height:850px}
    .afterimage-page .afterimage-intro{padding:18px max(20px,4vw);display:flex;justify-content:space-between;gap:20px;align-items:center;font-family:monospace;font-size:12px;color:#a4ffc9;letter-spacing:.07em}
    .afterimage-page .afterimage-intro a{color:#fff;text-decoration:underline}
    .afterimage-page iframe{border:0;display:block;width:100%;height:950px;min-height:640px;background:#080c15;overflow:hidden}
    @media(max-width:760px){.afterimage-page .afterimage-intro{flex-direction:column;align-items:flex-start}.afterimage-page iframe{height:950px;min-height:640px}}
  </style>
  <div class="afterimage-intro"><span>✳ AFTERIMAGE / VANCOUVER · CREATIVE WORK ATLAS</span><a href="https://suzyeaston.github.io/afterimage-vancouver/" target="_blank" rel="noopener noreferrer">OPEN FULLSCREEN ↗</a></div>
  <iframe id="afterimage-frame" title="AFTERIMAGE Vancouver: public creative work, organization directory, and community atlas" loading="eager" scrolling="no" referrerpolicy="strict-origin-when-cross-origin" src="https://suzyeaston.github.io/afterimage-vancouver/"></iframe>
  <script>
  (function () {
    'use strict';
    var frame = document.getElementById('afterimage-frame');
    if (!frame) return;
    window.addEventListener('message', function (event) {
      if (event.origin !== 'https://suzyeaston.github.io' || event.source !== frame.contentWindow) return;
      var data = event.data;
      if (!data) return;
      if (data.type === 'afterimage:resize') {
        var height = Number(data.height);
        if (!Number.isFinite(height) || height < 350 || height > 50000) return;
        frame.style.height = Math.ceil(height + 12) + 'px';
        frame.contentWindow.postMessage({type: 'afterimage:ready'}, 'https://suzyeaston.github.io');
      } else if (data.type === 'afterimage:scroll') {
        var y = Number(data.y);
        if (!Number.isFinite(y) || y < 0 || y > 50000) return;
        var top = frame.getBoundingClientRect().top + window.scrollY + y - 15;
        window.scrollTo({top: Math.max(0, top), behavior: 'smooth'});
      }
    }, false);
  }());
  </script>
  <noscript><p><a href="https://suzyeaston.github.io/afterimage-vancouver/">Open the interactive atlas</a> (JavaScript required).</p></noscript>
</main>
<?php get_footer(); ?>

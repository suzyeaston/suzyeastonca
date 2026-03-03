document.addEventListener('DOMContentLoaded', () => {
  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }

  const wrap = document.querySelector('.bio-crawl-wrap');
  if (!wrap) {
    return;
  }

  const restartCrawl = () => {
    window.scrollTo(0, 0);
    wrap.classList.remove('is-crawl-ready');
    void wrap.offsetWidth;
    requestAnimationFrame(() => {
      wrap.classList.add('is-crawl-ready');
    });
  };

  restartCrawl();

  window.addEventListener('pageshow', () => {
    restartCrawl();
  });
});

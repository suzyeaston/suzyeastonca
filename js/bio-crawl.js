document.addEventListener('DOMContentLoaded', () => {
  const wrap = document.querySelector('.bio-crawl-wrap');
  if (!wrap) {
    return;
  }

  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }

  window.scrollTo(0, 0);

  const restartCrawl = () => {
    window.scrollTo(0, 0);
    wrap.classList.remove('is-crawl-ready');
    void wrap.offsetWidth;
    queueMicrotask(() => {
      wrap.classList.add('is-crawl-ready');
    });
  };

  restartCrawl();

  window.addEventListener('pageshow', () => {
    restartCrawl();
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const wrap = document.querySelector('.bio-crawl-wrap');
  if (!wrap) {
    return;
  }

  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }

  window.scrollTo(0, 0);
  wrap.classList.add('is-crawl-ready');

  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
      return;
    }

    window.scrollTo(0, 0);
    wrap.classList.remove('is-crawl-ready');
    void wrap.offsetWidth;
    wrap.classList.add('is-crawl-ready');
  });
});

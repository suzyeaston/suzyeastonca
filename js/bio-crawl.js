document.addEventListener('DOMContentLoaded', () => {
  const wrap = document.querySelector('.bio-crawl-wrap');
  if (!wrap) {
    return;
  }

  const stage = wrap.querySelector('.bio-crawl-stage');
  const crawl = wrap.querySelector('.bio-crawl');
  const soundToggle = wrap.querySelector('.bio-crawl-sound-toggle');
  const audio = wrap.querySelector('.bio-crawl-audio');

  const setCrawlTravel = () => {
    if (!stage || !crawl) {
      return;
    }

    const crawlHeight = Math.ceil(crawl.scrollHeight);
    stage.style.height = `${crawlHeight}px`;
  };

  const setupAudioToggle = () => {
    if (!soundToggle || !audio) {
      return;
    }

    const audioSrc = audio.dataset.audioSrc ? audio.dataset.audioSrc.trim() : '';
    if (!audioSrc) {
      soundToggle.hidden = true;
      soundToggle.disabled = true;
      return;
    }

    audio.src = audioSrc;
    audio.loop = true;
    soundToggle.hidden = false;

    const setToggleState = (isPlaying) => {
      soundToggle.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
      soundToggle.textContent = isPlaying ? 'Sound: On' : 'Sound: Off';
    };

    setToggleState(false);

    soundToggle.addEventListener('click', async () => {
      if (audio.paused) {
        try {
          await audio.play();
          setToggleState(true);
        } catch (error) {
          setToggleState(false);
        }
        return;
      }

      audio.pause();
      setToggleState(false);
    });

    audio.addEventListener('ended', () => {
      setToggleState(false);
    });
  };

  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }

  window.scrollTo(0, 0);
  setCrawlTravel();
  setupAudioToggle();
  wrap.classList.add('is-crawl-ready');

  window.addEventListener('resize', setCrawlTravel);

  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
      return;
    }

    window.scrollTo(0, 0);
    setCrawlTravel();
    wrap.classList.remove('is-crawl-ready');
    void wrap.offsetWidth;
    wrap.classList.add('is-crawl-ready');
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const wrap = document.querySelector('.bio-crawl-wrap');
  if (!wrap) {
    return;
  }

  const track = wrap.querySelector('.bio-crawl-track');
  const crawl = wrap.querySelector('.bio-crawl');
  const crawlContent = wrap.querySelector('.bio-crawl-content');
  const soundToggle = wrap.querySelector('.bio-crawl-sound-toggle');
  const reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

  const state = {
    loop: null,
    bassLoop: null,
    leadSynth: null,
    bassSynth: null,
    fxNodes: [],
    isSoundOn: false,
    isTransportSetup: false
  };

  const setToggleState = (isOn) => {
    if (!soundToggle) {
      return;
    }

    const label = isOn
      ? 'Suzy’s Music Bio Theme Song: On'
      : 'Suzy’s Music Bio Theme Song: Off';

    soundToggle.setAttribute('aria-pressed', isOn ? 'true' : 'false');
    soundToggle.setAttribute('aria-label', label);
    soundToggle.textContent = label;
  };

  const updateCrawlMetrics = () => {
    if (!track || !crawl || !crawlContent) {
      return;
    }

    const wrapHeight = Math.ceil(wrap.clientHeight);
    const contentHeight = Math.ceil(crawlContent.scrollHeight);

    const title = crawlContent.querySelector('.bio-crawl-title');
    const subtitle = crawlContent.querySelector('.bio-crawl-subtitle');
    const firstParagraph = crawlContent.querySelector('p');

    const titleHeight = Math.ceil(title ? title.getBoundingClientRect().height : 0);
    const subtitleHeight = Math.ceil(subtitle ? subtitle.getBoundingClientRect().height : 0);
    const firstParagraphHeight = Math.ceil(firstParagraph ? firstParagraph.getBoundingClientRect().height : 0);
    const titleBlockHeight = titleHeight + subtitleHeight;

    // Explicitly anchor opener: title block starts around lower quarter/lower third,
    // with subtitle + first paragraph intro visible on frame one.
    const openerTargetY = Math.round(wrapHeight * (window.innerWidth <= 700 ? 0.68 : 0.64));
    const introVisibility = Math.round(firstParagraphHeight * 0.46);
    const baseStart = openerTargetY - titleBlockHeight;
    const minStart = Math.round(wrapHeight * 0.4);
    const maxStart = Math.round(wrapHeight * 0.8);
    const start = Math.max(minStart, Math.min(maxStart, baseStart - introVisibility));

    // Ensure enough upward travel to fully clear the horizon fade.
    const fadeClearance = Math.ceil(wrapHeight * 0.24);
    const travel = contentHeight + start + fadeClearance;

    wrap.style.setProperty('--crawl-start', `${start}px`);
    wrap.style.setProperty('--crawl-travel', `${travel}px`);
  };

  const refreshCrawl = () => {
    if (reduceMotionQuery.matches) {
      wrap.classList.remove('is-crawl-ready');
      return;
    }

    updateCrawlMetrics();
    wrap.classList.remove('is-crawl-ready');
    void wrap.offsetWidth;
    wrap.classList.add('is-crawl-ready');
  };

  const disposeSound = () => {
    if (state.loop) {
      state.loop.stop(0);
      state.loop.dispose();
      state.loop = null;
    }

    if (state.bassLoop) {
      state.bassLoop.stop(0);
      state.bassLoop.dispose();
      state.bassLoop = null;
    }

    if (state.leadSynth) {
      state.leadSynth.dispose();
      state.leadSynth = null;
    }

    if (state.bassSynth) {
      state.bassSynth.dispose();
      state.bassSynth = null;
    }

    state.fxNodes.forEach((node) => node.dispose());
    state.fxNodes = [];
    state.isTransportSetup = false;
  };

  const buildSoundLoop = () => {
    if (typeof Tone === 'undefined' || state.isTransportSetup) {
      return;
    }

    const leadDelay = new Tone.FeedbackDelay('8n', 0.2);
    const leadReverb = new Tone.Reverb({ decay: 1.4, wet: 0.23 });
    const leadGain = new Tone.Gain(0.16).toDestination();

    state.leadSynth = new Tone.PolySynth(Tone.Synth, {
      oscillator: { type: 'square8' },
      envelope: { attack: 0.005, decay: 0.22, sustain: 0.35, release: 0.45 }
    });
    state.leadSynth.chain(leadDelay, leadReverb, leadGain);

    const bassGain = new Tone.Gain(0.12).toDestination();
    state.bassSynth = new Tone.MonoSynth({
      oscillator: { type: 'pulse' },
      filter: { Q: 1.2, type: 'lowpass', rolloff: -24 },
      envelope: { attack: 0.01, decay: 0.2, sustain: 0.5, release: 0.35 },
      filterEnvelope: { attack: 0.01, decay: 0.12, sustain: 0.2, release: 0.3, baseFrequency: 90, octaves: 2 }
    }).connect(bassGain);

    state.fxNodes = [leadDelay, leadReverb, leadGain, bassGain];

    const fanfare = [
      ['C5', 'E5', 'G5'],
      ['D5', 'F5', 'A5'],
      ['E5', 'G5', 'B5'],
      ['G5', 'B5', 'D6'],
      ['F5', 'A5', 'C6'],
      ['E5', 'G5', 'B5'],
      ['D5', 'F5', 'A5'],
      ['G4', 'D5', 'G5']
    ];

    const bassline = ['C2', 'C2', 'D2', 'E2', 'F2', 'E2', 'D2', 'G1'];

    state.loop = new Tone.Sequence((time, chord) => {
      state.leadSynth.triggerAttackRelease(chord, '8n', time, 0.9);
    }, fanfare, '8n');

    state.bassLoop = new Tone.Sequence((time, note) => {
      state.bassSynth.triggerAttackRelease(note, '8n', time, 0.75);
    }, bassline, '8n');

    Tone.Transport.bpm.value = 126;
    Tone.Transport.loop = true;
    Tone.Transport.loopStart = 0;
    Tone.Transport.loopEnd = '2m';

    state.loop.start(0);
    state.bassLoop.start(0);
    state.isTransportSetup = true;
  };

  const setupSoundToggle = () => {
    if (!soundToggle) {
      return;
    }

    soundToggle.hidden = false;
    setToggleState(false);

    soundToggle.addEventListener('click', async () => {
      if (typeof Tone === 'undefined') {
        return;
      }

      try {
        await Tone.start();
        buildSoundLoop();

        if (!state.isSoundOn) {
          Tone.Transport.start('+0.05');
          state.isSoundOn = true;
          setToggleState(true);
          return;
        }

        Tone.Transport.stop();
        state.isSoundOn = false;
        setToggleState(false);
      } catch (error) {
        console.error('Bio crawl sound toggle failed', error);
        state.isSoundOn = false;
        setToggleState(false);
      }
    });
  };

  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }

  window.scrollTo(0, 0);
  setupSoundToggle();
  refreshCrawl();

  window.addEventListener('resize', updateCrawlMetrics);

  if (reduceMotionQuery.addEventListener) {
    reduceMotionQuery.addEventListener('change', refreshCrawl);
  } else if (reduceMotionQuery.addListener) {
    reduceMotionQuery.addListener(refreshCrawl);
  }

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(() => {
      refreshCrawl();
    });
  }

  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
      return;
    }

    window.scrollTo(0, 0);
    refreshCrawl();
  });

  window.addEventListener('beforeunload', () => {
    if (typeof Tone !== 'undefined') {
      Tone.Transport.stop();
      Tone.Transport.cancel();
    }
    disposeSound();
  });
});

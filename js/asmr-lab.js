(function () {
  'use strict';

  const app = document.getElementById('asmr-lab-app');
  if (!app || !window.seAsmrLab) return;

  const form = document.getElementById('asmr-lab-form');
  const statusEl = document.getElementById('asmr-status');
  const errorEl = document.getElementById('asmr-error');
  const resultsEl = document.getElementById('asmr-results');
  const previewBtn = document.getElementById('asmr-preview');
  const stopBtn = document.getElementById('asmr-stop');
  const exportBtn = document.getElementById('asmr-export');
  const exportVideoBtn = document.getElementById('asmr-export-video');
  const soundOnlyBtn = document.getElementById('asmr-sound-only');
  const qaPresetBtn = document.getElementById('asmr-qa-preset');
  const audioFeedback = document.getElementById('asmr-audio-feedback');
  const modeToggle = document.getElementById('asmr-playback-mode');
  const videoSupportEl = document.getElementById('asmr-video-support');
  const canvas = document.getElementById('asmr-visual-canvas');

  const engine = new window.AsmrFoleyEngine();
  const visuals = window.AsmrVisualEngine ? new window.AsmrVisualEngine(canvas) : null;
  let lastPayload = null;
  let currentPackage = null;

  const QA_PRESET = {
    concept: 'Glass relay prayer waking a dormant monolith',
    object: 'glass relay core',
    setting: 'midnight signal chamber',
    mood: 'spiritual tension, crystalline awe, then radiant bloom',
    duration: '20',
    voice_style: 'composed machine whisper',
    weirdness: '7',
    creative_goal: 'Build from tiny tactile pulses into a sacred electronic bloom with a terminal-style final reveal.'
  };

  const transport = {
    playing: false,
    stopFns: [],
    previewMeta: null,
    registerStop(fn) {
      this.stopFns.push(fn);
    },
    stopAll() {
      while (this.stopFns.length) {
        const fn = this.stopFns.pop();
        try { fn(); } catch (e) {}
      }
      this.playing = false;
      this.previewMeta = null;
    }
  };

  function setStatus(m) { if (statusEl) statusEl.textContent = m || ''; }
  function setError(m) {
    if (!errorEl) return;
    if (m) { errorEl.hidden = false; errorEl.textContent = m; }
    else { errorEl.hidden = true; errorEl.textContent = ''; }
  }

  function toList(parent, items) {
    if (!parent) return;
    parent.innerHTML = '';
    (Array.isArray(items) ? items : []).forEach((item) => {
      const li = document.createElement('li');
      li.textContent = typeof item === 'string' ? item : JSON.stringify(item);
      parent.appendChild(li);
    });
  }

  function getVideoSupport() {
    const support = {
      canCaptureCanvas: !!(window.HTMLCanvasElement && HTMLCanvasElement.prototype.captureStream),
      hasMediaRecorder: !!window.MediaRecorder,
      mp4Mime: '',
      webmMime: ''
    };

    if (support.hasMediaRecorder) {
      const mp4Types = ['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/mp4'];
      for (let i = 0; i < mp4Types.length; i += 1) {
        if (MediaRecorder.isTypeSupported(mp4Types[i])) { support.mp4Mime = mp4Types[i]; break; }
      }
      const webmTypes = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
      for (let i = 0; i < webmTypes.length; i += 1) {
        if (MediaRecorder.isTypeSupported(webmTypes[i])) { support.webmMime = webmTypes[i]; break; }
      }
    }

    support.canExport = support.canCaptureCanvas && support.hasMediaRecorder && !!(support.mp4Mime || support.webmMime);
    support.preferredMime = support.mp4Mime || support.webmMime || '';
    support.extension = support.mp4Mime ? 'mp4' : 'webm';
    return support;
  }

  const videoSupport = getVideoSupport();
  if (videoSupportEl) {
    if (videoSupport.mp4Mime) {
      videoSupportEl.textContent = 'Video export: MP4 supported on this browser.';
    } else if (videoSupport.webmMime) {
      videoSupportEl.textContent = 'Video export: MP4 unavailable here; exporting WebM fallback.';
    } else {
      videoSupportEl.textContent = 'Video export unavailable in this browser/runtime.';
    }
  }

  function renderData(data) {
    const concept = document.getElementById('asmr-concept');
    const beats = document.getElementById('asmr-beats');
    const prompts = document.getElementById('asmr-video-prompts');
    const edit = document.getElementById('asmr-edit-notes');
    const note = document.getElementById('asmr-presentation');
    const recipe = document.getElementById('asmr-sound-json');

    if (concept) concept.textContent = `${data.title} (${data.runtime_seconds}s) — ${data.hook}\n${data.concept_summary}`;
    toList(beats, data.sync_points || []);
    toList(prompts, data.style_tags || []);
    if (edit) {
      const er = data.edit_rhythm || {};
      edit.textContent = `${er.pacing_note || ''} ${er.silence_strategy || ''} ${er.release_strategy || ''}`.trim();
    }
    if (note) note.textContent = data.presentation_note || '';
    if (recipe) recipe.textContent = JSON.stringify({ audio_events: data.audio_events, visual_events: data.visual_events, end_card: data.end_card }, null, 2);

    currentPackage = data;
    if (visuals) {
      visuals.loadTimeline(data);
      visuals.seek(0);
    }
    if (resultsEl) resultsEl.hidden = false;
    [previewBtn, stopBtn, exportBtn, soundOnlyBtn, exportVideoBtn].forEach((b) => {
      if (!b) return;
      if (b === exportVideoBtn) b.disabled = !currentPackage || !videoSupport.canExport;
      else b.disabled = !currentPackage;
    });
  }

  async function requestGeneration(payload) {
    setError('');
    setStatus('Generating synchronized sensory film package...');
    const response = await fetch(window.seAsmrLab.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.seAsmrLab.nonce },
      body: JSON.stringify(payload)
    });

    let data;
    try { data = await response.json(); } catch (e) { throw new Error('Unexpected response format from ASMR Lab service.'); }
    if (!response.ok) throw new Error((data && data.message) ? data.message : 'Unable to generate ASMR package.');
    if (!data || typeof data !== 'object' || !Array.isArray(data.audio_events)) throw new Error('Generated package was incomplete. Please try again.');

    setStatus('Package generated. Ready for synchronized preview.');
    return data;
  }

  async function startPreview() {
    if (!currentPackage) return;
    transport.stopAll();
    engine.stop();
    if (visuals) visuals.stop();

    const fullMode = !modeToggle || modeToggle.value === 'audiovisual';
    if (visuals && fullMode) {
      visuals.loadTimeline(currentPackage);
      visuals.seek(0); // meaningful first frame before audio transport start
    }

    const playback = await engine.preview(currentPackage);
    transport.previewMeta = playback;
    transport.playing = true;

    if (visuals && fullMode) {
      visuals.play(-playback.preroll);
      transport.registerStop(() => visuals.stop());
    }
    transport.registerStop(() => engine.stop());

    if (audioFeedback) audioFeedback.textContent = fullMode ? 'Synchronized preview playing.' : 'Sound-only preview playing.';
  }

  async function exportVideo() {
    if (!currentPackage) return;
    if (!videoSupport.canExport) throw new Error('Video export is not supported in this browser/runtime.');

    transport.stopAll();
    engine.stop();
    if (visuals) visuals.stop();

    const runtime = Math.max(10, Math.min(30, Number(currentPackage.runtime_seconds || 20)));
    const fps = 30;

    const target = document.createElement('canvas');
    target.width = 1920;
    target.height = 1080;
    const targetCtx = target.getContext('2d');

    const exportVisuals = new window.AsmrVisualEngine(target);
    exportVisuals.loadTimeline(currentPackage);
    exportVisuals.renderToContext(targetCtx, 1920, 1080, 0);

    const stream = target.captureStream(fps);
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 48000 });
    if (audioCtx.state === 'suspended') await audioCtx.resume();
    const rendered = await engine.renderToOfflineBuffer(currentPackage, 48000);
    const source = audioCtx.createBufferSource();
    source.buffer = rendered;
    const gain = audioCtx.createGain();
    gain.gain.value = 0.95;
    const mediaDest = audioCtx.createMediaStreamDestination();
    source.connect(gain);
    gain.connect(mediaDest);
    mediaDest.stream.getAudioTracks().forEach((track) => stream.addTrack(track));

    const chunks = [];
    const rec = new MediaRecorder(stream, { mimeType: videoSupport.preferredMime, videoBitsPerSecond: 12_000_000 });
    rec.ondataavailable = (event) => { if (event.data && event.data.size > 0) chunks.push(event.data); };

    await new Promise((resolve, reject) => {
      const startAt = audioCtx.currentTime + 0.08;
      let raf = null;
      let ended = false;

      const cleanup = () => {
        if (raf) cancelAnimationFrame(raf);
        source.onended = null;
        try { source.stop(); } catch (e) {}
        stream.getTracks().forEach((t) => t.stop());
        audioCtx.close().catch(() => {});
      };

      const frameLoop = () => {
        const t = Math.max(0, audioCtx.currentTime - startAt);
        exportVisuals.renderToContext(targetCtx, 1920, 1080, Math.min(t, runtime));
        if (t < runtime + 0.12 && !ended) raf = requestAnimationFrame(frameLoop);
      };

      rec.onstop = () => {
        cleanup();
        resolve();
      };
      rec.onerror = () => {
        cleanup();
        reject(new Error('Video recorder failed.'));
      };

      source.onended = () => {
        ended = true;
        window.setTimeout(() => { try { rec.stop(); } catch (e) {} }, 120);
      };

      rec.start(250);
      source.start(startAt, 0, runtime);
      frameLoop();
    });

    const blob = new Blob(chunks, { type: videoSupport.preferredMime });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `asmr-lab-film-1920x1080.${videoSupport.extension}`;
    a.click();
    URL.revokeObjectURL(url);

    if (audioFeedback) {
      audioFeedback.textContent = videoSupport.extension === 'mp4'
        ? '1080p MP4 export complete.'
        : '1080p WebM export complete (MP4 not supported in this browser).';
    }
  }

  if (form) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      lastPayload = Object.fromEntries(new FormData(form).entries());
      try { renderData(await requestGeneration(lastPayload)); }
      catch (err) { setError(err.message || 'Generation failed.'); setStatus(''); }
    });
  }

  if (qaPresetBtn && form) {
    qaPresetBtn.addEventListener('click', () => {
      Object.entries(QA_PRESET).forEach(([key, value]) => {
        const field = form.elements.namedItem(key);
        if (field) field.value = value;
      });
      setStatus('QA preset loaded. Generate package to test known-good mood arc.');
      setError('');
    });
  }

  if (soundOnlyBtn) {
    soundOnlyBtn.addEventListener('click', async () => {
      if (!lastPayload) return setError('Generate a full package first, then regenerate sound.');
      try { renderData(await requestGeneration(Object.assign({}, lastPayload, { sound_only: true }))); }
      catch (err) { setError(err.message || 'Sound regeneration failed.'); }
    });
  }

  if (previewBtn) {
    previewBtn.addEventListener('click', async () => {
      if (!currentPackage) return;
      try {
        await startPreview();
      } catch (err) {
        if (audioFeedback) audioFeedback.textContent = 'Audio may be blocked until user interaction. Click preview again.';
      }
    });
  }

  if (stopBtn) {
    stopBtn.addEventListener('click', () => {
      transport.stopAll();
      if (visuals) visuals.stop();
      engine.stop();
      if (audioFeedback) audioFeedback.textContent = 'Playback stopped.';
    });
  }

  if (exportBtn) {
    exportBtn.addEventListener('click', async () => {
      if (!currentPackage) return;
      if (audioFeedback) audioFeedback.textContent = 'Rendering WAV...';
      try {
        const blob = await engine.exportWav(currentPackage);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'asmr-lab-preview.wav';
        a.click();
        URL.revokeObjectURL(url);
        if (audioFeedback) audioFeedback.textContent = 'WAV export complete.';
      } catch (err) {
        if (audioFeedback) audioFeedback.textContent = 'WAV export failed in this browser.';
      }
    });
  }

  if (exportVideoBtn) {
    exportVideoBtn.addEventListener('click', async () => {
      if (!currentPackage) return;
      if (audioFeedback) audioFeedback.textContent = 'Rendering 1920x1080 video...';
      try {
        await exportVideo();
      } catch (err) {
        if (audioFeedback) audioFeedback.textContent = err.message || 'Video export failed in this browser.';
      }
    });
  }

  window.addEventListener('resize', function () { if (visuals) visuals.resize(); });
})();

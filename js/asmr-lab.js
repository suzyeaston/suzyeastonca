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
  const soundOnlyBtn = document.getElementById('asmr-sound-only');
  const audioFeedback = document.getElementById('asmr-audio-feedback');
  const modeToggle = document.getElementById('asmr-playback-mode');
  const canvas = document.getElementById('asmr-visual-canvas');

  const engine = new window.AsmrFoleyEngine();
  const visuals = window.AsmrVisualEngine ? new window.AsmrVisualEngine(canvas) : null;
  let lastPayload = null;
  let currentPackage = null;

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
    [previewBtn, stopBtn, exportBtn, soundOnlyBtn].forEach((b) => b && (b.disabled = !currentPackage));
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

  if (form) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      lastPayload = Object.fromEntries(new FormData(form).entries());
      try { renderData(await requestGeneration(lastPayload)); }
      catch (err) { setError(err.message || 'Generation failed.'); setStatus(''); }
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
        const playback = await engine.preview(currentPackage);
        const fullMode = !modeToggle || modeToggle.value === 'audiovisual';
        if (visuals && fullMode) visuals.play(playback.startAt - engine.ctx.currentTime);
        if (audioFeedback) audioFeedback.textContent = fullMode ? 'Synchronized preview playing.' : 'Sound-only preview playing.';
      } catch (err) {
        if (audioFeedback) audioFeedback.textContent = 'Audio may be blocked until user interaction. Click preview again.';
      }
    });
  }

  if (stopBtn) {
    stopBtn.addEventListener('click', () => {
      engine.stop();
      if (visuals) visuals.stop();
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

  window.addEventListener('resize', function () { if (visuals) visuals.resize(); });
})();

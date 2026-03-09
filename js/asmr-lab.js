(function () {
  'use strict';

  const app = document.getElementById('asmr-lab-app');
  if (!app) return;
  if (!window.seAsmrLab) return;

  const form = document.getElementById('asmr-lab-form');
  const statusEl = document.getElementById('asmr-status');
  const errorEl = document.getElementById('asmr-error');
  const resultsEl = document.getElementById('asmr-results');
  const previewBtn = document.getElementById('asmr-preview');
  const stopBtn = document.getElementById('asmr-stop');
  const exportBtn = document.getElementById('asmr-export');
  const soundOnlyBtn = document.getElementById('asmr-sound-only');
  const audioFeedback = document.getElementById('asmr-audio-feedback');
  const copyPromptsBtn = document.getElementById('asmr-copy-prompts');

  const engine = new window.AsmrFoleyEngine();
  let lastPayload = null;
  let currentRecipe = null;

  function setStatus(message) {
    if (statusEl) statusEl.textContent = message || '';
  }

  function setError(message) {
    if (!errorEl) return;
    if (message) {
      errorEl.hidden = false;
      errorEl.textContent = message;
    } else {
      errorEl.hidden = true;
      errorEl.textContent = '';
    }
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

    if (concept) {
      concept.textContent = `${data.title} (${data.runtime_seconds}s) — ${data.hook}\n${data.concept_summary}`;
    }
    toList(beats, data.beat_sheet);
    toList(prompts, data.video_prompts);
    if (edit) edit.textContent = Array.isArray(data.edit_notes) ? data.edit_notes.join(' ') : (data.edit_notes || '');
    if (note) note.textContent = data.presentation_note || '';
    if (recipe) recipe.textContent = JSON.stringify(data.sound_recipe || {}, null, 2);

    currentRecipe = data.sound_recipe || null;
    if (resultsEl) resultsEl.hidden = false;
    [previewBtn, stopBtn, exportBtn, soundOnlyBtn].forEach((b) => b && (b.disabled = !currentRecipe));
  }

  async function requestGeneration(payload) {
    setError('');
    setStatus('Generating in the lab...');
    const response = await fetch(window.seAsmrLab.endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.seAsmrLab.nonce
      },
      body: JSON.stringify(payload)
    });

    let data;
    try {
      data = await response.json();
    } catch (e) {
      throw new Error('Unexpected response format from ASMR Lab service.');
    }

    if (!response.ok) {
      throw new Error((data && data.message) ? data.message : 'Unable to generate ASMR package.');
    }

    if (!data || typeof data !== 'object' || !data.sound_recipe) {
      throw new Error('Generated package was incomplete. Please try again.');
    }

    setStatus('Package generated. Ready to preview sound.');
    return data;
  }

  if (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const formData = new FormData(form);
      lastPayload = Object.fromEntries(formData.entries());
      try {
        const data = await requestGeneration(lastPayload);
        renderData(data);
      } catch (err) {
        setError(err.message || 'Generation failed.');
        setStatus('');
      }
    });
  }

  if (soundOnlyBtn) {
    soundOnlyBtn.addEventListener('click', async function () {
      if (!lastPayload) {
        setError('Generate a full package first, then regenerate sound.');
        return;
      }
      try {
        const payload = Object.assign({}, lastPayload, { sound_only: true });
        const data = await requestGeneration(payload);
        renderData(data);
      } catch (err) {
        setError(err.message || 'Sound regeneration failed.');
      }
    });
  }

  if (previewBtn) {
    previewBtn.addEventListener('click', async function () {
      if (!currentRecipe) return;
      try {
        await engine.preview(currentRecipe);
        if (audioFeedback) audioFeedback.textContent = 'Preview playing.';
      } catch (err) {
        if (audioFeedback) audioFeedback.textContent = 'Audio may be blocked until user interaction. Click preview again.';
      }
    });
  }

  if (stopBtn) {
    stopBtn.addEventListener('click', function () {
      engine.stop();
      if (audioFeedback) audioFeedback.textContent = 'Playback stopped.';
    });
  }

  if (exportBtn) {
    exportBtn.addEventListener('click', async function () {
      if (!currentRecipe) return;
      if (audioFeedback) audioFeedback.textContent = 'Rendering WAV...';
      try {
        const blob = await engine.exportWav(currentRecipe);
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

  if (copyPromptsBtn) {
    copyPromptsBtn.addEventListener('click', async function () {
      const promptItems = document.querySelectorAll('#asmr-video-prompts li');
      const text = Array.from(promptItems).map((li) => li.textContent).join('\n');
      try {
        await navigator.clipboard.writeText(text);
        setStatus('Video prompts copied.');
      } catch (e) {
        setError('Clipboard access unavailable.');
      }
    });
  }
})();

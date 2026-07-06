(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory(root, false);
  else factory(root, true);
})(typeof window !== 'undefined' ? window : globalThis, function (root, autoInit) {
  'use strict';

  function formatDuration(seconds) {
    const safe = Number.isFinite(seconds) && seconds > 0 ? seconds : 0;
    return `${safe.toFixed(1).padStart(4, '0')}s`;
  }

  function createLayerName(index) {
    return `Layer ${String(index + 1).padStart(2, '0')}`;
  }

  function initLoopLab(doc) {
    const documentRef = doc || root.document;
    if (!documentRef) return null;
    const app = documentRef.getElementById('loop-lab-app');
    if (!app || !root.MediaRecorder) return null;

    const machine = app.querySelector('.loop-lab-machine');
    const recordBtn = app.querySelector('[data-loop-record]');
    const stopBtn = app.querySelector('[data-loop-stop]');
    const resetBtn = app.querySelector('[data-loop-reset]');
    const stateEl = app.querySelector('[data-loop-state]');
    const clockEl = app.querySelector('[data-loop-clock]');
    const messageEl = app.querySelector('[data-loop-message]');
    const progressEl = app.querySelector('[data-loop-progress]');
    const layersEl = app.querySelector('[data-loop-layers]');
    const emptyEl = app.querySelector('[data-loop-empty]');

    let audioContext = null;
    let mediaStream = null;
    let recorder = null;
    let chunks = [];
    let layers = [];
    let sources = new Map();
    let loopDuration = 0;
    let loopStartedAt = 0;
    let recordingStartedAt = 0;
    let ticker = 0;
    let mode = 'ready';

    function setMessage(text) { messageEl.textContent = text; }
    function setMode(next) {
      mode = next;
      machine.classList.toggle('is-recording', next === 'recording');
      machine.classList.toggle('is-playing', next === 'playing' || next === 'build');
      const label = { ready: 'READY TO PLAY', recording: 'RECORDING', captured: 'LOOP CAPTURED', playing: 'PLAYING', build: 'BUILD' }[next] || 'READY TO PLAY';
      stateEl.textContent = label;
      recordBtn.textContent = layers.length ? 'add layer' : 'record first loop';
      recordBtn.disabled = next === 'recording';
      stopBtn.disabled = next !== 'recording';
      resetBtn.disabled = layers.length === 0 && next !== 'recording';
    }

    async function ensureAudio() {
      if (!audioContext) audioContext = new (root.AudioContext || root.webkitAudioContext)();
      if (audioContext.state === 'suspended') await audioContext.resume();
      if (!mediaStream) mediaStream = await root.navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: false, noiseSuppression: false, autoGainControl: false } });
    }

    function stopSources() {
      sources.forEach((source) => { try { source.stop(); } catch (error) {} });
      sources.clear();
    }

    function scheduleLayer(layer) {
      if (!audioContext || layer.muted) return;
      const source = audioContext.createBufferSource();
      const gain = audioContext.createGain();
      source.buffer = layer.buffer;
      source.loop = true;
      gain.gain.value = 0.95;
      source.connect(gain).connect(audioContext.destination);
      source.start(audioContext.currentTime);
      sources.set(layer.id, source);
    }

    function playLoop() {
      stopSources();
      loopStartedAt = audioContext ? audioContext.currentTime : 0;
      layers.forEach(scheduleLayer);
      setMode(layers.length > 1 ? 'build' : 'playing');
      tick();
    }

    function tick() {
      root.cancelAnimationFrame(ticker);
      const step = () => {
        const now = audioContext ? audioContext.currentTime : 0;
        if (mode === 'recording') clockEl.textContent = formatDuration((performance.now() - recordingStartedAt) / 1000);
        else clockEl.textContent = formatDuration(loopDuration);
        const progress = loopDuration > 0 ? (((now - loopStartedAt) % loopDuration) / loopDuration) * 100 : 0;
        progressEl.style.width = `${Math.max(0, Math.min(100, progress))}%`;
        ticker = root.requestAnimationFrame(step);
      };
      ticker = root.requestAnimationFrame(step);
    }

    function renderLayers() {
      emptyEl.hidden = layers.length > 0;
      layersEl.innerHTML = '';
      layers.forEach((layer, index) => {
        const item = documentRef.createElement('li');
        item.className = `loop-lab-layer${layer.muted ? ' is-muted' : ''}`;
        item.innerHTML = `<span class="loop-lab-layer__lamp" aria-hidden="true"></span><div><p class="loop-lab-layer__name">${createLayerName(index)}</p><p class="loop-lab-layer__meta">${formatDuration(layer.duration)} · ${layer.muted ? 'muted' : 'active'}</p></div><button type="button" data-action="mute">${layer.muted ? 'unmute' : 'mute'}</button><button type="button" data-action="delete">remove</button>`;
        item.querySelector('[data-action="mute"]').addEventListener('click', () => {
          layer.muted = !layer.muted;
          playLoop();
          renderLayers();
        });
        item.querySelector('[data-action="delete"]').addEventListener('click', () => {
          layers = layers.filter((candidate) => candidate.id !== layer.id);
          if (!layers.length) reset(); else { playLoop(); renderLayers(); }
        });
        layersEl.appendChild(item);
      });
    }

    async function startRecording() {
      try {
        await ensureAudio();
        chunks = [];
        recorder = new root.MediaRecorder(mediaStream);
        recorder.addEventListener('dataavailable', (event) => { if (event.data && event.data.size) chunks.push(event.data); });
        recorder.addEventListener('stop', finishRecording, { once: true });
        recordingStartedAt = performance.now();
        recorder.start();
        setMode('recording');
        setMessage(layers.length ? 'recording over the loop. headphones make this less haunted.' : 'recording. play the bit.');
        tick();
      } catch (error) {
        setMessage('mic refused. check browser permission, then hit record again.');
      }
    }

    async function finishRecording() {
      const blob = new Blob(chunks, { type: recorder && recorder.mimeType ? recorder.mimeType : 'audio/webm' });
      if (!blob.size) { setMode(layers.length ? 'playing' : 'ready'); return; }
      const arrayBuffer = await blob.arrayBuffer();
      const buffer = await audioContext.decodeAudioData(arrayBuffer.slice(0));
      const duration = layers.length ? loopDuration : buffer.duration;
      if (!layers.length) loopDuration = buffer.duration;
      layers.push({ id: `layer-${Date.now()}`, buffer, duration, muted: false });
      renderLayers();
      setMode('captured');
      setMessage(layers.length === 1 ? 'loop captured. it is already moving.' : 'layer caught. keep or kill it.');
      playLoop();
    }

    function stopRecording() { if (recorder && recorder.state === 'recording') recorder.stop(); }

    function reset() {
      if (recorder && recorder.state === 'recording') recorder.stop();
      stopSources();
      layers = [];
      loopDuration = 0;
      clockEl.textContent = formatDuration(0);
      progressEl.style.width = '0%';
      root.cancelAnimationFrame(ticker);
      renderLayers();
      setMode('ready');
      setMessage('clean tape. make sound into the mic.');
    }

    recordBtn.addEventListener('click', startRecording);
    stopBtn.addEventListener('click', stopRecording);
    resetBtn.addEventListener('click', reset);
    setMode('ready');
    renderLayers();
    return { formatDuration, createLayerName };
  }

  if (autoInit) root.addEventListener('DOMContentLoaded', () => initLoopLab(root.document));
  return { formatDuration, createLayerName, initLoopLab };
});

(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory(root, false);
  else root.LoopLab = factory(root, true);
})(typeof window !== 'undefined' ? window : globalThis, function (root, autoInit) {
  'use strict';

  const LAYER_GAIN = 0.95;

  function formatDuration(seconds) {
    const safe = Number.isFinite(seconds) && seconds > 0 ? seconds : 0;
    return `${safe.toFixed(1).padStart(4, '0')}s`;
  }

  function createLayerName(index) {
    return `Layer ${String(index + 1).padStart(2, '0')}`;
  }

  function hasAudibleLayers(layers) {
    return Array.isArray(layers) && layers.some((layer) => layer && !layer.muted);
  }

  // Recording and exporting lock the destructive and playback controls, so a take in
  // flight can never be wiped or restarted out from under itself.
  function resolveControlLocks(state) {
    const status = state || {};
    const layerCount = Number.isFinite(status.layerCount) ? status.layerCount : 0;
    const loopDuration = Number.isFinite(status.loopDuration) ? status.loopDuration : 0;
    const recording = status.mode === 'recording';
    const busy = recording || Boolean(status.exporting);
    return {
      record: busy,
      stop: !recording,
      restart: busy || layerCount === 0 || loopDuration <= 0,
      exportMix: busy || loopDuration <= 0 || !status.audible,
      clear: busy || layerCount === 0,
    };
  }

  function createMixFilename(date) {
    const stamp = date instanceof Date && !Number.isNaN(date.getTime()) ? date : new Date();
    const pad = (value) => String(value).padStart(2, '0');
    const day = `${stamp.getFullYear()}-${pad(stamp.getMonth() + 1)}-${pad(stamp.getDate())}`;
    return `loop-lab-mix-${day}-${pad(stamp.getHours())}${pad(stamp.getMinutes())}.wav`;
  }

  function writeAscii(view, offset, text) {
    for (let index = 0; index < text.length; index += 1) view.setUint8(offset + index, text.charCodeAt(index));
  }

  // 16-bit PCM WAV so a Loop Lab sketch opens straight in Logic, Ableton or Audacity.
  function encodeWav(channels, sampleRate) {
    const tracks = Array.isArray(channels) && channels.length ? channels : [[]];
    const channelCount = tracks.length;
    const frameCount = tracks[0] && tracks[0].length ? tracks[0].length : 0;
    const rate = Number.isFinite(sampleRate) && sampleRate > 0 ? Math.round(sampleRate) : 44100;
    const blockAlign = channelCount * 2;
    const dataBytes = frameCount * blockAlign;
    const buffer = new ArrayBuffer(44 + dataBytes);
    const view = new DataView(buffer);

    writeAscii(view, 0, 'RIFF');
    view.setUint32(4, 36 + dataBytes, true);
    writeAscii(view, 8, 'WAVE');
    writeAscii(view, 12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, channelCount, true);
    view.setUint32(24, rate, true);
    view.setUint32(28, rate * blockAlign, true);
    view.setUint16(32, blockAlign, true);
    view.setUint16(34, 16, true);
    writeAscii(view, 36, 'data');
    view.setUint32(40, dataBytes, true);

    let offset = 44;
    for (let frame = 0; frame < frameCount; frame += 1) {
      for (let channel = 0; channel < channelCount; channel += 1) {
        const track = tracks[channel];
        const sample = track && frame < track.length ? track[frame] : 0;
        const clamped = Math.max(-1, Math.min(1, Number.isFinite(sample) ? sample : 0));
        view.setInt16(offset, Math.round(clamped < 0 ? clamped * 0x8000 : clamped * 0x7fff), true);
        offset += 2;
      }
    }
    return buffer;
  }

  function audioBufferToWav(audioBuffer) {
    if (!audioBuffer) return encodeWav([[]], 44100);
    const channelCount = Math.max(1, audioBuffer.numberOfChannels || 1);
    const channels = [];
    for (let index = 0; index < channelCount; index += 1) channels.push(audioBuffer.getChannelData(index));
    return encodeWav(channels, audioBuffer.sampleRate);
  }

  function initLoopLab(doc) {
    const documentRef = doc || root.document;
    if (!documentRef) return null;
    const app = documentRef.getElementById('loop-lab-app');
    if (!app || !root.MediaRecorder) return null;

    const machine = app.querySelector('.loop-lab-machine');
    const recordBtn = app.querySelector('[data-loop-record]');
    const stopBtn = app.querySelector('[data-loop-stop]');
    const restartBtn = app.querySelector('[data-loop-restart]');
    const exportBtn = app.querySelector('[data-loop-export]');
    const clearBtn = app.querySelector('[data-loop-clear]');
    const clearDialog = app.querySelector('[data-loop-clear-dialog]');
    const clearConfirmBtn = app.querySelector('[data-loop-clear-confirm]');
    const clearCancelBtn = app.querySelector('[data-loop-clear-cancel]');
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
    let exporting = false;
    let tapeEpoch = 0;
    let dialogOpener = null;

    function setMessage(text) { messageEl.textContent = text; }

    function refreshControls() {
      const locks = resolveControlLocks({
        mode,
        exporting,
        layerCount: layers.length,
        loopDuration,
        audible: hasAudibleLayers(layers),
      });
      recordBtn.textContent = layers.length ? 'add layer' : 'record first loop';
      recordBtn.disabled = locks.record;
      stopBtn.disabled = locks.stop;
      if (restartBtn) restartBtn.disabled = locks.restart;
      if (exportBtn) exportBtn.disabled = locks.exportMix;
      if (clearBtn) clearBtn.disabled = locks.clear;
    }

    function setMode(next) {
      mode = next;
      machine.classList.toggle('is-recording', next === 'recording');
      machine.classList.toggle('is-playing', next === 'playing' || next === 'build');
      const label = { ready: 'READY TO PLAY', recording: 'RECORDING', captured: 'LOOP CAPTURED', playing: 'PLAYING', build: 'BUILD' }[next] || 'READY TO PLAY';
      stateEl.textContent = label;
      refreshControls();
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
      gain.gain.value = LAYER_GAIN;
      source.connect(gain).connect(audioContext.destination);
      source.start(audioContext.currentTime);
      sources.set(layer.id, source);
    }

    function playLoop() {
      stopSources();
      loopStartedAt = audioContext ? audioContext.currentTime : 0;
      progressEl.style.width = '0%';
      layers.forEach(scheduleLayer);
      setMode(layers.length > 1 ? 'build' : 'playing');
      tick();
    }

    function restartLoop() {
      if (mode === 'recording' || exporting || !layers.length) return;
      playLoop();
      setMessage(hasAudibleLayers(layers) ? 'back to the top. every layer intact.' : 'back to the top. every layer is muted, so it is a silent lap.');
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
          if (!layers.length) clearArrangement(); else { playLoop(); renderLayers(); }
        });
        layersEl.appendChild(item);
      });
      refreshControls();
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
      const epoch = tapeEpoch;
      try {
        const blob = new Blob(chunks, { type: recorder && recorder.mimeType ? recorder.mimeType : 'audio/webm' });
        if (!blob.size) { setMode(layers.length ? 'playing' : 'ready'); return; }
        const arrayBuffer = await blob.arrayBuffer();
        const buffer = await audioContext.decodeAudioData(arrayBuffer.slice(0));
        // The tape was wiped while this take was decoding, so it no longer belongs to anything.
        if (epoch !== tapeEpoch) return;
        const duration = layers.length ? loopDuration : buffer.duration;
        if (!layers.length) loopDuration = buffer.duration;
        layers.push({ id: `layer-${Date.now()}`, buffer, duration, muted: false });
        renderLayers();
        setMode('captured');
        setMessage(layers.length === 1 ? 'loop captured. it is already moving.' : 'layer caught. keep or kill it.');
        playLoop();
      } catch (error) {
        if (epoch !== tapeEpoch) return;
        setMode(layers.length ? 'playing' : 'ready');
        setMessage('that take did not decode. hit record and go again.');
      }
    }

    function stopRecording() { if (recorder && recorder.state === 'recording') recorder.stop(); }

    function downloadBlob(blob, filename) {
      const url = root.URL.createObjectURL(blob);
      const link = documentRef.createElement('a');
      link.href = url;
      link.download = filename;
      documentRef.body.appendChild(link);
      link.click();
      link.remove();
      root.setTimeout(() => root.URL.revokeObjectURL(url), 2000);
    }

    async function renderMix() {
      const audible = layers.filter((layer) => !layer.muted);
      const OfflineCtor = root.OfflineAudioContext || root.webkitOfflineAudioContext;
      const sampleRate = audioContext && audioContext.sampleRate ? audioContext.sampleRate : 44100;
      const channelCount = audible.reduce((max, layer) => Math.max(max, layer.buffer && layer.buffer.numberOfChannels ? layer.buffer.numberOfChannels : 1), 1);
      const frames = Math.max(1, Math.ceil(loopDuration * sampleRate));
      const offline = new OfflineCtor(channelCount, frames, sampleRate);
      audible.forEach((layer) => {
        const source = offline.createBufferSource();
        const gain = offline.createGain();
        source.buffer = layer.buffer;
        source.loop = true;
        gain.gain.value = LAYER_GAIN;
        source.connect(gain).connect(offline.destination);
        source.start(0);
      });
      return offline.startRendering();
    }

    async function exportMix() {
      if (exporting || mode === 'recording') return;
      if (!hasAudibleLayers(layers) || loopDuration <= 0) {
        setMessage('nothing audible on the tape. unmute a layer, then export.');
        return;
      }
      if (!(root.OfflineAudioContext || root.webkitOfflineAudioContext)) {
        setMessage('this browser will not render offline audio. try chrome or firefox.');
        return;
      }
      exporting = true;
      refreshControls();
      setMessage('printing the tape...');
      try {
        const rendered = await renderMix();
        downloadBlob(new Blob([audioBufferToWav(rendered)], { type: 'audio/wav' }), createMixFilename(new Date()));
        setMessage('mix exported. tape escaped.');
      } catch (error) {
        setMessage('export jammed. the loop is fine, try again.');
      } finally {
        exporting = false;
        refreshControls();
      }
    }

    // A native <dialog> restores focus to whatever was focused when showModal() ran,
    // so our own target has to land after that restoration rather than before it.
    function restoreFocus(target) {
      if (!target || typeof target.focus !== 'function') return;
      root.setTimeout(() => { if (!target.disabled) target.focus(); }, 0);
    }

    function closeClearDialog() {
      if (!clearDialog) return;
      if (typeof clearDialog.close === 'function' && clearDialog.open) clearDialog.close();
      else clearDialog.removeAttribute('open');
      clearDialog.classList.remove('is-fallback');
      documentRef.removeEventListener('keydown', handleFallbackKeydown);
      restoreFocus(dialogOpener);
      dialogOpener = null;
    }

    function handleFallbackKeydown(event) {
      if (event.key === 'Escape') { event.preventDefault(); closeClearDialog(); }
    }

    function openClearDialog() {
      if (mode === 'recording' || exporting || !layers.length) return;
      if (!clearDialog) {
        if (root.confirm('clear the tape? this removes every recorded layer.')) clearArrangement();
        return;
      }
      dialogOpener = clearBtn || documentRef.activeElement;
      if (typeof clearDialog.showModal === 'function') {
        clearDialog.showModal();
      } else {
        clearDialog.classList.add('is-fallback');
        clearDialog.setAttribute('open', '');
        documentRef.addEventListener('keydown', handleFallbackKeydown);
      }
      if (clearCancelBtn) clearCancelBtn.focus();
    }

    function clearArrangement() {
      tapeEpoch += 1;
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

    function confirmClear() {
      closeClearDialog();
      clearArrangement();
      // Clear Tape disables itself once the tape is empty, so send focus to the only move left.
      restoreFocus(recordBtn);
    }

    recordBtn.addEventListener('click', startRecording);
    stopBtn.addEventListener('click', stopRecording);
    if (restartBtn) restartBtn.addEventListener('click', restartLoop);
    if (exportBtn) exportBtn.addEventListener('click', exportMix);
    if (clearBtn) clearBtn.addEventListener('click', openClearDialog);
    if (clearCancelBtn) clearCancelBtn.addEventListener('click', closeClearDialog);
    if (clearConfirmBtn) clearConfirmBtn.addEventListener('click', confirmClear);
    if (clearDialog) {
      clearDialog.addEventListener('cancel', (event) => { event.preventDefault(); closeClearDialog(); });
      clearDialog.addEventListener('click', (event) => { if (event.target === clearDialog) closeClearDialog(); });
    }
    setMode('ready');
    renderLayers();
    return { formatDuration, createLayerName, hasAudibleLayers, resolveControlLocks, createMixFilename, encodeWav, audioBufferToWav, restartLoop, exportMix, clearArrangement, openClearDialog, closeClearDialog };
  }

  if (autoInit) root.addEventListener('DOMContentLoaded', () => initLoopLab(root.document));
  return { formatDuration, createLayerName, hasAudibleLayers, resolveControlLocks, createMixFilename, encodeWav, audioBufferToWav, initLoopLab };
});

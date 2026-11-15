(() => {
  'use strict';
  const core = window.MosaicCore;
  const effects = window.MosaicEffects;
  if (!core || !effects) return;

  const el = (id) => document.getElementById(id);
  const ui = {
    grid: el('performance-grid'), gridHint: el('grid-hint'), countdown: el('countdown-overlay'),
    countdownValue: el('countdown-value'), play: el('play-btn'), stop: el('stop-btn'),
    clock: el('clock'), durationLabel: el('duration-label'), progress: el('progress-bar'),
    progressRegion: document.querySelector('.progress'), duration: el('duration-select'),
    bpm: el('bpm-input'), bpmValue: el('bpm-value'), clicks: el('metronome-input'),
    camera: el('camera-input'), selectedNumber: el('selected-track-number'),
    selectedDescription: el('selected-track-description'), record: el('record-btn'),
    cancel: el('cancel-btn'), levelSection: el('level-section'), level: el('level-input'),
    levelOutput: el('level-output'), mute: el('mute-btn'), remove: el('remove-btn'),
    downloadTile: el('download-tile-btn'), export: el('export-btn'), clear: el('clear-btn'),
    status: el('status'), layout: [...document.querySelectorAll('[data-grid]')],
    fxPreview: el('fx-preview'), fxPreset: el('fx-preset'), fxStrength: el('fx-strength'),
    fxStrengthValue: el('fx-strength-value'), share: el('share-btn'),
    caption: el('share-caption'), copyCaption: el('copy-caption-btn'),
  };
  const clips = new Map();
  const mediaNodes = new Map();
  const videoElements = new Map();
  let gridSize = 4;
  let selected = 0;
  let targetDuration = 15;
  let sessionDuration = 0;
  let db = null;
  let audioContext = null;
  let playbackStarted = 0;
  let playbackFrame = 0;
  let playbackMode = 'stopped';
  let activeStream = null;
  let activeRecorder = null;
  let takeStartedAt = 0;
  let takeStoppedAt = 0;
  let busy = false;
  let cancelTake = false;
  let exportDestination = null;
  let fxPreset = 'clean';
  let fxStrength = 65;
  let lastExport = null;
  let scratchCanvas = null;
  let scratchContext = null;
  const previewContext = ui.fxPreview.getContext('2d', { alpha: false });

  const status = (message, error = false) => {
    ui.status.textContent = message;
    ui.status.classList.toggle('error', error);
  };
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
  const supported = type => typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(type);
  const recMime = hasVideo => core.chooseMime(hasVideo
    ? ['video/webm;codecs=vp8,opus','video/webm;codecs=vp9,opus','video/mp4','video/webm']
    : ['audio/webm;codecs=opus','audio/mp4','audio/webm'], supported);
  const dateName = () => new Date().toISOString().slice(0, 10);
  const extFor = mime => /mp4|m4a/.test(mime) ? 'mp4' : /ogg/.test(mime) ? 'ogg' : 'webm';
  const anyClips = () => clips.size > 0;

  function openDatabase() {
    if (!('indexedDB' in window)) return Promise.resolve(null);
    return new Promise(resolve => {
      try {
        const request = indexedDB.open('mosaic-studio-v1', 1);
        request.onupgradeneeded = () => {
          if (!request.result.objectStoreNames.contains('clips')) request.result.createObjectStore('clips', { keyPath: 'index' });
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => resolve(null);
        request.onblocked = () => resolve(null);
      } catch { resolve(null); }
    });
  }
  function dbWrite(kind, value) {
    if (!db) return Promise.resolve();
    return new Promise((resolve, reject) => {
      try {
        const tx = db.transaction('clips', 'readwrite');
        const store = tx.objectStore('clips');
        if (kind === 'put') store.put(value);
        if (kind === 'delete') store.delete(value);
        if (kind === 'clear') store.clear();
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
      } catch (error) { reject(error); }
    });
  }
  function loadDatabase() {
    if (!db) return Promise.resolve([]);
    return new Promise(resolve => {
      try {
        const request = db.transaction('clips', 'readonly').objectStore('clips').getAll();
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => resolve([]);
      } catch { resolve([]); }
    });
  }
  function savePreferences() {
    try { localStorage.setItem('mosaic-studio-prefs', JSON.stringify({ gridSize, targetDuration, bpm: Number(ui.bpm.value), camera: ui.camera.checked, clicks: ui.clicks.checked, fxPreset, fxStrength })); } catch { /* private browsing */ }
  }
  function restorePreferences() {
    try {
      const prefs = JSON.parse(localStorage.getItem('mosaic-studio-prefs') || '{}');
      gridSize = prefs.gridSize === 9 ? 9 : 4;
      const savedTarget = Number(prefs.targetDuration ?? prefs.duration);
      targetDuration = core.DURATIONS.includes(savedTarget) ? savedTarget : 15;
      ui.bpm.value = core.clamp(prefs.bpm || 120, 60, 200);
      ui.camera.checked = prefs.camera !== false;
      ui.clicks.checked = prefs.clicks !== false;
      fxPreset = effects.preset(prefs.fxPreset);
      fxStrength = effects.strength(prefs.fxStrength ?? 65);
    } catch { /* use defaults */ }
  }
  function setTarget(value) {
    targetDuration = core.DURATIONS.includes(Number(value)) ? Number(value) : 15;
    ui.duration.value = String(targetDuration);
    updateClock(playbackMode === 'recording' ? (performance.now() - takeStartedAt) / 1000 : 0);
    savePreferences();
  }
  function refreshSessionDuration() {
    sessionDuration = core.sessionSeconds(clips.values());
  }
  function releaseClip(clip) {
    if (clip && clip.url) URL.revokeObjectURL(clip.url);
  }
  function installClip(record) {
    if (!record || !record.blob || !Number.isInteger(record.index) || record.index < 0 || record.index > 8) return;
    const prior = clips.get(record.index);
    releaseClip(prior);
    clips.set(record.index, {
      index: record.index, blob: record.blob, audioOnly: !!record.audioOnly,
      muted: !!record.muted, volume: core.clamp(record.volume == null ? 1 : record.volume, 0, 1.5),
      seconds: Number.isFinite(record.seconds) && record.seconds > 0 ? record.seconds : legacySeconds(),
      url: URL.createObjectURL(record.blob),
    });
  }
  function legacySeconds() {
    // v0.1 stored fixed-length takes without per-clip metadata. Preserve those
    // old sessions using their original duration instead of the new guide.
    try {
      const value = Number(localStorage.getItem('mosaic-studio-fixed-duration'));
      return value > 0 && Number.isFinite(value) ? value : 15;
    } catch { return 15; }
  }
  async function saveClip(clip) {
    try {
      await dbWrite('put', {
        index: clip.index, blob: clip.blob, audioOnly: clip.audioOnly,
        muted: clip.muted, volume: clip.volume, seconds: clip.seconds,
      });
    } catch {
      status('Take recorded, but browser storage is full or unavailable. Download it before closing this tab.', true);
    }
  }
  function releaseMediaNodes() {
    for (const node of mediaNodes.values()) {
      try { node.source.disconnect(); node.gain.disconnect(); } catch { /* disconnected */ }
    }
    mediaNodes.clear();
    videoElements.clear();
  }
  function renderGrid() {
    stopPlayback();
    releaseMediaNodes();
    ui.grid.replaceChildren();
    ui.grid.className = `performance-grid cols-${core.columnsFor(gridSize)}`;
    for (let i = 0; i < gridSize; i++) {
      const clip = clips.get(i);
      const tile = document.createElement('button');
      tile.type = 'button';
      tile.className = `tile${clip && clip.muted ? ' is-muted' : ''}${activeStream && selected === i ? ' is-recording' : ''}`;
      tile.dataset.index = String(i);
      tile.setAttribute('aria-pressed', String(selected === i));
      tile.setAttribute('aria-label', `Select tile ${i + 1}${clip ? ', recorded' : ', empty'}${clip && clip.muted ? ', muted' : ''}`);
      const number = document.createElement('span');
      number.className = 'tile-number';
      number.textContent = `${String(i + 1).padStart(2, '0')} / ${String(gridSize).padStart(2, '0')}`;
      tile.append(number);
      if ((activeStream && selected === i) || clip) {
        const video = document.createElement('video');
        video.setAttribute('playsinline', '');
        video.playsInline = true;
        video.preload = 'auto';
        video.autoplay = false;
        if (activeStream && selected === i) {
          video.srcObject = activeStream;
          video.muted = true;
          video.autoplay = true;
          video.play().catch(() => {});
        } else {
          video.src = clip.url;
          video.loop = true;
          video.muted = false; // Audio routed only through Web Audio after createMediaElementSource.
          videoElements.set(i, video);
        }
        tile.append(video);
        if (clip && clip.audioOnly && !activeStream) {
          const art = document.createElement('span');
          art.className = 'empty-art'; art.textContent = '♫'; tile.append(art);
        }
      } else {
        const art = document.createElement('span');
        art.className = 'empty-art'; art.textContent = '+'; tile.append(art);
      }
      const bottom = document.createElement('span'); bottom.className = 'tile-bottom';
      const name = document.createElement('span'); name.textContent = clip ? (clip.audioOnly ? 'AUDIO TAKE' : 'VIDEO TAKE') : 'EMPTY / +';
      const icon = document.createElement('span'); icon.className = 'tile-icon'; icon.textContent = clip ? (clip.muted ? 'MUTED' : '●') : (selected === i ? 'SELECTED' : '');
      bottom.append(name, icon); tile.append(number, bottom);
      tile.addEventListener('click', () => selectTile(i));
      ui.grid.append(tile);
    }
    updateUI();
  }
  function selectTile(i) {
    if (busy) return;
    selected = i;
    for (const tile of ui.grid.querySelectorAll('.tile')) tile.setAttribute('aria-pressed', String(Number(tile.dataset.index) === selected));
    updateUI();
  }
  function updateUI() {
    const selectedClip = clips.get(selected);
    ui.selectedNumber.textContent = String(selected + 1).padStart(2, '0');
    ui.selectedDescription.textContent = selectedClip
      ? 'Take recorded. You can replace, mute, mix, or download it.'
      : 'Ready for its first performance.';
    const recording = busy && activeRecorder?.state === 'recording';
    ui.record.disabled = (busy && !recording) || typeof MediaRecorder === 'undefined' || !navigator.mediaDevices?.getUserMedia;
    ui.record.textContent = recording ? '■ STOP & KEEP TAKE' : busy ? 'PREPARING…' : (selectedClip ? '● RE-RECORD THIS TILE' : '● RECORD THIS TILE');
    ui.record.classList.toggle('is-stop', !!recording);
    ui.cancel.hidden = !busy || playbackMode === 'exporting';
    ui.cancel.disabled = busy && !!activeRecorder && activeRecorder.state !== 'recording';
    ui.play.disabled = busy || !anyClips() || playbackMode === 'playing';
    ui.stop.disabled = busy || playbackMode !== 'playing';
    ui.export.disabled = busy || !anyClips();
    ui.share.disabled = busy || !lastExport;
    ui.clear.disabled = busy || !anyClips();
    ui.downloadTile.disabled = busy || !selectedClip;
    ui.mute.disabled = busy || !selectedClip;
    ui.mute.textContent = selectedClip && selectedClip.muted ? 'Unmute tile' : 'Mute tile';
    ui.remove.disabled = busy || !selectedClip;
    ui.levelSection.hidden = !selectedClip;
    ui.level.value = String(Math.round((selectedClip?.volume || 0) * 100));
    ui.levelOutput.textContent = `${ui.level.value}%`;
    ui.duration.disabled = busy;
    ui.camera.disabled = busy;
    ui.bpm.disabled = busy;
    ui.clicks.disabled = busy;
    for (const btn of ui.layout) {
      btn.disabled = busy;
      btn.classList.toggle('is-active', Number(btn.dataset.grid) === gridSize);
      btn.setAttribute('aria-pressed', String(Number(btn.dataset.grid) === gridSize));
    }
    ui.bpmValue.textContent = `${ui.bpm.value} BPM`;
    ui.fxPreset.disabled = busy;
    ui.fxStrength.disabled = busy;
    ui.fxStrengthValue.textContent = `${fxStrength}%`;
  }
  async function ensureAudioContext() {
    if (!audioContext) audioContext = new (window.AudioContext || window.webkitAudioContext)();
    if (audioContext.state === 'suspended') await audioContext.resume();
    return audioContext;
  }
  function ensureMediaNode(i, video) {
    let node = mediaNodes.get(i);
    if (node && node.video === video) return node;
    const source = audioContext.createMediaElementSource(video);
    const gain = audioContext.createGain();
    source.connect(gain);
    gain.connect(audioContext.destination);
    if (exportDestination) gain.connect(exportDestination);
    node = { source, gain, video };
    mediaNodes.set(i, node);
    return node;
  }
  function applyTrackGain(i) {
    const clip = clips.get(i);
    const node = mediaNodes.get(i);
    if (clip && node) node.gain.gain.value = clip.muted ? 0 : clip.volume;
  }
  function updateClock(seconds) {
    ui.clock.textContent = core.formatTime(seconds);
    const recording = playbackMode === 'recording';
    const total = recording ? targetDuration : sessionDuration;
    const percent = recording ? core.targetProgress(seconds, targetDuration)
      : core.targetProgress(seconds, total);
    ui.durationLabel.textContent = recording
      ? (targetDuration ? `/ TARGET ${core.formatTime(targetDuration)}` : '/ FREE TAKE')
      : (sessionDuration ? `/ ${core.formatTime(sessionDuration)}` :
        (targetDuration ? `/ TARGET ${core.formatTime(targetDuration)}` : '/ FREE TAKE'));
    ui.progress.style.width = `${percent}%`;
    ui.progressRegion.setAttribute('aria-valuenow', String(Math.round(percent)));
    ui.progressRegion.setAttribute('aria-label', recording ? 'Take progress against optional planning target' : 'Session playback position');
  }
  function stopPlayback() {
    cancelAnimationFrame(playbackFrame);
    playbackFrame = 0;
    playbackMode = 'stopped';
    for (const video of videoElements.values()) {
      video.pause();
      try { video.currentTime = 0; } catch { /* metadata not yet ready */ }
    }
    ui.fxPreview.hidden = true;
    updateClock(0);
    updateUI();
  }
  function monitorPlayback() {
    const step = () => {
      if (playbackMode === 'stopped') return;
      const elapsed = (performance.now() - playbackStarted) / 1000;
      const position = core.expectedPosition(elapsed, sessionDuration);
      updateClock(playbackMode === 'recording' ? (performance.now() - takeStartedAt) / 1000 : position);
      if (playbackMode === 'playing' && previewContext) {
        ui.fxPreview.hidden = false;
        renderFinalFrame(previewContext, 720, 720, elapsed);
      }
      // Media elements decode independently. Pull obvious stragglers back toward the shared timeline.
      if (Math.floor(elapsed * 2) !== Math.floor((elapsed - 0.05) * 2)) {
        for (const [index, video] of videoElements) {
          const clipSeconds = clips.get(index)?.seconds || sessionDuration;
          if (!video.paused && video.readyState >= 1 && clipSeconds > 0) {
            const clipPosition = core.expectedPosition(elapsed, clipSeconds);
            const diff = Math.abs(video.currentTime - clipPosition);
            if (Math.min(diff, Math.abs(clipSeconds - diff)) > 0.22) {
              try { video.currentTime = clipPosition; } catch { /* seek not yet allowed */ }
            }
          }
        }
      }
      playbackFrame = requestAnimationFrame(step);
    };
    playbackFrame = requestAnimationFrame(step);
  }
  async function startPlayback(mode = 'playing') {
    if (!anyClips()) return;
    stopPlayback();
    await ensureAudioContext();
    for (const [i, video] of videoElements) {
      ensureMediaNode(i, video);
      applyTrackGain(i);
      video.pause();
      if (video.readyState >= 1) video.currentTime = 0;
    }
    const videos = [...videoElements.values()];
    const results = await Promise.allSettled(videos.map(video => video.play()));
    if (results.length && results.every(result => result.status === 'rejected')) throw new Error('Browser blocked playback. Click Play All, and check sound permissions.');
    playbackStarted = performance.now();
    playbackMode = mode;
    ui.fxPreview.hidden = mode !== 'playing';
    monitorPlayback();
    updateUI();
  }
  function beatClick(beat) {
    if (!ui.clicks.checked || !audioContext) return;
    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();
    oscillator.type = 'sine';
    oscillator.frequency.value = beat === 4 ? 880 : 660;
    const now = audioContext.currentTime;
    gain.gain.setValueAtTime(0.001, now);
    gain.gain.exponentialRampToValueAtTime(0.16, now + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.085);
    oscillator.connect(gain).connect(audioContext.destination);
    oscillator.start(now);
    oscillator.stop(now + 0.09);
  }
  async function countIn() {
    if (!ui.clicks.checked) return;
    ui.countdown.hidden = false;
    const beatDuration = 60000 / Number(ui.bpm.value);
    for (let beat = 4; beat >= 1; beat--) {
      if (cancelTake) break;
      ui.countdownValue.textContent = String(beat);
      beatClick(beat);
      await sleep(beatDuration);
    }
    ui.countdown.hidden = true;
  }
  function stopInput() {
    if (activeStream) for (const track of activeStream.getTracks()) track.stop();
    activeStream = null;
  }
  async function acquireInput() {
    const audio = { echoCancellation: false, noiseSuppression: false, autoGainControl: false };
    if (!ui.camera.checked) return { stream: await navigator.mediaDevices.getUserMedia({ audio }), audioOnly: true };
    try {
      return { stream: await navigator.mediaDevices.getUserMedia({ audio, video: { width: { ideal: 720 }, height: { ideal: 720 }, facingMode: 'user' } }), audioOnly: false };
    } catch (error) {
      if (error && ['NotAllowedError','SecurityError'].includes(error.name)) throw error;
      const stream = await navigator.mediaDevices.getUserMedia({ audio });
      status('Camera was unavailable. Recording this tile as audio only.');
      return { stream, audioOnly: true };
    }
  }
  async function startTake() {
    if (busy) return;
    if (typeof MediaRecorder === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
      status('Recording requires a modern browser on HTTPS or localhost. Try Chrome or Edge.', true);
      return;
    }
    stopPlayback();
    lastExport = null;
    busy = true;
    cancelTake = false;
    updateUI();
    let stream;
    let selectedAtStart = selected;
    try {
      await ensureAudioContext();
      const input = await acquireInput();
      stream = input.stream;
      activeStream = stream;
      renderGrid();
      status('Camera and mic are live only for this take. Starting count-in…');
      await countIn();
      if (cancelTake) { status('Take discarded.'); return; }
      const mime = recMime(!input.audioOnly);
      activeRecorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
      const chunks = [];
      activeRecorder.ondataavailable = event => { if (event.data?.size) chunks.push(event.data); };
      const stopped = new Promise((resolve, reject) => {
        activeRecorder.onstop = resolve;
        activeRecorder.onerror = e => reject(e.error || new Error('Recorder failed'));
      });
      // Start the new recorder and the old-take monitoring on the same recording boundary.
      activeRecorder.start(250);
      takeStartedAt = performance.now();
      takeStoppedAt = 0;
      if (anyClips()) {
        try { await startPlayback('recording'); }
        catch (error) { status(`Recording started, but monitoring failed: ${error.message}`, true); }
      } else {
        playbackMode = 'recording';
        playbackStarted = performance.now();
        monitorPlayback();
      }
      updateUI();
      status(`Recording tile ${selectedAtStart + 1}. ${targetDuration ? `${targetDuration}s is a guide only. ` : ''}Press Stop & Keep Take whenever you're done.`);
      await stopped;
      const wasCanceled = cancelTake;
      const blob = new Blob(chunks, { type: activeRecorder.mimeType || mime || (input.audioOnly ? 'audio/webm' : 'video/webm') });
      stopPlayback();
      if (!wasCanceled && blob.size > 0) {
        const clip = {
          index: selectedAtStart, blob, audioOnly: input.audioOnly, muted: false, volume: 1,
          seconds: Math.max(0.1, ((takeStoppedAt || performance.now()) - takeStartedAt) / 1000),
        };
        installClip(clip);
        refreshSessionDuration();
        await saveClip(clip);
        status(`Tile ${selectedAtStart + 1} captured (${core.formatTime(clip.seconds)}). Choose another tile to overdub, or Play All.`);
      } else {
        status(wasCanceled ? 'Take discarded. Previous tile preserved.' : 'The recorder returned an empty take. Try again.', !wasCanceled);
      }
    } catch (error) {
      status(`Could not record: ${error.message || error.name || 'permission failed'}. Check browser permissions and try again.`, true);
    } finally {
      stopInput();
      if (stream) for (const track of stream.getTracks()) track.stop();
      activeRecorder = null;
      busy = false;
      ui.countdown.hidden = true;
      renderGrid();
    }
  }
  function stopTake(keep) {
    if (!busy) return;
    if (!keep) cancelTake = true;
    if (activeRecorder?.state === 'recording') {
      takeStoppedAt = performance.now();
      activeRecorder.stop();
      updateUI();
      status(keep ? 'Finishing take…' : 'Discarding take…');
    } else if (!keep && activeStream) {
      stopInput();
    }
  }
  function cancelRecording() { stopTake(false); }
  function recordOrStop() {
    if (activeRecorder?.state === 'recording') stopTake(true);
    else if (!busy) startTake();
  }
  function downloadBlob(blob, name) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = name;
    document.body.appendChild(a);
    a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 30000);
  }
  function exportFrame(ctx, width, height) {
    const count = core.columnsFor(gridSize);
    const cell = width / count;
    ctx.fillStyle = '#10121b'; ctx.fillRect(0, 0, width, height);
    for (let i = 0; i < gridSize; i++) {
      const x = (i % count) * cell;
      const y = Math.floor(i / count) * cell;
      const pad = 4;
      const clip = clips.get(i);
      ctx.fillStyle = '#252639'; ctx.fillRect(x + pad, y + pad, cell - pad * 2, cell - pad * 2);
      const video = videoElements.get(i);
      if (video && video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0) {
        const sourceW = video.videoWidth, sourceH = video.videoHeight;
        const square = Math.min(sourceW, sourceH);
        const cropX = (sourceW - square) / 2, cropY = (sourceH - square) / 2;
        try { ctx.drawImage(video, cropX, cropY, square, square, x + pad, y + pad, cell - pad * 2, cell - pad * 2); } catch { /* frame unavailable */ }
      } else if (clip && clip.audioOnly) {
        ctx.font = `bold ${Math.round(cell * .3)}px sans-serif`;
        ctx.fillStyle = '#baa6ff';
        ctx.fillText('♫', x + cell * .38, y + cell * .6);
      }
      ctx.fillStyle = '#10121bc9';
      ctx.fillRect(x + pad, y + cell - 32, cell - pad * 2, 28);
      ctx.font = 'bold 14px monospace';
      ctx.fillStyle = clip?.muted ? '#fa9eb5' : '#eee8fb';
      ctx.fillText(`TAKE ${String(i + 1).padStart(2, '0')}${clip?.muted ? ' / MUTED' : ''}`, x + 14, y + cell - 13);
    }
  }
  // Render once into a clean square, then run the same FX engine for preview and export.
  // Recording media itself is never filtered or modified.
  function renderFinalFrame(ctx, width, height, elapsed) {
    if (!scratchCanvas) {
      scratchCanvas = document.createElement('canvas');
      scratchContext = scratchCanvas.getContext('2d', { alpha: false });
    }
    if (!scratchContext) throw new Error('Video effect rendering unavailable.');
    if (scratchCanvas.width !== width || scratchCanvas.height !== height) {
      scratchCanvas.width = width;
      scratchCanvas.height = height;
    }
    exportFrame(scratchContext, width, height);
    effects.draw(ctx, scratchCanvas, { preset: fxPreset, strength: fxStrength, seconds: elapsed });
  }
  function invalidateExport() {
    lastExport = null;
    updateUI();
  }
  async function shareVideo() {
    if (busy || !lastExport) return;
    const { blob, name } = lastExport;
    const caption = ui.caption.value.trim();
    const file = new File([blob], name, { type: blob.type || 'video/webm' });
    // Native file share requires a real user gesture and can vary by browser/platform.
    if (navigator.share && navigator.canShare?.({ files: [file] })) {
      try {
        await navigator.share({ files: [file], title: 'MOSAIC performance', text: caption });
        status('Shared through your device. Your video was not uploaded by MOSAIC.');
      } catch (error) {
        if (error?.name === 'AbortError') status('Share cancelled. Your exported video is still available.');
        else status('That share destination could not accept the video. Download and upload it manually.', true);
      }
      return;
    }
    downloadBlob(blob, name);
    status('Your browser cannot share video files directly. The video was downloaded; attach it to your social post and use Copy Caption.');
  }
  async function copyCaption() {
    const text = ui.caption.value.trim();
    if (!text) { status('Write a caption first.'); return; }
    try {
      await navigator.clipboard.writeText(text);
      status('Caption copied. Paste it into your social post. ♡');
    } catch {
      ui.caption.focus();
      ui.caption.select();
      status('Clipboard permission denied. Caption selected; copy it manually.', true);
    }
  }
  async function exportVideo() {
    if (busy || !anyClips()) return;
    if (!HTMLCanvasElement.prototype.captureStream || typeof MediaRecorder === 'undefined') {
      status('Composite export is unavailable in this browser. You can still download each tile.', true); return;
    }
    busy = true;
    updateUI();
    let outputStream, canvasStream, rec;
    let drawing = false, drawId = 0;
    try {
      stopPlayback();
      await ensureAudioContext();
      const canvas = document.createElement('canvas');
      canvas.width = 720; canvas.height = 720;
      const ctx = canvas.getContext('2d', { alpha: false });
      if (!ctx) throw new Error('Canvas unavailable.');
      exportDestination = audioContext.createMediaStreamDestination();
      for (const [index, video] of videoElements) {
        const node = ensureMediaNode(index, video);
        node.gain.connect(exportDestination);
        applyTrackGain(index);
      }
      drawing = true;
      const draw = () => { if (!drawing) return; renderFinalFrame(ctx, 720, 720, performance.now() / 1000); drawId = requestAnimationFrame(draw); };
      draw();
      canvasStream = canvas.captureStream(30);
      outputStream = new MediaStream([...canvasStream.getVideoTracks(), ...exportDestination.stream.getAudioTracks()]);
      const mime = recMime(true);
      rec = mime ? new MediaRecorder(outputStream, { mimeType: mime, videoBitsPerSecond: 3500000 }) : new MediaRecorder(outputStream);
      const chunks = [];
      rec.ondataavailable = event => { if (event.data?.size) chunks.push(event.data); };
      const stopped = new Promise((resolve, reject) => { rec.onstop = resolve; rec.onerror = e => reject(e.error || new Error('Export recorder failed')); });
      rec.start(250);
      await startPlayback('exporting');
      status(`Printing ${Math.ceil(sessionDuration)}s of video and audio in real time. Keep this tab active…`);
      await sleep(sessionDuration * 1000);
      if (rec.state === 'recording') rec.stop();
      await stopped;
      drawing = false; cancelAnimationFrame(drawId);
      if (!chunks.some(chunk => chunk.size)) throw new Error('Export file was empty.');
      const type = rec.mimeType || mime || 'video/webm';
      const blob = new Blob(chunks, { type });
      const name = `mosaic-${effects.preset(fxPreset)}-${dateName()}.${extFor(type)}`;
      lastExport = { blob, name };
      downloadBlob(blob, name);
      status('Your effected video was saved locally. Use Share Exported Video or Copy Caption to post it. ♡');
    } catch (error) {
      if (rec?.state === 'recording') rec.stop();
      status(`Export failed: ${error.message || 'unsupported browser'}. Individual tile downloads still work.`, true);
    } finally {
      drawing = false; cancelAnimationFrame(drawId);
      stopPlayback();
      for (const node of mediaNodes.values()) {
        if (exportDestination) try { node.gain.disconnect(exportDestination); } catch { /* noop */ }
      }
      if (outputStream) for (const track of outputStream.getTracks()) track.stop();
      if (canvasStream) for (const track of canvasStream.getTracks()) track.stop();
      exportDestination = null;
      busy = false;
      updateUI();
    }
  }
  async function removeTile() {
    if (busy || !clips.has(selected) || !confirm(`Remove tile ${selected + 1}? You can't undo this.`)) return;
    stopPlayback();
    invalidateExport();
    releaseClip(clips.get(selected)); clips.delete(selected);
    refreshSessionDuration();
    try { await dbWrite('delete', selected); } catch { status('Tile removed in memory, but storage update failed.', true); }
    renderGrid();
    status(`Tile ${selected + 1} removed.`);
  }
  async function clearSession() {
    if (busy || !anyClips() || !confirm('Clear every recorded tile? This cannot be undone.')) return;
    stopPlayback();
    invalidateExport();
    for (const clip of clips.values()) releaseClip(clip);
    clips.clear();
    refreshSessionDuration();
    try { await dbWrite('clear'); } catch { status('Session cleared in memory. Browser storage may still contain old clips.', true); }
    renderGrid();
    status('Clean slate. Your next arrangement starts here.');
  }
  async function toggleMute() {
    const clip = clips.get(selected);
    if (busy || !clip) return;
    invalidateExport();
    clip.muted = !clip.muted;
    applyTrackGain(selected);
    await saveClip(clip);
    renderGrid();
  }
  function changeGrid(value) {
    if (busy) return;
    const count = Number(value);
    if (!core.canChangeGrid(count, [...clips.keys()])) {
      status('Tiles 5–9 contain recordings. Remove them before switching to a 2 × 2 grid.', true);
      return;
    }
    invalidateExport();
    gridSize = count;
    if (selected >= count) selected = count - 1;
    savePreferences();
    renderGrid();
  }
  function downloadSelected() {
    const clip = clips.get(selected);
    if (busy || !clip) return;
    downloadBlob(clip.blob, `mosaic-take-${selected + 1}-${dateName()}.${extFor(clip.blob.type)}`);
  }
  ui.layout.forEach(btn => btn.addEventListener('click', () => changeGrid(btn.dataset.grid)));
  ui.duration.addEventListener('change', () => { if (!busy) setTarget(ui.duration.value); });
  ui.bpm.addEventListener('input', () => { ui.bpmValue.textContent = `${ui.bpm.value} BPM`; savePreferences(); });
  ui.camera.addEventListener('change', savePreferences);
  ui.clicks.addEventListener('change', savePreferences);
  ui.record.addEventListener('click', recordOrStop);
  ui.cancel.addEventListener('click', cancelRecording);
  ui.play.addEventListener('click', () => startPlayback().then(() => status('Playback running. The tiles are your rhythm section.')).catch(err => status(err.message, true)));
  ui.stop.addEventListener('click', () => { stopPlayback(); status('Playback stopped.'); });
  ui.export.addEventListener('click', exportVideo);
  ui.share.addEventListener('click', shareVideo);
  ui.copyCaption.addEventListener('click', copyCaption);
  ui.fxPreset.addEventListener('change', () => {
    fxPreset = effects.preset(ui.fxPreset.value);
    invalidateExport();
    savePreferences();
    status('Effect selected. Hit Play All for full-grid preview, then export to include this look.');
  });
  ui.fxStrength.addEventListener('input', () => {
    fxStrength = effects.strength(ui.fxStrength.value);
    invalidateExport();
    ui.fxStrengthValue.textContent = `${fxStrength}%`;
    savePreferences();
  });
  ui.clear.addEventListener('click', clearSession);
  ui.remove.addEventListener('click', removeTile);
  ui.downloadTile.addEventListener('click', downloadSelected);
  ui.mute.addEventListener('click', toggleMute);
  ui.level.addEventListener('input', () => {
    const clip = clips.get(selected);
    if (!clip || busy) return;
    invalidateExport();
    clip.volume = Number(ui.level.value) / 100;
    ui.levelOutput.textContent = `${ui.level.value}%`;
    applyTrackGain(selected);
  });
  ui.level.addEventListener('change', async () => { const clip = clips.get(selected); if (clip) await saveClip(clip); });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden && playbackMode === 'playing') stopPlayback();
    if (document.hidden && playbackMode === 'exporting') status('Keep this tab visible during video export for best results.', true);
  });
  window.addEventListener('pagehide', () => { stopInput(); stopPlayback(); });

  async function init() {
    restorePreferences();
    db = await openDatabase();
    const stored = await loadDatabase();
    for (const record of stored) installClip(record);
    if (stored.some(clip => clip.index >= gridSize)) gridSize = 9;
    refreshSessionDuration();
    setTarget(targetDuration);
    ui.fxPreset.value = fxPreset;
    ui.fxStrength.value = String(fxStrength);
    renderGrid();
    if (stored.length) status(`${stored.length} saved take${stored.length === 1 ? '' : 's'} restored from this browser. Hit Play All to listen.`);
    else if (!db) status('Private mode / storage unavailable. Recordings will not survive a page reload; download your clips.', true);
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) status('Camera and microphone require HTTPS or localhost. Use the included Mac launcher.', true);
    if (typeof MediaRecorder === 'undefined') status('This browser does not support MediaRecorder. Try current Chrome, Edge, or Safari.', true);
  }
  init().catch(error => status(`Setup issue: ${error.message}`, true));
})();

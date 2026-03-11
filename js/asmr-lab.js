(function () {
  'use strict';

  const app = document.getElementById('asmr-lab-app');
  if (!app || !window.seAsmrLab) return;

  const form = document.getElementById('asmr-lab-form');
  const statusEl = document.getElementById('asmr-status');
  const debugStatusEl = document.getElementById('asmr-debug-status');
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
  const atlasGridEl = document.getElementById('asmr-visual-atlas-grid');
  const soundGridEl = document.getElementById('asmr-sound-inspector-grid');
  const visualSearchEl = document.getElementById('asmr-visual-inspector-search');
  const soundSearchEl = document.getElementById('asmr-sound-inspector-search');
  const debugInspectorsEl = document.getElementById('asmr-debug-inspectors');
  const visualMetaEl = document.getElementById('asmr-visual-inspector-meta');
  const soundMetaEl = document.getElementById('asmr-sound-inspector-meta');
  const soundStatusEl = document.getElementById('asmr-sound-inspector-status');
  const visualDebugCanvas = document.getElementById('asmr-visual-debug-canvas');
  const pinVisualPreviewBtn = document.getElementById('asmr-pin-visual-preview');
  const stopVisualPreviewBtn = document.getElementById('asmr-stop-visual-preview');
  const clearVisualPreviewBtn = document.getElementById('asmr-clear-visual-preview');
  const stopSoundPreviewBtn = document.getElementById('asmr-stop-sound-preview');
  const inspectSelectedVisualsBtn = document.getElementById('asmr-inspect-selected-visuals');
  const inspectSelectedSoundsBtn = document.getElementById('asmr-inspect-selected-sounds');
  const previewCurrentHeroBtn = document.getElementById('asmr-preview-current-hero');
  const visualPreviewPanelEl = document.querySelector('[data-panel="visual"] .asmr-inspector-preview');
  const visualPreviewTitleEl = document.getElementById('asmr-visual-preview-title');
  const provenanceToggle = document.getElementById('asmr-debug-provenance-toggle');

  const engine = new window.AsmrFoleyEngine();
  const inspectorSoundEngine = new window.AsmrFoleyEngine();
  const visuals = window.AsmrVisualEngine ? new window.AsmrVisualEngine(canvas) : null;
  let lastPayload = null;
  let currentPackage = null;
  const visualRegistry = (window.seAsmrLab && Array.isArray(window.seAsmrLab.visualRegistry) && window.seAsmrLab.visualRegistry.length)
    ? window.seAsmrLab.visualRegistry
    : (window.ASMR_VISUAL_REGISTRY || []);
  const soundRegistry = (window.ASMR_SOUND_REGISTRY && Array.isArray(window.ASMR_SOUND_REGISTRY) && window.ASMR_SOUND_REGISTRY.length)
    ? window.ASMR_SOUND_REGISTRY
    : (typeof inspectorSoundEngine.getSoundRegistry === 'function' ? inspectorSoundEngine.getSoundRegistry() : []);

  const inspectorState = {
    activeTab: 'visual',
    visualFilter: '',
    soundFilter: '',
    pinnedVisualId: null,
    visualHoverTimer: null,
    selectedVisualOnly: false,
    selectedSoundOnly: false,
    currentSoundId: null,
    hoveredVisualId: null,
    focusedVisualId: null,
    currentPreviewVisualId: null,
    previewPanelHovered: false,
    pendingCancelTimer: null,
    visualPreviewState: 'stopped',
    visualMode: 'hold'
  };

  const previewVisuals = window.AsmrVisualEngine ? new window.AsmrVisualEngine(visualDebugCanvas) : null;

  const LINKED_VISUAL_TO_AUDIO = {
    seabus_silhouette: ['seabus_horn'],
    rain_streaks: ['rain_ambience'],
    skytrain_pass_visual: ['skytrain_pass']
  };

  function applyLayerLinking(audioLayers, visualLayers, isLinked) {
    const audioSet = new Set(Array.isArray(audioLayers) ? audioLayers : []);
    const visualSet = new Set(Array.isArray(visualLayers) ? visualLayers : []);
    if (!isLinked) {
      return { audio_layers: Array.from(audioSet), visual_layers: Array.from(visualSet) };
    }

    Object.entries(LINKED_VISUAL_TO_AUDIO).forEach(([visualToken, audioTokens]) => {
      if (!visualSet.has(visualToken)) return;
      audioTokens.forEach((audioToken) => audioSet.add(audioToken));
    });

    return { audio_layers: Array.from(audioSet), visual_layers: Array.from(visualSet) };
  }


  const QA_PRESET = {
    duration: '20',
    link_av: true,
    audio_layers: ['ocean_waves', 'seabus_horn'],
    visual_layers: ['waterfront_scene', 'harbor_mist', 'lions_gate_bridge', 'ocean_surface_shimmer']
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
    const storyBeats = document.getElementById('asmr-story-beats');
    const prompts = document.getElementById('asmr-video-prompts');
    const activePlan = document.getElementById('asmr-active-plan');
    const edit = document.getElementById('asmr-edit-notes');
    const note = document.getElementById('asmr-presentation');
    const recipe = document.getElementById('asmr-sound-json');
    const planVisualSummary = document.getElementById('asmr-plan-visual-summary');

    if (concept) concept.textContent = `${data.title} (${data.runtime_seconds}s) — ${data.hook}
${data.concept_summary}`;
    if (storyBeats) {
      storyBeats.innerHTML = '';
      const labelOrder = ['Opening', 'Arrival', 'Lift', 'Resolve'];
      (Array.isArray(data.story_beats) ? data.story_beats : []).forEach((beat, idx) => {
        const li = document.createElement('li');
        const t0 = Number(beat.t0 || 0).toFixed(1);
        const t1 = Number(beat.t1 || 0).toFixed(1);
        const label = labelOrder[idx] || beat.beat || 'Beat';
        li.textContent = `${label} (${t0}s–${t1}s): ${beat.intent || beat.beat || ''}`.trim();
        storyBeats.appendChild(li);
      });
    }

    if (activePlan) {
      activePlan.innerHTML = '';
      const plan = data.generation_plan || {};
      const vPlan = plan.visual_plan || {};
      const aPlan = plan.audio_plan || {};
      const planRows = [
        `Primary scene: ${vPlan.primary_scene || '—'}`,
        `Landmark: ${vPlan.landmark || '—'}`,
        `Motion: ${vPlan.motion || '—'}`,
        `Atmosphere: ${vPlan.atmosphere || '—'}`,
        `End card: ${data.end_card && data.end_card.use_end_card ? 'on' : 'off'}`,
        `Primary bed: ${aPlan.primary_bed || '—'}`,
        `Signature cues: ${Array.isArray(aPlan.signature_cues) && aPlan.signature_cues.length ? aPlan.signature_cues.join(', ') : '—'}`
      ];
      planRows.forEach((row) => {
        const li = document.createElement('li');
        li.textContent = row;
        activePlan.appendChild(li);
      });
    }


    if (planVisualSummary) {
      const vPlan = (data.generation_plan && data.generation_plan.visual_plan) || {};
      const heroEvents = (Array.isArray(data.visual_events) ? data.visual_events : []).filter((event) => {
        const entry = visualRegistry.find((item) => item.id === event.visual_type);
        return entry && entry.priority === 'hero';
      }).map((event) => event.visual_type);
      const planned = [vPlan.primary_scene, vPlan.landmark, vPlan.motion].filter(Boolean);
      const mismatch = planned.some((id) => !heroEvents.includes(id));
      const rows = [
        `Primary scene: ${vPlan.primary_scene || '—'}`,
        `Landmark: ${vPlan.landmark || '—'}`,
        `Motion: ${vPlan.motion || '—'}`,
        `Atmosphere: ${vPlan.atmosphere || '—'}`,
        `Compiled hero events: ${heroEvents.length ? Array.from(new Set(heroEvents)).join(', ') : '—'}`
      ];
      if (mismatch && provenanceToggle && provenanceToggle.checked) {
        rows.push('⚠️ Debug warning: planned hero visual(s) missing from compiled hero event list.');
      }
      toList(planVisualSummary, rows);
    }
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


  function setDebugStatus(payload) {
    if (!debugStatusEl) return;
    const audioCount = Array.isArray(payload.audio_layers) ? payload.audio_layers.length : 0;
    const visualCount = Array.isArray(payload.visual_layers) ? payload.visual_layers.length : 0;
    debugStatusEl.textContent = `Selection snapshot: audio=${audioCount}, visual=${visualCount}, link_av=${payload.link_av ? 'on' : 'off'}`;
  }

  function collectFormPayload() {
    const formData = new FormData(form);
    const base = {
      duration: String(formData.get('duration') || '20')
    };

    const linkAV = !!formData.get('link_av');
    const audioLayers = formData.getAll('audio_layers[]').map((item) => String(item || '').trim()).filter(Boolean);
    const visualLayers = formData.getAll('visual_layers[]').map((item) => String(item || '').trim()).filter(Boolean);
    const linked = applyLayerLinking(audioLayers, visualLayers, linkAV);

    return Object.assign({}, base, {
      link_av: linkAV,
      audio_layers: linked.audio_layers,
      visual_layers: linked.visual_layers
    });
  }



  function groupByCategory(list) {
    return list.reduce((acc, item) => {
      const key = String(item.category || 'other');
      if (!acc[key]) acc[key] = [];
      acc[key].push(item);
      return acc;
    }, {});
  }

  function matchesFilter(item, needle) {
    if (!needle) return true;
    const text = [item.id, item.label, item.category, item.description, item.expected_shape, item.expected_sound, item.priority].filter(Boolean).join(' ').toLowerCase();
    return text.includes(needle);
  }

  function getSelectedValues(name) {
    if (!form) return [];
    return Array.from(form.querySelectorAll(`input[name="${name}[]"]:checked`)).map((el) => el.value);
  }

  function updatePreviewPanelHeader(item) {
    if (!visualPreviewTitleEl) return;
    const tag = item ? ` <span class="preview-tag">${item.label} • ${item.priority || 'support'}</span>` : '';
    visualPreviewTitleEl.innerHTML = `Visual Preview${tag}`;
    visualPreviewTitleEl.classList.toggle('is-previewing', !!item);
  }

  function updateVisualMeta(item, status) {
    if (!visualMetaEl) return;
    if (!item) {
      visualMetaEl.textContent = 'Hover or focus a motif card to preview.';
      updatePreviewPanelHeader(null);
      return;
    }
    updatePreviewPanelHeader(item);
    const mode = inspectorState.visualMode || 'hold';
    const state = inspectorState.visualPreviewState || 'stopped';
    visualMetaEl.textContent = `${item.label} (${item.id}) • ${item.category} • ${item.priority || 'support'} • state:${state} • mode:${mode} • ${status || item.expected_shape || ''}`;
  }

  function clearPendingVisualCancel() {
    if (inspectorState.pendingCancelTimer) {
      window.clearTimeout(inspectorState.pendingCancelTimer);
      inspectorState.pendingCancelTimer = null;
    }
  }

  function isVisualInteractionActive() {
    if (inspectorState.pinnedVisualId) return true;
    return !!(inspectorState.hoveredVisualId || inspectorState.focusedVisualId || inspectorState.previewPanelHovered);
  }

  function refreshVisualCardStates() {
    if (!atlasGridEl) return;
    Array.from(atlasGridEl.querySelectorAll('.asmr-atlas-card')).forEach((el) => {
      const id = el.dataset.id;
      const isHovered = !!id && (id === inspectorState.hoveredVisualId || id === inspectorState.focusedVisualId || id === inspectorState.currentPreviewVisualId);
      const isPinned = !!id && id === inspectorState.pinnedVisualId;
      el.classList.toggle('is-hovered', isHovered);
      el.classList.toggle('is-pinned', isPinned);
    });
    if (visualPreviewPanelEl) {
      visualPreviewPanelEl.classList.toggle('is-hover-active', isVisualInteractionActive());
    }
  }

  function scheduleVisualCancel() {
    clearPendingVisualCancel();
    inspectorState.pendingCancelTimer = window.setTimeout(() => {
      if (isVisualInteractionActive()) return;
      if (previewVisuals) previewVisuals.cancelPreview({ clearFrame: false, resetTime: false });
      inspectorState.currentPreviewVisualId = null;
      inspectorState.visualPreviewState = 'stopped';
      refreshVisualCardStates();
    }, 190);
  }

  function stopVisualPreviewMotion() {
    if (!previewVisuals) return;
    previewVisuals.cancelPreview({ clearFrame: false, resetTime: false });
    inspectorState.visualPreviewState = 'stopped';
    const item = visualRegistry.find((entry) => entry.id === inspectorState.currentPreviewVisualId) || null;
    updateVisualMeta(item, item ? (item.expected_shape || 'stopped') : 'stopped');
    refreshVisualCardStates();
  }

  function clearVisualPreview() {
    if (!previewVisuals) return;
    previewVisuals.cancelPreview({ clearFrame: true, resetTime: true });
    inspectorState.hoveredVisualId = null;
    inspectorState.focusedVisualId = null;
    inspectorState.currentPreviewVisualId = null;
    inspectorState.visualPreviewState = 'stopped';
    clearPendingVisualCancel();
    refreshVisualCardStates();
    updateVisualMeta(null, 'stopped');
  }

  function updateSoundStatus(id, status, expected) {
    if (soundStatusEl) soundStatusEl.textContent = status;
    if (soundMetaEl) {
      if (!id) soundMetaEl.textContent = 'Select a sound engine and click Preview sound.';
      else soundMetaEl.textContent = `engine:${id}${expected ? ' • expected: ' + expected : ''}`;
    }
  }

  function previewVisualItem(item, sourceCard, mode) {
    if (!item || !previewVisuals) return;
    if (inspectorState.pinnedVisualId && inspectorState.pinnedVisualId !== item.id) return;
    clearPendingVisualCancel();
    if (inspectorState.visualHoverTimer) window.clearTimeout(inspectorState.visualHoverTimer);
    inspectorState.visualHoverTimer = window.setTimeout(() => {
      const isPinned = inspectorState.pinnedVisualId === item.id;
      const loopPreview = isPinned;
      const holdLastFrame = !isPinned;
      inspectorState.visualPreviewState = isPinned ? 'pinned' : (mode || 'hover');
      inspectorState.visualMode = loopPreview ? 'loop' : (holdLastFrame ? 'hold' : 'oneshot');
      if (!previewVisuals.previewVisualMotifById(item.id, {
        runtimeSeconds: isPinned ? 2.2 : 1.8,
        autoStop: !isPinned,
        loopPreview,
        holdLastFrame,
        preserveFrameOnStop: true
      })) {
        inspectorState.visualPreviewState = 'stopped';
        updateVisualMeta(item, 'Renderer missing');
        return;
      }
      const report = typeof previewVisuals.getLastPreviewReport === 'function' ? previewVisuals.getLastPreviewReport() : null;
      const motifMeta = report && report.activePreviewMotif ? `active preview motif:${report.activePreviewMotif}` : '';
      const countMeta = report && Number.isFinite(report.timelineEventCount) ? `timeline event count:${report.timelineEventCount}` : '';
      const baseStatus = report && report.warning ? report.warning : (item.expected_shape || 'previewing');
      const status = [baseStatus, motifMeta, countMeta].filter(Boolean).join(' • ');
      inspectorState.currentPreviewVisualId = item.id;
      updateVisualMeta(item, status);
      refreshVisualCardStates();
    }, 130);
  }

  function renderVisualAtlas() {
    if (!atlasGridEl) return;
    atlasGridEl.innerHTML = '';
    const filterNeedle = String(inspectorState.visualFilter || '').trim().toLowerCase();
    const selected = new Set(getSelectedValues('visual_layers'));
    const base = visualRegistry.filter((item) => !inspectorState.selectedVisualOnly || selected.has(item.id));
    const filtered = base.filter((item) => matchesFilter(item, filterNeedle));
    const grouped = groupByCategory(filtered);
    ['scene', 'landmark', 'atmosphere', 'motion', 'texture', 'support'].forEach((category) => {
      if (!grouped[category] || !grouped[category].length) return;
      const section = document.createElement('section');
      section.className = 'asmr-inspector-group';
      section.innerHTML = `<h4 class="asmr-inspector-group-title">${category}</h4>`;
      grouped[category].forEach((item) => {
        const card = document.createElement('article');
        card.className = 'asmr-atlas-card';
        card.tabIndex = 0;
        card.dataset.id = item.id;
        card.innerHTML = `<h4>${item.label}</h4><p><code>${item.id}</code></p><p>${item.description || ''}</p><p>${item.category} • ${item.priority}</p><p><strong>Expected:</strong> ${item.expected_shape || '—'}</p>`;
        const pinBtn = document.createElement('button');
        pinBtn.type = 'button';
        pinBtn.className = 'pixel-button tiny secondary';
        pinBtn.textContent = 'Pin preview';
        pinBtn.addEventListener('click', () => {
          inspectorState.pinnedVisualId = inspectorState.pinnedVisualId === item.id ? null : item.id;
          if (pinVisualPreviewBtn) pinVisualPreviewBtn.setAttribute('aria-pressed', inspectorState.pinnedVisualId ? 'true' : 'false');
          if (inspectorState.pinnedVisualId) {
            previewVisualItem(item, card, 'pinned');
          } else {
            stopVisualPreviewMotion();
          }
          refreshVisualCardStates();
        });
        card.appendChild(pinBtn);
        card.addEventListener('mouseenter', () => {
          inspectorState.hoveredVisualId = item.id;
          previewVisualItem(item, card, 'hover');
        });
        card.addEventListener('mouseleave', () => {
          if (inspectorState.hoveredVisualId === item.id) inspectorState.hoveredVisualId = null;
          scheduleVisualCancel();
          refreshVisualCardStates();
        });
        card.addEventListener('focus', () => {
          inspectorState.focusedVisualId = item.id;
          previewVisualItem(item, card, 'hover');
        });
        card.addEventListener('blur', () => {
          if (inspectorState.focusedVisualId === item.id) inspectorState.focusedVisualId = null;
          scheduleVisualCancel();
          refreshVisualCardStates();
        });
        section.appendChild(card);
      });
      atlasGridEl.appendChild(section);
    });
    refreshVisualCardStates();
  }

  function renderSoundInspector() {
    if (!soundGridEl) return;
    soundGridEl.innerHTML = '';
    const filterNeedle = String(inspectorState.soundFilter || '').trim().toLowerCase();
    const selected = new Set(getSelectedValues('audio_layers'));
    const base = soundRegistry.filter((item) => !inspectorState.selectedSoundOnly || selected.has(item.id));
    const filtered = base.filter((item) => matchesFilter(item, filterNeedle));
    const grouped = groupByCategory(filtered);
    ['bed', 'transit', 'cue', 'ambience', 'texture', 'support'].forEach((category) => {
      if (!grouped[category] || !grouped[category].length) return;
      const section = document.createElement('section');
      section.className = 'asmr-inspector-group';
      section.innerHTML = `<h4 class="asmr-inspector-group-title">${category}</h4>`;
      grouped[category].forEach((item) => {
        const card = document.createElement('article');
        card.className = 'asmr-sound-card';
        card.innerHTML = `<h4>${item.label}</h4><p><code>${item.id}</code></p><p>${item.description || ''}</p><p>${item.category}</p><p><strong>Expected:</strong> ${item.expected_sound || '—'}</p>`;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pixel-button tiny secondary';
        btn.textContent = 'Preview sound';
        btn.addEventListener('click', async () => {
          inspectorSoundEngine.stop();
          updateSoundStatus(item.id, 'previewing', item.expected_sound);
          try {
            await inspectorSoundEngine.previewEngineById(item.id, { preview_duration: item.preview_duration || 2.2 });
            inspectorState.currentSoundId = item.id;
            updateSoundStatus(item.id, 'previewing', item.expected_sound);
          } catch (err) {
            updateSoundStatus(item.id, 'stopped', item.expected_sound);
          }
        });
        card.appendChild(btn);
        section.appendChild(card);
      });
      soundGridEl.appendChild(section);
    });
  }

  async function previewCurrentHeroVisuals() {
    if (!currentPackage || !visuals) return;
    const vPlan = (currentPackage.generation_plan && currentPackage.generation_plan.visual_plan) || {};
    const queue = [vPlan.primary_scene, vPlan.landmark, vPlan.motion]
      .filter(Boolean)
      .filter((value, index, arr) => arr.indexOf(value) === index);
    for (let i = 0; i < queue.length; i += 1) {
      if (previewVisuals) previewVisuals.previewVisualMotifById(queue[i], { runtimeSeconds: 1.8 });
      await new Promise((resolve) => window.setTimeout(resolve, 3100));
    }
  }



  function setInspectorTab(tab) {
    inspectorState.activeTab = tab === 'sound' ? 'sound' : 'visual';
    document.querySelectorAll('.asmr-inspector-tab').forEach((btn) => {
      const active = btn.dataset.tab === inspectorState.activeTab;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('.asmr-inspector-panel').forEach((panel) => {
      const active = panel.dataset.panel === inspectorState.activeTab;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
  }

  function openInspector(tab, selectedOnly) {
    if (debugInspectorsEl) debugInspectorsEl.open = true;
    if (tab === 'sound') {
      inspectorState.selectedSoundOnly = !!selectedOnly;
      setInspectorTab('sound');
      renderSoundInspector();
      return;
    }
    inspectorState.selectedVisualOnly = !!selectedOnly;
    setInspectorTab('visual');
    renderVisualAtlas();
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

  async function inspectRecordedBlob(blob) {
    const meta = { size: blob ? blob.size : 0, duration: 0 };
    if (!blob || !blob.size) return meta;
    await new Promise((resolve) => {
      const probe = document.createElement('video');
      probe.preload = 'metadata';
      probe.muted = true;
      const url = URL.createObjectURL(blob);
      probe.onloadedmetadata = () => {
        meta.duration = Number.isFinite(probe.duration) ? probe.duration : 0;
        URL.revokeObjectURL(url);
        resolve();
      };
      probe.onerror = () => {
        URL.revokeObjectURL(url);
        resolve();
      };
      probe.src = url;
    });
    return meta;
  }

  async function recordVideoWithMime(pkg, mimeType, extension) {
    const runtime = Math.max(10, Math.min(30, Number(pkg.runtime_seconds || 20)));
    const fps = 30;
    const frameMs = Math.round(1000 / fps);

    const target = document.createElement('canvas');
    target.width = 1920;
    target.height = 1080;
    const targetCtx = target.getContext('2d');

    const exportVisuals = new window.AsmrVisualEngine(target);
    exportVisuals.loadTimeline(pkg);
    exportVisuals.renderToContext(targetCtx, 1920, 1080, 0);

    const stream = target.captureStream(0);
    const videoTrack = stream.getVideoTracks()[0];
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 48000 });
    if (audioCtx.state === 'suspended') await audioCtx.resume();
    const rendered = await engine.renderToOfflineBuffer(pkg, 48000);
    const source = audioCtx.createBufferSource();
    source.buffer = rendered;
    const gain = audioCtx.createGain();
    gain.gain.value = 0.95;
    const mediaDest = audioCtx.createMediaStreamDestination();
    source.connect(gain);
    gain.connect(mediaDest);
    mediaDest.stream.getAudioTracks().forEach((track) => stream.addTrack(track));

    const chunks = [];
    const rec = new MediaRecorder(stream, { mimeType, videoBitsPerSecond: 12_000_000 });
    rec.ondataavailable = (event) => { if (event.data && event.data.size > 0) chunks.push(event.data); };

    await new Promise((resolve, reject) => {
      const startAt = audioCtx.currentTime + 0.12;
      let ended = false;
      let timer = null;
      let frameIndex = 0;

      const cleanup = () => {
        if (timer) window.clearInterval(timer);
        source.onended = null;
        try { source.stop(); } catch (e) {}
        stream.getTracks().forEach((t) => t.stop());
        audioCtx.close().catch(() => {});
      };

      const drawFrame = () => {
        const t = Math.min(runtime, frameIndex / fps);
        exportVisuals.renderToContext(targetCtx, 1920, 1080, t);
        if (videoTrack && typeof videoTrack.requestFrame === 'function') videoTrack.requestFrame();
        frameIndex += 1;
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
        window.setTimeout(() => { try { rec.stop(); } catch (e) {} }, 180);
      };

      rec.start(250);
      source.start(startAt, 0, runtime);

      timer = window.setInterval(() => {
        if (ended) return;
        drawFrame();
      }, frameMs);

      drawFrame();
    });

    const blob = new Blob(chunks, { type: mimeType });
    const inspection = await inspectRecordedBlob(blob);
    return { blob, extension, mimeType, inspection, runtime };
  }

  async function exportVideo() {
    if (!currentPackage) return;
    if (!videoSupport.canExport) throw new Error('Video export is not supported in this browser/runtime.');

    transport.stopAll();
    engine.stop();
    if (visuals) visuals.stop();

    const attempts = [];
    if (videoSupport.preferredMime) attempts.push({ mimeType: videoSupport.preferredMime, extension: videoSupport.extension });
    if (videoSupport.webmMime && videoSupport.webmMime !== videoSupport.preferredMime) {
      attempts.push({ mimeType: videoSupport.webmMime, extension: 'webm' });
    }

    let result = null;
    for (let i = 0; i < attempts.length; i += 1) {
      const attempt = attempts[i];
      result = await recordVideoWithMime(currentPackage, attempt.mimeType, attempt.extension);
      const tinyBlob = result.inspection.size < 300000;
      const shortDuration = result.inspection.duration > 0 && result.inspection.duration < result.runtime * 0.8;
      const shouldRetryWebm = attempt.extension === 'mp4' && attempts[i + 1] && (tinyBlob || shortDuration);
      if (!shouldRetryWebm) break;
      if (audioFeedback) audioFeedback.textContent = 'MP4 export looked incomplete; retrying WebM fallback...';
    }

    const url = URL.createObjectURL(result.blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `asmr-lab-film-1920x1080.${result.extension}`;
    a.click();
    URL.revokeObjectURL(url);

    if (audioFeedback) {
      audioFeedback.textContent = result.extension === 'mp4'
        ? '1080p MP4 export complete.'
        : '1080p WebM export complete (fallback mode).';
    }
  }


  if (form) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      lastPayload = collectFormPayload();
      setDebugStatus(lastPayload);
      try { renderData(await requestGeneration(lastPayload)); }
      catch (err) { setError(err.message || 'Generation failed.'); setStatus(''); }
    });
  }

  if (qaPresetBtn && form) {
    qaPresetBtn.addEventListener('click', () => {
      Object.entries(QA_PRESET).forEach(([key, value]) => {
        if ((key === 'audio_layers' || key === 'visual_layers') && Array.isArray(value)) {
          const boxes = form.querySelectorAll(`input[name="${key}[]"]`);
          boxes.forEach((box) => { box.checked = value.includes(box.value); });
          return;
        }
        if (key === 'link_av') {
          const box = form.querySelector('input[name="link_av"]');
          if (box) box.checked = !!value;
          return;
        }
        const field = form.elements.namedItem(key);
        if (field) field.value = value;
      });
      setStatus('QA preset loaded (waterfront rack). Generate package to test ocean-linked motifs.');
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
      inspectorSoundEngine.stop();
      stopVisualPreviewMotion();
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




  document.querySelectorAll('.asmr-inspector-tab').forEach((btn) => {
    btn.addEventListener('click', () => setInspectorTab(btn.dataset.tab));
  });


  if (debugInspectorsEl) {
    debugInspectorsEl.addEventListener('toggle', () => {
      if (!debugInspectorsEl.open) {
        clearVisualPreview();
      }
    });
  }


  if (visualPreviewPanelEl) {
    visualPreviewPanelEl.addEventListener('mouseenter', () => {
      inspectorState.previewPanelHovered = true;
      clearPendingVisualCancel();
      refreshVisualCardStates();
    });
    visualPreviewPanelEl.addEventListener('mouseleave', () => {
      inspectorState.previewPanelHovered = false;
      scheduleVisualCancel();
      refreshVisualCardStates();
    });
  }

  if (visualSearchEl) {
    visualSearchEl.addEventListener('input', () => {
      inspectorState.visualFilter = visualSearchEl.value || '';
      inspectorState.selectedVisualOnly = false;
      renderVisualAtlas();
    });
  }

  if (soundSearchEl) {
    soundSearchEl.addEventListener('input', () => {
      inspectorState.soundFilter = soundSearchEl.value || '';
      inspectorState.selectedSoundOnly = false;
      renderSoundInspector();
    });
  }

  if (inspectSelectedVisualsBtn) {
    inspectSelectedVisualsBtn.addEventListener('click', () => openInspector('visual', true));
  }

  if (inspectSelectedSoundsBtn) {
    inspectSelectedSoundsBtn.addEventListener('click', () => openInspector('sound', true));
  }

  if (pinVisualPreviewBtn) {
    pinVisualPreviewBtn.addEventListener('click', () => {
      inspectorState.pinnedVisualId = null;
      pinVisualPreviewBtn.setAttribute('aria-pressed', 'false');
      stopVisualPreviewMotion();
      refreshVisualCardStates();
    });
  }

  if (stopVisualPreviewBtn) {
    stopVisualPreviewBtn.addEventListener('click', () => {
      inspectorState.pinnedVisualId = null;
      if (pinVisualPreviewBtn) pinVisualPreviewBtn.setAttribute('aria-pressed', 'false');
      stopVisualPreviewMotion();
    });
  }

  if (clearVisualPreviewBtn) {
    clearVisualPreviewBtn.addEventListener('click', () => {
      inspectorState.pinnedVisualId = null;
      if (pinVisualPreviewBtn) pinVisualPreviewBtn.setAttribute('aria-pressed', 'false');
      clearVisualPreview();
    });
  }

  if (stopSoundPreviewBtn) {
    stopSoundPreviewBtn.addEventListener('click', () => {
      inspectorSoundEngine.stop();
      inspectorState.currentSoundId = null;
      updateSoundStatus(null, 'stopped');
    });
  }
  if (visuals) {
    visuals.setDebugOptions({ enabled: false, showProvenance: false });
  }
  if (previewVisuals) {
    previewVisuals.setDebugOptions({ enabled: true, showProvenance: false });
  }
  renderVisualAtlas();
  renderSoundInspector();
  setInspectorTab('visual');
  updateVisualMeta(null, "stopped");
  updateSoundStatus(null, "stopped");

  if (provenanceToggle) {
    provenanceToggle.addEventListener('change', () => {
      if (visuals) visuals.setDebugOptions({ enabled: provenanceToggle.checked, showProvenance: provenanceToggle.checked });
      if (currentPackage && visuals) visuals.seek(0);
    });
  }

  if (previewCurrentHeroBtn) {
    previewCurrentHeroBtn.addEventListener('click', async () => {
      await previewCurrentHeroVisuals();
    });
  }


  window.addEventListener('resize', function () { if (visuals) visuals.resize(); });
})();

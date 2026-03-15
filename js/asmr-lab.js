(function () {
  'use strict';

  function initLabNarrator() {
    const trigger = document.getElementById('asmr-lab-voice-trigger');
    const status = document.getElementById('asmr-lab-voice-status');
    if (!trigger || !status) return;

    const lines = [
      'Lab status update. Yes, this prototype is under major development. No, pressing buttons harder will not speed it up.',
      'ASMR Lab was the glitchy ancestor of the Gastown simulator. It did its job, then demanded a redesign.',
      'Rebuild chamber active. Legacy code remains in containment. Interface recovery is in progress.',
      'System note. Prototype unstable, story intact, vibes operational.'
    ];

    function speakMessage(text) {
      if (!text || !('speechSynthesis' in window)) return false;

      const synth = window.speechSynthesis;
      const utterance = new SpeechSynthesisUtterance(text);

      const pickVoice = () => {
        const voices = synth.getVoices();
        const preferred = voices.find((voice) =>
          voice.name.includes('Google UK English Male') ||
          voice.name.includes('Microsoft David') ||
          /en/i.test(voice.lang)
        );

        utterance.voice = preferred || null;
        utterance.rate = 0.92;
        utterance.pitch = 0.84;
        synth.cancel();
        synth.speak(utterance);
      };

      const voices = synth.getVoices();
      if (!voices || voices.length === 0) {
        synth.addEventListener('voiceschanged', pickVoice, { once: true });
      } else {
        pickVoice();
      }

      return true;
    }

    trigger.addEventListener('click', () => {
      const line = lines[Math.floor(Math.random() * lines.length)];
      status.textContent = 'Lab narrator online…';

      const supported = speakMessage(line);
      if (!supported) {
        status.textContent = line;
        return;
      }

      status.textContent = `Lab narrator: "${line}"`;
    });
  }

  initLabNarrator();

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
  const advancedPanelEl = document.getElementById('asmr-advanced-panel');
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
  const resultsTabButtons = document.querySelectorAll('.asmr-results-tab');
  const resultsPanels = document.querySelectorAll('.asmr-results-panel');
  const modeChips = document.querySelectorAll('.asmr-mode-chip');
  const lookChips = document.querySelectorAll('.asmr-look-chip');
  const prevNodeBtn = document.getElementById('asmr-node-prev');
  const nextNodeBtn = document.getElementById('asmr-node-next');
  const nodeOrderEl = document.getElementById('asmr-route-node-order');
  const nodeTitleEl = document.getElementById('asmr-route-node-title');
  const nodeIdEl = document.getElementById('asmr-route-node-id');
  const nodeTransitionEl = document.getElementById('asmr-route-node-transition');

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

  const SCENE_MOTIF_MAP = {
    gastown: ['gastown_scene', 'gastown_clock_silhouette', 'steam_clock_approach', 'water_street_corridor', 'station_threshold_glow', 'angled_building_split', 'lamp_rhythm_row', 'wet_cobble_axis', 'cobblestone_perspective', 'brick_wall_parallax', 'streetlamp_halo_row', 'clock_face_reveal'],
    granville: ['granville_scene', 'granville_neon_marquee', 'neon_sign_flicker', 'traffic_light_glow', 'neon_wet_reflections'],
    chinatown: ['chinatown_gate']
  };


  const GASTOWN_ROUTE_PROFILE = {
    route_id: 'gastown_water_street_walk',
    district: 'gastown',
    continuity: {
      single_district: true,
      continuous_path: true,
      reveal_mode: 'one_at_a_time',
      disallow_unrelated_landmarks: true
    },
    camera_grammar: {
      pov: 'first_person_unseen',
      movement: 'forward_walk',
      framing: 'off_center_landmark',
      corridor_bias: 'strong',
      asymmetry_bias: 'high'
    },
    waypoint_order: [
      'waterfront_threshold',
      'brick_lamp_cobblestone_corridor',
      'steam_clock_approach',
      'street_split_angled_building'
    ],
    landmark_order: [
      'station_threshold_glow',
      'water_street_corridor',
      'steam_clock_approach',
      'angled_building_split'
    ],
    facade_tendency_by_segment: [
      { segment: 'waterfront_threshold', left: 'station_mass', right: 'brick_entry' },
      { segment: 'brick_lamp_cobblestone_corridor', left: 'heritage_brick_dense', right: 'shopfront_neon_sparse' },
      { segment: 'steam_clock_approach', left: 'compressed_facade_planes', right: 'clock_reveal_candidate' },
      { segment: 'street_split_angled_building', left: 'receding_curb_line', right: 'angled_node_mass' }
    ],
    audio_traits_by_segment: [
      { segment: 'waterfront_threshold', traits: ['distant_transit_hum', 'cold_air_hush'] },
      { segment: 'brick_lamp_cobblestone_corridor', traits: ['footstep_cobble_texture', 'lamp_hum_rhythm'] },
      { segment: 'steam_clock_approach', traits: ['steam_whistle_hint', 'metal_resonance_ping'] },
      { segment: 'street_split_angled_building', traits: ['open_street_tail', 'reverb_decay_soft'] }
    ],
    visual_traits_by_segment: [
      { segment: 'waterfront_threshold', motifs: ['station_threshold_glow', 'water_street_corridor'] },
      { segment: 'brick_lamp_cobblestone_corridor', motifs: ['water_street_corridor', 'lamp_rhythm_row', 'wet_cobble_axis'] },
      { segment: 'steam_clock_approach', motifs: ['steam_clock_approach', 'wet_cobble_axis'] },
      { segment: 'street_split_angled_building', motifs: ['angled_building_split', 'water_street_corridor'] }
    ]
  };

  const sceneSeedState = {
    loaded: false,
    attempted: false,
    byScene: {},
    total: 0
  };

  async function loadSceneSeedData() {
    const sceneDataUrl = window.seAsmrLab && window.seAsmrLab.sceneDataUrl;
    if (!sceneDataUrl || sceneSeedState.attempted) return;
    sceneSeedState.attempted = true;

    try {
      const response = await fetch(sceneDataUrl, { credentials: 'same-origin' });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const rows = await response.json();
      if (!Array.isArray(rows)) throw new Error('Scene seed payload was not an array.');

      const grouped = rows.reduce((acc, row) => {
        const scene = String((row && row.scene) || '').trim().toLowerCase();
        if (!scene) return acc;
        if (!acc[scene]) acc[scene] = [];
        acc[scene].push({
          label: String((row && row.label) || '').trim() || 'untitled anchor',
          kind: String((row && row.dataset) || (row && row.geometryType) || 'unknown').trim().toLowerCase(),
          geometry_type: String((row && row.geometryType) || 'Unknown').trim(),
          hint: String(((row && row.properties && row.properties.name) || (row && row.label) || '').trim())
        });
        return acc;
      }, {});

      sceneSeedState.byScene = grouped;
      sceneSeedState.total = rows.length;
      sceneSeedState.loaded = Object.keys(grouped).length > 0;

      if (debugStatusEl && sceneSeedState.loaded) {
        debugStatusEl.textContent = `Vancouver scene seeds loaded: ${sceneSeedState.total} records across ${Object.keys(grouped).length} scenes.`;
      }
    } catch (error) {
      console.warn('[ASMR Lab] Vancouver scene seeds unavailable; continuing without world context.', error);
    }
  }

  function getWorldContextForVisualSelection(selectedVisuals) {
    if (!sceneSeedState.loaded || !selectedVisuals || !selectedVisuals.length) return null;

    const selectedSet = new Set(selectedVisuals);
    const matchedScenes = Object.entries(SCENE_MOTIF_MAP)
      .filter(([, motifs]) => motifs.some((motif) => selectedSet.has(motif)))
      .map(([scene]) => scene);

    if (!matchedScenes.length) return null;

    const sceneContexts = matchedScenes
      .map((scene) => {
        const records = sceneSeedState.byScene[scene] || [];
        if (!records.length) return null;

        const anchors = records.slice(0, 3).map((item) => ({ label: item.label, kind: item.kind }));
        const hints = records
          .slice(0, 4)
          .map((item) => item.hint)
          .filter(Boolean)
          .filter((value, idx, arr) => arr.indexOf(value) === idx)
          .slice(0, 3);
        const geometry_types = records
          .map((item) => item.geometry_type)
          .filter(Boolean)
          .filter((value, idx, arr) => arr.indexOf(value) === idx)
          .slice(0, 3);

        return {
          scene,
          anchors,
          geometry_types,
          hints
        };
      })
      .filter(Boolean);

    if (!sceneContexts.length) return null;

    return {
      scenes: sceneContexts,
      source: 'vancouver-scene-seeds',
      version: 1
    };
  }


  function getGastownRouteContext(selectedVisuals) {
    const selectedSet = new Set(Array.isArray(selectedVisuals) ? selectedVisuals : []);
    const routeMotifs = ['gastown_scene', 'steam_clock_approach', 'water_street_corridor', 'station_threshold_glow', 'angled_building_split', 'lamp_rhythm_row', 'wet_cobble_axis', 'gastown_clock_silhouette'];
    const routeSelected = routeMotifs.some((motif) => selectedSet.has(motif));
    if (!routeSelected) return null;

    const visualTraits = (GASTOWN_ROUTE_PROFILE.visual_traits_by_segment || []).map((segment) => ({
      segment: segment.segment,
      motifs: (segment.motifs || []).filter((motif) => selectedSet.has(motif) || motif === 'water_street_corridor' || motif === 'steam_clock_approach')
    }));

    return {
      route_id: GASTOWN_ROUTE_PROFILE.route_id,
      district: GASTOWN_ROUTE_PROFILE.district,
      continuity: GASTOWN_ROUTE_PROFILE.continuity,
      camera_grammar: GASTOWN_ROUTE_PROFILE.camera_grammar,
      waypoint_order: GASTOWN_ROUTE_PROFILE.waypoint_order,
      landmark_order: GASTOWN_ROUTE_PROFILE.landmark_order,
      facade_tendency_by_segment: GASTOWN_ROUTE_PROFILE.facade_tendency_by_segment,
      audio_traits_by_segment: GASTOWN_ROUTE_PROFILE.audio_traits_by_segment,
      visual_traits_by_segment: visualTraits,
      selected_route_motifs: Array.from(selectedSet).filter((token) => routeMotifs.includes(token)),
      source: 'gastown-route-profile-v1'
    };
  }

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


  const ROUTE_PRESETS = {
    gastown_water_street_walk: {
      route_id: 'gastown_water_street_walk',
      title: 'Gastown Water Street Walk',
      nodes: [
        {
          id: 'station_threshold',
          title: 'Station Threshold',
          route_order: 1,
          visual_layers: ['station_threshold_glow', 'water_street_corridor', 'street_corridor_vanishing_point'],
          audio_layers: ['footsteps', 'skytrain_pass', 'crowd_murmur', 'wind_gust'],
          look_bias: 'center',
          transition_targets: ['water_street_corridor']
        },
        {
          id: 'water_street_corridor',
          title: 'Water Street Corridor',
          route_order: 2,
          visual_layers: ['water_street_corridor', 'wet_cobble_axis', 'lamp_rhythm_row', 'brick_wall_parallax'],
          audio_layers: ['footsteps', 'crowd_murmur', 'bike_bell', 'crosswalk_chirp'],
          look_bias: 'right',
          transition_targets: ['steam_clock_approach']
        },
        {
          id: 'steam_clock_approach',
          title: 'Steam Clock Approach',
          route_order: 3,
          visual_layers: ['steam_clock_approach', 'gastown_clock_silhouette', 'water_street_corridor', 'streetlamp_halo_row'],
          audio_layers: ['steam_clock', 'gastown_clock_whistle', 'footsteps', 'crowd_murmur'],
          look_bias: 'left',
          transition_targets: ['split_building_node']
        },
        {
          id: 'split_building_node',
          title: 'Split / Angled Building Node',
          route_order: 4,
          visual_layers: ['angled_building_split', 'water_street_corridor', 'cobblestone_perspective', 'lamp_rhythm_row'],
          audio_layers: ['footsteps', 'wind_gust', 'crowd_murmur', 'car_horn_short'],
          look_bias: 'center',
          transition_targets: []
        }
      ]
    }
  };

  const QA_PRESET = {
    duration: '20',
    link_av: true,
    route_preset: 'gastown_water_street_walk',
    active_node: 'station_threshold',
    look_bias: 'center'
  };

  const routeState = {
    mode: 'compose',
    routePresetId: 'gastown_water_street_walk',
    currentNodeId: 'station_threshold',
    lookBias: 'center',
    traversal: ['station_threshold']
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

  function getRoutePreset() {
    return ROUTE_PRESETS[routeState.routePresetId] || ROUTE_PRESETS.gastown_water_street_walk;
  }

  function getRouteNode(nodeId) {
    const route = getRoutePreset();
    return (route.nodes || []).find((node) => node.id === nodeId) || route.nodes[0] || null;
  }

  function normalizeLookBias(value) {
    return value === 'left' || value === 'right' ? value : 'center';
  }

  function setMode(mode) {
    routeState.mode = mode === 'explore' || mode === 'record' ? mode : 'compose';
    modeChips.forEach((chip) => {
      const active = chip.dataset.asmrMode === routeState.mode;
      chip.classList.toggle('is-active', active);
      chip.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    if (audioFeedback) audioFeedback.textContent = `Mode: ${routeState.mode}.`;
  }

  function setLookBias(value) {
    routeState.lookBias = normalizeLookBias(value);
    if (form && form.elements.namedItem('look_bias')) form.elements.namedItem('look_bias').value = routeState.lookBias;
    lookChips.forEach((chip) => {
      const active = chip.dataset.lookBias === routeState.lookBias;
      chip.classList.toggle('is-active', active);
      chip.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function syncNodeMeta() {
    const route = getRoutePreset();
    const nodes = route.nodes || [];
    const idx = nodes.findIndex((node) => node.id === routeState.currentNodeId);
    const node = idx >= 0 ? nodes[idx] : nodes[0];
    if (!node) return;
    routeState.currentNodeId = node.id;
    if (form && form.elements.namedItem('active_node')) form.elements.namedItem('active_node').value = node.id;
    if (!routeState.traversal.includes(node.id)) routeState.traversal.push(node.id);
    if (nodeOrderEl) nodeOrderEl.textContent = `Node ${node.route_order || idx + 1} / ${nodes.length}`;
    if (nodeTitleEl) nodeTitleEl.textContent = node.title || node.id;
    if (nodeIdEl) nodeIdEl.textContent = node.id;
    if (nodeTransitionEl) {
      nodeTransitionEl.textContent = node.transition_targets && node.transition_targets.length
        ? `Transitions to: ${node.transition_targets.join(', ')}`
        : 'Transitions to: end of current route corridor';
    }
    if (prevNodeBtn) prevNodeBtn.disabled = idx <= 0;
    if (nextNodeBtn) nextNodeBtn.disabled = idx < 0 || idx >= nodes.length - 1;
    if (routeState.mode !== 'explore' && routeState.mode !== 'record') return;
    const look = routeState.lookBias || node.look_bias || 'center';
    const previewPkg = buildRoutePreviewPackage(node, look);
    currentPackage = previewPkg;
    if (visuals) {
      visuals.loadTimeline(previewPkg);
      visuals.seek(0);
    }
    [previewBtn, stopBtn, exportBtn, soundOnlyBtn, exportVideoBtn].forEach((b) => {
      if (!b) return;
      if (b === exportVideoBtn) b.disabled = !currentPackage || !videoSupport.canExport;
      else b.disabled = !currentPackage;
    });
  }

  function moveNode(delta) {
    const route = getRoutePreset();
    const nodes = route.nodes || [];
    const idx = nodes.findIndex((node) => node.id === routeState.currentNodeId);
    if (idx < 0) return;
    const target = nodes[idx + delta];
    if (!target) return;
    routeState.currentNodeId = target.id;
    if (!routeState.traversal.includes(target.id)) routeState.traversal.push(target.id);
    setLookBias(target.look_bias || routeState.lookBias);
    syncNodeMeta();
    if (audioFeedback) audioFeedback.textContent = `Moved to ${target.title}.`;
  }

  function collectAdvancedSelections(name) {
    return getSelectedValues(name).filter(Boolean);
  }

  function buildRoutePreviewPackage(node, lookBias) {
    const runtime = Number((form && form.elements.namedItem('duration') && form.elements.namedItem('duration').value) || 20) || 20;
    const look = normalizeLookBias(lookBias || node.look_bias || 'center');
    const lookPan = look === 'left' ? -0.32 : (look === 'right' ? 0.32 : 0);
    const visualEvents = (node.visual_layers || []).map((visualType) => ({
      t: 0,
      duration: runtime,
      visual_type: visualType,
      intensity: 0.72,
      params: {
        pov: 'first_person_unseen',
        look_bias: look,
        pan_bias: lookPan,
        corridor_bias: 'strong',
        asymmetry_bias: 'high',
        route_id: routeState.routePresetId,
        node_id: node.id
      }
    }));
    return {
      runtime_seconds: runtime,
      route_preset: routeState.routePresetId,
      route_mode: routeState.mode,
      active_node: node.id,
      look_bias: look,
      traversal_nodes: routeState.traversal.slice(),
      audio_layers: (node.audio_layers || []).slice(),
      visual_layers: (node.visual_layers || []).slice(),
      audio_events: [],
      visual_events: visualEvents,
      sync_points: [`Start: ${node.title}`, `Look: ${look}`, `Traverse: ${(node.transition_targets || []).join(', ') || 'route_end'}`],
      style_tags: ['gastown', 'water_street', 'first_person', 'crt'],
      creative_brief: `Route node preview for ${node.title}`
    };
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




  function setResultsTab(tab) {
    const activeTab = tab === 'timeline' || tab === 'json' ? tab : 'overview';
    resultsTabButtons.forEach((btn) => {
      const active = btn.dataset.resultsTab === activeTab;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    resultsPanels.forEach((panel) => {
      const active = panel.dataset.resultsPanel === activeTab;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
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
    setResultsTab('overview');
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
    const worldScenes = payload && payload.vancouver_world_context && Array.isArray(payload.vancouver_world_context.scenes)
      ? payload.vancouver_world_context.scenes.length
      : 0;
    const sceneStatus = sceneSeedState.loaded ? `sceneSeeds=loaded:${sceneSeedState.total}` : 'sceneSeeds=offline';
    debugStatusEl.textContent = `Route snapshot: preset=${payload.route_preset || 'none'}, mode=${payload.route_mode || 'compose'}, node=${payload.active_node || 'station_threshold'}, look=${payload.look_bias || 'center'}, audio=${audioCount}, visual=${visualCount}, worldScenes=${worldScenes}, ${sceneStatus}`;
  }

  function collectFormPayload() {
    const formData = new FormData(form);
    const base = {
      duration: String(formData.get('duration') || '20')
    };

    const node = getRouteNode(routeState.currentNodeId);
    const nodeAudio = (node && Array.isArray(node.audio_layers)) ? node.audio_layers : [];
    const nodeVisuals = (node && Array.isArray(node.visual_layers)) ? node.visual_layers : [];

    const linkAV = !!formData.get('link_av');
    const audioLayers = Array.from(new Set(nodeAudio.concat(collectAdvancedSelections('audio_layers'))));
    const visualLayers = Array.from(new Set(nodeVisuals.concat(collectAdvancedSelections('visual_layers'))));
    const linked = applyLayerLinking(audioLayers, visualLayers, linkAV);

    const routeContext = getGastownRouteContext(linked.visual_layers) || {
      route_id: routeState.routePresetId,
      node_id: node ? node.id : 'station_threshold',
      look_bias: routeState.lookBias,
      route_mode: routeState.mode,
      traversal_nodes: routeState.traversal.slice(),
      source: 'gastown-route-preset-v2'
    };
    const worldContext = null;

    return Object.assign({}, base, {
      link_av: linkAV,
      route_mode: routeState.mode,
      route_preset: routeState.routePresetId,
      active_node: node ? node.id : 'station_threshold',
      look_bias: routeState.lookBias,
      traversal_nodes: routeState.traversal.slice(),
      audio_layers: linked.audio_layers,
      visual_layers: linked.visual_layers,
      gastown_route_context: routeContext || undefined,
      vancouver_world_context: worldContext || undefined
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
    if (advancedPanelEl) advancedPanelEl.open = true;
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
      visuals.seek(0);
    }

    if (routeState.mode === 'explore') {
      if (visuals) {
        visuals.play(0);
        transport.registerStop(() => visuals.stop());
      }
      if (audioFeedback) audioFeedback.textContent = 'Explore preview playing (visual route look-through).';
      return;
    }

    const playback = await engine.preview(currentPackage);
    transport.previewMeta = playback;
    transport.playing = true;

    if (visuals && fullMode) {
      visuals.play(-playback.preroll);
      transport.registerStop(() => visuals.stop());
    }
    transport.registerStop(() => engine.stop());

    if (audioFeedback) {
      audioFeedback.textContent = routeState.mode === 'record'
        ? 'Record mode preview playing.'
        : (fullMode ? 'Synchronized preview playing.' : 'Sound-only preview playing.');
    }
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
    const sequenceTag = (routeState.traversal || []).join('-') || 'route';
    a.download = `asmr-lab-${routeState.routePresetId}-${sequenceTag}-1920x1080.${result.extension}`;
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
      routeState.routePresetId = QA_PRESET.route_preset || 'gastown_water_street_walk';
      routeState.currentNodeId = QA_PRESET.active_node || 'station_threshold';
      routeState.traversal = [routeState.currentNodeId];
      setLookBias(QA_PRESET.look_bias || 'center');
      setMode('compose');
      syncNodeMeta();
      setStatus('Route preset loaded for Gastown Water Street walk.');
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
        const sequenceTag = (routeState.traversal || []).join('-') || 'route';
        a.download = `asmr-lab-${routeState.routePresetId}-${sequenceTag}.wav`;
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

  resultsTabButtons.forEach((btn) => {
    btn.addEventListener('click', () => setResultsTab(btn.dataset.resultsTab));
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
  if (advancedPanelEl) advancedPanelEl.open = false;
  if (debugInspectorsEl) debugInspectorsEl.open = false;

  renderVisualAtlas();
  renderSoundInspector();
  setInspectorTab('visual');
  updateVisualMeta(null, "stopped");
  updateSoundStatus(null, "stopped");
  setMode('compose');
  setLookBias(routeState.lookBias);
  syncNodeMeta();

  modeChips.forEach((chip) => {
    chip.addEventListener('click', () => {
      setMode(chip.dataset.asmrMode);
      syncNodeMeta();
    });
  });

  lookChips.forEach((chip) => {
    chip.addEventListener('click', () => {
      setLookBias(chip.dataset.lookBias);
      syncNodeMeta();
    });
  });

  if (prevNodeBtn) prevNodeBtn.addEventListener('click', () => moveNode(-1));
  if (nextNodeBtn) nextNodeBtn.addEventListener('click', () => moveNode(1));

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

  loadSceneSeedData();

  window.addEventListener('resize', function () { if (visuals) visuals.resize(); });
})();

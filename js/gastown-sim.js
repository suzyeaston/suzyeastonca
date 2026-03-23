
(function (window, document) {
  'use strict';

  const config = window.seGastownSim || {};
  const app = document.getElementById('gastown-sim-app');
  const statusEl = app ? app.querySelector('[data-sim-status]') : null;

  function failStartupDependency(message) {
    if (statusEl) {
      statusEl.textContent = message;
    }
    if (window.console && typeof window.console.error === 'function') {
      window.console.error('[Gastown Sim] ' + message);
    }
  }

  if (!app) {
    return;
  }

  const THREE = window.THREE;
  if (!THREE) {
    failStartupDependency('Missing THREE global. Simulator could not start.');
    return;
  }
  if (!window.GastownWorldLoader) {
    failStartupDependency('Missing world loader. Simulator could not start.');
    return;
  }
  if (!window.GastownBuildingNormalizer) {
    failStartupDependency('Missing building normalizer. Simulator could not start.');
    return;
  }
  const hasHowler = !!(window.Howl && window.Howler);

  const canvasWrap = app.querySelector('[data-sim-canvas]');
  const pointerStatusEl = app.querySelector('[data-sim-pointer-status]');
  const worldStatusEl = app.querySelector('[data-sim-world-status]');
  const landmarkEl = app.querySelector('[data-sim-landmark]');
  const pauseBtn = app.querySelector('[data-action="pause"]');
  const resetBtn = app.querySelector('[data-action="reset"]');
  const timeOfDaySelect = app.querySelector('[name="time-of-day"]');
  const weatherSelect = app.querySelector('[name="weather"]');
  const moodSelect = app.querySelector('[name="mood"]');
  const debugToggle = app.querySelector('[data-action="debug-toggle"]');
  const debugPanel = app.querySelector('[data-debug-panel]');
  const routeDebugOverlay = app.querySelector('[data-route-debug-overlay]');
  const minimapCanvas = app.querySelector('[data-sim-minimap]');
  const minimapLandmarkEl = app.querySelector('[data-sim-minimap-landmark]');
  const minimapModeStatusEl = app.querySelector('[data-sim-minimap-mode-status]');
  const minimapContextEl = app.querySelector('[data-sim-minimap-context]');
  const routeSegmentEl = app.querySelector('[data-sim-route-segment]');
  const minimapZoomInBtn = app.querySelector('[data-action="minimap-zoom-in"]');
  const minimapZoomOutBtn = app.querySelector('[data-action="minimap-zoom-out"]');
  const minimapModeBtn = app.querySelector('[data-action="minimap-mode-toggle"]');
  const osmAttributionEl = app.querySelector('[data-gastown-osm-attribution]');
  const interactPromptEl = app.querySelector('[data-sim-interact-prompt]');
  const renameWalkerBtn = app.querySelector('[data-action="rename-walker"]');
  const walkerNameDisplayEl = app.querySelector('[data-walker-name-display]');
  const walkerNameOverlayEl = app.querySelector('[data-name-overlay]');
  const walkerNameInputEl = app.querySelector('[data-walker-name-input]');
  const walkerStartBtn = app.querySelector('[data-action="walker-start"]');
  const walkerSkipBtn = app.querySelector('[data-action="walker-skip"]');
  const questStatusEl = app.querySelector('[data-sim-quest-status]');
  const objectiveEl = app.querySelector('[data-sim-objective]');
  const nextStepEl = app.querySelector('[data-sim-next-step]');
  const routeScoreEl = app.querySelector('[data-sim-route-score]');
  const collectiblesLogEl = app.querySelector('[data-sim-collectibles-log]');
  const journalEl = app.querySelector('[data-sim-journal]');
  const threadButtons = app.querySelectorAll('[data-thread-mode]');
  const tutorialOverlayEl = app.querySelector('[data-tutorial-overlay]');
  const tutorialOpenBtn = app.querySelector('[data-action="tutorial-open"]');
  const tutorialCloseEls = app.querySelectorAll('[data-action="tutorial-close"]');
  const tutorialStartBtn = app.querySelector('[data-action="tutorial-start"]');
  const minimapLegendEl = app.querySelector('[data-sim-minimap-legend]');
  const minimapTooltipEl = app.querySelector('[data-sim-minimap-tooltip]');
  const compassEl = app.querySelector('[data-sim-compass]');
  const lowGraphicsToggle = app.querySelector('[data-setting="low-graphics"]');
  const reopenTutorialToggle = app.querySelector('[data-setting="reopen-tutorial"]');
  const dialogModalEl = app.querySelector('[data-dialog-modal]');
  const dialogTitleEl = app.querySelector('[data-dialog-title]');
  const dialogBodyEl = app.querySelector('[data-dialog-body]');
  const dialogActionsDynamicEl = app.querySelector('[data-dialog-actions-dynamic]');
  const dialogAudioStatusEl = app.querySelector('[data-dialog-audio-status]');
  const dialogCloseEls = app.querySelectorAll('[data-dialog-close]');
  const dialogFallbackCloseEl = app.querySelector('[data-dialog-close-fallback]');
  const dialogUtils = window.GastownDialog || {};
  const normalizeDialogEntry = typeof dialogUtils.normalizeDialogEntry === 'function'
    ? dialogUtils.normalizeDialogEntry
    : function fallbackNormalizeDialogEntry(entry, options) {
      const fallbackTitle = (options && options.fallbackTitle) || 'Gastown local';
      const missingLine = (options && options.missingLine) || 'This local voice does not have dialog copy yet.';
      const unavailableLine = (options && options.unavailableLine) || 'Dialog data unavailable.';
      const defaultCloseLabel = (options && options.defaultCloseLabel) || 'Back to walk';
      const hasEntry = entry && typeof entry === 'object' && !Array.isArray(entry);
      const lines = hasEntry && Array.isArray(entry.lines) && entry.lines.length ? entry.lines : [hasEntry ? missingLine : unavailableLine];
      return {
        title: (hasEntry && entry.title) || fallbackTitle,
        lines,
        actions: hasEntry && Array.isArray(entry.actions) ? entry.actions : [{ type: 'close', label: defaultCloseLabel }],
        hasCustomActions: !!(hasEntry && Array.isArray(entry.actions) && entry.actions.length),
      };
    };

  const state = {
    world: null,
    isRunning: false,
    move: { forward: false, backward: false, left: false, right: false },
    turn: { left: false, right: false },
    gait: { precise: false, traverse: false },
    yaw: 0,
    pitch: 0,
    cameraMode: 'street',
    streetPointerLockRequested: false,
    overviewAltitude: 35,
    velocity: new THREE.Vector3(),
    lastSafePosition: new THREE.Vector3(),
    debugEnabled: new URLSearchParams(window.location.search).get('gastownDebug') === '1',
    activeWeather: config.defaultWeather || 'clear',
    activeTimeOfDay: config.defaultTimeOfDay || 'morning',
    activeMood: config.defaultMood || 'calm',
    sounds: {
      beds: {},
      zoneBeds: {},
      rainLoop: null,
      npcAudio: {},
      steamClockChime: null,
    },
    steamClockState: {
      anchor: null,
      plume: null,
      toneReady: false,
      enabled: false,
      lastChimeAt: -Infinity,
      nextChimeAt: 0,
      proximityCooldownUntil: 0,
      lastPlayerNear: false,
    },
    dialogData: {},
    activeDialogNpcId: '',
    activeDialogEntry: null,
    dialogLastFocusEl: null,
    npcConversationCache: {},
    npcConversationPending: false,
    hoveredNpcId: '',
    audioContext: null,
    audioUnlocked: false,
    npcVoiceTimer: null,
    lastNpcVoiceAt: -Infinity,
    npcVoiceIndex: 0,
    walkerName: 'Walker',
    hasInteracted: false,
    npcSpeechCache: {},
    npcSpeechController: null,
    npcSpeechAudio: null,
    resumePointerAfterDialogClose: false,
    startupSting: { pending: null, queued: false, hasPlayed: false, remoteRequested: false, currentVoiceAudio: null },
    clockTimer: null,
    boundaryNoticeTimer: null,
    currentMicroAreaId: '',
    audioWarningIssued: false,
    worldBuildStatus: null,
    npcs: [],
    props: [],
    guideQuestTriggered: false,
    motion: {
      currentSpeed: 0,
      smoothedSpeed: 0,
      surface: 'sidewalk',
      bobPhase: 0,
      bobOffset: 0,
      swayOffset: 0,
      footstepTimer: 0,
      lastFootstepAt: -Infinity,
      interactionPulse: 0,
    },
    discoveries: { props: {}, landmarks: {}, lastContextKey: '' },
    tutorial: { seen: false, lastMKeyAt: 0 },
    lowGraphics: false,
    quest: { active: false, completed: false, rewardPending: false, items: [], chains: {}, activeChainId: 'orientation' },
    currentThread: 'drift',
    progression: {
      discoveredLandmarks: {},
      talkedNpcIds: {},
      journalEntries: [],
      unlockedStories: {},
      routeCompletionScore: 0,
      nextHint: null,
      openingObjectiveStartedAt: 0,
    },
    band: {
      contextKey: '',
      arrangements: [],
      arrangementIndex: 0,
      scheduler: null,
      currentCycleStartedAt: 0,
      currentBeatCursor: 0,
      activeArrangement: null,
      watchdogs: [],
      lastWatchdogSweepAt: 0,
      lastFamily: '',
      familyBias: '',
      bus: null,
      synths: null,
      active: false,
      pendingRequest: null,
      disposalToken: 0,
    },
  };

  const DEFAULT_EYE_HEIGHT = 1.7;
  const WALKER_NAME_STORAGE_KEY = 'gastownWalkerName';
  const NPC_SPEECH_CACHE_LIMIT = 18;
  const BAND_ARRANGEMENT_CACHE_PREFIX = 'gastownBandArrangement:';
  const BAND_MAX_NOTE_SECONDS = 2.8;
  const STARTUP_STING_FALLBACK_SPEC = {
    tempo: 96,
    key: 'E minor',
    duration_bars: 2,
    chord_hits: [
      { bar: 1, beat: 1, notes: ['E3', 'G3', 'B3'], length_beats: 1.5, velocity: 0.84 },
      { bar: 2, beat: 1, notes: ['D3', 'G3', 'B3'], length_beats: 1.25, velocity: 0.8 }
    ],
    bass_notes: [
      { bar: 1, beat: 1, note: 'E2', length_beats: 1, velocity: 0.94 },
      { bar: 1, beat: 3, note: 'E2', length_beats: 0.75, velocity: 0.82 },
      { bar: 2, beat: 1, note: 'G2', length_beats: 1, velocity: 0.88 },
      { bar: 2, beat: 3, note: 'D2', length_beats: 0.75, velocity: 0.76 }
    ],
    drum_pattern: {
      kick: [{ bar: 1, beat: 1 }, { bar: 1, beat: 3.5 }, { bar: 2, beat: 1 }, { bar: 2, beat: 3 }],
      snare: [{ bar: 1, beat: 2.75 }, { bar: 2, beat: 2.75 }],
      hat: [{ bar: 1, beat: 1.5 }, { bar: 1, beat: 2 }, { bar: 1, beat: 2.5 }, { bar: 1, beat: 4 }, { bar: 2, beat: 1.5 }, { bar: 2, beat: 2 }, { bar: 2, beat: 2.5 }, { bar: 2, beat: 4 }]
    },
    lead_phrase: [
      { bar: 1, beat: 1.5, note: 'B4', length_beats: 0.5, velocity: 0.82, articulation: 'growl' },
      { bar: 1, beat: 2, note: 'D5', length_beats: 0.5, velocity: 0.78, articulation: 'bend' },
      { bar: 1, beat: 2.5, note: 'E5', length_beats: 1, velocity: 0.92, articulation: 'hold' },
      { bar: 2, beat: 1.5, note: 'G5', length_beats: 0.5, velocity: 0.84, articulation: 'stab' },
      { bar: 2, beat: 2, note: 'E5', length_beats: 0.75, velocity: 0.86, articulation: 'fall' },
      { bar: 2, beat: 3, note: 'B4', length_beats: 1, velocity: 0.76, articulation: 'hold' }
    ],
    style_description: 'gritty urban rock-adjacent welcome sting with a stylized sax lead; brisk, punchy, not lounge jazz'
  };
  const FALLBACK_SURFACE_PRESETS = {
    road: { color: 0x1b2632, roughness: 0.96, metalness: 0.05 },
    sidewalk: { color: 0x655c51, roughness: 0.98, metalness: 0.02 },
    plaza: { color: 0x726150, roughness: 0.96, metalness: 0.02 },
    curb: { color: 0x9e8d78, roughness: 1, metalness: 0 },
    lane: { color: 0x54493d, roughness: 0.98, metalness: 0.01 },
    lampGlass: { color: 0xf5d9a2, emissive: 0xffbe62, roughness: 0.22, metalness: 0.04 },
    lampHalo: { color: 0xffcb83, opacity: 0.16 },
  };
  const ART_DIRECTION = {
    label: 'stylized realism with cinematic Vancouver rain-lighting',
    streetReflectionBoost: 0.22,
    facadeGlowBoost: 0.2,
  };

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(68, 1, 0.08, 700);
  camera.position.set(0, DEFAULT_EYE_HEIGHT, 0);
  const renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.shadowMap.enabled = true;
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.03;
  canvasWrap.appendChild(renderer.domElement);

  const player = new THREE.Object3D();
  player.add(camera);
  scene.add(player);

  const ambient = new THREE.AmbientLight(0x7f90b2, 0.72);
  scene.add(ambient);
  const keyLight = new THREE.DirectionalLight(0xaab6cb, 0.74);
  keyLight.position.set(10, 22, 9);
  scene.add(keyLight);
  const fillLight = new THREE.DirectionalLight(0x5f7695, 0.34);
  fillLight.position.set(-14, 12, -9);
  scene.add(fillLight);
  const lightningLight = new THREE.DirectionalLight(0xe7efff, 0);
  lightningLight.position.set(4, 30, 6);
  scene.add(lightningLight);
  const rimLight = new THREE.DirectionalLight(0x6d8cc1, 0.18);
  rimLight.position.set(-6, 8, 18);
  scene.add(rimLight);

  const rainGroup = new THREE.Group();
  const cloudGroup = new THREE.Group();
  const worldGroup = new THREE.Group();
  const debugGroup = new THREE.Group();
  debugGroup.visible = state.debugEnabled;
  scene.add(worldGroup);
  scene.add(rainGroup);
  scene.add(cloudGroup);
  scene.add(debugGroup);

  const visualState = {
    currentWeather: null,
    currentTime: null,
    currentMood: null,
    roadMaterial: null,
    sidewalkMaterial: null,
    laneMaterial: null,
    curbMaterial: null,
    buildingMaterials: [],
    landmarkVisuals: [],
    groundMeshes: [],
    groundTextures: {},
    instancedProps: [],
    propEntries: [],
    npcMeshes: [],
    routeGuideMaterials: [],
    weatherMaterials: [],
    lightningOverlay: null,
    steamClockVisuals: [],
    glassMaterials: [],
    emissiveMaterials: [],
    metalMaterials: [],
    reflectiveMaterials: [],
    fallbackSurfaceMaterials: {},
    lampVisuals: [],
  };

  function applyLandmarkVisualState(landmarkVisuals, lightingState) {
    if (!Array.isArray(landmarkVisuals) || !lightingState || typeof lightingState !== 'object') {
      return;
    }

    const mood = lightingState.mood || {};
    const weather = lightingState.weather || {};
    const timeOfDay = lightingState.timeOfDay || {};
    const markerGlow = (timeOfDay.landmarkGlow || 0.3) * (0.6 + ((mood.lightIntensity || 0) * 0.5));
    const haloOpacity = 0.08 + ((timeOfDay.landmarkGlow || 0.3) * 0.18);
    const reflectionOpacity = 0.12 + ((timeOfDay.landmarkGlow || 0.3) * 0.18) + ((weather.rainIntensity || 0) * 0.12);

    landmarkVisuals.forEach((landmarkVisual) => {
      if (!landmarkVisual || typeof landmarkVisual !== 'object') {
        return;
      }
      if (landmarkVisual.markerMaterial) {
        landmarkVisual.markerMaterial.emissiveIntensity = markerGlow;
      }
      if (landmarkVisual.haloMaterial) {
        landmarkVisual.haloMaterial.opacity = haloOpacity;
      }
      if (landmarkVisual.reflectionMaterial) {
        landmarkVisual.reflectionMaterial.opacity = reflectionOpacity;
      }
    });
  }

  const LOOK_PITCH_LIMIT = Math.PI / 2.25;
  const WHEEL_PITCH_SENSITIVITY = 0.00085;
  const KEYBOARD_PITCH_STEP = 0.045;
  const STREET_CAMERA_NEAR = 0.08;
  const STREET_CAMERA_FAR = 700;
  const OVERVIEW_CAMERA_FAR = 1600;
  const OVERVIEW_CAMERA_PITCH = THREE.MathUtils.degToRad(-60);
  const OVERVIEW_ALTITUDE_MIN = 18;
  const OVERVIEW_ALTITUDE_MAX = 90;
  const OVERVIEW_ZOOM_STEP = 2.4;

  function clampPitch(value) {
    return Math.max(-LOOK_PITCH_LIMIT, Math.min(LOOK_PITCH_LIMIT, value));
  }

  function adjustPitch(delta) {
    state.pitch = clampPitch(state.pitch + delta);
    applyStreetCameraPose();
  }

  function canUseLookFallbackControls() {
    return state.isRunning || document.pointerLockElement === renderer.domElement || document.activeElement === renderer.domElement;
  }

  const minimapState = {
    ctx: minimapCanvas ? minimapCanvas.getContext('2d') : null,
    width: minimapCanvas ? minimapCanvas.width : 0,
    height: minimapCanvas ? minimapCanvas.height : 0,
    worldMetrics: null,
    nearestNode: null,
    nearestGuidance: null,
    zoom: 1.2,
    minZoom: 0.8,
    maxZoom: 3.2,
    zoomStep: 0.2,
    mode: 'north-up',
  };

  function updateSize() {
    const width = canvasWrap.clientWidth;
    const height = Math.max(440, Math.min(window.innerHeight * 0.72, 760));
    renderer.setSize(width, height);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
  }

  function setStatus(text) {
    statusEl.textContent = text;
  }

  function getShortWalkerHudLine() {
    return state.walkerName.toUpperCase() + ' · ' + Math.round(state.progression.routeCompletionScore || 0) + '% mapped';
  }

  function getStartupWelcomeText(payload) {
    return (payload && (payload.welcomeText || payload.welcome_text)) || ('Welcome to the Gastown Simulator, ' + (state.walkerName || 'Walker') + '. Follow the street.');
  }

  function setQuestStatus(text) {
    if (questStatusEl) {
      questStatusEl.textContent = text;
    }
  }

  function normalizeWalkerName(value) {
    const normalized = String(value || '').replace(/\s+/g, ' ').trim();
    return normalized ? normalized.slice(0, 24) : 'Walker';
  }

  function setWalkerName(value, options) {
    state.walkerName = normalizeWalkerName(value);
    if (walkerNameDisplayEl) walkerNameDisplayEl.textContent = getShortWalkerHudLine();
    if (!(options && options.skipStore)) {
      try { window.localStorage.setItem(WALKER_NAME_STORAGE_KEY, state.walkerName); } catch (error) {}
    }
    if (walkerNameInputEl && !(options && options.skipInputSync)) walkerNameInputEl.value = state.walkerName === 'Walker' ? '' : state.walkerName;
  }

  function restoreWalkerName() {
    try {
      const saved = window.localStorage.getItem(WALKER_NAME_STORAGE_KEY);
      if (saved) {
        setWalkerName(saved, { skipStore: true });
        return true;
      }
    } catch (error) {}
    setWalkerName('Walker', { skipStore: true });
    return false;
  }

  function openWalkerNameOverlay(prefill) {
    if (!walkerNameOverlayEl) return;
    if (typeof prefill === 'string' && walkerNameInputEl) walkerNameInputEl.value = prefill === 'Walker' ? '' : prefill;
    walkerNameOverlayEl.removeAttribute('hidden');
    window.setTimeout(() => { if (walkerNameInputEl && typeof walkerNameInputEl.focus === 'function') walkerNameInputEl.focus(); }, 0);
  }

  function closeWalkerNameOverlay() {
    if (walkerNameOverlayEl) walkerNameOverlayEl.setAttribute('hidden', 'hidden');
  }

  function confirmWalkerName(value) {
    setWalkerName(value);
    closeWalkerNameOverlay();
    state.startupSting.hasPlayed = false;
    state.startupSting.remoteRequested = false;
    state.startupSting.queued = false;
    pushJournalEntry('On the street as ' + state.walkerName + '.');
    setStatus(state.walkerName.toUpperCase() + ' ready. Click in.');
    triggerStartupSting();
  }

  const QUEST_DEFINITIONS = {
    orientation: { label: 'Get moving', status: 'Get moving.' },
    scavenger: { label: 'Street details', status: 'Street details ready.' },
    survey: { label: 'Route survey', status: 'Route opens east.' },
    soundwalk: { label: 'Sound cues', status: 'Band ahead.' },
    stories: { label: 'Layered places', status: 'Keep walking past the obvious photo stop.' },
  };

  const THREAD_DEFINITIONS = {
    drift: {
      label: 'Drift',
      objective: 'Steam Clock ahead.',
      hint: 'Follow the first bit of street energy.',
      status: 'Free roam.',
      autoTarget: { type: 'landmark', id: 'water-cordova-seam' },
    },
    scavenger: {
      label: 'Street details',
      objective: 'Notice the small things.',
      hint: 'Log the next detail that catches your eye.',
      status: 'Street details ready.',
    },
    survey: {
      label: 'Survey',
      objective: 'Read the block as it opens up.',
      hint: 'Compare the station edge, storefronts, and the Steam Clock pull.',
      status: 'Route opens east.',
    },
    soundwalk: {
      label: 'Sound cues',
      objective: 'Follow the sax, crowd, and clock.',
      hint: 'Pause where the street sounds thickest.',
      status: 'Band ahead.',
    },
    stories: {
      label: 'Layered places',
      objective: 'Push past the obvious landmark.',
      hint: 'Look for the block behind the postcard shot.',
      status: 'Keep walking past the obvious photo stop.',
    },
  };

  function setObjective(text) {
    if (objectiveEl) objectiveEl.textContent = text;
  }

  function setNextStep(text) {
    if (nextStepEl) nextStepEl.textContent = text;
    if (minimapTooltipEl) minimapTooltipEl.textContent = text;
  }

  function getActiveThreadDefinition() {
    return THREAD_DEFINITIONS[state.currentThread] || THREAD_DEFINITIONS.drift;
  }

  function getPublicGoalText() {
    const thread = getActiveThreadDefinition();
    return thread.objective || 'Get your bearings and see what the street gives you.';
  }

  function getStreetModeStatusText() {
    return getPublicGoalText();
  }

  function pushJournalEntry(text) {
    if (!text) return;
    const entries = state.progression.journalEntries;
    if (entries.includes(text)) return;
    entries.unshift(text);
    state.progression.journalEntries = entries.slice(0, 5);
    renderProgressionUi();
  }

  function renderProgressionUi() {
    if (routeScoreEl) {
      routeScoreEl.textContent = Math.round(state.progression.routeCompletionScore) + '% mapped';
    }
    if (walkerNameDisplayEl) {
      walkerNameDisplayEl.textContent = getShortWalkerHudLine();
    }
    if (collectiblesLogEl) {
      const deduped = [];
      const seenKeys = new Set();
      getCollectibleProps().forEach((prop) => {
        const key = prop.collectibleKey || prop.collectibleLabel || prop.id;
        if (seenKeys.has(key)) return;
        seenKeys.add(key);
        deduped.push(prop);
      });
      collectiblesLogEl.innerHTML = deduped.map((prop) => '<li>' + (prop.collectibleLabel || prop.id) + ' — ' + (prop.collected ? 'logged' : 'not yet') + '</li>').join('');
      if (questStatusEl) questStatusEl.textContent = deduped.filter((prop) => prop.collected).length + ' details';
    }
    if (journalEl) {
      const entries = state.progression.journalEntries.length ? state.progression.journalEntries : ['Station threshold.'];
      journalEl.innerHTML = entries.slice(0, 4).map((entry) => '<li>' + entry + '</li>').join('');
    }
  }

  function getQuestChain(id) {
    if (!state.quest.chains[id]) {
      state.quest.chains[id] = { active: id === 'orientation', completed: false };
    }
    return state.quest.chains[id];
  }

  function getLandmarkByIdLocal(id) {
    return (state.world && state.world.landmarks || []).find((landmark) => landmark.id === id) || null;
  }

  function getNpcByRole(role) {
    return (state.npcs || []).find((npc) => npc.role === role) || null;
  }

  function getHintTargetPosition(target) {
    if (!target) return null;
    if (target.type === 'npc' && target.id) {
      const npc = (state.npcs || []).find((entry) => entry.id === target.id);
      return npc ? npc.position : null;
    }
    if (target.type === 'landmark' && target.id) return getLandmarkByIdLocal(target.id);
    if (target.type === 'collectible' && target.id) {
      const prop = (state.props || []).find((entry) => entry.id === target.id);
      return prop ? prop._position : null;
    }
    return null;
  }


  function setActiveThread(id, options) {
    const nextId = THREAD_DEFINITIONS[id] ? id : 'drift';
    const silent = !!(options && options.silent);
    state.currentThread = nextId;
    if (!silent) {
      setQuestStatus(THREAD_DEFINITIONS[nextId].status);
    }
    updateThreadButtonsUi();
    updateNextMeaningfulThing();
    updateMinimapLegend();
  }

  function updateThreadButtonsUi() {
    threadButtons.forEach((button) => {
      const isActive = button.getAttribute('data-thread-mode') === state.currentThread;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function unlockOrientationIfNeeded(reason) {
    const chain = getQuestChain('orientation');
    if (chain.completed) {
      return;
    }
    completeQuestById('orientation', reason || 'Orientation complete: you found your own way into the route.');
  }

  function autoSelectThreadFromContext() {
    const context = getPlayerRouteContext();
    if (!context || state.currentThread !== 'drift') {
      return;
    }
    if (context.key === 'steam_clock') {
      setActiveThread('soundwalk', { silent: true });
    } else if (context.key === 'maple_tree_square') {
      setActiveThread('stories', { silent: true });
    } else if (context.key === 'water_street' || context.key === 'cambie_rise') {
      setActiveThread('survey', { silent: true });
    }
  }

  function startQuestById(id) {
    const chain = getQuestChain(id);
    chain.active = true;
    state.quest.activeChainId = id;
    setActiveThread(id, { silent: true });
    if (id === 'scavenger') {
      const items = getCollectibleProps().map((prop) => ({ key: prop.collectibleKey || prop.id, label: prop.collectibleLabel || prop.id, found: !!prop.collected, propId: prop.id }));
      state.quest.active = true;
      state.quest.completed = items.length && items.every((item) => item.found);
      state.quest.items = items;
      setQuestStatus(items.filter((item) => item.found).length + ' details');
      renderDialogBody(['Street details ready.', 'Log the newspaper box, plaque, and painted brick.']);
    } else {
      setQuestStatus((QUEST_DEFINITIONS[id] || {}).status || 'Updated.');
      renderDialogBody([((QUEST_DEFINITIONS[id] || {}).label || 'Note') + '.']);
    }
    updateNextMeaningfulThing();
  }

  function completeQuestById(id, message) {
    const chain = getQuestChain(id);
    if (chain.completed) return;
    chain.completed = true;
    chain.active = false;
    if (id === 'scavenger') { state.quest.completed = true; state.quest.active = false; }
    if (message) setQuestStatus(message);
    pushJournalEntry(message || (((QUEST_DEFINITIONS[id] || {}).label || 'Quest') + ' completed.'));
    updateNextMeaningfulThing();
  }

  function discoverLandmark(landmark) {
    if (!landmark || state.progression.discoveredLandmarks[landmark.id]) return;
    state.progression.discoveredLandmarks[landmark.id] = true;
    setStatus('Logged: ' + landmark.label + '.');
    pushJournalEntry('Discovered ' + landmark.label + ': ' + (landmark.discoveryNote || landmark.cue || 'New route context logged.'));
    if (landmark.storyUnlock) {
      state.progression.unlockedStories[landmark.storyUnlock] = true;
    }
    updateNextMeaningfulThing();
  }

  function updateRouteCompletionScore() {
    if (!state.world || !Array.isArray(state.world.landmarks)) return;
    const total = state.world.landmarks.length || 1;
    const seen = Object.keys(state.progression.discoveredLandmarks).length;
    state.progression.routeCompletionScore = Math.min(100, (seen / total) * 100);
  }

  function updateLandmarkDiscoveries() {
    if (!state.world || !Array.isArray(state.world.landmarks)) return;
    state.world.landmarks.forEach((landmark) => {
      const radius = landmark.discoveryRadius || landmark.radius || 10;
      if (Math.hypot(landmark.x - player.position.x, landmark.z - player.position.z) <= radius) {
        discoverLandmark(landmark);
      }
    });
  }

  function updateQuestChainsFromProgress() {
    const movedOffThreshold = Math.hypot(player.position.x - state.lastSafePosition.x, player.position.z - state.lastSafePosition.z) > 3.5;
    if (movedOffThreshold || Object.keys(state.progression.discoveredLandmarks).length || Object.keys(state.progression.talkedNpcIds).length || state.currentThread !== 'drift') {
      unlockOrientationIfNeeded('Orientation complete: you found your own lead through the route.');
    }
    const survey = getQuestChain('survey');
    if (survey.active && !survey.completed && state.progression.discoveredLandmarks['water-street-mid-block'] && state.progression.discoveredLandmarks['cambie-rise-continuation']) {
      completeQuestById('survey', 'Route read complete.');
    }
    const stories = getQuestChain('stories');
    const busker = getNpcByRole('busker');
    if (stories.active && !stories.completed && state.progression.discoveredLandmarks['steam-clock'] && state.progression.discoveredLandmarks['maple-tree-square-edge'] && busker && state.progression.talkedNpcIds[busker.id]) {
      completeQuestById('stories', 'You read past the postcard version.');
    }
    if (state.quest.items.length && state.quest.items.every((item) => item.found)) {
      completeQuestById('scavenger', 'All details logged.');
    }
  }

  function updateNextMeaningfulThing() {
    const busker = getNpcByRole('busker');
    const chainScavenger = getQuestChain('scavenger');
    const chainSurvey = getQuestChain('survey');
    const chainSoundwalk = getQuestChain('soundwalk');
    const chainStories = getQuestChain('stories');
    const activeThread = THREAD_DEFINITIONS[state.currentThread] || THREAD_DEFINITIONS.drift;
    let objective = activeThread.objective;
    let hint = { text: activeThread.hint, target: activeThread.autoTarget || null };

    if (state.currentThread === 'scavenger' && chainScavenger.active && !chainScavenger.completed) {
      const nextProp = getCollectibleProps().find((prop) => !prop.collected);
      if (nextProp) {
        hint = { text: 'Log: ' + (nextProp.collectibleLabel || nextProp.id) + '.', target: { type: 'collectible', id: nextProp.id } };
      }
    } else if (state.currentThread === 'survey' && chainSurvey.active && !chainSurvey.completed) {
      hint = !state.progression.discoveredLandmarks['water-street-mid-block']
        ? { text: 'Mid-block ahead.', target: { type: 'landmark', id: 'water-street-mid-block' } }
        : { text: 'Cambie rise ahead.', target: { type: 'landmark', id: 'cambie-rise-continuation' } };
    } else if (state.currentThread === 'soundwalk' && chainSoundwalk.active && !chainSoundwalk.completed) {
      if (!state.progression.discoveredLandmarks['steam-clock']) {
        hint = { text: 'Steam Clock ahead.', target: { type: 'landmark', id: 'steam-clock' } };
      } else if (busker) {
        hint = { text: 'Band ahead.', target: { type: 'npc', id: busker.id } };
      }
    } else if (state.currentThread === 'stories' && chainStories.active && !chainStories.completed) {
      if (!state.progression.discoveredLandmarks['maple-tree-square-edge']) {
        hint = { text: 'Past the clock.', target: { type: 'landmark', id: 'maple-tree-square-edge' } };
      } else if (busker) {
        hint = { text: 'Check back with the band.', target: { type: 'npc', id: busker.id } };
      }
    } else if ((state.progression.routeCompletionScore || 0) < 100) {
      const nextLandmark = (state.world && state.world.landmarks || []).find((landmark) => !state.progression.discoveredLandmarks[landmark.id]);
      if (nextLandmark) {
        hint = { text: nextLandmark.label + ' ahead.', target: { type: 'landmark', id: nextLandmark.id } };
      }
    } else {
      objective = 'Keep roaming.';
      hint = { text: 'Keep roaming.', target: null };
    }

    state.progression.nextHint = hint;
    setObjective(objective);
    setNextStep(hint.text);
    renderProgressionUi();
  }


  function openTutorialOverlay() {
    if (!tutorialOverlayEl) return;
    tutorialOverlayEl.removeAttribute('hidden');
    tutorialOverlayEl.setAttribute('aria-hidden', 'false');
    setStatus('Help open.');
  }

  function closeTutorialOverlay() {
    if (!tutorialOverlayEl) return;
    tutorialOverlayEl.setAttribute('hidden', 'hidden');
    tutorialOverlayEl.setAttribute('aria-hidden', 'true');
    try { window.localStorage.setItem('gastownTutorialSeen', '1'); } catch (error) {}
    state.tutorial.seen = true;
  }

  function shouldShowTutorialOnLoad() {
    try {
      const forced = window.localStorage.getItem('gastownTutorialReopen') === '1';
      const seen = window.localStorage.getItem('gastownTutorialSeen') === '1';
      return forced || !seen;
    } catch (error) {
      return true;
    }
  }

  function setLowGraphicsMode(enabled) {
    state.lowGraphics = !!enabled;
    renderer.shadowMap.enabled = !state.lowGraphics;
    keyLight.castShadow = !state.lowGraphics;
    fillLight.castShadow = false;
    if (lowGraphicsToggle) lowGraphicsToggle.checked = state.lowGraphics;
    try { window.localStorage.setItem('gastownLowGraphics', state.lowGraphics ? '1' : '0'); } catch (error) {}
    if (state.world) {
      rebuildRain(((state.world.weatherPresets[state.activeWeather] || {}).rainIntensity || 0) * ((state.world.timeOfDayPresets[state.activeTimeOfDay] || {}).rainVisibility || 1));
      refreshNpcPopulation();
    }
  }

  function flashBoundaryStatus(text) {
    setStatus(text);
    if (state.boundaryNoticeTimer) {
      clearTimeout(state.boundaryNoticeTimer);
    }
    state.boundaryNoticeTimer = setTimeout(() => {
      if (!state.isRunning || state.cameraMode !== 'street') return;
      setStatus(getPublicGoalText());
    }, 1900);
  }

  function getCameraModeLabel() {
    return state.cameraMode === 'overview' ? 'Overview' : 'Street';
  }

  function setPointerStatus(text) {
    if (pointerStatusEl) {
      pointerStatusEl.textContent = text;
    }
  }

  function getStartupStingFallbackSpec() {
    return JSON.parse(JSON.stringify(STARTUP_STING_FALLBACK_SPEC));
  }

  function sanitizeStartupStingSpec(spec) {
    const fallback = getStartupStingFallbackSpec();
    if (!spec || typeof spec !== 'object') return fallback;
    const sanitizeEvent = (event, chordMode) => {
      if (!event || typeof event !== 'object') return null;
      const base = {
        bar: Math.max(1, Math.min(3, Number(event.bar) || 1)),
        beat: Math.max(1, Math.min(4, Number(event.beat) || 1)),
        length_beats: Math.max(0.25, Math.min(2.5, Number(event.length_beats) || 0.5)),
        velocity: Math.max(0.35, Math.min(1, Number(event.velocity) || 0.8))
      };
      if (chordMode) {
        const notes = Array.isArray(event.notes) ? event.notes.map((note) => String(note || '').trim()).filter(Boolean).slice(0, 4) : [];
        if (!notes.length) return null;
        base.notes = notes;
      } else {
        const note = String(event.note || '').trim();
        if (!note) return null;
        base.note = note;
      }
      if (event.articulation) base.articulation = String(event.articulation);
      return base;
    };
    const specOut = {
      tempo: Math.max(80, Math.min(132, Number(spec.tempo) || fallback.tempo)),
      key: String(spec.key || fallback.key),
      duration_bars: Math.max(2, Math.min(3, Number(spec.duration_bars) || fallback.duration_bars)),
      chord_hits: (Array.isArray(spec.chord_hits) ? spec.chord_hits : []).map((event) => sanitizeEvent(event, true)).filter(Boolean),
      bass_notes: (Array.isArray(spec.bass_notes) ? spec.bass_notes : []).map((event) => sanitizeEvent(event, false)).filter(Boolean),
      drum_pattern: { kick: [], snare: [], hat: [] },
      lead_phrase: (Array.isArray(spec.lead_phrase) ? spec.lead_phrase : []).map((event) => sanitizeEvent(event, false)).filter(Boolean),
      style_description: String(spec.style_description || fallback.style_description)
    };
    ['kick', 'snare', 'hat'].forEach((piece) => {
      specOut.drum_pattern[piece] = (spec.drum_pattern && Array.isArray(spec.drum_pattern[piece]) ? spec.drum_pattern[piece] : []).map((event) => ({
        bar: Math.max(1, Math.min(3, Number(event && event.bar) || 1)),
        beat: Math.max(1, Math.min(4, Number(event && event.beat) || 1))
      }));
      if (!specOut.drum_pattern[piece].length) specOut.drum_pattern[piece] = fallback.drum_pattern[piece];
    });
    if (!specOut.chord_hits.length) specOut.chord_hits = fallback.chord_hits;
    if (!specOut.bass_notes.length) specOut.bass_notes = fallback.bass_notes;
    if (!specOut.lead_phrase.length) specOut.lead_phrase = fallback.lead_phrase;
    return specOut;
  }

  function decodeBase64ToUint8Array(base64) {
    const binary = window.atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i);
    return bytes;
  }

  async function ensureToneReady() {
    const Tone = getTone();
    if (!Tone) return null;
    try {
      if (typeof Tone.start === 'function') await Tone.start();
      state.audioUnlocked = true;
      return Tone;
    } catch (error) {
      return null;
    }
  }

  async function fetchStartupStingPayload() {
    if (!config.startupStingEndpoint) return null;
    try {
      const response = await window.fetch(config.startupStingEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce || '' },
        body: JSON.stringify({ walkerName: state.walkerName || 'Walker' })
      });
      if (!response.ok) throw new Error('Startup sting request failed.');
      return await response.json();
    } catch (error) {
      if (window.console && window.console.warn) window.console.warn('[Gastown Sim] Startup sting endpoint unavailable.', error);
      return null;
    }
  }

  function scheduleStartupVoice(payload, Tone, destination, startedAt) {
    if (!payload || !payload.audioBase64 || !Tone) return;
    try {
      const player = new Tone.Player({ autostart: false, fadeOut: 0.12 }).connect(destination);
      player.volume.value = -7;
      player.load(URL.createObjectURL(new Blob([decodeBase64ToUint8Array(payload.audioBase64)], { type: payload.voiceMimeType || 'audio/wav' }))).then(() => {
        player.start(startedAt + 0.08);
        state.startupSting.currentVoiceAudio = player;
      }).catch(() => {});
    } catch (error) {}
  }

  async function playStartupStingNow(payload) {
    const Tone = await ensureToneReady();
    if (!Tone) return false;
    const spec = sanitizeStartupStingSpec(payload && (payload.musicSpec || payload.music_spec) ? (payload.musicSpec || payload.music_spec) : getStartupStingFallbackSpec());
    const master = new Tone.Gain(0.9).toDestination();
    const limiter = new Tone.Limiter(-4).connect(master);
    const chords = new Tone.PolySynth(Tone.Synth, { oscillator: { type: 'fatsawtooth' }, envelope: { attack: 0.01, decay: 0.18, sustain: 0.16, release: 0.55 }, volume: -15 }).connect(new Tone.Filter(1800, 'lowpass').connect(limiter));
    const bass = new Tone.MonoSynth({ oscillator: { type: 'square' }, filter: { Q: 1.4, type: 'lowpass', rolloff: -24 }, envelope: { attack: 0.01, decay: 0.14, sustain: 0.35, release: 0.4 }, filterEnvelope: { attack: 0.01, decay: 0.16, sustain: 0.3, release: 0.3, baseFrequency: 80, octaves: 2.1 }, volume: -9 }).connect(limiter);
    const lead = new Tone.DuoSynth({ voice0: { oscillator: { type: 'sawtooth' }, envelope: { attack: 0.02, decay: 0.1, sustain: 0.28, release: 0.32 } }, voice1: { oscillator: { type: 'triangle' }, envelope: { attack: 0.03, decay: 0.08, sustain: 0.18, release: 0.26 } }, harmonicity: 1.5, vibratoAmount: 0.18, volume: -9 }).connect(new Tone.FeedbackDelay('16n', 0.16).connect(new Tone.Filter(2200, 'bandpass').connect(limiter)));
    const kick = new Tone.MembraneSynth({ pitchDecay: 0.025, octaves: 5, envelope: { attack: 0.001, decay: 0.22, sustain: 0, release: 0.05 }, volume: -7 }).connect(limiter);
    const snare = new Tone.NoiseSynth({ noise: { type: 'pink' }, envelope: { attack: 0.001, decay: 0.16, sustain: 0 }, volume: -16 }).connect(limiter);
    const hat = new Tone.MetalSynth({ frequency: 260, envelope: { attack: 0.001, decay: 0.08, release: 0.01 }, harmonicity: 5.1, modulationIndex: 16, resonance: 1800, octaves: 1.6, volume: -26 }).connect(limiter);
    const beatToSeconds = 60 / spec.tempo;
    const eventTime = (bar, beat) => Tone.now() + ((Math.max(1, bar) - 1) * 4 + (Math.max(1, beat) - 1)) * beatToSeconds + 0.05;
    spec.chord_hits.forEach((event) => chords.triggerAttackRelease(event.notes, event.length_beats * beatToSeconds, eventTime(event.bar, event.beat), event.velocity));
    spec.bass_notes.forEach((event) => bass.triggerAttackRelease(event.note, event.length_beats * beatToSeconds, eventTime(event.bar, event.beat), event.velocity));
    spec.lead_phrase.forEach((event) => lead.triggerAttackRelease(event.note, event.length_beats * beatToSeconds, eventTime(event.bar, event.beat), event.velocity));
    (spec.drum_pattern.kick || []).forEach((event) => kick.triggerAttackRelease('C1', '8n', eventTime(event.bar, event.beat), 0.95));
    (spec.drum_pattern.snare || []).forEach((event) => snare.triggerAttackRelease('16n', eventTime(event.bar, event.beat), 0.6));
    (spec.drum_pattern.hat || []).forEach((event) => hat.triggerAttackRelease('16n', eventTime(event.bar, event.beat), 0.28));
    scheduleStartupVoice(payload, Tone, limiter, Tone.now());
    const cleanupAfter = ((((spec.duration_bars || 2) * 4) + 2) * beatToSeconds * 1000);
    window.setTimeout(() => { [chords, bass, lead, kick, snare, hat, limiter, master].forEach((node) => { try { if (node && typeof node.dispose === 'function') node.dispose(); } catch (error) {} }); }, cleanupAfter);
    return true;
  }

  async function consumeStartupStingQueue() {
    if (!state.startupSting.queued || state.startupSting.hasPlayed) return;
    const payload = state.startupSting.pending || { musicSpec: getStartupStingFallbackSpec(), audioBase64: '' };
    const played = await playStartupStingNow(payload);
    if (played) {
      state.startupSting.queued = false;
      state.startupSting.hasPlayed = true;
      setStatus(getStartupWelcomeText(payload));
    }
  }

  function queueStartupSting(payload) {
    state.startupSting.pending = payload || { musicSpec: getStartupStingFallbackSpec(), audioBase64: '' };
    state.startupSting.queued = true;
    consumeStartupStingQueue();
  }

  async function triggerStartupSting() {
    if (state.startupSting.remoteRequested) return;
    state.startupSting.remoteRequested = true;
    const payload = await fetchStartupStingPayload();
    queueStartupSting(payload || {
      ok: false,
      walkerName: state.walkerName || 'Walker',
      welcomeText: 'Welcome to the Gastown Simulator, ' + (state.walkerName || 'Walker') + '. Follow the street.',
      welcome_text: 'Welcome to the Gastown Simulator, ' + (state.walkerName || 'Walker') + '. Follow the street.',
      audioBase64: '',
      musicSpec: getStartupStingFallbackSpec(),
      music_spec: getStartupStingFallbackSpec(),
      musicSpecFallback: true,
      voiceFallback: true
    });
  }

  function syncCameraProjection(mode) {
    camera.near = STREET_CAMERA_NEAR;
    camera.far = mode === 'overview' ? OVERVIEW_CAMERA_FAR : STREET_CAMERA_FAR;
    camera.updateProjectionMatrix();
  }

  function applyStreetCameraPose() {
    if (camera.parent !== player) {
      player.add(camera);
    }
    camera.position.set(0, 1.7, 0);
    camera.rotation.set(state.pitch, 0, 0);
    player.rotation.y = state.yaw;
    syncCameraProjection('street');
  }

  function applyOverviewCameraPose() {
    if (camera.parent !== scene) {
      scene.add(camera);
    }
    camera.position.set(player.position.x, player.position.y + state.overviewAltitude, player.position.z);
    camera.rotation.set(OVERVIEW_CAMERA_PITCH, state.yaw, 0);
    syncCameraProjection('overview');
  }

  function updateCameraModeUi() {
    if (state.cameraMode === 'overview') {
      setStatus('Overview active.');
      setPointerStatus('Pointer unlocked. Overview camera detached above player.');
    } else if (state.isRunning) {
      setStatus(getPublicGoalText());
      if (state.cameraMode === 'street' && document.pointerLockElement === renderer.domElement) {
        setPointerStatus('Pointer live. Esc releases.');
      } else {
        setPointerStatus('Pointer live. Esc releases.');
      }
    } else {
      setStatus(getPublicGoalText());
      setPointerStatus('Pointer free. Click in.');
    }
  }

  function setCameraMode(mode) {
    if (mode === state.cameraMode) {
      updateCameraModeUi();
      updateMinimapLegend();
      if (shouldShowTutorialOnLoad()) { openTutorialOverlay(); if (reopenTutorialToggle) reopenTutorialToggle.checked = true; }
      return;
    }

    if (mode === 'overview') {
      state.streetPointerLockRequested = state.isRunning || document.pointerLockElement === renderer.domElement;
      state.isRunning = false;
      clearMovementInput();
      if (state.cameraMode === 'street' && document.pointerLockElement === renderer.domElement) {
        document.exitPointerLock();
      }
      state.cameraMode = 'overview';
      setInteractPrompt('');
      applyOverviewCameraPose();
      updateCameraModeUi();
      return;
    }

    state.cameraMode = 'street';
    applyStreetCameraPose();
    updateCameraModeUi();
    if (state.streetPointerLockRequested) {
      startSim();
    }
    state.streetPointerLockRequested = false;
  }

  function toggleCameraMode() {
    setCameraMode(state.cameraMode === 'street' ? 'overview' : 'street');
  }

  function setLandmark(text) {
    landmarkEl.textContent = text;
  }

  function isApproximateWorld(world) {
    return !!(world && world.meta && (
      world.meta.fallbackMode === 'working-gastown-corridor'
      || world.meta.runtimeFallbackActive
      || world.meta.isRealCivicBuild === false
    ));
  }

  function getOpenDataCoverageSummary(meta) {
    const inputs = meta && meta.openDataInputs && typeof meta.openDataInputs === 'object' ? meta.openDataInputs : {};
    const values = Object.keys(inputs).map((key) => !!inputs[key]);
    if (!values.length) return '0/0 civic inputs';
    const enabled = values.filter(Boolean).length;
    return enabled + '/' + values.length + ' civic inputs';
  }

  function setWorldModeStatus(world) {
    if (!world || !world.meta) return;
    const approximate = isApproximateWorld(world);
    const buildNote = (world.meta.buildNotes && world.meta.buildNotes[0]) || 'Working fallback Gastown corridor active.';
    const coverage = getOpenDataCoverageSummary(world.meta);
    state.worldBuildStatus = approximate
      ? 'Approximate fallback build: ' + buildNote + ' (' + coverage + ').'
      : 'Offline civic-data build loaded with ' + coverage + '.';

    if (worldStatusEl) {
      worldStatusEl.textContent = 'World data status: ' + state.worldBuildStatus;
      worldStatusEl.classList.toggle('is-approximate', approximate);
      worldStatusEl.classList.toggle('is-civic', !approximate);
    }

    if (approximate) {
      setStatus('Street loaded.');
    }
  }

  function updateAttribution(world) {
    if (!osmAttributionEl) {
      return;
    }
    const source = ((world && world.meta && world.meta.source) || '').toLowerCase();
    if (source.includes('openstreetmap')) {
      osmAttributionEl.removeAttribute('hidden');
    } else {
      osmAttributionEl.setAttribute('hidden', 'hidden');
    }
  }

  const textureLoader = new THREE.TextureLoader();
  const sharedRaycaster = new THREE.Raycaster();
  const lookTarget = new THREE.Vector3();
  const tempCameraWorldPosition = new THREE.Vector3();
  const tempMatrix = new THREE.Matrix4();
  const tempQuaternion = new THREE.Quaternion();
  const tempEuler = new THREE.Euler(0, 0, 0, 'YXZ');
  const tempScale = new THREE.Vector3();
  const WORLD_TEXTURE_REPEAT = {
    street: { x: 0.24, y: 0.24 },
    sidewalk: { x: 0.22, y: 0.22 },
  };
  const SURFACE_TEXTURES = {
    street: {
      map: 'cobblestone/albedo.svg',
      normalMap: 'cobblestone/normal.svg',
      roughnessMap: 'cobblestone/roughness.svg',
      aoMap: 'cobblestone/ao.svg',
    },
    sidewalk: {
      map: 'concrete-slabs/albedo.svg',
      normalMap: 'concrete-slabs/normal.svg',
      roughnessMap: 'concrete-slabs/roughness.svg',
      aoMap: 'concrete-slabs/ao.svg',
    },
  };
  const PROP_DEFINITIONS = {
    trash_bag: {
      geometry: new THREE.SphereGeometry(0.42, 10, 8),
      material: new THREE.MeshStandardMaterial({ color: 0x1f2328, roughness: 0.92, metalness: 0.06 }),
      y: 0.38,
    },
    cardboard_box: {
      geometry: new THREE.BoxGeometry(0.85, 0.58, 0.72),
      material: new THREE.MeshStandardMaterial({ color: 0x8f6a45, roughness: 0.9, metalness: 0.02 }),
      y: 0.29,
    },
    newspaper_box: {
      geometry: new THREE.BoxGeometry(0.72, 1.18, 0.72),
      material: new THREE.MeshStandardMaterial({ color: 0xa5392b, roughness: 0.62, metalness: 0.24, emissive: 0x2a0905, emissiveIntensity: 0.08 }),
      y: 0.59,
    },
    utility_box: {
      geometry: new THREE.BoxGeometry(1.18, 1.42, 0.68),
      material: new THREE.MeshStandardMaterial({ color: 0x646d76, roughness: 0.74, metalness: 0.34 }),
      y: 0.71,
    },
    bench: {
      geometry: new THREE.BoxGeometry(1.5, 0.42, 0.46),
      material: new THREE.MeshStandardMaterial({ color: 0x6a4a31, roughness: 0.82, metalness: 0.14 }),
      y: 0.34,
    },
    planter: {
      geometry: new THREE.CylinderGeometry(0.48, 0.56, 0.72, 10),
      material: new THREE.MeshStandardMaterial({ color: 0x78634a, roughness: 0.84, metalness: 0.1 }),
      y: 0.36,
    },
  };
  const NPC_ROLE_STYLE = {
    pedestrian: { color: 0x8da4b9, accent: 0x2f3a45, height: 1.72, shoulderWidth: 0.43, coat: true },
    guide: { color: 0xc7a56f, accent: 0x352615, height: 1.78, shoulderWidth: 0.45, coat: true, hat: true },
    busker: { color: 0x7f5daa, accent: 0x261837, height: 1.74, shoulderWidth: 0.46, coat: true, scarf: true },
    tourist: { color: 0xcda57d, accent: 0x5b4023, height: 1.7, shoulderWidth: 0.42, coat: true },
    photographer: { color: 0xa6bac9, accent: 0x1f2730, height: 1.71, shoulderWidth: 0.41, coat: true, bag: true },
    skateboarder: { color: 0xd19663, accent: 0x402c1f, height: 1.73, shoulderWidth: 0.42, hoodie: true },
    cyclist: { color: 0x7cb29a, accent: 0x1e4135, height: 1.76, shoulderWidth: 0.4, jacket: true, cap: true },
  };
  const STEAM_CLOCK_CHIME_MOTIF = [
    ['G#4', 'F#4', 'E4', 'B3'],
    ['E4', 'G#4', 'F#4', 'B3'],
    ['E4', 'F#4', 'G#4', 'E4'],
    ['G#4', 'E4', 'F#4', 'B3'],
    ['B3', 'F#4', 'G#4', 'E4'],
  ];

  function getTextureBaseUrl() {
    return (config.textureBaseUrl || '').replace(/\/$/, '');
  }

  function cloneUvToUv2(geometry) {
    const uv = geometry.getAttribute('uv');
    if (uv) {
      geometry.setAttribute('uv2', uv.clone());
    }
  }

  function applyWorldUvs(geometry, repeat) {
    const position = geometry.getAttribute('position');
    const uvs = new Float32Array(position.count * 2);
    for (let index = 0; index < position.count; index += 1) {
      const x = position.getX(index);
      const y = position.getY(index);
      uvs[(index * 2)] = x * repeat.x;
      uvs[(index * 2) + 1] = y * repeat.y;
    }
    geometry.setAttribute('uv', new THREE.BufferAttribute(uvs, 2));
    geometry.setAttribute('uv2', new THREE.BufferAttribute(uvs.slice(0), 2));
  }

  function loadSurfaceTextureSet(kind) {
    if (visualState.groundTextures[kind]) {
      return visualState.groundTextures[kind];
    }
    const base = getTextureBaseUrl();
    const manifest = SURFACE_TEXTURES[kind] || {};
    const set = {};
    let loadedCount = 0;
    Object.keys(manifest).forEach((key) => {
      if (!base || !manifest[key]) {
        return;
      }
      const texture = textureLoader.load(base + '/' + manifest[key]);
      texture.wrapS = THREE.RepeatWrapping;
      texture.wrapT = THREE.RepeatWrapping;
      texture.colorSpace = key === 'map' ? THREE.SRGBColorSpace : THREE.NoColorSpace;
      texture.anisotropy = 4;
      set[key] = texture;
      loadedCount += 1;
    });
    visualState.groundTextures[kind] = set;
    if (state.debugEnabled && window.console && typeof window.console.info === 'function') {
      window.console.info('[Gastown Sim] Surface textures', kind, loadedCount ? 'loaded' : 'fallback preset used');
    }
    return set;
  }

  function buildClosedBorderPoints(points, y) {
    const borderPoints = points.map((point) => new THREE.Vector3(point.x, y, point.z));
    const first = points[0];
    borderPoints.push(new THREE.Vector3(first.x, y, first.z));
    return borderPoints;
  }

  function createGroundMaterial(kind, baseColor, roughness, metalness) {
    const maps = loadSurfaceTextureSet(kind);
    const fallbackPreset = kind === 'street'
      ? FALLBACK_SURFACE_PRESETS.road
      : kind === 'sidewalk'
        ? FALLBACK_SURFACE_PRESETS.sidewalk
        : FALLBACK_SURFACE_PRESETS.plaza;
    return new THREE.MeshStandardMaterial(Object.assign({
      color: baseColor,
      roughness: roughness,
      metalness: metalness,
      polygonOffset: true,
      polygonOffsetFactor: kind === 'street' ? 1 : 0.5,
      polygonOffsetUnits: kind === 'street' ? 1 : 0.5,
    }, maps, {
      color: Object.keys(maps).length ? baseColor : fallbackPreset.color,
      roughness: Object.keys(maps).length ? roughness : fallbackPreset.roughness,
      metalness: Object.keys(maps).length ? metalness : fallbackPreset.metalness,
    }));
  }

  function registerFallbackSurfaceMaterial(key, material, baseColor, baseRoughness, baseMetalness) {
    if (!material) return material;
    material.userData = Object.assign({}, material.userData, {
      fallbackSurfaceKey: key,
      baseColor: baseColor,
      baseRoughness: baseRoughness,
      baseMetalness: baseMetalness,
    });
    visualState.fallbackSurfaceMaterials[key] = visualState.fallbackSurfaceMaterials[key] || [];
    visualState.fallbackSurfaceMaterials[key].push(material);
    visualState.reflectiveMaterials.push(material);
    return material;
  }

  function setSurfaceWetness(material, baseRoughness, baseMetalness, rainIntensity) {
    if (!material) return;
    material.metalness = Math.min(0.38, baseMetalness + (rainIntensity * 0.18));
    material.roughness = Math.max(0.4, baseRoughness - (rainIntensity * 0.18));
    if (material.roughnessMap) {
      material.roughnessMap.needsUpdate = true;
    }
  }

  function segmentHeading(a, b) {
    return Math.atan2(b.x - a.x, b.z - a.z);
  }

  function deterministicUnit(seed) {
    const str = String(seed || 'seed');
    let hash = 2166136261;
    for (let i = 0; i < str.length; i += 1) {
      hash ^= str.charCodeAt(i);
      hash = Math.imul(hash, 16777619);
    }
    return ((hash >>> 0) % 1000) / 1000;
  }

  function buildFallbackSurfaceBands(world) {
    if (!world || !world.route || !Array.isArray(world.route.centerline)) {
      return [];
    }

    const centerline = world.route.centerline;
    const streetWidth = world.route.streetWidth || 10;
    const bands = [];

    centerline.forEach((point, index) => {
      const next = centerline[index + 1];
      const prev = centerline[index - 1];
      const anchor = next || prev;
      if (!anchor || index >= centerline.length - 1) return;

      const heading = segmentHeading(point, anchor);
      const segmentLength = Math.max(8.5, Math.min(18, Math.hypot(anchor.x - point.x, anchor.z - point.z) * 0.96 || 10));
      const steamZone = point.id === 'steam-clock' || index >= Math.max(1, centerline.length - 4);

      bands.push({ segment_id: point.id, width: streetWidth * 0.98, length: segmentLength * 1.04, yaw: heading, offset_x: 0, offset_z: 0, tone: 'road_base_dark', opacity: 0.44, elevation: 0.012 });
      bands.push({ segment_id: point.id, width: streetWidth * 0.42, length: Math.max(6.8, segmentLength * 0.82), yaw: heading, offset_x: 0, offset_z: 0, tone: steamZone ? 'intersection_pavers' : 'wheel_track', opacity: steamZone ? 0.34 : 0.26, elevation: 0.016 });
      bands.push({ segment_id: point.id, width: streetWidth * 0.17, length: Math.max(5.8, segmentLength * 0.76), yaw: heading, offset_x: streetWidth * 0.34, offset_z: 0, tone: 'curb_grime', opacity: 0.2, elevation: 0.025 });
      bands.push({ segment_id: point.id, width: streetWidth * 0.17, length: Math.max(5.8, segmentLength * 0.76), yaw: heading, offset_x: -streetWidth * 0.34, offset_z: 0, tone: 'curb_grime', opacity: 0.2, elevation: 0.025 });

      bands.push({ segment_id: point.id, width: streetWidth * 0.88, length: Math.max(4.8, segmentLength * 0.22), yaw: heading, offset_x: 0, offset_z: (segmentLength * 0.16), tone: 'cobble_break', opacity: steamZone ? 0.22 : 0.1, elevation: 0.02 });
      if (steamZone) {
        bands.push({ segment_id: point.id, width: streetWidth * 0.72, length: Math.max(5.2, segmentLength * 0.34), yaw: heading, offset_x: 0, offset_z: 0, tone: 'intersection_pavers', opacity: 0.18, elevation: 0.02 });
      }
    });

    return bands;
  }

  function getSteamClockAnchor(world) {
    if (!world) return null;
    const heroLandmarks = Array.isArray(world.hero_landmarks) ? world.hero_landmarks : [];
    const landmarks = Array.isArray(world.landmarks) ? world.landmarks : [];
    const nodes = Array.isArray(world.nodes) ? world.nodes : [];
    return heroLandmarks.find((hero) => hero.id === 'steam-clock-hero')
      || landmarks.find((landmark) => landmark.id === 'steam-clock')
      || nodes.find((node) => node.id === 'steam-clock')
      || null;
  }

  async function loadDialogData() {
    if (!config.dialogDataUrl) {
      return {};
    }
    const response = await fetch(config.dialogDataUrl, { credentials: 'same-origin' });
    if (!response.ok) {
      throw new Error('Could not load Gastown dialog data.');
    }
    const data = await response.json();
    return data && typeof data === 'object' ? data : {};
  }

  function setInteractPrompt(text) {
    if (!interactPromptEl) return;
    if (text) {
      interactPromptEl.textContent = text;
      interactPromptEl.removeAttribute('hidden');
    } else {
      interactPromptEl.setAttribute('hidden', 'hidden');
      interactPromptEl.textContent = '';
    }
  }

  function formatDistanceLabel(distance) {
    if (!Number.isFinite(distance)) {
      return '';
    }
    return distance >= 10 ? Math.round(distance) + ' m' : distance.toFixed(1) + ' m';
  }

  function getMovementProfile() {
    if (state.cameraMode === 'overview') {
      return {
        label: 'overview traversal',
        maxSpeed: 18.5,
        acceleration: 7.6,
        deceleration: 5.8,
        turnSpeed: 1.65,
        bobAmount: 0.02,
        swayAmount: 0.008,
        stepInterval: 0.34,
      };
    }
    if (state.gait.precise) {
      return {
        label: 'precise exploration',
        maxSpeed: 3.4,
        acceleration: 10.8,
        deceleration: 11.6,
        turnSpeed: 2.05,
        bobAmount: 0.012,
        swayAmount: 0.005,
        stepInterval: 0.52,
      };
    }
    if (state.gait.traverse) {
      return {
        label: 'brisk walk',
        maxSpeed: 11.75,
        acceleration: 6.6,
        deceleration: 6.2,
        turnSpeed: 1.9,
        bobAmount: 0.03,
        swayAmount: 0.011,
        stepInterval: 0.29,
      };
    }
    return {
      label: 'walking',
      maxSpeed: 7.6,
      acceleration: 8.6,
      deceleration: 8.9,
      turnSpeed: 1.85,
      bobAmount: 0.022,
      swayAmount: 0.008,
      stepInterval: 0.41,
    };
  }

  function detectPlayerSurface() {
    if (!state.world || !state.world.zones) {
      return 'sidewalk';
    }
    const point = { x: player.position.x, z: player.position.z };
    if (Array.isArray(state.world.zones.sidewalk) && state.world.zones.sidewalk.some((zone) => isPointInPolygon(point, zone.polygon || []))) {
      return 'sidewalk';
    }
    if (Array.isArray(state.world.zones.street) && state.world.zones.street.some((zone) => isPointInPolygon(point, zone.polygon || []))) {
      return 'street';
    }
    return state.motion.surface || 'sidewalk';
  }

  function playFootstepCue(surface, intensity) {
    const audioContext = ensureAudioContext();
    if (!audioContext) {
      return;
    }
    const now = audioContext.currentTime;
    if ((now - state.motion.lastFootstepAt) < 0.08) {
      return;
    }
    state.motion.lastFootstepAt = now;
    const clampedIntensity = THREE.MathUtils.clamp(intensity || 0.35, 0.18, 1);
    const noiseBuffer = audioContext.createBuffer(1, Math.max(1, Math.floor(audioContext.sampleRate * 0.03)), audioContext.sampleRate);
    const data = noiseBuffer.getChannelData(0);
    for (let i = 0; i < data.length; i += 1) {
      data[i] = (Math.random() * 2 - 1) * (1 - (i / data.length));
    }
    const noise = audioContext.createBufferSource();
    noise.buffer = noiseBuffer;
    const noiseFilter = audioContext.createBiquadFilter();
    noiseFilter.type = 'bandpass';
    noiseFilter.frequency.value = surface === 'street' ? 520 : 980;
    noiseFilter.Q.value = surface === 'street' ? 0.9 : 1.4;
    const noiseGain = audioContext.createGain();
    noiseGain.gain.setValueAtTime(0.0001, now);
    noiseGain.gain.exponentialRampToValueAtTime((surface === 'street' ? 0.018 : 0.012) * clampedIntensity, now + 0.006);
    noiseGain.gain.exponentialRampToValueAtTime(0.0001, now + (surface === 'street' ? 0.085 : 0.05));
    noise.connect(noiseFilter);
    noiseFilter.connect(noiseGain);
    noiseGain.connect(audioContext.destination);
    noise.start(now);
    noise.stop(now + 0.09);

    const thump = audioContext.createOscillator();
    thump.type = surface === 'street' ? 'triangle' : 'sine';
    thump.frequency.setValueAtTime(surface === 'street' ? 86 : 124, now);
    thump.frequency.exponentialRampToValueAtTime(surface === 'street' ? 62 : 94, now + 0.06);
    const thumpGain = audioContext.createGain();
    thumpGain.gain.setValueAtTime(0.0001, now);
    thumpGain.gain.exponentialRampToValueAtTime((surface === 'street' ? 0.022 : 0.012) * clampedIntensity, now + 0.008);
    thumpGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.07);
    thump.connect(thumpGain);
    thumpGain.connect(audioContext.destination);
    thump.start(now);
    thump.stop(now + 0.08);
  }

  function updateCameraMotionFeedback(delta, movementProfile) {
    const speed = state.velocity.length();
    const normalizedSpeed = THREE.MathUtils.clamp(speed / Math.max(0.01, movementProfile.maxSpeed), 0, 1.25);
    state.motion.currentSpeed = speed;
    state.motion.smoothedSpeed = THREE.MathUtils.lerp(state.motion.smoothedSpeed || 0, speed, 1 - Math.exp(-delta * 8));
    state.motion.surface = detectPlayerSurface();
    const moving = normalizedSpeed > 0.08;
    const cadence = (state.motion.surface === 'street' ? 7.2 : 8.8) * (0.55 + normalizedSpeed);
    state.motion.bobPhase += delta * cadence * Math.PI * 2;
    const targetBob = moving ? Math.sin(state.motion.bobPhase) * movementProfile.bobAmount * normalizedSpeed * (state.motion.surface === 'street' ? 1.1 : 0.82) : 0;
    const targetSway = moving ? Math.cos(state.motion.bobPhase * 0.5) * movementProfile.swayAmount * normalizedSpeed : 0;
    state.motion.bobOffset = THREE.MathUtils.lerp(state.motion.bobOffset || 0, targetBob, 1 - Math.exp(-delta * 10));
    state.motion.swayOffset = THREE.MathUtils.lerp(state.motion.swayOffset || 0, targetSway, 1 - Math.exp(-delta * 8));

    if (state.cameraMode === 'street') {
      camera.position.y = DEFAULT_EYE_HEIGHT + state.motion.bobOffset;
      camera.position.x = state.motion.swayOffset;
      camera.rotation.set(state.pitch + (state.motion.surface === 'street' ? state.motion.bobOffset * 0.16 : state.motion.bobOffset * 0.1), 0, state.motion.swayOffset * 1.8);
    } else {
      const overviewDrift = movementProfile.bobAmount * normalizedSpeed * 5.5;
      camera.position.y = player.position.y + state.overviewAltitude + overviewDrift;
      camera.rotation.set(OVERVIEW_CAMERA_PITCH + (normalizedSpeed * 0.01), state.yaw, -state.motion.swayOffset * 0.6);
    }

    if (moving) {
      state.motion.footstepTimer -= delta * (0.9 + normalizedSpeed);
      if (state.motion.footstepTimer <= 0) {
        playFootstepCue(state.motion.surface, normalizedSpeed);
        state.motion.footstepTimer = movementProfile.stepInterval * (state.motion.surface === 'street' ? 1.08 : 0.92) * (1.18 - Math.min(0.72, normalizedSpeed * 0.5));
      }
    } else {
      state.motion.footstepTimer = Math.min(state.motion.footstepTimer || 0, 0.12);
    }
  }

  function findNearbyCollectible() {
    const forward = getHeadingVector();
    let best = null;
    (state.props || []).forEach((prop) => {
      if (!prop.collectible || prop.collected || !prop._position) {
        return;
      }
      const dx = prop._position.x - player.position.x;
      const dz = prop._position.z - player.position.z;
      const distance = Math.hypot(dx, dz);
      if (distance > 4.8) {
        return;
      }
      const dirX = dx / Math.max(distance, 0.001);
      const dirZ = dz / Math.max(distance, 0.001);
      const alignment = (dirX * forward.x) + (dirZ * forward.z);
      const score = (alignment * 1.35) - (distance * 0.22);
      if (!best || score > best.score) {
        best = { prop, distance, alignment, score };
      }
    });
    return best;
  }

  function warnAudioUnavailable(message, error) {
    if (state.audioWarningIssued) {
      return;
    }
    state.audioWarningIssued = true;
    if (window.console && typeof window.console.warn === 'function') {
      if (error) {
        window.console.warn('[Gastown Sim] ' + message, error);
      } else {
        window.console.warn('[Gastown Sim] ' + message);
      }
    }
  }

  function createSafeHowl(options) {
    if (!hasHowler) {
      warnAudioUnavailable('Howler audio unavailable; simulator continuing without ambient audio.');
      return null;
    }

    const srcList = Array.isArray(options && options.src) ? options.src.filter(Boolean) : [];

    try {
      const howl = new Howl(Object.assign({}, options, {
        onloaderror(id, error) {
          warnAudioUnavailable('Gastown audio assets missing or failed to load; simulator continuing without some audio.', error);
          if (typeof options.onloaderror === 'function') {
            options.onloaderror.call(this, id, error);
          }
        },
        onplayerror(id, error) {
          warnAudioUnavailable('Gastown audio playback failed; simulator continuing without some audio.', error);
          if (typeof options.onplayerror === 'function') {
            options.onplayerror.call(this, id, error);
          }
        }
      }));
      howl.__seSources = srcList;
      return howl;
    } catch (error) {
      warnAudioUnavailable('Gastown audio setup failed; simulator continuing without ambient audio.', error);
      return null;
    }
  }

  function playHowl(howl) {
    if (!howl || typeof howl.play !== 'function') {
      return;
    }
    try {
      if (!howl.playing()) {
        howl.play();
      }
    } catch (error) {
      warnAudioUnavailable('Gastown audio playback failed; simulator continuing without some audio.', error);
    }
  }

  function fadeHowl(howl, toVolume, duration) {
    if (!howl || typeof howl.fade !== 'function' || typeof howl.volume !== 'function') {
      return;
    }
    try {
      howl.fade(howl.volume(), toVolume, duration);
    } catch (error) {
      warnAudioUnavailable('Gastown audio fade failed; simulator continuing without some audio.', error);
    }
  }

  function maybeTriggerNpcVoice(voiceFreq) {
    if (!state.isRunning || !Array.isArray(state.npcs) || !window.speechSynthesis || voiceFreq <= 0.05) {
      return false;
    }
    if (window.speechSynthesis.speaking || ((performance.now() / 1000) - state.lastNpcVoiceAt) < 10) {
      return false;
    }
    const nearby = state.npcs.filter((npc) => npc.voiceCue && npc.mesh && Math.hypot(player.position.x - npc.mesh.position.x, player.position.z - npc.mesh.position.z) < 15);
    if (!nearby.length) {
      return false;
    }
    const selected = nearby[state.npcVoiceIndex % nearby.length];
    state.npcVoiceIndex += 1;
    const snippetsByCue = {
      'busker-hook': ['Clock corner tune, folks.', 'Gather round the clock.'],
      'tourist-cluster': ['One more picture here.', 'Stand by the clock, please.'],
      'photo-direction': ['Hold still by the clock.', 'Okay, one more shot.'],
    };
    const options = snippetsByCue[selected.voiceCue] || [];
    if (!options.length) {
      return false;
    }
    const utterance = new window.SpeechSynthesisUtterance(options[state.npcVoiceIndex % options.length]);
    utterance.volume = 0.18;
    utterance.rate = 0.96;
    utterance.pitch = selected.role === 'busker' ? 0.84 : 1;
    utterance.onstart = function onNpcVoiceStart() {
      state.lastNpcVoiceAt = performance.now() / 1000;
    };
    try {
      window.speechSynthesis.speak(utterance);
      return true;
    } catch (error) {
      warnAudioUnavailable('NPC voice ambience unavailable; simulator continuing without speech snippets.', error);
      return false;
    }
  }

  function clearMovementInput() {
    state.move.forward = false;
    state.move.backward = false;
    state.move.left = false;
    state.move.right = false;
    state.turn.left = false;
    state.turn.right = false;
    state.gait.precise = false;
    state.gait.traverse = false;
    state.velocity.set(0, 0, 0);
    state.motion.footstepTimer = 0;
  }

  function getPlayerRouteContext() {
    if (!state.world || !state.world.route || !Array.isArray(state.world.route.centerline) || !state.world.route.centerline.length) {
      return { key: 'gastown', label: 'Gastown', landmark: '', nearestNodeId: '' };
    }
    let nearest = state.world.route.centerline[0];
    let nearestDistance = Infinity;
    state.world.route.centerline.forEach((point) => {
      const distance = Math.hypot(player.position.x - point.x, player.position.z - point.z);
      if (distance < nearestDistance) {
        nearest = point;
        nearestDistance = distance;
      }
    });
    const pointId = nearest && nearest.id ? nearest.id : '';
    if (pointId === 'steam-clock' || pointId === 'steam-clock-approach') {
      return { key: 'steam_clock', label: 'Steam Clock corner', landmark: 'Steam Clock', nearestNodeId: pointId };
    }
    if (pointId === 'maple-tree-square-edge') {
      return { key: 'maple_tree_square', label: 'Maple Tree Square', landmark: 'Maple Tree Square', nearestNodeId: pointId };
    }
    if (pointId === 'waterfront-station-threshold') {
      return { key: 'waterfront_station', label: 'Waterfront Station edge', landmark: 'Waterfront Station', nearestNodeId: pointId };
    }
    if (pointId === 'water-cordova-seam' || pointId === 'gastown-beat-1' || pointId === 'gastown-beat-3' || pointId === 'water-street-mid-block') {
      return { key: 'water_street', label: 'Water Street', landmark: 'Water Street', nearestNodeId: pointId };
    }
    if (pointId === 'cambie-rise-continuation') {
      return { key: 'cambie_rise', label: 'Cambie rise', landmark: 'Cambie rise', nearestNodeId: pointId };
    }
    return { key: 'gastown', label: 'Gastown', landmark: nearest && nearest.label ? nearest.label : 'Gastown', nearestNodeId: pointId };
  }

  function getDiscoverySummary() {
    const propsFound = Object.keys(state.discoveries.props || {}).filter((key) => state.discoveries.props[key]).length;
    return {
      foundNewspaperBox: !!state.discoveries.props.newspaper_box,
      foundHistoricPlaque: !!state.discoveries.props.historic_plaque,
      foundMural: !!state.discoveries.props.mural,
      scavengerComplete: !!state.quest.completed,
      propsFound: propsFound,
      landmarks: Object.assign({}, state.discoveries.landmarks || {}),
    };
  }

  function resolveDialogLines(entry, npcState) {
    if (!entry || typeof entry !== 'object') return null;
    const baseLines = Array.isArray(entry.lines) ? entry.lines.slice() : [];
    const context = getPlayerRouteContext();
    const discovery = getDiscoverySummary();
    const variants = Array.isArray(entry.variants) ? entry.variants : [];
    variants.forEach((variant) => {
      if (!variant || typeof variant !== 'object' || !Array.isArray(variant.lines) || !variant.lines.length) return;
      const when = variant.when || {};
      const weatherMatch = !when.weather || when.weather === state.activeWeather;
      const timeMatch = !when.timeOfDay || when.timeOfDay === state.activeTimeOfDay;
      const roleMatch = !when.role || when.role === (npcState && npcState.role);
      const locationMatch = !when.location || when.location === context.key;
      const landmarkMatch = !when.discoveredLandmark || discovery.landmarks[when.discoveredLandmark];
      const questMatch = typeof when.scavengerComplete !== 'boolean' || when.scavengerComplete === discovery.scavengerComplete;
      const propMatch = !when.foundProp || !!discovery[when.foundProp];
      if (weatherMatch && timeMatch && roleMatch && locationMatch && landmarkMatch && questMatch && propMatch) {
        Array.prototype.push.apply(baseLines, variant.lines);
      }
    });
    if (!baseLines.length) return null;
    return baseLines;
  }

  function resolveDialogEntry(entry, npcState) {
    if (!entry || typeof entry !== 'object') return entry;
    const resolved = Object.assign({}, entry);
    resolved.lines = resolveDialogLines(entry, npcState) || entry.lines;
    return resolved;
  }

  function addNpcContextActions(npcState, normalized) {
    const context = getPlayerRouteContext();
    const discovery = getDiscoverySummary();
    const actions = Array.isArray(normalized.actions) ? normalized.actions.slice() : [];
    const contextLabel = context.landmark || context.label;
    actions.unshift({
      type: 'response',
      label: 'What is special about this spot?',
      responseLines: [
        'You are standing near ' + contextLabel + ', where the route shifts between shoreline access, working street movement, and public gathering space.',
        discovery.scavengerComplete
          ? 'Since you already filled the street details log, the smaller observations start connecting to Maple Tree Square, the Steam Clock corner, and the wider route.'
          : 'If you keep noticing small details here, the street details log starts to make more sense in context.'
      ],
      followupStatus: 'Shared place-specific context for ' + context.label + '.',
    });
    if (discovery.landmarks.steam_clock && npcState && npcState.role !== 'busker') {
      actions.push({
        type: 'response',
        label: 'How does the Steam Clock change the street?',
        responseLines: [
          'Once you have seen the Steam Clock up close, you notice how everyone calibrates their pace around it: commuters cut through, visitors pause, and performers treat it like a stage marker.',
          'That rhythm is a big part of why Gastown feels inhabited instead of frozen.'
        ],
        followupStatus: 'NPC reflected on the Steam Clock crowd rhythm.',
      });
    }
    if (discovery.landmarks.maple_tree_square && npcState && npcState.role !== 'guide') {
      actions.push({
        type: 'response',
        label: 'What changes near Maple Tree Square?',
        responseLines: [
          'Maple Tree Square loosens the block into a knot of streets, so the route reads less like a corridor and more like a layered public space.',
          'That is part of why the square feels social even when it is only lightly crowded.'
        ],
        followupStatus: 'NPC added Maple Tree Square context.',
      });
    }
    normalized.actions = actions;
    return normalized;
  }

  function refreshDiscoveryState() {
    const context = getPlayerRouteContext();
    if (context.key) {
      state.discoveries.landmarks[context.key] = true;
      state.discoveries.lastContextKey = context.key;
    }
    (state.props || []).forEach((prop) => {
      if (prop && prop.collected) {
        state.discoveries.props[prop.collectibleKey || prop.id] = true;
      }
    });
  }

  function getDialogFallbackTitle(npcState) {
    if (npcState && npcState.role === 'guide') {
      return 'Gastown local';
    }
    return (npcState && npcState.id) || 'Gastown local';
  }

  function getNpcRoleLabel(npcState) {
    const labels = { guide: 'local voice', busker: 'busker', tourist: 'tourist', photographer: 'photographer', cyclist: 'cyclist', skateboarder: 'skateboarder', pedestrian: 'pedestrian' };
    return labels[npcState && npcState.role] || 'pedestrian';
  }

  function getNpcConversationFallback(npcState) {
    const role = npcState && npcState.role ? npcState.role : 'pedestrian';
    const fallbackByRole = {
      guide: ['You can start anywhere here — the station edge, the storefront rhythm, the clock corner, or Maple Tree Square all pull the walk in different directions.', 'This area sits on the unceded territories of the Musqueam, Squamish, and Tsleil-Waututh Nations, so it helps to read the street as a layered place rather than a single-origin myth.'],
      tourist: ['We came for the Steam Clock, but the block feels richer when the storefronts and sidewalks frame the plaza instead of flattening into one pale surface.', 'It is a great photo stop because the street life and brick facades stack behind the landmark.'],
      photographer: ['A slight side angle gives you the Steam Clock, the paving texture, and the storefront rhythm all at once.', 'The plaza works best when people pause here and the route still flows around them.'],
      busker: ['I am keeping the set light near the clock now: short melodic gestures, no constant drone.', 'A little rhythm helps the corner feel alive without stepping on the chime motif.'],
      cyclist: ['This stretch rewards smooth lines and predictable motion because the tourist cluster opens and closes around the Steam Clock.', 'The ride reads differently from walking because the whole corridor starts to feel like one connected sweep.'],
      skateboarder: ['A couple of pushes and a long glide make this block feel playful without turning it into chaos.', 'I stay clear of the clock crowd, then roll back toward the quieter mid-block stretch.'],
      pedestrian: ['Water Street feels strongest when the road stays darker than the sidewalks and the storefronts repeat in a tight heritage cadence.', 'A small cluster of people near the Steam Clock makes the corridor feel active without overwhelming it.'],
    };
    return {
      title: getDialogFallbackTitle(npcState),
      lines: fallbackByRole[role] || fallbackByRole.pedestrian,
    };
  }

  async function requestNpcConversation(npcState) {
    const fallback = getNpcConversationFallback(npcState);
    if (!config.conversationEndpoint || !window.fetch) {
      return { title: fallback.title, lines: fallback.lines, fallback: true };
    }

    const headers = { 'Content-Type': 'application/json' };
    if (config.nonce) {
      headers['X-WP-Nonce'] = config.nonce;
    }

    try {
      const response = await fetch(config.conversationEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify({
          role: npcState.role || 'pedestrian',
          name: npcState.id || '',
          prompt: 'Share a quick place-based thought about Gastown, Water Street, Maple Tree Square, or the Steam Clock area without centering founder mythology.',
        }),
      });
      if (!response.ok) {
        throw new Error('conversation unavailable');
      }
      const data = await response.json();
      if (!data || !Array.isArray(data.lines) || !data.lines.length) {
        throw new Error('empty conversation');
      }
      return {
        title: data.title || fallback.title,
        lines: data.lines.slice(0, 3),
        fallback: !!data.fallback,
      };
    } catch (error) {
      return { title: fallback.title, lines: fallback.lines, fallback: true };
    }
  }

  function buildNpcConversationEntry(npcState, conversation) {
    const result = conversation || getNpcConversationFallback(npcState);
    return {
      title: result.title || getDialogFallbackTitle(npcState),
      lines: result.lines || getNpcConversationFallback(npcState).lines,
      actions: getNpcDialogActions(npcState),
      hasCustomActions: true,
    };
  }

  function updateDialogAudioStatus(message) {
    if (dialogAudioStatusEl) dialogAudioStatusEl.textContent = message || '';
  }

  function hashNpcSpeechKey(payload) {
    const source = [payload.role || '', payload.name || '', payload.text || '', payload.style_hint || ''].join('|');
    let hash = 0;
    for (let i = 0; i < source.length; i += 1) {
      hash = ((hash << 5) - hash) + source.charCodeAt(i);
      hash |= 0;
    }
    return 'speech-' + Math.abs(hash);
  }

  function stopNpcSpeech() {
    if (state.npcSpeechController) {
      try { state.npcSpeechController.abort(); } catch (error) {}
      state.npcSpeechController = null;
    }
    if (state.npcSpeechAudio) {
      try { state.npcSpeechAudio.pause(); } catch (error) {}
      state.npcSpeechAudio.src = '';
      state.npcSpeechAudio = null;
    }
    updateDialogAudioStatus('');
  }

  async function requestNpcSpeech(npcState, textBlock) {
    if (!config.voiceEndpoint || !window.fetch || !textBlock) return null;
    const payload = {
      role: npcState.role || 'pedestrian',
      name: npcState.name || npcState.id || '',
      text: textBlock,
      style_hint: 'Dry, blunt, grounded local commentary. Terse, sharp, low-hype, never an impersonation.',
    };
    const cacheKey = hashNpcSpeechKey(payload);
    if (state.npcSpeechCache[cacheKey]) return state.npcSpeechCache[cacheKey];
    const headers = { 'Content-Type': 'application/json' };
    if (config.nonce) headers['X-WP-Nonce'] = config.nonce;
    const controller = new AbortController();
    state.npcSpeechController = controller;
    try {
      const response = await fetch(config.voiceEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(payload),
        signal: controller.signal,
      });
      if (!response.ok) throw new Error('speech unavailable');
      const data = await response.json();
      if (!data || !data.ok || !data.audioBase64) throw new Error('speech empty');
      const cached = { url: 'data:audio/mpeg;base64,' + data.audioBase64, fallback: !!data.fallback, cacheKey };
      state.npcSpeechCache[cacheKey] = cached;
      const keys = Object.keys(state.npcSpeechCache);
      if (keys.length > NPC_SPEECH_CACHE_LIMIT) delete state.npcSpeechCache[keys[0]];
      return cached;
    } catch (error) {
      return null;
    } finally {
      if (state.npcSpeechController === controller) state.npcSpeechController = null;
    }
  }

  async function playNpcSpeech(npcState, lines) {
    stopNpcSpeech();
    if (!state.hasInteracted || !Array.isArray(lines) || !lines.length) {
      updateDialogAudioStatus('');
      return;
    }
    updateDialogAudioStatus('Voice loading…');
    const speech = await requestNpcSpeech(npcState, lines.join(' '));
    if (!speech || !state.activeDialogNpcId || state.activeDialogNpcId !== npcState.id) {
      updateDialogAudioStatus('');
      return;
    }
    try {
      const audio = new window.Audio(speech.url);
      audio.autoplay = true;
      audio.preload = 'auto';
      audio.addEventListener('ended', () => {
        if (state.npcSpeechAudio === audio) state.npcSpeechAudio = null;
        updateDialogAudioStatus('');
      });
      audio.addEventListener('error', () => {
        if (state.npcSpeechAudio === audio) state.npcSpeechAudio = null;
        updateDialogAudioStatus('Voice unavailable. Text stays live.');
      });
      state.npcSpeechAudio = audio;
      await audio.play().catch(() => {
        updateDialogAudioStatus('Voice blocked by browser. Text stays live.');
      });
      if (!audio.paused) updateDialogAudioStatus('AI voice playing.');
    } catch (error) {
      updateDialogAudioStatus('Voice unavailable. Text stays live.');
    }
  }

  function getNpcDialogActions(npcState) {
    if (!npcState) {
      return [{ type: 'close', label: 'Back to walk' }];
    }
    if (npcState.role === 'guide') {
      return [
        { type: 'response', label: 'What should I notice?', responseLines: ['The paving changes first.', 'Then the storefront rhythm takes over.'], followupStatus: 'Guide pointed out a route cue.' },
        { type: 'close', label: 'Back to walk' }
      ];
    }
    if (npcState.role === 'busker') {
      return [
        { type: 'response', label: 'What changes if I stay?', responseLines: ['The crowd starts moving around the sound instead of through it.', 'That is when the block feels alive.'], followupStatus: 'Busker reacted to the street energy.' },
        { type: 'close', label: 'Back to walk' }
      ];
    }
    return [{ type: 'close', label: 'Back to walk' }];
  }

  async function hydrateNpcConversation(npcState) {
    if (!npcState || !state.activeDialogNpcId || state.activeDialogNpcId !== npcState.id) {
      return;
    }
    if (state.npcConversationCache[npcState.id]) {
      const cached = buildNpcConversationEntry(npcState, state.npcConversationCache[npcState.id]);
      state.activeDialogEntry = cached;
      dialogTitleEl.textContent = cached.title;
      renderDialogBody(cached.lines);
      renderDialogActions(cached);
      focusFirstDialogControl();
      playNpcSpeech(npcState, cached.lines || []);
      return;
    }

    state.npcConversationPending = true;
    const conversation = await requestNpcConversation(npcState);
    state.npcConversationPending = false;
    state.npcConversationCache[npcState.id] = conversation;

    if (!state.activeDialogNpcId || state.activeDialogNpcId !== npcState.id || !dialogModalEl || dialogModalEl.hasAttribute('hidden')) {
      return;
    }

    const entry = buildNpcConversationEntry(npcState, conversation);
    state.activeDialogEntry = entry;
    dialogTitleEl.textContent = entry.title;
    renderDialogBody(entry.lines);
    renderDialogActions(entry);
    setStatus(conversation.fallback ? 'NPC chat fallback active. Click scene to resume when ready.' : 'NPC conversation ready. Click scene to resume when ready.');
    focusFirstDialogControl();
    playNpcSpeech(npcState, entry.lines || []);
  }

  function createDialogLineElement(line) {
    const paragraph = document.createElement('p');
    paragraph.textContent = line;
    return paragraph;
  }

  function renderDialogBody(lines) {
    if (!dialogBodyEl) return;
    dialogBodyEl.innerHTML = '';
    (Array.isArray(lines) && lines.length ? lines : ['Dialog data unavailable.']).forEach((line) => {
      dialogBodyEl.appendChild(createDialogLineElement(line));
    });
  }

  function renderDialogActions(entry) {
    if (!dialogActionsDynamicEl || !dialogFallbackCloseEl) {
      return [];
    }

    dialogActionsDynamicEl.innerHTML = '';
    const actions = entry && Array.isArray(entry.actions) ? entry.actions : [];
    const controls = [];

    actions.forEach((action) => {
      if (!action || typeof action !== 'object' || !action.type) {
        return;
      }

      if (action.type === 'response' && Array.isArray(action.responseLines)) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pixel-button secondary';
        button.textContent = action.label || 'Tell me more';
        button.addEventListener('click', () => {
          renderDialogBody(action.responseLines);
          if (action.followupStatus) { setStatus(action.followupStatus); }
        });
        dialogActionsDynamicEl.appendChild(button);
        controls.push(button);
        return;
      }

      if (action.type === 'quest') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pixel-button secondary';
        button.textContent = action.label || 'Start scavenger hunt';
        button.addEventListener('click', () => startQuestById(action.questId || 'scavenger'));
        dialogActionsDynamicEl.appendChild(button);
        controls.push(button);
        return;
      }

      if (action.type === 'close') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pixel-button secondary';
        button.textContent = action.label || 'Back to walk';
        button.addEventListener('click', closeDialog);
        dialogActionsDynamicEl.appendChild(button);
        controls.push(button);
        return;
      }

      if (action.type === 'link' && action.href) {
        const link = document.createElement('a');
        link.className = 'pixel-button secondary';
        link.href = action.href;
        link.textContent = action.label || 'Open';
        dialogActionsDynamicEl.appendChild(link);
        controls.push(link);
      }
    });

    if (controls.length > 0) {
      dialogFallbackCloseEl.setAttribute('hidden', 'hidden');
    } else {
      dialogFallbackCloseEl.removeAttribute('hidden');
      controls.push(dialogFallbackCloseEl);
    }

    return controls;
  }

  function focusFirstDialogControl() {
    const controls = [];
    if (dialogActionsDynamicEl) {
      dialogActionsDynamicEl.querySelectorAll('button, a').forEach((el) => controls.push(el));
    }
    dialogCloseEls.forEach((el) => {
      if (!el.hasAttribute('hidden')) controls.push(el);
    });
    const target = controls.find((el) => el && typeof el.focus === 'function');
    if (target) {
      window.setTimeout(() => target.focus(), 0);
    }
  }

  function closeDialog(options) {
    stopNpcSpeech();
    stopBandAudio('dialog-close');
    state.npcConversationPending = false;
    state.activeDialogNpcId = '';
    state.activeDialogEntry = null;
    if (dialogActionsDynamicEl) {
      dialogActionsDynamicEl.innerHTML = '';
    }
    if (dialogFallbackCloseEl) {
      dialogFallbackCloseEl.removeAttribute('hidden');
    }
    if (dialogModalEl) {
      dialogModalEl.setAttribute('hidden', 'hidden');
      dialogModalEl.setAttribute('aria-hidden', 'true');
    }
    setInteractPrompt('');
    state.hoveredNpcId = '';
    state.isRunning = false;
    setPointerStatus('Pointer free. Click in.');
    const shouldResume = !!(options && options.resumePointer) || state.resumePointerAfterDialogClose;
    state.resumePointerAfterDialogClose = false;
    if (shouldResume && state.cameraMode === 'street') {
      window.setTimeout(() => enterPlayMode(), 30);
    }
    if (state.dialogLastFocusEl && typeof state.dialogLastFocusEl.focus === 'function') {
      state.dialogLastFocusEl.focus();
    }
  }

  function openDialogForNpc(npcState) {
    if (!npcState || !dialogModalEl || !dialogTitleEl || !dialogBodyEl) return;
    clearMovementInput();
    stopBandAudio('dialog-open');
    state.isRunning = false;
    state.dialogLastFocusEl = document.activeElement;
    state.resumePointerAfterDialogClose = document.pointerLockElement === renderer.domElement;

    const rawEntry = Object.prototype.hasOwnProperty.call(state.dialogData, npcState.dialogId)
      ? state.dialogData[npcState.dialogId]
      : null;
    const entry = resolveDialogEntry(rawEntry, npcState);

    if (!entry && window.console && typeof window.console.warn === 'function') {
      window.console.warn('[Gastown Sim] Missing dialog entry for', npcState.dialogId);
    }

    const baseFallback = getNpcConversationFallback(npcState);
    refreshDiscoveryState();
    const normalized = addNpcContextActions(npcState, normalizeDialogEntry(entry || {
      title: baseFallback.title,
      lines: ['Listening…'],
      actions: [{ type: 'close', label: 'Back to walk' }],
    }, {
      fallbackTitle: getDialogFallbackTitle(npcState),
      unavailableLine: 'Dialog data unavailable.',
      missingLine: 'This local voice does not have dialog copy yet.',
      defaultCloseLabel: 'Back to walk',
    }));

    normalized.actions = getNpcDialogActions(npcState);

    dialogTitleEl.textContent = normalized.title;
    renderDialogBody(normalized.lines);
    renderDialogActions(normalized);

    const showDialog = () => {
      dialogModalEl.removeAttribute('hidden');
      dialogModalEl.setAttribute('aria-hidden', 'false');
      state.activeDialogNpcId = npcState.id;
      state.activeDialogEntry = normalized;
      updateDialogAudioStatus('');
      setStatus('Dialog open. Loading nearby conversation.');
      setPointerStatus('Pointer unlocked. Dialog controls are active.');
      focusFirstDialogControl();
    };

    state.progression.talkedNpcIds[npcState.id] = true;
    updateQuestChainsFromProgress();
    updateNextMeaningfulThing();

    if (document.pointerLockElement === renderer.domElement) {
      document.exitPointerLock();
      window.setTimeout(() => {
        showDialog();
        hydrateNpcConversation(npcState);
      }, 0);
      return;
    }

    showDialog();
    hydrateNpcConversation(npcState);
  }

  function getCollectibleProps() {
    return (state.props || []).filter((prop) => prop.collectible);
  }

  function startGuideQuest() {
    startQuestById('scavenger');
    updateMinimapLegend();
  }

  function completeGuideQuest() {
    completeQuestById('scavenger', 'Street details log complete: you now have enough observations to compare the clock corner with Maple Tree Square.');
    const busker = (state.npcs || []).find((npc) => npc.role === 'busker');
    if (busker) {
      state.npcConversationCache[busker.id] = {
        title: 'Clock-corner busker',
        lines: ['You found all three clues, so now the route starts linking together: storefront details, public plaques, and the mural all point beyond a single landmark.', 'Maple Tree Square works better as layered public history — shoreline change, traffic patterns, and later heritage storytelling all meet there.'],
      };
    }
    updateMinimapLegend();
  }

  function updateQuestProgress() {
    if (!state.quest.active) return;
    state.quest.items.forEach((item) => {
      const prop = (state.props || []).find((entry) => entry.id === item.propId);
      item.found = !!(prop && prop.collected);
    });
    const foundCount = state.quest.items.filter((item) => item.found).length;
    setQuestStatus('Street details log: ' + foundCount + '/' + state.quest.items.length + ' logged.');
    if (foundCount === state.quest.items.length && state.quest.items.length) {
      completeGuideQuest();
    }
  }

  function collectNearbyProp() {
    const match = (state.props || []).find((prop) => prop.collectible && !prop.collected && Math.hypot(player.position.x - prop._position.x, player.position.z - prop._position.z) < 2.2);
    if (!match) return false;
    match.collected = true;
    state.discoveries.props[match.collectibleKey || match.id] = true;
    setStatus('Logged observation: ' + (match.collectibleLabel || match.id) + '.');
    pushJournalEntry('Logged observation: ' + (match.collectibleLabel || match.id) + '. ' + (match.journalNote || ''));
    updateQuestProgress();
    renderProgressionUi();
    updateNextMeaningfulThing();
    return true;
  }

  function createNpcProp(propId, accentMaterial) {
    if (propId === 'guitar') {
      const prop = new THREE.Group();
      const body = new THREE.Mesh(new THREE.BoxGeometry(0.34, 0.5, 0.12), accentMaterial.clone());
      const neck = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.46, 0.08), accentMaterial.clone());
      body.position.set(0, -0.06, 0);
      neck.position.set(0.16, 0.28, 0);
      neck.rotation.z = -0.38;
      prop.add(body);
      prop.add(neck);
      prop.rotation.z = 0.72;
      prop.position.set(0.22, 1.08, 0.19);
      return prop;
    }
    if (propId === 'skateboard') {
      const prop = new THREE.Group();
      const deck = new THREE.Mesh(new THREE.BoxGeometry(0.52, 0.04, 0.16), accentMaterial.clone());
      const wheelMat = new THREE.MeshStandardMaterial({ color: 0x1a1c20, roughness: 0.52, metalness: 0.12 });
      [[-0.18, -0.08], [0.18, -0.08], [-0.18, 0.08], [0.18, 0.08]].forEach((coords) => {
        const wheel = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.03, 10), wheelMat);
        wheel.rotation.z = Math.PI / 2;
        wheel.position.set(coords[0], -0.04, coords[1]);
        prop.add(wheel);
      });
      prop.add(deck);
      prop.position.set(0, 0.08, 0.06);
      return prop;
    }
    if (propId === 'bike') {
      const prop = new THREE.Group();
      const frameMat = accentMaterial.clone();
      const wheelMat = new THREE.MeshStandardMaterial({ color: 0x20242b, roughness: 0.55, metalness: 0.14 });
      const frame = new THREE.Mesh(new THREE.BoxGeometry(0.58, 0.05, 0.05), frameMat);
      frame.rotation.z = -0.18;
      frame.position.y = 0.48;
      prop.add(frame);
      const handle = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.04, 0.04), frameMat);
      handle.position.set(0.22, 0.72, 0);
      prop.add(handle);
      const seat = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.03, 0.05), frameMat);
      seat.position.set(-0.08, 0.66, 0);
      prop.add(seat);
      [-0.22, 0.24].forEach((x) => {
        const wheel = new THREE.Mesh(new THREE.TorusGeometry(0.19, 0.03, 8, 18), wheelMat);
        wheel.rotation.y = Math.PI / 2;
        wheel.position.set(x, 0.22, 0);
        prop.add(wheel);
      });
      prop.position.set(0, 0, 0.18);
      return prop;
    }
    if (propId === 'camera') {
      const prop = new THREE.Group();
      const body = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.12, 0.12), accentMaterial.clone());
      const lens = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.08, 10), new THREE.MeshStandardMaterial({ color: 0x191d21, roughness: 0.64, metalness: 0.16 }));
      lens.rotation.x = Math.PI / 2;
      lens.position.z = 0.08;
      prop.add(body);
      prop.add(lens);
      prop.position.set(0.16, 1.4, 0.28);
      return prop;
    }
    return null;
  }

  function registerMaterial(material, bucket) {
    if (!material) return material;
    if (bucket && Array.isArray(visualState[bucket])) {
      visualState[bucket].push(material);
    }
    return material;
  }

  function makeNpcVisual(npc) {
    const style = NPC_ROLE_STYLE[npc.role] || NPC_ROLE_STYLE.pedestrian;
    const scaleFactor = Math.max(0.72, Math.min(1.15, npc.silhouetteScale || 1));
    const root = new THREE.Group();
    const bodyMaterial = new THREE.MeshStandardMaterial({ color: style.color, roughness: 0.8, metalness: 0.1 });
    const skinMaterial = new THREE.MeshStandardMaterial({ color: 0xe0c7aa, roughness: 0.92, metalness: 0.02 });
    const accentMaterial = new THREE.MeshStandardMaterial({ color: style.accent, roughness: 0.72, metalness: 0.14 });
    const trimMaterial = new THREE.MeshStandardMaterial({ color: 0x161b20, roughness: 0.58, metalness: 0.2 });
    const body = new THREE.Mesh(
      new THREE.CapsuleGeometry((style.shoulderWidth || 0.42) * 0.5, Math.max(0.6, style.height - 1.08), 5, 10),
      bodyMaterial
    );
    body.position.y = style.height * 0.5;
    const head = new THREE.Mesh(
      new THREE.SphereGeometry(0.17, 10, 10),
      skinMaterial
    );
    head.position.y = style.height - 0.12;
    const accent = new THREE.Mesh(
      new THREE.BoxGeometry(0.38, 0.16, 0.2),
      accentMaterial
    );
    accent.position.set(0, style.height * 0.58, 0.2);
    const leftArm = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.54, 0.08), bodyMaterial);
    leftArm.position.set(-0.22, style.height * 0.63, 0.02);
    const rightArm = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.54, 0.08), bodyMaterial);
    rightArm.position.set(0.22, style.height * 0.63, 0.02);
    const leftLeg = new THREE.Mesh(new THREE.BoxGeometry(0.09, 0.58, 0.09), accentMaterial);
    leftLeg.position.set(-0.09, 0.34, 0);
    const rightLeg = new THREE.Mesh(new THREE.BoxGeometry(0.09, 0.58, 0.09), accentMaterial);
    rightLeg.position.set(0.09, 0.34, 0);
    const shoulders = new THREE.Mesh(new THREE.BoxGeometry(style.shoulderWidth || 0.42, 0.18, 0.24), bodyMaterial);
    shoulders.position.set(0, style.height * 0.73, 0.02);
    const coatHem = new THREE.Mesh(new THREE.CylinderGeometry((style.shoulderWidth || 0.42) * 0.42, (style.shoulderWidth || 0.42) * 0.58, 0.55, 8), accentMaterial);
    coatHem.position.set(0, style.height * 0.46, 0.01);
    const leftFoot = new THREE.Mesh(new THREE.BoxGeometry(0.11, 0.06, 0.2), trimMaterial);
    leftFoot.position.set(-0.09, 0.05, 0.05);
    const rightFoot = new THREE.Mesh(new THREE.BoxGeometry(0.11, 0.06, 0.2), trimMaterial);
    rightFoot.position.set(0.09, 0.05, 0.05);
    root.add(body);
    root.add(head);
    root.add(accent);
    root.add(shoulders);
    root.add(coatHem);
    root.add(leftArm);
    root.add(rightArm);
    root.add(leftLeg);
    root.add(rightLeg);
    root.add(leftFoot);
    root.add(rightFoot);
    let hat = null;
    let brim = null;
    if (style.hat || style.cap) {
      hat = new THREE.Mesh(
        new THREE.CylinderGeometry(0.13, 0.14, style.cap ? 0.1 : 0.14, 12),
        trimMaterial
      );
      hat.position.set(0, style.height + 0.04, 0);
      root.add(hat);
      if (style.hat) {
        brim = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.22, 0.02, 18), trimMaterial);
        brim.position.set(0, style.height, 0);
        root.add(brim);
      }
    }
    let scarf = null;
    if (style.scarf) {
      scarf = new THREE.Mesh(new THREE.TorusGeometry(0.15, 0.04, 8, 18), accentMaterial);
      scarf.rotation.x = Math.PI / 2;
      scarf.position.set(0, style.height * 0.76, 0.1);
      root.add(scarf);
    }
    let bag = null;
    if (style.bag) {
      bag = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.24, 0.12), trimMaterial);
      bag.position.set(-0.25, style.height * 0.5, 0.12);
      root.add(bag);
    }
    const heldProp = createNpcProp(npc.heldProp, accentMaterial);
    if (heldProp) {
      root.add(heldProp);
    }
    root.userData = {
      body,
      head,
      accent,
      leftArm,
      rightArm,
      leftLeg,
      rightLeg,
      shoulders,
      coatHem,
      leftFoot,
      rightFoot,
      heldProp,
      scaleFactor,
      trimMaterial,
      hat,
      brim,
      scarf,
      bag,
      umbrella: null,
      umbrellaShaft: null,
    };
    if ((state.activeWeather === 'rain' || state.activeWeather === 'thunderstorm') && npc.role !== 'cyclist' && npc.role !== 'skateboarder') {
      const umbrellaMat = new THREE.MeshStandardMaterial({ color: 0x1a2028, roughness: 0.44, metalness: 0.26 });
      const canopy = new THREE.Mesh(new THREE.ConeGeometry(0.55, 0.22, 14, 1, true), umbrellaMat);
      canopy.rotation.x = Math.PI;
      canopy.position.set(0.28, style.height + 0.18, 0);
      const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.015, 0.015, 0.9, 8), trimMaterial);
      shaft.position.set(0.28, style.height * 0.88, 0);
      root.add(canopy);
      root.add(shaft);
      root.userData.umbrella = canopy;
      root.userData.umbrellaShaft = shaft;
    }
    root.scale.setScalar(scaleFactor);
    return root;
  }

  function getNpcSpawnProfile() {
    const weather = state.activeWeather || 'clear';
    const mood = state.activeMood || 'calm';
    const lowGraphicsPenalty = state.lowGraphics ? 1 : 0;
    return {
      pedestrian: (weather === 'fog' ? 1 : 2) + (mood === 'commuter' ? 1 : 0) - lowGraphicsPenalty,
      tourist: (weather === 'clear' && mood === 'lively' ? 5 : weather === 'clear' ? 4 : weather === 'drizzle' ? 3 : 2) - lowGraphicsPenalty,
      cyclist: (weather === 'rain' || weather === 'thunderstorm' || weather === 'fog' ? 0 : 1),
      skateboarder: (weather === 'rain' || weather === 'thunderstorm' ? 0 : 1),
      photographer: (weather === 'fog' ? 0 : 1),
      busker: 1,
      guide: 1,
    };
  }

  function filterSpawnNpcs(world) {
    const profile = getNpcSpawnProfile();
    const counts = {};
    return (world.npcs || []).filter((npc) => {
      const role = npc.role || 'pedestrian';
      counts[role] = counts[role] || 0;
      const limit = Number.isFinite(profile[role]) ? profile[role] : 99;
      if (counts[role] >= Math.max(0, limit)) {
        return false;
      }
      counts[role] += 1;
      return true;
    });
  }

  function addProps(world) {
    state.props = [];
    visualState.propEntries = [];
    Object.values(PROP_DEFINITIONS).forEach((def) => {
      if (def && def.material && visualState.metalMaterials.indexOf(def.material) === -1 && def.material.metalness > 0.12) {
        visualState.metalMaterials.push(def.material);
      }
    });
    const grouped = world.props.reduce((acc, prop) => {
      const kind = PROP_DEFINITIONS[prop.kind] ? prop.kind : 'cardboard_box';
      acc[kind] = acc[kind] || [];
      acc[kind].push(prop);
      return acc;
    }, {});

    Object.keys(grouped).forEach((kind) => {
      const def = PROP_DEFINITIONS[kind];
      const props = grouped[kind];
      const mesh = new THREE.InstancedMesh(def.geometry, def.material, props.length);
      mesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
      props.forEach((prop, index) => {
        const offsetX = prop.randomOffset ? (deterministicUnit(prop.id + '-offset-x') - 0.5) : 0;
        const offsetZ = prop.randomOffset ? (deterministicUnit(prop.id + '-offset-z') - 0.5) : 0;
        tempEuler.set(0, prop.yaw || 0, 0);
        tempQuaternion.setFromEuler(tempEuler);
        tempScale.setScalar(Math.max(0.55, prop.scale || 1));
        const finalPosition = new THREE.Vector3(prop.x + offsetX, (prop.y || 0) + (def.y || 0), prop.z + offsetZ);
        tempMatrix.compose(finalPosition, tempQuaternion, tempScale);
        mesh.setMatrixAt(index, tempMatrix);
        state.props.push(Object.assign({}, prop, { kind, _position: { x: finalPosition.x, y: finalPosition.y, z: finalPosition.z }, _instanceIndex: index, _mesh: mesh, collected: false }));
      });
      mesh.castShadow = false;
      mesh.receiveShadow = true;
      worldGroup.add(mesh);
      visualState.instancedProps.push(mesh);
    });
  }

  function ensureAudioContext() {
    const AudioContextCtor = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextCtor) {
      return null;
    }
    if (!state.audioContext) {
      try {
        state.audioContext = new AudioContextCtor();
      } catch (error) {
        warnAudioUnavailable('Native audio context unavailable; simulator continuing without gesture audio.', error);
        return null;
      }
    }
    return state.audioContext;
  }

  async function unlockAudioFromGesture() {
    if (state.audioUnlocked) {
      return true;
    }
    let unlocked = false;
    const audioContext = ensureAudioContext();
    if (audioContext && audioContext.state === 'suspended' && typeof audioContext.resume === 'function') {
      try {
        await audioContext.resume();
        unlocked = true;
      } catch (error) {
        warnAudioUnavailable('Native audio resume failed; simulator continuing without gesture audio.', error);
      }
    } else if (audioContext) {
      unlocked = true;
    }

    try {
      if (window.Tone && typeof window.Tone.start === 'function') {
        await window.Tone.start();
        unlocked = true;
      }
    } catch (error) {
      warnAudioUnavailable('Tone.start() failed after user gesture; simulator continuing without Tone audio.', error);
    }

    state.audioUnlocked = unlocked || state.audioUnlocked;
    return state.audioUnlocked;
  }

  function getBuskerRiffCacheKey() {
    return BUSKER_RIFF_CACHE_PREFIX + [state.activeMood || 'calm', state.activeWeather || 'clear', state.activeTimeOfDay || 'morning'].join(':');
  }


  function getBandArrangementCacheKey(contextKey) {
    return BAND_ARRANGEMENT_CACHE_PREFIX + contextKey;
  }

  function makeBandContext(seed) {
    const proximity = getBandProximity();
    const area = (getPlayerRouteContext() || {}).key || 'waterfront_station';
    const intensity = proximity > 0.72 ? 'high' : proximity > 0.38 ? 'medium' : 'low';
    return {
      mood: state.activeMood || 'calm',
      weather: state.activeWeather || 'clear',
      time_of_day: state.activeTimeOfDay || 'morning',
      area,
      intensity,
      seed: seed || ('seed-' + Math.round(proximity * 100)),
    };
  }

  function getBandContextKey(context) {
    return [context.mood, context.weather, context.time_of_day, context.area, context.intensity].join(':');
  }

  function getFallbackBandArrangements(context) {
    const families = ['upbeat_welcome_shuffle', 'moody_rain_groove', 'dusk_wander', 'clock_corner_flourish', 'sparse_early_morning'];
    return families.map((family, index) => {
      const variant = Object.assign({}, {
        tempo: family === 'clock_corner_flourish' ? 118 : family === 'moody_rain_groove' ? 94 : family === 'sparse_early_morning' ? 86 : family === 'dusk_wander' ? 102 : 112,
        key: family === 'moody_rain_groove' ? 'E minor' : family === 'clock_corner_flourish' ? 'C major' : family === 'dusk_wander' ? 'D major' : 'G major',
        swing: family === 'sparse_early_morning' ? 0.03 : 0.08,
        bars: 8,
        style: 'street bluegrass jazz',
        family,
        energy: family === 'sparse_early_morning' ? 'low' : family === 'clock_corner_flourish' ? 'high' : 'medium',
        variation_hint: 'answer the lead with small responses and keep the groove loose',
        parts: {
          sax: ['B4:8n','D5:8n','G5:4n','A5:8n','G5:8n','E5:4n','D5:8n','B4:8n'],
          mandolin: ['G4:8n','D5:8n','B4:8n','A4:8n','G4:8n','A4:8n','B4:8n','D5:8n'],
          bass: ['G2:4n','D3:4n','E3:4n','D3:4n','G2:4n','B2:4n','D3:4n','D3:4n'],
          guitar: ['G6(9)','Em7','Am7','D7','G6(9)','Cmaj7','Am7','D7'],
          percussion: { kick: ['1','3'], snare: ['2','4'], hat: ['1.5','2.5','3.5','4.5'] }
        }
      });
      if (family === 'moody_rain_groove') {
        variant.parts.sax = ['E4:4n','G4:8n','B4:8n','D5:4n','B4:8n','A4:8n','G4:4n','rest:4n'];
        variant.parts.bass = ['E2:4n','B2:4n','D3:4n','A2:4n','E2:4n','G2:4n','B2:4n','A2:4n'];
        variant.parts.guitar = ['Em9','G6','D7sus4','A7','Em9','Cmaj7','G6','B7'];
      }
      if (family === 'dusk_wander') {
        variant.parts.sax = ['F#4:8n','A4:8n','D5:4n','E5:8n','F#5:8n','E5:4n','A4:8n','D5:8n'];
        variant.parts.guitar = ['D6','Bm7','Gmaj7','A7','D6','Gmaj7','Em7','A7'];
      }
      if (family === 'clock_corner_flourish') {
        variant.parts.sax = ['G4:8n','A4:8n','C5:4n','E5:8n','D5:8n','C5:4n','A4:8n','G4:8n'];
        variant.parts.mandolin = ['C5:8n','G4:8n','E5:8n','D5:8n','C5:8n','D5:8n','E5:8n','G5:8n'];
        variant.parts.bass = ['C2:4n','G2:4n','A2:4n','G2:4n','F2:4n','G2:4n','A2:4n','G2:4n'];
        variant.parts.guitar = ['C6','Am7','Dm7','G13','Fmaj7','G13','Am7','G13'];
      }
      if (family === 'sparse_early_morning') {
        variant.parts.sax = ['rest:4n','B4:8n','D5:4n','rest:8n','G4:4n','rest:4n','A4:8n','G4:8n'];
        variant.parts.mandolin = ['G4:8n','rest:8n','D5:8n','rest:8n','B4:8n','rest:8n','A4:8n','rest:8n'];
        variant.parts.bass = ['G2:2n','D3:2n','E3:2n','D3:2n'];
        variant.parts.guitar = ['G6','G6','Cmaj7','D7'];
        variant.parts.percussion = { kick: ['1','3'], snare: [], hat: ['2.5'] };
      }
      variant.seed = context.seed + '-' + index;
      variant.mood = context.mood;
      variant.weather = context.weather;
      variant.time_of_day = context.time_of_day;
      variant.area = context.area;
      variant.intensity = context.intensity;
      return variant;
    });
  }

  function loadCachedBandArrangements(contextKey) {
    try {
      const raw = window.localStorage.getItem(getBandArrangementCacheKey(contextKey));
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : null;
    } catch (error) {
      return null;
    }
  }

  function saveCachedBandArrangements(contextKey, arrangements) {
    try { window.localStorage.setItem(getBandArrangementCacheKey(contextKey), JSON.stringify(arrangements)); } catch (error) {}
  }

  function getBandProximity() {
    const busker = getNpcByRole('busker');
    if (!busker || !busker.mesh) return 0;
    const distance = Math.hypot(player.position.x - busker.mesh.position.x, player.position.z - busker.mesh.position.z);
    return Math.max(0, 1 - (distance / 22));
  }

  function parseBandToken(token, fallbackDuration) {
    const value = String(token || '').trim();
    if (!value) return null;
    const parts = value.split(':');
    return { note: parts[0], duration: parts[1] || fallbackDuration || '8n' };
  }

  function parseChordNotes(symbol) {
    const rootMap = { C: ['C','E','G'], D: ['D','F#','A'], E: ['E','G#','B'], F: ['F','A','C'], G: ['G','B','D'], A: ['A','C#','E'], B: ['B','D#','F#'] };
    const match = String(symbol || '').match(/^([A-G])([#b]?)(.*)$/);
    if (!match) return ['G3','B3','D4'];
    let [_, letter, accidental, quality] = match;
    let triad = rootMap[letter] ? rootMap[letter].slice() : ['G','B','D'];
    const sharpen = accidental === '#';
    const flatten = accidental === 'b';
    const adjust = (note) => note + (sharpen ? '#' : flatten ? 'b' : '');
    triad = triad.map((n, i) => i === 0 ? adjust(n) : n);
    if (/m(?!aj)/i.test(quality)) triad[1] = triad[1].replace('#','');
    const octave = /13|9|7/.test(quality) ? ['3','4','4'] : ['3','3','4'];
    const notes = triad.map((n, i) => n + octave[i]);
    if (/7|9|13/.test(quality)) notes.push((match[1] === 'B' ? 'A' : 'F') + '4');
    if (/sus4/i.test(quality)) notes[1] = (letter === 'C' ? 'F4' : letter === 'D' ? 'G4' : letter === 'G' ? 'C4' : notes[1]);
    return notes;
  }

  function clearBandWatchdogs() {
    (state.band.watchdogs || []).forEach((id) => window.clearTimeout(id));
    state.band.watchdogs = [];
  }

  function releaseBandVoice(voice) {
    if (!voice) return;
    try { if (typeof voice.releaseAll === 'function') voice.releaseAll(); } catch (error) {}
    try { if (typeof voice.triggerRelease === 'function') voice.triggerRelease(); } catch (error) {}
  }

  function stopBandAudio(reason) {
    if (state.band.scheduler) {
      window.clearTimeout(state.band.scheduler);
      state.band.scheduler = null;
    }
    clearBandWatchdogs();
    if (state.band.synths) {
      Object.values(state.band.synths).forEach((voice) => releaseBandVoice(voice));
      window.setTimeout(() => {
        if (!state.band.synths) return;
        Object.values(state.band.synths).forEach((voice) => { try { if (voice && typeof voice.dispose === 'function') voice.dispose(); } catch (error) {} });
      }, 20);
    }
    if (state.band.bus) {
      try { state.band.bus.dispose(); } catch (error) {}
    }
    state.band.synths = null;
    state.band.bus = null;
    state.band.active = false;
    state.band.activeArrangement = null;
    state.band.currentBeatCursor = 0;
    state.band.currentCycleStartedAt = 0;
    if (state.debugEnabled && window.console && typeof window.console.info === 'function' && reason) {
      window.console.info('[Gastown Sim] Band audio stopped:', reason);
    }
  }

  function watchBandVoice(voice, maxMs) {
    const timer = window.setTimeout(() => releaseBandVoice(voice), Math.max(120, maxMs || BAND_MAX_NOTE_SECONDS * 1000));
    state.band.watchdogs.push(timer);
  }

  function createBandSynths(Tone) {
    const bus = new Tone.Gain(0.64).toDestination();
    const room = new Tone.Reverb({ decay: 1.8, wet: 0.12 }).connect(bus);
    const comp = new Tone.Compressor(-20, 3).connect(room);
    return {
      bus,
      synths: {
        sax: new Tone.MonoSynth({ oscillator: { type: 'sawtooth2' }, envelope: { attack: 0.02, decay: 0.12, sustain: 0.22, release: 0.28 }, filter: { Q: 3.2, type: 'bandpass' }, filterEnvelope: { attack: 0.01, decay: 0.18, sustain: 0.1, release: 0.2, baseFrequency: 280, octaves: 3.6 }, volume: -13 }).connect(comp),
        mandolin: new Tone.PluckSynth({ attackNoise: 1.1, dampening: 3200, resonance: 0.9 }).connect(new Tone.Filter(2400, 'lowpass').connect(comp)),
        bass: new Tone.MonoSynth({ oscillator: { type: 'triangle' }, envelope: { attack: 0.01, decay: 0.14, sustain: 0.45, release: 0.32 }, filter: { Q: 1.4, type: 'lowpass' }, filterEnvelope: { attack: 0.01, decay: 0.14, sustain: 0.3, release: 0.18, baseFrequency: 90, octaves: 2.2 }, volume: -10 }).connect(comp),
        guitar: new Tone.PolySynth(Tone.Synth, { oscillator: { type: 'triangle' }, envelope: { attack: 0.005, decay: 0.1, sustain: 0.08, release: 0.16 }, volume: -18 }).connect(new Tone.Filter(1800, 'lowpass').connect(comp)),
        kick: new Tone.MembraneSynth({ pitchDecay: 0.02, octaves: 4.2, envelope: { attack: 0.001, decay: 0.16, sustain: 0, release: 0.04 }, volume: -18 }).connect(comp),
        snare: new Tone.NoiseSynth({ noise: { type: 'pink' }, envelope: { attack: 0.001, decay: 0.12, sustain: 0, release: 0.03 }, volume: -22 }).connect(comp),
        hat: new Tone.MetalSynth({ frequency: 220, envelope: { attack: 0.001, decay: 0.05, release: 0.02 }, harmonicity: 4.1, modulationIndex: 12, resonance: 1200, octaves: 1.5, volume: -28 }).connect(comp),
      }
    };
  }

  async function ensureBandAudioReady() {
    const Tone = await ensureToneReady();
    if (!Tone) return null;
    if (!state.band.synths || !state.band.bus) {
      stopBandAudio('rebuild');
      const rig = createBandSynths(Tone);
      state.band.bus = rig.bus;
      state.band.synths = rig.synths;
    }
    return Tone;
  }

  async function fetchBandArrangements(force) {
    const context = makeBandContext('seed-a');
    const contextKey = getBandContextKey(context);
    if (!force && state.band.contextKey === contextKey && state.band.arrangements.length) return state.band.arrangements;
    const cached = loadCachedBandArrangements(contextKey);
    if (!force && cached && cached.length) {
      state.band.contextKey = contextKey;
      state.band.arrangements = cached;
      return cached;
    }
    const fallback = getFallbackBandArrangements(context);
    if (!config.bandArrangementEndpoint || !window.fetch) {
      saveCachedBandArrangements(contextKey, fallback);
      state.band.contextKey = contextKey;
      state.band.arrangements = fallback;
      return fallback;
    }
    try {
      const params = new URLSearchParams(context);
      const response = await fetch(config.bandArrangementEndpoint + '?' + params.toString(), { credentials: 'same-origin' });
      if (!response.ok) throw new Error('band arrangement unavailable');
      const data = await response.json();
      const arrangements = data && Array.isArray(data.arrangements) && data.arrangements.length ? data.arrangements : fallback;
      saveCachedBandArrangements(contextKey, arrangements);
      state.band.contextKey = contextKey;
      state.band.arrangements = arrangements;
      return arrangements;
    } catch (error) {
      saveCachedBandArrangements(contextKey, fallback);
      state.band.contextKey = contextKey;
      state.band.arrangements = fallback;
      return fallback;
    }
  }

  function chooseNextBandArrangement() {
    if (!state.band.arrangements.length) return null;
    const context = makeBandContext('live');
    const preferredFamily = context.weather === 'rain' || context.weather === 'drizzle' ? 'moody_rain_groove'
      : context.time_of_day === 'dusk' ? 'dusk_wander'
      : context.time_of_day === 'morning' && context.intensity === 'low' ? 'sparse_early_morning'
      : context.area.indexOf('clock') !== -1 ? 'clock_corner_flourish'
      : 'upbeat_welcome_shuffle';
    const candidates = state.band.arrangements.filter((item) => item.family === preferredFamily);
    const pool = candidates.length ? candidates : state.band.arrangements;
    const arrangement = pool[state.band.arrangementIndex % pool.length];
    state.band.arrangementIndex = (state.band.arrangementIndex + 1) % Math.max(pool.length, 1);
    state.band.lastFamily = arrangement.family || preferredFamily;
    return arrangement;
  }

  function triggerBandPart(partName, token, when, proximity, arrangement) {
    if (!state.band.synths) return;
    const parsed = parseBandToken(token, partName === 'bass' ? '4n' : '8n');
    if (!parsed || parsed.note === 'rest') return;
    const durationSeconds = Math.min(BAND_MAX_NOTE_SECONDS, ((60 / Math.max(78, arrangement.tempo || 100)) * (parsed.duration === '2n' ? 2 : parsed.duration === '4n' ? 1 : parsed.duration === '8n' ? 0.5 : 0.25)));
    const velocity = Math.min(0.95, 0.42 + (proximity * 0.28));
    if (partName === 'guitar') {
      state.band.synths.guitar.triggerAttackRelease(parseChordNotes(parsed.note), Math.max(0.08, durationSeconds * 0.8), when, velocity);
      watchBandVoice(state.band.synths.guitar, (durationSeconds * 1000) + 90);
      return;
    }
    const synth = state.band.synths[partName];
    if (!synth || typeof synth.triggerAttackRelease !== 'function') return;
    synth.triggerAttackRelease(parsed.note, Math.max(0.06, durationSeconds), when, velocity + (partName === 'sax' ? 0.06 : 0));
    watchBandVoice(synth, (durationSeconds * 1000) + 120);
  }

  function triggerBandPercussion(beatNumber, when, arrangement, proximity) {
    const percussion = (arrangement.parts && arrangement.parts.percussion) || {};
    const beatLabel = String(beatNumber);
    if ((percussion.kick || []).includes(beatLabel)) state.band.synths.kick.triggerAttackRelease('C1', '16n', when, 0.4 + (proximity * 0.2));
    if ((percussion.snare || []).includes(beatLabel)) state.band.synths.snare.triggerAttackRelease('16n', when, 0.22 + (proximity * 0.1));
    if ((percussion.hat || []).includes(beatLabel)) state.band.synths.hat.triggerAttackRelease('32n', when, 0.12 + (proximity * 0.06));
    ['kick','snare','hat'].forEach((name) => watchBandVoice(state.band.synths[name], 160));
  }

  function bandShouldBeActive() {
    return !state.activeDialogNpcId && state.cameraMode === 'street' && getBandProximity() > 0.08 && state.audioUnlocked;
  }

  function scheduleBandCycle() {
    if (!state.band.activeArrangement || !state.band.active || !state.band.synths) return;
    if (state.band.scheduler) window.clearTimeout(state.band.scheduler);
    const arrangement = state.band.activeArrangement;
    const proximity = getBandProximity();
    const beatMs = (60 / Math.max(78, arrangement.tempo || 100)) * 1000;
    const beatStep = 0.5;
    const totalBeats = Math.max(8, (arrangement.bars || 8) * 4);
    const stepIndex = state.band.currentBeatCursor;
    const musicalBeat = (stepIndex * beatStep) + 1;
    const when = getTone().now() + 0.04;
    const partIndex = stepIndex % Math.max((arrangement.parts.sax || []).length, 1);
    triggerBandPart('bass', (arrangement.parts.bass || [])[stepIndex % Math.max((arrangement.parts.bass || []).length, 1)], when, proximity, arrangement);
    if (stepIndex % 2 === 0) triggerBandPart('guitar', (arrangement.parts.guitar || [])[Math.floor(stepIndex / 2) % Math.max((arrangement.parts.guitar || []).length, 1)], when, proximity, arrangement);
    if (stepIndex % 2 === 0 || proximity > 0.7) triggerBandPart('mandolin', (arrangement.parts.mandolin || [])[partIndex], when + 0.01, proximity, arrangement);
    if (stepIndex % 2 === 0) triggerBandPart('sax', (arrangement.parts.sax || [])[partIndex], when + (stepIndex % 4 === 0 ? 0.02 : 0.07), proximity, arrangement);
    triggerBandPercussion((musicalBeat % 4) || 4, when, arrangement, proximity);
    state.band.currentBeatCursor += 1;
    if ((state.band.currentBeatCursor * beatStep) >= totalBeats) {
      state.band.currentBeatCursor = 0;
      state.band.activeArrangement = chooseNextBandArrangement();
    }
    state.band.scheduler = window.setTimeout(scheduleBandCycle, Math.max(120, beatMs * beatStep));
  }

  async function ensureBandPlayback() {
    if (!bandShouldBeActive()) {
      if (state.band.active) stopBandAudio('inactive');
      return;
    }
    await ensureBandAudioReady();
    if (!state.band.arrangements.length) {
      if (!state.band.pendingRequest) {
        state.band.pendingRequest = fetchBandArrangements(false).finally(() => { state.band.pendingRequest = null; });
      }
      await state.band.pendingRequest;
    }
    if (!state.band.activeArrangement) state.band.activeArrangement = chooseNextBandArrangement();
    if (!state.band.activeArrangement) return;
    state.band.active = true;
    if (!state.band.scheduler) scheduleBandCycle();
  }

  function tickBandSystem() {
    if (state.band.lastWatchdogSweepAt && performance.now() - state.band.lastWatchdogSweepAt < 500) return;
    state.band.lastWatchdogSweepAt = performance.now();
    if (!bandShouldBeActive()) {
      if (state.band.active) stopBandAudio('proximity-or-state');
      return;
    }
    ensureBandPlayback().catch((error) => {
      warnAudioUnavailable('Band arrangement playback failed; simulator continuing without street band audio.', error);
      stopBandAudio('error');
    });
  }

  function clearNpcVisuals() {
    (state.npcs || []).forEach((npc) => { if (npc.mesh) { worldGroup.remove(npc.mesh); } });
    state.npcs = [];
    visualState.npcMeshes = [];
  }

  function addNpcs(world) {
    clearNpcVisuals();
    const sourceNpcs = filterSpawnNpcs(world);
    state.npcs = sourceNpcs.map((npc) => {
      const visual = makeNpcVisual(npc);
      const start = npc.idleSpot || npc.patrol[0] || { x: 0, z: 0 };
      visual.position.set(start.x, 0, start.z);
      worldGroup.add(visual);
      const swaySeed = deterministicUnit(npc.id + '-sway');
      const startYaw = typeof npc.yaw === 'number' ? npc.yaw : deterministicUnit(npc.id + '-yaw') * Math.PI * 2;
      const npcState = Object.assign({
        mesh: visual, patrolIndex: 0, direction: 1, baseY: 0, baseYaw: startYaw, currentYaw: startYaw,
        speed: npc.role === 'pedestrian' || npc.role === 'tourist' || npc.role === 'photographer' ? 0.84 + (deterministicUnit(npc.id) * 0.34) : npc.role === 'guide' ? 0.18 : npc.role === 'skateboarder' ? 2.35 : npc.role === 'cyclist' ? 3.1 : 0,
        pauseUntil: 0, animPhase: swaySeed * Math.PI * 2, swayAmount: 0.012 + (swaySeed * 0.014),
        microEvent: '', interactionTargetId: '', socialRadius: npc.role === 'tourist' || npc.role === 'photographer' ? 3.2 : 2.2,
      }, npc);
      visual.rotation.y = startYaw;
      visualState.npcMeshes.push(visual);
      return npcState;
    });
    updateMinimapLegend();
  }

  function refreshNpcPopulation() {
    if (!state.world) return;
    addNpcs(state.world);
  }

  function getNpcTargetSpeed(npc) {
    const weather = state.activeWeather || 'clear';
    const timeOfDay = state.activeTimeOfDay || 'morning';
    const nearPlayer = npc && npc.mesh ? Math.hypot(player.position.x - npc.mesh.position.x, player.position.z - npc.mesh.position.z) < 5.5 : false;
    let multiplier = 1;
    if (weather === 'rain' || weather === 'thunderstorm') multiplier *= npc.role === 'cyclist' || npc.role === 'skateboarder' ? 0.62 : 0.82;
    if (weather === 'fog') multiplier *= npc.role === 'tourist' || npc.role === 'photographer' ? 0.76 : 0.9;
    if (timeOfDay === 'night') multiplier *= npc.role === 'guide' || npc.role === 'busker' ? 0.9 : 0.8;
    if (timeOfDay === 'morning' && npc.role === 'pedestrian') multiplier *= 1.14;
    if (nearPlayer && (npc.role === 'cyclist' || npc.role === 'skateboarder')) multiplier *= 0.74;
    return Math.max(0, (npc.speed || 0) * multiplier);
  }

  function assignNpcMicroEvents(nowSeconds) {
    const context = getPlayerRouteContext();
    const byGroup = {};
    (state.npcs || []).forEach((npc) => {
      npc.microEvent = '';
      npc.interactionTargetId = '';
      if (npc.companionGroup) {
        byGroup[npc.companionGroup] = byGroup[npc.companionGroup] || [];
        byGroup[npc.companionGroup].push(npc);
      }
      if (npc.role === 'busker' && context.key === 'steam_clock') npc.microEvent = 'busking_moment';
      if (npc.role === 'photographer' && context.key === 'steam_clock') npc.microEvent = 'photo_window';
      if (npc.role === 'pedestrian' && state.activeMood === 'commuter') npc.microEvent = 'worker_crossing';
      if (npc.role === 'tourist' && context.key === 'maple_tree_square') npc.microEvent = 'landmark_gather';
      npc.reactingToPlayer = npc.mesh ? Math.hypot(player.position.x - npc.mesh.position.x, player.position.z - npc.mesh.position.z) < 4.8 : false;
      if (npc.reactingToPlayer && (npc.role === 'tourist' || npc.role === 'photographer')) npc.microEvent = 'player_notice';
      if ((state.activeWeather === 'rain' || state.activeWeather === 'thunderstorm') && (npc.role === 'tourist' || npc.role === 'photographer')) npc.microEvent = 'weather_huddle';
      if (state.activeTimeOfDay === 'night' && npc.role === 'guide') npc.microEvent = 'night_story_pause';
      npc.nextAmbientShiftAt = npc.nextAmbientShiftAt || (nowSeconds + 2 + (deterministicUnit(npc.id + '-ambient') * 3));
      if (nowSeconds >= npc.nextAmbientShiftAt) {
        npc.ambientPhase = (npc.ambientPhase || 0) + 1;
        npc.nextAmbientShiftAt = nowSeconds + 4 + (deterministicUnit(npc.id + '-ambient-' + npc.ambientPhase) * 5);
      }
    });
    Object.keys(byGroup).forEach((groupId) => {
      const members = byGroup[groupId];
      members.forEach((npc, index) => {
        const partner = members[(index + 1) % members.length];
        if (partner && partner.id !== npc.id) {
          npc.interactionTargetId = partner.id;
          if (!npc.microEvent || npc.microEvent === 'landmark_gather') npc.microEvent = 'chatting_cluster';
        }
      });
    });
  }

  function updateNpcs(delta) {
    if (!Array.isArray(state.npcs)) return;
    const nowSeconds = performance.now() / 1000;
    refreshDiscoveryState();
    assignNpcMicroEvents(nowSeconds);
    state.npcs.forEach((npc) => {
      if (!npc.mesh) return;
      const isWalker = Array.isArray(npc.patrol) && npc.patrol.length > 1;
      const touristPause = npc.behavior === 'tourist_pause' || npc.behavior === 'photo_idle' || npc.behavior === 'tourist_wander';
      const subtleWalker = npc.role === 'guide';
      const rollingMover = npc.role === 'skateboarder' || npc.role === 'cyclist';
      const canWalk = isWalker && (npc.role === 'pedestrian' || npc.role === 'tourist' || npc.role === 'photographer' || subtleWalker || rollingMover);

      if (canWalk) {
        const target = npc.patrol[npc.patrolIndex] || npc.patrol[0];
        const dx = target.x - npc.mesh.position.x;
        const dz = target.z - npc.mesh.position.z;
        const distance = Math.hypot(dx, dz);
        if (distance < 0.24) {
          if (touristPause) {
            npc.pauseUntil = nowSeconds + 1.4 + (deterministicUnit(npc.id + '-pause-' + npc.patrolIndex) * 1.1);
          }
          npc.patrolIndex = (npc.patrolIndex + npc.direction + npc.patrol.length) % npc.patrol.length;
        } else if (nowSeconds >= (npc.pauseUntil || 0)) {
          const step = Math.min(distance, getNpcTargetSpeed(npc) * delta);
          npc.mesh.position.x += (dx / distance) * step;
          npc.mesh.position.z += (dz / distance) * step;
          const desiredYaw = Math.atan2(dx, dz);
          npc.currentYaw += THREE.MathUtils.clamp(desiredYaw - npc.currentYaw, -0.08, 0.08);
        }
      } else if (npc.idleSpot) {
        npc.mesh.position.x = npc.idleSpot.x;
        npc.mesh.position.z = npc.idleSpot.z;
        if (npc.behavior === 'photo_idle') {
          npc.currentYaw = -0.45;
        }
      }

      const interactionTarget = npc.interactionTargetId ? (state.npcs || []).find((candidate) => candidate.id === npc.interactionTargetId) : null;
      if (interactionTarget && interactionTarget.mesh) {
        const ix = interactionTarget.mesh.position.x - npc.mesh.position.x;
        const iz = interactionTarget.mesh.position.z - npc.mesh.position.z;
        if (Math.hypot(ix, iz) < npc.socialRadius) {
          npc.currentYaw += THREE.MathUtils.clamp(Math.atan2(ix, iz) - npc.currentYaw, -0.035, 0.035);
        }
      }
      if (npc.microEvent === 'weather_huddle') {
        npc.pauseUntil = Math.max(npc.pauseUntil || 0, nowSeconds + 0.6);
      } else if (npc.microEvent === 'player_notice') {
        const px = player.position.x - npc.mesh.position.x;
        const pz = player.position.z - npc.mesh.position.z;
        npc.currentYaw += THREE.MathUtils.clamp(Math.atan2(px, pz) - npc.currentYaw, -0.03, 0.03);
      }

      const motionStrength = canWalk && nowSeconds >= (npc.pauseUntil || 0) ? (rollingMover ? 0.4 : 1) : npc.role === 'busker' ? 0.8 : npc.microEvent === 'chatting_cluster' ? 0.62 : 0.45;
      npc.mesh.position.y = npc.baseY + (Math.sin((nowSeconds * 3.4) + npc.animPhase) * npc.swayAmount * motionStrength);
      npc.mesh.rotation.z = Math.sin((nowSeconds * 2.2) + npc.animPhase) * 0.02;
      const rig = npc.mesh.userData || {};
      const stride = Math.sin((nowSeconds * (rollingMover ? 4.2 : canWalk ? 7.4 : 3.1)) + npc.animPhase);
      if (rig.leftArm && rig.rightArm) {
        const armSwing = rollingMover ? 0.16 : (canWalk ? 0.6 : 0.18);
        rig.leftArm.rotation.x = stride * armSwing;
        rig.rightArm.rotation.x = -stride * armSwing;
      }
      if (rig.leftLeg && rig.rightLeg) {
        const legSwing = rollingMover ? 0.08 : (canWalk ? 0.52 : 0.1);
        rig.leftLeg.rotation.x = -stride * legSwing;
        rig.rightLeg.rotation.x = stride * legSwing;
      }
      if (rig.coatHem) {
        rig.coatHem.rotation.x = (canWalk ? 0.08 : 0.03) + (Math.abs(stride) * 0.12);
      }
      if (rig.shoulders) {
        rig.shoulders.rotation.z = Math.sin((nowSeconds * 1.6) + npc.animPhase) * (canWalk ? 0.02 : 0.008);
      }
      if (rig.head) {
        rig.head.rotation.y = Math.sin((nowSeconds * 0.9) + npc.animPhase) * 0.12;
        rig.head.rotation.x = npc.pose === 'taking_photo' ? -0.18 : Math.sin((nowSeconds * 0.55) + npc.animPhase) * 0.03;
      }
      if (rig.hat) {
        rig.hat.rotation.z = npc.role === 'skateboarder' ? 0.08 : 0;
      }
      if (rig.bag) {
        rig.bag.rotation.z = -0.08 + (stride * 0.12);
      }
      if (rig.umbrella) {
        rig.umbrella.rotation.z = 0.06 + (Math.sin((nowSeconds * 1.1) + npc.animPhase) * 0.02);
      }
      if (rig.umbrellaShaft) {
        rig.umbrellaShaft.rotation.z = 0.06;
      }
      if (rig.heldProp) {
        if (npc.heldProp === 'guitar') {
          rig.heldProp.rotation.y = Math.sin((nowSeconds * 2.6) + npc.animPhase) * 0.12;
        } else if (npc.heldProp === 'camera') {
          rig.heldProp.position.y = (npc.pose === 'taking_photo' ? 1.48 : 1.32) + (Math.sin((nowSeconds * 1.2) + npc.animPhase) * 0.03);
          rig.heldProp.rotation.x = npc.pose === 'taking_photo' ? -0.42 : -0.18;
        }
      }
      if (npc.microEvent === 'busking_moment' && rig.leftArm && rig.rightArm) {
        rig.rightArm.rotation.x = -1.05 + (Math.sin((nowSeconds * 5.2) + npc.animPhase) * 0.18);
        rig.leftArm.rotation.x = -0.42 + (Math.cos((nowSeconds * 4.8) + npc.animPhase) * 0.16);
      }
      if ((npc.microEvent === 'photo_window' || npc.microEvent === 'player_notice') && rig.heldProp && npc.heldProp === 'camera') {
        rig.heldProp.position.y = 1.48;
        rig.heldProp.rotation.x = -0.48;
      }
      if (npc.microEvent === 'worker_crossing' && rig.leftArm && rig.rightArm) {
        rig.leftArm.rotation.x *= 1.2;
        rig.rightArm.rotation.x *= 1.2;
      }
      if (npc.microEvent === 'chatting_cluster' && rig.leftArm) {
        rig.leftArm.rotation.z = 0.08 + (Math.sin((nowSeconds * 2) + npc.animPhase) * 0.08);
      }
      npc.currentYaw = Number.isFinite(npc.currentYaw) ? npc.currentYaw : (npc.baseYaw || 0);
      if (npc.role === 'busker') {
        npc.currentYaw = (npc.baseYaw || 0) + (Math.sin((nowSeconds * 1.3) + npc.animPhase) * 0.01);
      }
      if (rollingMover) {
        npc.mesh.rotation.z = Math.sin((nowSeconds * (npc.role === 'cyclist' ? 1.8 : 2.6)) + npc.animPhase) * (npc.role === 'cyclist' ? 0.012 : 0.028);
        if (rig.heldProp && npc.heldProp === 'skateboard') {
          rig.heldProp.rotation.z = 1.57;
          rig.heldProp.rotation.x = Math.sin((nowSeconds * 4.6) + npc.animPhase) * 0.04;
        }
        if (rig.heldProp && npc.heldProp === 'bike') {
          rig.heldProp.rotation.y = Math.sin((nowSeconds * 2.4) + npc.animPhase) * 0.06;
        }
      }
      if (npc.pose === 'being_photographed') {
        npc.currentYaw = -0.24;
      }
      if (npc.pose === 'group_gather' || npc.pose === 'gathered') {
        npc.currentYaw += Math.sin((nowSeconds * 0.8) + npc.animPhase) * 0.0015;
      }
      npc.mesh.rotation.y = npc.currentYaw;

    });
  }

  function scoreNpcInteractionCandidate(npc, options) {
    if (!npc || !npc.mesh) return null;
    const radius = (npc.interactRadius || 2.8) + 1.6;
    const dx = npc.mesh.position.x - player.position.x;
    const dz = npc.mesh.position.z - player.position.z;
    const distance = Math.hypot(dx, dz);
    if (distance > radius) return null;
    const heading = getHeadingVector();
    const dirX = dx / Math.max(distance, 0.001);
    const dirZ = dz / Math.max(distance, 0.001);
    const facingDot = (heading.x * dirX) + (heading.z * dirZ);
    if (facingDot < -0.25) return null;
    const centerBonus = options && options.centered ? Math.max(0, facingDot - 0.75) * 1.6 : 0;
    const rayBonus = options && options.rayHit ? 0.45 : 0;
    const visibleScore = Math.max(0, facingDot + 0.25) * 1.8;
    const distanceScore = Math.max(0, 1.5 - (distance / Math.max(1, radius)));
    return {
      npc,
      distance,
      facingDot,
      score: visibleScore + distanceScore + centerBonus + rayBonus,
      centered: !!(options && options.centered),
      rayHit: !!(options && options.rayHit),
    };
  }

  function findLookedAtNpc() {
    if (!Array.isArray(state.npcs) || state.npcs.length === 0) return null;
    camera.getWorldDirection(lookTarget);
    camera.getWorldPosition(tempCameraWorldPosition);
    sharedRaycaster.set(tempCameraWorldPosition, lookTarget);
    let best = null;
    state.npcs.forEach((npc) => {
      if (!npc.mesh) return;
      const hits = sharedRaycaster.intersectObject(npc.mesh, true);
      const hitDistance = hits.length ? hits[0].distance : null;
      const centered = hits.length || lookTarget.dot(new THREE.Vector3().subVectors(npc.mesh.position, tempCameraWorldPosition).normalize()) > 0.92;
      const candidate = scoreNpcInteractionCandidate(npc, { rayHit: hits.length > 0, centered });
      if (!candidate) return;
      candidate.hitDistance = hitDistance;
      if (!best || candidate.score > best.score || (candidate.score === best.score && candidate.distance < best.distance)) best = candidate;
    });
    return best;
  }

  function updateInteractionTarget() {
    if (state.cameraMode !== 'street') {
      state.hoveredNpcId = '';
      setInteractPrompt('');
      return;
    }
    const npcTarget = findLookedAtNpc();
    const collectible = findNearbyCollectible();
    state.hoveredNpcId = npcTarget ? npcTarget.npc.id : '';
    state.motion.interactionPulse = (Math.sin((performance.now() / 1000) * 5.4) + 1) * 0.5;
    if (npcTarget) {
      const npc = npcTarget.npc;
      const roleLabel = getNpcRoleLabel(npc);
      const verb = npc.role === 'busker' ? 'listen' : 'talk';
      setInteractPrompt('E: ' + verb + ' to ' + (npc.name || roleLabel).toLowerCase());
      return;
    }
    if (collectible) {
      const prop = collectible.prop;
      const range = 2.2;
      const closeEnough = collectible.distance <= range;
      setInteractPrompt(closeEnough ? 'E: log ' + (prop.collectibleLabel || prop.id).toLowerCase() : 'Street detail nearby');
      return;
    }
    setInteractPrompt('');
  }

  function interactWithHoveredNpc() {
    const target = findLookedAtNpc();
    if (!target || !target.npc) return false;
    state.hoveredNpcId = target.npc.id;
    openDialogForNpc(target.npc);
    return true;
  }

  function toneToColor(tone) {
    switch (tone) {
      case 'brickWarm': return 0x6a4638;
      case 'stoneMuted': return 0x545b64;
      case 'brickDark':
      default:
        return 0x3f2a2a;
    }
  }

  const facadeProfilePresets = {
    waterfront_station_civic: {
      baseInset: 0.98,
      windowRows: 2,
      rooflineLift: 1.4,
      storefrontBand: 0.1,
      awningDepth: 0,
      silhouetteLift: 1.1,
      columnRhythm: true,
    },
    cordova_commercial_transition: {
      baseInset: 0.96,
      windowRows: 3,
      rooflineLift: 1,
      storefrontBand: 0.15,
      awningDepth: 0.9,
      silhouetteLift: 0.7,
    },
    gastown_heritage_row: {
      baseInset: 0.95,
      windowRows: 3,
      rooflineLift: 1.2,
      storefrontBand: 0.18,
      awningDepth: 1.05,
      silhouetteLift: 0.95,
    },
    gastown_corner_retail: {
      baseInset: 0.95,
      windowRows: 3,
      rooflineLift: 1.3,
      storefrontBand: 0.2,
      awningDepth: 1.1,
      silhouetteLift: 1,
    },
    gastown_heritage_masonry: {
      baseInset: 0.94,
      windowRows: 3,
      rooflineLift: 1.1,
      storefrontBand: 0.16,
      awningDepth: 1.05,
      silhouetteLift: 0.8,
    },
    victorian_storefront_row: {
      baseInset: 0.96,
      windowRows: 2,
      rooflineLift: 0.9,
      storefrontBand: 0.18,
      awningDepth: 1.2,
      silhouetteLift: 0.6,
    },
    narrow_brick_shaft: {
      baseInset: 0.94,
      windowRows: 4,
      rooflineLift: 1.35,
      storefrontBand: 0.16,
      awningDepth: 0.9,
      silhouetteLift: 1.2,
    },
    wedge_corner_block: {
      baseInset: 0.95,
      windowRows: 4,
      rooflineLift: 1.6,
      storefrontBand: 0.17,
      awningDepth: 1.1,
      silhouetteLift: 1.5,
    },
    warehouse_commercial_front: {
      baseInset: 0.97,
      windowRows: 3,
      rooflineLift: 0.7,
      storefrontBand: 0.12,
      awningDepth: 0,
      silhouetteLift: 0.4,
    },
    steam_clock_corner: {
      baseInset: 0.94,
      windowRows: 4,
      rooflineLift: 1.45,
      storefrontBand: 0.2,
      awningDepth: 1,
      silhouetteLift: 1.3,
    },
  };

  function paletteToColors(building) {
    const profile = (state.world && state.world.facade_profiles && state.world.facade_profiles[building.facade_profile]) || {};
    const palette = building.material_palette || profile.material_palette || {};
    const primary = palette.primary || building.tone || 'brickDark';
    const accent = palette.accent || 'stoneMuted';
    const trim = palette.trim || 'stoneMuted';
    const secondary = palette.secondary || primary;
    return {
      primary: toneToColor(primary),
      accent: toneToColor(accent),
      trim: toneToColor(trim),
      secondary: toneToColor(secondary),
    };
  }

  function polygonSignedArea(points) {
    if (!Array.isArray(points) || points.length < 3) {
      return 0;
    }
    let area = 0;
    for (let i = 0; i < points.length; i += 1) {
      const current = points[i];
      const next = points[(i + 1) % points.length];
      area += (current.x * next.z) - (next.x * current.z);
    }
    return area / 2;
  }

  function sanitizePolygon(points) {
    if (!Array.isArray(points)) {
      return [];
    }

    const deduped = [];
    points.forEach((point) => {
      if (!point || !Number.isFinite(point.x) || !Number.isFinite(point.z)) {
        return;
      }
      const last = deduped[deduped.length - 1];
      if (last && Math.abs(last.x - point.x) < 0.001 && Math.abs(last.z - point.z) < 0.001) {
        return;
      }
      deduped.push({ x: point.x, z: point.z });
    });

    if (deduped.length > 2) {
      const first = deduped[0];
      const last = deduped[deduped.length - 1];
      if (Math.abs(first.x - last.x) < 0.001 && Math.abs(first.z - last.z) < 0.001) {
        deduped.pop();
      }
    }

    const cleaned = deduped.filter((point, index, arr) => {
      if (arr.length < 3) {
        return true;
      }
      const prev = arr[(index + arr.length - 1) % arr.length];
      const next = arr[(index + 1) % arr.length];
      const cross = ((point.x - prev.x) * (next.z - point.z)) - ((point.z - prev.z) * (next.x - point.x));
      return Math.abs(cross) > 0.0005;
    });

    if (cleaned.length >= 3 && polygonSignedArea(cleaned) < 0) {
      cleaned.reverse();
    }
    return cleaned;
  }

  function toShape(points) {
    const sanitizedPoints = sanitizePolygon(points);
    if (sanitizedPoints.length < 3) {
      return null;
    }
    const shape = new THREE.Shape();
    sanitizedPoints.forEach((point, index) => {
      if (index === 0) {
        shape.moveTo(point.x, point.z);
      } else {
        shape.lineTo(point.x, point.z);
      }
    });
    shape.closePath();
    return shape;
  }

  function createZoneMesh(points, material, y, textureKind) {
    const shape = toShape(points);
    if (!shape) {
      return null;
    }
    const geometry = new THREE.ShapeGeometry(shape);
    if (textureKind && WORLD_TEXTURE_REPEAT[textureKind]) {
      applyWorldUvs(geometry, WORLD_TEXTURE_REPEAT[textureKind]);
    } else {
      cloneUvToUv2(geometry);
    }
    const mesh = new THREE.Mesh(geometry, material);
    mesh.rotation.x = -Math.PI / 2;
    mesh.position.y = y;
    mesh.receiveShadow = true;
    worldGroup.add(mesh);
    visualState.groundMeshes.push(mesh);
    return mesh;
  }

  function addGround(world) {
    visualState.roadMaterial = registerFallbackSurfaceMaterial('road', createGroundMaterial('street', FALLBACK_SURFACE_PRESETS.road.color, FALLBACK_SURFACE_PRESETS.road.roughness, FALLBACK_SURFACE_PRESETS.road.metalness), FALLBACK_SURFACE_PRESETS.road.color, FALLBACK_SURFACE_PRESETS.road.roughness, FALLBACK_SURFACE_PRESETS.road.metalness);
    visualState.sidewalkMaterial = registerFallbackSurfaceMaterial('sidewalk', createGroundMaterial('sidewalk', FALLBACK_SURFACE_PRESETS.sidewalk.color, FALLBACK_SURFACE_PRESETS.sidewalk.roughness, FALLBACK_SURFACE_PRESETS.sidewalk.metalness), FALLBACK_SURFACE_PRESETS.sidewalk.color, FALLBACK_SURFACE_PRESETS.sidewalk.roughness, FALLBACK_SURFACE_PRESETS.sidewalk.metalness);
    visualState.curbMaterial = new THREE.LineBasicMaterial({ color: FALLBACK_SURFACE_PRESETS.curb.color, transparent: true, opacity: 0.34 });
    visualState.laneMaterial = registerFallbackSurfaceMaterial('lane', new THREE.MeshStandardMaterial({ color: FALLBACK_SURFACE_PRESETS.lane.color, roughness: FALLBACK_SURFACE_PRESETS.lane.roughness, metalness: FALLBACK_SURFACE_PRESETS.lane.metalness, transparent: false, opacity: 1, depthWrite: true, depthTest: true }), FALLBACK_SURFACE_PRESETS.lane.color, FALLBACK_SURFACE_PRESETS.lane.roughness, FALLBACK_SURFACE_PRESETS.lane.metalness);
    visualState.routeGuideMaterials = [visualState.laneMaterial];

    world.zones.street.forEach((zone) => createZoneMesh(zone.polygon, visualState.roadMaterial, 0, 'street'));
    world.zones.sidewalk.forEach((zone) => createZoneMesh(zone.polygon, visualState.sidewalkMaterial, 0.12, 'sidewalk'));

    world.zones.street.forEach((zone) => {
      if (!Array.isArray(zone.polygon) || zone.polygon.length < 3) return;
      const borderPoints = buildClosedBorderPoints(zone.polygon, 0.16);
      const curbLine = new THREE.Line(new THREE.BufferGeometry().setFromPoints(borderPoints), visualState.curbMaterial);
      worldGroup.add(curbLine);
    });

    const surfaceBands = (world.streetscape && world.streetscape.surfaceBands && world.streetscape.surfaceBands.length)
      ? world.streetscape.surfaceBands
      : buildFallbackSurfaceBands(world);

    surfaceBands.forEach((band) => {
      const segment = world.route.centerline.find((point) => point.id === band.segment_id);
      if (!segment) return;
      const paver = new THREE.Mesh(
        new THREE.PlaneGeometry(band.width || world.route.streetWidth, band.length || 14),
        (() => {
          const toneStyles = {
            brick: { role: 'plaza', color: 0x5c4033, roughness: 0.82, metalness: 0.06, opacity: 0.72 },
            road_base_dark: { role: 'road', color: 0x202d39, roughness: 0.98, metalness: 0.01, opacity: 0.9 },
            wheel_track: { role: 'road', color: 0x161f28, roughness: 0.9, metalness: 0.03, opacity: 0.76 },
            patch: { color: 0x453930, roughness: 0.9, metalness: 0.02, opacity: 0.7 },
            repair_patch_dark: { color: 0x372f2a, roughness: 0.92, metalness: 0.02, opacity: 0.74 },
            puddle: { role: 'road', color: 0x253240, roughness: 0.56, metalness: 0.15, opacity: 0.05 },
            wet_streak: { role: 'road', color: 0x31404e, roughness: 0.72, metalness: 0.08, opacity: 0.08 },
            reflection_pool: { role: 'road', color: 0x2b3948, roughness: 0.62, metalness: 0.11, opacity: 0.04 },
            edge_grime: { role: 'sidewalk', color: 0x3d352d, roughness: 0.94, metalness: 0.02, opacity: 0.44 },
            curb_grime: { role: 'sidewalk', color: 0x312b25, roughness: 0.95, metalness: 0.02, opacity: 0.48 },
            cobble_break: { role: 'plaza', color: 0x6a5848, roughness: 0.92, metalness: 0.02, opacity: 0.56 },
            paver_break: { role: 'plaza', color: 0x705d4b, roughness: 0.92, metalness: 0.02, opacity: 0.58 },
            intersection_pavers: { role: 'plaza', color: 0x7b6856, roughness: 0.95, metalness: 0.02, opacity: 0.72 },
            default: { role: 'plaza', color: 0x4a4038, roughness: 0.92, metalness: 0.02, opacity: 0.54 },
          };
          const style = toneStyles[band.tone] || toneStyles.default;
          const material = registerFallbackSurfaceMaterial(style.role || 'plaza', new THREE.MeshStandardMaterial({
            color: style.color,
            roughness: style.roughness,
            metalness: style.metalness,
            transparent: true,
            opacity: Math.min(style.opacity, band.opacity || style.opacity),
            depthWrite: false,
          }), style.color, style.roughness, style.metalness);
          return material;
        })()
      );
      paver.rotation.x = -Math.PI / 2;
      paver.renderOrder = 0;
      paver.rotation.y = band.yaw || 0;
      paver.position.set(segment.x + (band.offset_x || 0), band.elevation || 0.02, segment.z + (band.offset_z || 0));
      worldGroup.add(paver);
    });
  }

  function addStreetscape(world) {
    const streetscape = world.streetscape || {};

    (streetscape.lamps || []).forEach((lamp) => {
      const pole = new THREE.Mesh(
        new THREE.CylinderGeometry(0.08, 0.1, lamp.height || 4.6, 8),
        registerMaterial(new THREE.MeshStandardMaterial({ color: 0x2b2f35, roughness: 0.6, metalness: 0.35 }), 'metalMaterials')
      );
      pole.position.set(lamp.x, (lamp.height || 4.6) / 2, lamp.z);
      worldGroup.add(pole);

      const globeMaterial = registerMaterial(new THREE.MeshStandardMaterial({
        color: FALLBACK_SURFACE_PRESETS.lampGlass.color,
        emissive: FALLBACK_SURFACE_PRESETS.lampGlass.emissive,
        emissiveIntensity: 0.16,
        roughness: FALLBACK_SURFACE_PRESETS.lampGlass.roughness,
        metalness: FALLBACK_SURFACE_PRESETS.lampGlass.metalness,
      }), 'emissiveMaterials');
      const globe = new THREE.Mesh(
        new THREE.SphereGeometry(0.3, 12, 10),
        globeMaterial
      );
      globe.position.set(lamp.x, (lamp.height || 4.6) + 0.25, lamp.z);
      worldGroup.add(globe);

      const halo = new THREE.Mesh(
        new THREE.SphereGeometry(0.6, 10, 10),
        new THREE.MeshBasicMaterial({ color: FALLBACK_SURFACE_PRESETS.lampHalo.color, transparent: true, opacity: 0.04, depthWrite: false })
      );
      halo.position.copy(globe.position);
      worldGroup.add(halo);

      const lampLight = new THREE.PointLight(0xffc27a, 0, 7.5, 2);
      lampLight.position.copy(globe.position);
      worldGroup.add(lampLight);
      visualState.lampVisuals.push({ globeMaterial, haloMaterial: halo.material, pointLight: lampLight });
    });

    (streetscape.bollards || []).forEach((bollard) => {
      const post = new THREE.Mesh(
        new THREE.CylinderGeometry(0.09, 0.11, 0.95, 10),
        new THREE.MeshStandardMaterial({ color: 0x2f363f, roughness: 0.58, metalness: 0.34 })
      );
      post.position.set(bollard.x, 0.48, bollard.z);
      worldGroup.add(post);

      if (bollard.chain_to) {
        const chainTo = streetscape.bollards.find((candidate) => candidate.id === bollard.chain_to);
        if (chainTo) {
          const dx = chainTo.x - bollard.x;
          const dz = chainTo.z - bollard.z;
          const span = Math.hypot(dx, dz);
          if (span > 0.2) {
            const chain = new THREE.Mesh(
              new THREE.CylinderGeometry(0.03, 0.03, span, 8),
              new THREE.MeshStandardMaterial({ color: 0x5c666f, roughness: 0.46, metalness: 0.62 })
            );
            chain.position.set((bollard.x + chainTo.x) / 2, 0.58, (bollard.z + chainTo.z) / 2);
            chain.rotation.x = Math.PI / 2;
            chain.rotation.z = Math.atan2(dz, dx);
            worldGroup.add(chain);
          }
        }
      }
    });

    (streetscape.trees || []).forEach((tree) => {
      const trunk = new THREE.Mesh(
        new THREE.CylinderGeometry(0.16, 0.2, 2, 7),
        new THREE.MeshStandardMaterial({ color: 0x4a3729, roughness: 0.85, metalness: 0.03 })
      );
      trunk.position.set(tree.x, 1, tree.z);
      worldGroup.add(trunk);

      const canopy = new THREE.Mesh(
        new THREE.DodecahedronGeometry(tree.radius || 0.9, 0),
        new THREE.MeshStandardMaterial({ color: 0x476147, roughness: 0.9, metalness: 0.02 })
      );
      canopy.position.set(tree.x, 2.5, tree.z);
      worldGroup.add(canopy);
    });

    (streetscape.signs || []).forEach((sign) => {
      const poleMat = registerMaterial(new THREE.MeshStandardMaterial({ color: 0x505862, roughness: 0.52, metalness: 0.54 }), 'metalMaterials');
      const faceMat = registerMaterial(new THREE.MeshStandardMaterial({
        color: sign.faceColor || 0xd8ddd9,
        roughness: 0.48,
        metalness: 0.18,
        emissive: sign.emissive || 0x09131d,
        emissiveIntensity: sign.glow ? 0.12 : 0.02,
      }), 'emissiveMaterials');
      const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.05, sign.height || 2.4, 8), poleMat);
      pole.position.set(sign.x, (sign.height || 2.4) / 2, sign.z);
      worldGroup.add(pole);
      const face = new THREE.Mesh(new THREE.BoxGeometry(sign.width || 0.62, sign.panelHeight || 0.42, 0.05), faceMat);
      face.position.set(sign.x, (sign.height || 2.4) - ((sign.panelOffset || 0.42)), sign.z);
      face.rotation.y = sign.yaw || 0;
      worldGroup.add(face);
    });

    (streetscape.streetFurniture || []).forEach((item) => {
      const metalMat = registerMaterial(new THREE.MeshStandardMaterial({ color: 0x4e545d, roughness: 0.58, metalness: 0.42 }), 'metalMaterials');
      const woodMat = new THREE.MeshStandardMaterial({ color: 0x6b4e35, roughness: 0.8, metalness: 0.08 });
      const stoneMat = new THREE.MeshStandardMaterial({ color: 0x777066, roughness: 0.88, metalness: 0.06 });
      let mesh = null;
      if (item.kind === 'bench') {
        mesh = new THREE.Group();
        const seat = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.08, 0.36), woodMat);
        seat.position.y = 0.48;
        mesh.add(seat);
        [-0.7, 0, 0.7].forEach((x) => {
          const slat = new THREE.Mesh(new THREE.BoxGeometry(0.42, 0.06, 0.06), woodMat);
          slat.position.set(x, 0.78, -0.12);
          mesh.add(slat);
        });
        [-0.68, 0.68].forEach((x) => {
          const leg = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.52, 0.08), metalMat);
          leg.position.set(x, 0.24, 0);
          mesh.add(leg);
        });
      } else if (item.kind === 'bin') {
        mesh = new THREE.Group();
        const body = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.28, 0.88, 12), metalMat);
        body.position.y = 0.44;
        mesh.add(body);
        const lid = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.26, 0.08, 12), stoneMat);
        lid.position.y = 0.9;
        mesh.add(lid);
      } else if (item.kind === 'planter') {
        mesh = new THREE.Group();
        const base = new THREE.Mesh(new THREE.BoxGeometry(1, 0.6, 1), stoneMat);
        base.position.y = 0.3;
        mesh.add(base);
        const shrub = new THREE.Mesh(new THREE.DodecahedronGeometry(0.48, 0), new THREE.MeshStandardMaterial({ color: 0x4e6b47, roughness: 0.9, metalness: 0.02 }));
        shrub.position.y = 0.82;
        mesh.add(shrub);
      } else if (item.kind === 'utility') {
        mesh = new THREE.Mesh(new THREE.BoxGeometry(0.7, 1.5, 0.46), metalMat);
        mesh.position.y = 0.75;
      }
      if (mesh) {
        mesh.position.set(item.x, mesh.position.y || 0, item.z);
        mesh.rotation.y = item.yaw || 0;
        worldGroup.add(mesh);
      }
    });
  }

  function localPointToWorld(building, localX, localZ) {
    const yaw = building.yaw || 0;
    const cos = Math.cos(yaw);
    const sin = Math.sin(yaw);
    return {
      x: building.x + (localX * cos) - (localZ * sin),
      z: building.z + (localX * sin) + (localZ * cos),
    };
  }

  function toRenderableShapePoints(building) {
    if (!Array.isArray(building.footprint_local) || building.footprint_local.length < 3) {
      return [];
    }
    const points = building.footprint_local
      .filter((point) => Number.isFinite(point.x) && Number.isFinite(point.z))
      .map((point) => ({ x: point.x, z: point.z }));

    if (points.length < 3) {
      return [];
    }

    const first = points[0];
    const last = points[points.length - 1];
    if (Math.abs(first.x - last.x) < 0.0001 && Math.abs(first.z - last.z) < 0.0001) {
      points.pop();
    }

    return points.length >= 3 ? points : [];
  }

  function addBuildings(world) {
    world.buildings = (world.buildings || []).map((building) => window.GastownBuildingNormalizer.normalizeBuildingForRender(building));

    world.buildings.forEach((b) => {
      if (!Number.isFinite(b.x) || !Number.isFinite(b.z)) return;
      const shapePoints = toRenderableShapePoints(b);
      if (shapePoints.length < 3) return;

      const profilePreset = facadeProfilePresets[b.facade_profile] || facadeProfilePresets.gastown_heritage_masonry;
      const colors = paletteToColors(b);
      const storefrontRhythm = b.storefront_rhythm || {};
      const heroScale = b.hero_fidelity === 'hero' ? 1.03 : 1;
      const massingInset = b.mass_inset || profilePreset.baseInset;
      const geom = new THREE.ExtrudeGeometry(new THREE.Shape(shapePoints.map((point) => new THREE.Vector2(point.x, point.z))), {
        depth: b.height,
        bevelEnabled: false,
      });
      geom.rotateX(-Math.PI / 2);
      const mat = registerMaterial(new THREE.MeshStandardMaterial({
        color: colors.primary,
        roughness: b.tone === 'brickWarm' ? 0.88 : 0.76,
        metalness: 0.12,
        emissive: 0x11151f,
        emissiveIntensity: 0.18,
      }), 'buildingMaterials');
      mat.userData = {
        baseRoughness: mat.roughness,
        baseEmissiveIntensity: mat.emissiveIntensity,
        tone: b.tone || 'brickDark',
      };
      const mesh = new THREE.Mesh(geom, mat);
      mesh.scale.set(massingInset, 1, massingInset);
      mesh.position.set(b.x, 0, b.z);
      mesh.rotation.y = b.yaw || 0;
      worldGroup.add(mesh);

      const rooflineType = b.roofline_type || 'flat_cornice';
      if (rooflineType !== 'flat') {
        const roofHeight = Math.max(0.5, (b.cornice_emphasis || 0.2) * 2 + profilePreset.rooflineLift * 0.2);
        const roofGeom = new THREE.CylinderGeometry((Math.max(b.width, b.depth) * 0.5) * 0.92, (Math.max(b.width, b.depth) * 0.5), roofHeight, 6);
        const roofMat = registerMaterial(new THREE.MeshStandardMaterial({ color: colors.trim, roughness: 0.7, metalness: 0.16 }), 'metalMaterials');
        const roof = new THREE.Mesh(roofGeom, roofMat);
        roof.position.set(b.x, b.height + roofHeight / 2, b.z);
        roof.rotation.y = (b.yaw || 0) + (rooflineType === 'angled_parapet' ? 0.45 : 0);
        if (rooflineType === 'stepped') {
          roof.scale.set(0.7, 1, 1);
        } else if (rooflineType === 'gable_shallow') {
          roof.rotation.z = Math.PI / 2;
          roof.scale.set(0.5, 1, 1.2);
        }
        worldGroup.add(roof);
      }

      const storefrontBandHeight = Math.max(1.4, b.height * ((b.storefront_rhythm && b.storefront_rhythm.base_band) || profilePreset.storefrontBand));
      const storefrontMat = registerMaterial(new THREE.MeshStandardMaterial({ color: colors.accent, roughness: 0.62, metalness: 0.16, emissive: 0x120d0a, emissiveIntensity: 0.06 }), 'emissiveMaterials');
      const storefront = new THREE.Mesh(
        new THREE.BoxGeometry((b.width || 8) * 0.92, storefrontBandHeight, Math.max(2, (b.depth || 8) * 0.15)),
        storefrontMat
      );
      const storefrontPos = localPointToWorld(b, 0, (b.depth || 8) * 0.34);
      storefront.position.set(storefrontPos.x, storefrontBandHeight / 2 + 0.2, storefrontPos.z);
      storefront.rotation.y = b.yaw || 0;
      storefront.scale.x = heroScale;
      worldGroup.add(storefront);

      const bayCount = Math.max(2, Math.min(8, b.window_bay_count || 4));
      const windowRows = Math.max(1, Math.min(5, (b.storefront_rhythm && b.storefront_rhythm.upper_rows) || profilePreset.windowRows));
      const windowMat = registerMaterial(new THREE.MeshStandardMaterial({
        color: 0x223345,
        emissive: 0x385a79,
        emissiveIntensity: 0.12,
        roughness: 0.16,
        metalness: 0.34,
        transparent: true,
        opacity: 0.88,
      }), 'glassMaterials');
      windowMat.userData = { baseRoughness: 0.16, baseOpacity: 0.88 };
      for (let row = 0; row < windowRows; row += 1) {
        for (let bay = 0; bay < bayCount; bay += 1) {
          const win = new THREE.Mesh(new THREE.PlaneGeometry((b.width || 8) / (bayCount + 1.35), (b.height || 12) / (windowRows * (b.hero_fidelity === 'hero' ? 4.8 : 4.3))), windowMat);
          const xOffset = (((bay + 1) / (bayCount + 1)) - 0.5) * ((b.width || 8) * 0.78);
          const yOffset = storefrontBandHeight + 1.4 + row * ((b.height - storefrontBandHeight - 2) / windowRows);
          const depthOffset = (b.depth || 8) * 0.52;
          const winPos = localPointToWorld(b, xOffset, depthOffset);
          win.position.set(winPos.x, yOffset, winPos.z);
          win.rotation.y = b.yaw || 0;
          worldGroup.add(win);

          const sill = new THREE.Mesh(
            new THREE.BoxGeometry((b.width || 8) / (bayCount + 1.6), 0.09, 0.14),
            new THREE.MeshStandardMaterial({ color: colors.trim, roughness: 0.72, metalness: 0.12 })
          );
          sill.position.set(winPos.x, yOffset - 0.36, winPos.z - 0.02);
          sill.rotation.y = b.yaw || 0;
          worldGroup.add(sill);
        }
      }

      const storefrontWindowMat = registerMaterial(new THREE.MeshStandardMaterial({
        color: 0x1d2f3f,
        emissive: 0x4f6e8b,
        emissiveIntensity: 0.1,
        roughness: 0.12,
        metalness: 0.38,
        transparent: true,
        opacity: 0.82,
      }), 'glassMaterials');
      const displayCount = Math.max(2, Math.min(5, bayCount - Math.min(1, b.recessed_entry_count || 0)));
      for (let bay = 0; bay < displayCount; bay += 1) {
        const xOffset = (((bay + 0.5) / displayCount) - 0.5) * ((b.width || 8) * 0.76);
        const glass = new THREE.Mesh(new THREE.PlaneGeometry((b.width || 8) / (displayCount + 0.8), storefrontBandHeight * 0.68), storefrontWindowMat);
        const glassPos = localPointToWorld(b, xOffset, (b.depth || 8) * 0.49);
        glass.position.set(glassPos.x, (storefrontBandHeight * 0.5) + 0.36, glassPos.z);
        glass.rotation.y = b.yaw || 0;
        worldGroup.add(glass);
      }

      if (b.awning_presence || (profilePreset.awningDepth > 0.1)) {
        const awningMat = registerMaterial(new THREE.MeshStandardMaterial({ color: colors.trim, roughness: 0.56, metalness: 0.16 }), 'metalMaterials');
        const awning = new THREE.Mesh(
          new THREE.BoxGeometry((b.width || 8) * 0.86, 0.3, Math.max(0.8, profilePreset.awningDepth)),
          awningMat
        );
        const awningPos = localPointToWorld(b, 0, (b.depth || 8) * 0.45);
        awning.position.set(awningPos.x, storefrontBandHeight + 0.6, awningPos.z);
        awning.rotation.y = b.yaw || 0;
        worldGroup.add(awning);
      }

      const signBandMat = registerMaterial(new THREE.MeshStandardMaterial({
        color: colors.trim,
        roughness: 0.54,
        metalness: 0.2,
        emissive: colors.accent,
        emissiveIntensity: 0.05,
      }), 'emissiveMaterials');
      const signBand = new THREE.Mesh(
        new THREE.BoxGeometry((b.width || 8) * 0.78, 0.46, 0.08),
        signBandMat
      );
      const signBandPos = localPointToWorld(b, 0, (b.depth || 8) * 0.495);
      signBand.position.set(signBandPos.x, storefrontBandHeight + 0.16, signBandPos.z);
      signBand.rotation.y = b.yaw || 0;
      worldGroup.add(signBand);

      if (profilePreset.columnRhythm) {
        const columns = Math.max(6, Math.round((b.width || 20) / 3.2));
        for (let i = 0; i < columns; i += 1) {
          const t = (columns === 1 ? 0.5 : i / (columns - 1)) - 0.5;
          const localX = t * ((b.width || 20) * 0.8);
          const colHeight = Math.max(4.2, b.height * 0.5);
          const col = new THREE.Mesh(
            new THREE.CylinderGeometry(0.24, 0.28, colHeight, 10),
            new THREE.MeshStandardMaterial({ color: 0xd6d3ca, roughness: 0.5, metalness: 0.08 })
          );
          const colPos = localPointToWorld(b, localX, (b.depth || 10) * 0.5);
          col.position.set(colPos.x, colHeight / 2, colPos.z);
          worldGroup.add(col);

          if (b.id === 'waterfront-station-civic') {
            const base = new THREE.Mesh(
              new THREE.CylinderGeometry(0.34, 0.38, 0.42, 12),
              new THREE.MeshStandardMaterial({ color: 0xb4b8bf, roughness: 0.7, metalness: 0.12 })
            );
            base.position.copy(col.position);
            base.position.y = 0.21;
            worldGroup.add(base);
          }
        }
      }

      if (b.recessed_entry_count) {
        const entryCount = Math.min(3, Math.max(1, b.recessed_entry_count));
        for (let i = 0; i < entryCount; i += 1) {
          const t = (((i + 1) / (entryCount + 1)) - 0.5) * ((b.width || 8) * 0.5);
          const entryDepth = Math.max(0.5, storefrontRhythm.entry_depth || 0.72);
          const entry = new THREE.Mesh(
            new THREE.BoxGeometry(0.9, 2.4, 0.5),
            registerMaterial(new THREE.MeshStandardMaterial({ color: 0x1d222b, roughness: 0.3, metalness: 0.28 }), 'reflectiveMaterials')
          );
          const entryPos = localPointToWorld(b, t, (b.depth || 8) * 0.48 - (entryDepth * 0.08));
          entry.position.set(entryPos.x, 1.25, entryPos.z);
          worldGroup.add(entry);

          if (b.id === 'waterfront-station-civic') {
            const door = new THREE.Mesh(
              new THREE.PlaneGeometry(1.3, 3),
              new THREE.MeshStandardMaterial({ color: 0x161d27, emissive: 0x2f3d4f, emissiveIntensity: 0.2, roughness: 0.34, metalness: 0.25 })
            );
            const doorPos = localPointToWorld(b, t, (b.depth || 8) * 0.48 + 0.23);
            door.position.set(doorPos.x, 1.6, doorPos.z);
            door.rotation.y = b.yaw || 0;
            worldGroup.add(door);

            const transom = new THREE.Mesh(
              new THREE.PlaneGeometry(1.35, 0.55),
              new THREE.MeshStandardMaterial({ color: 0x45576c, emissive: 0x29394b, emissiveIntensity: 0.16, roughness: 0.42, metalness: 0.2 })
            );
            const transomPos = localPointToWorld(b, t, (b.depth || 8) * 0.48 + 0.24);
            transom.position.set(transomPos.x, 3.2, transomPos.z);
            transom.rotation.y = b.yaw || 0;
            worldGroup.add(transom);
          }
        }
      }

      if (b.id === 'waterfront-station-civic') {
        const canopy = new THREE.Mesh(
          new THREE.BoxGeometry((b.width || 40) * 0.78, 0.42, 1.8),
          new THREE.MeshStandardMaterial({ color: 0x8a8f96, roughness: 0.62, metalness: 0.2 })
        );
        const canopyPos = localPointToWorld(b, 0, (b.depth || 10) * 0.52);
        canopy.position.set(canopyPos.x, storefrontBandHeight + 1.8, canopyPos.z);
        canopy.rotation.y = b.yaw || 0;
        worldGroup.add(canopy);

        const recess = new THREE.Mesh(
          new THREE.BoxGeometry((b.width || 40) * 0.7, Math.max(3.2, storefrontBandHeight * 0.82), 1.1),
          new THREE.MeshStandardMaterial({ color: 0x18202a, roughness: 0.76, metalness: 0.08 })
        );
        const recessPos = localPointToWorld(b, 0, (b.depth || 10) * 0.49);
        recess.position.set(recessPos.x, Math.max(1.9, storefrontBandHeight * 0.4) + 0.3, recessPos.z);
        recess.rotation.y = b.yaw || 0;
        worldGroup.add(recess);
      }

      const edge = new THREE.LineSegments(
        new THREE.EdgesGeometry(geom),
        new THREE.LineBasicMaterial({ color: b.hero_fidelity === 'hero' ? 0x8f9eb0 : 0x6f8197, transparent: true, opacity: b.hero_fidelity === 'hero' ? 0.34 : 0.28 })
      );
      edge.position.copy(mesh.position);
      edge.rotation.y = mesh.rotation.y;
      worldGroup.add(edge);
    });
  }

  function addHeroLandmarks(world) {
    (world.hero_landmarks || []).forEach((hero) => {
      if (hero.id === 'steam-clock-hero') {
        const ironMaterial = new THREE.MeshStandardMaterial({ color: 0x24282d, roughness: 0.48, metalness: 0.68 });
        const brassMaterial = new THREE.MeshStandardMaterial({ color: 0xb08b48, roughness: 0.34, metalness: 0.72 });
        const glassMaterial = new THREE.MeshStandardMaterial({ color: 0xabc4c0, roughness: 0.12, metalness: 0.08, transparent: true, opacity: 0.32, emissive: 0x223333, emissiveIntensity: 0.22 });
        const accentMaterial = new THREE.MeshStandardMaterial({ color: 0x6a4c31, roughness: 0.76, metalness: 0.14 });
        const clockRoot = new THREE.Group();
        clockRoot.position.set(hero.x, 0, hero.z);
        clockRoot.rotation.y = hero.yaw || 0;

        const plazaRadius = (hero.plaza && hero.plaza.radius) || (hero.ground_emphasis_radius || 4.2);
        const plinth = new THREE.Mesh(new THREE.CylinderGeometry(1.52, 1.74, 0.84, 16), accentMaterial);
        plinth.position.y = 0.36;
        clockRoot.add(plinth);
        const pedestal = new THREE.Mesh(new THREE.BoxGeometry(1.54, 1.28, 1.54), ironMaterial);
        pedestal.position.y = 1.26;
        clockRoot.add(pedestal);
        const pedestalTrim = new THREE.Mesh(new THREE.BoxGeometry(1.76, 0.22, 1.76), brassMaterial);
        pedestalTrim.position.y = 1.88;
        clockRoot.add(pedestalTrim);
        const shaft = new THREE.Mesh(new THREE.BoxGeometry(1.08, 5.56, 1.08), ironMaterial);
        shaft.position.y = 4.32;
        clockRoot.add(shaft);
        const glassCore = new THREE.Mesh(new THREE.BoxGeometry(0.82, 2.92, 0.82), glassMaterial);
        glassCore.position.y = 4.16;
        clockRoot.add(glassCore);
        const cagePosts = [
          [-0.38, -0.38], [0.38, -0.38], [-0.38, 0.38], [0.38, 0.38],
        ];
        cagePosts.forEach((post) => {
          const cage = new THREE.Mesh(new THREE.BoxGeometry(0.08, 2.8, 0.08), brassMaterial);
          cage.position.set(post[0], 4.18, post[1]);
          clockRoot.add(cage);
        });
        const clockHousing = new THREE.Mesh(new THREE.BoxGeometry(1.94, 1.62, 1.94), ironMaterial);
        clockHousing.position.y = 6.74;
        clockRoot.add(clockHousing);
        const clockCornice = new THREE.Mesh(new THREE.BoxGeometry(2.18, 0.24, 2.18), brassMaterial);
        clockCornice.position.y = 7.44;
        clockRoot.add(clockCornice);
        const crown = new THREE.Mesh(new THREE.CylinderGeometry(0.98, 1.24, 0.58, 14), brassMaterial);
        crown.position.y = 7.88;
        clockRoot.add(crown);
        const cap = new THREE.Mesh(new THREE.ConeGeometry(1.08, 1.26, 14), ironMaterial);
        cap.position.y = 8.74;
        clockRoot.add(cap);
        const finial = new THREE.Mesh(new THREE.SphereGeometry(0.14, 10, 10), brassMaterial);
        finial.position.set(0, 9.5, 0);
        clockRoot.add(finial);
        const steamStack = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.18, 1.18, 8), brassMaterial);
        steamStack.position.set(0, 9.06, 0);
        clockRoot.add(steamStack);

        [0, Math.PI / 2, Math.PI, -Math.PI / 2].forEach((rotation) => {
          const frame = new THREE.Mesh(new THREE.CylinderGeometry(0.62, 0.62, 0.12, 24), brassMaterial);
          frame.rotation.x = Math.PI / 2;
          frame.rotation.z = rotation;
          frame.position.set(Math.sin(rotation) * 1.02, 6.76, Math.cos(rotation) * 1.02);
          clockRoot.add(frame);

          const face = new THREE.Mesh(new THREE.CircleGeometry(0.5, 24), new THREE.MeshStandardMaterial({ color: 0xf2e4b6, emissive: 0x7d6639, emissiveIntensity: 0.28, roughness: 0.62, metalness: 0.04 }));
          face.position.set(Math.sin(rotation) * 1.04, 6.76, Math.cos(rotation) * 1.04);
          face.rotation.y = rotation;
          clockRoot.add(face);

          const hand = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.4, 0.03), new THREE.MeshStandardMaterial({ color: 0x2b2420, roughness: 0.5, metalness: 0.22 }));
          hand.position.set(Math.sin(rotation) * 1.08, 6.76, Math.cos(rotation) * 1.08);
          hand.rotation.set(0, rotation, Math.PI / 5);
          clockRoot.add(hand);
        });

        const steamVents = Array.isArray(hero.steamVentOffsets) && hero.steamVentOffsets.length
          ? hero.steamVentOffsets
          : [{ x: -0.42, z: 0.64, y: 8.72 }, { x: 0.42, z: -0.64, y: 8.72 }];
        steamVents.forEach((vent, index) => {
          const pipe = new THREE.Mesh(new THREE.CylinderGeometry(0.09, 0.09, 1.8, 8), brassMaterial);
          pipe.position.set(vent.x, 5.62, index % 2 === 0 ? 0.82 : -0.82);
          pipe.rotation.x = Math.PI / 2.45;
          clockRoot.add(pipe);
          const elbow = new THREE.Mesh(new THREE.TorusGeometry(0.18, 0.04, 8, 16, Math.PI), brassMaterial);
          elbow.position.set(vent.x, 5.02, index % 2 === 0 ? 1.2 : -1.2);
          elbow.rotation.y = index % 2 === 0 ? -Math.PI / 2 : Math.PI / 2;
          clockRoot.add(elbow);
          const ventCap = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.14, 0.3, 8), brassMaterial);
          ventCap.position.set(vent.x, vent.y, vent.z);
          clockRoot.add(ventCap);
        });

        const plaza = new THREE.Mesh(new THREE.CircleGeometry(plazaRadius, 40), new THREE.MeshStandardMaterial({ color: 0x4c3d32, roughness: 0.92, metalness: 0.02 }));
        plaza.rotation.x = -Math.PI / 2;
        plaza.position.y = 0.03;
        clockRoot.add(plaza);
        const plazaApron = new THREE.Mesh(new THREE.RingGeometry(plazaRadius * 0.62, plazaRadius + 1.1, 40), new THREE.MeshStandardMaterial({ color: 0x7a6a5b, roughness: 0.96, metalness: 0.01 }));
        plazaApron.rotation.x = -Math.PI / 2;
        plazaApron.position.y = 0.031;
        clockRoot.add(plazaApron);

        const steamMaterial = new THREE.MeshBasicMaterial({ color: 0xd8dfdf, transparent: true, opacity: 0.12, depthWrite: false, depthTest: true });
        const steamPlume = new THREE.Mesh(new THREE.SphereGeometry(0.62, 10, 10), steamMaterial);
        steamPlume.position.set(0, 9.72, 0.08);
        clockRoot.add(steamPlume);

        worldGroup.add(clockRoot);
        state.steamClockState.anchor = { x: hero.x, z: hero.z };
        state.steamClockState.plume = steamPlume;
        visualState.steamClockVisuals.push({ steamMaterial, glassMaterial, brassMaterial });
      }
    });
  }

  function addLandmarks(world) {
    const heroLandmarkIds = new Set((world.hero_landmarks || []).map((hero) => String(hero.id || '').replace(/-hero$/, '')));
    const suppressedKinds = new Set(['clock', 'street_pivot', 'plaza_edge', 'district_gate', 'view_axis']);
    world.landmarks.forEach((landmark) => {
      const landmarkKind = landmark.kind || landmark.type;
      const col = landmarkKind === 'clock' ? 0xc8a460 : landmarkKind === 'street_pivot' ? 0x7ea2c7 : 0x8793a1;
      const suppressGenericMarker = heroLandmarkIds.has(landmark.id)
        || suppressedKinds.has(landmark.kind)
        || landmark.id === 'steam-clock'
        || landmark.id === 'water-cambie-intersection'
        || landmark.id === 'water-street-mid-block';

      if (state.debugEnabled || !suppressGenericMarker) {
        const markerMaterial = new THREE.MeshStandardMaterial({ color: col, emissive: col, emissiveIntensity: 0.2 });
        const markerHeight = landmarkKind === 'clock' ? 7.2 : 4.5;
        const markerRadius = landmarkKind === 'clock' ? 0.38 : 0.7;
        const marker = new THREE.Mesh(new THREE.CylinderGeometry(markerRadius, markerRadius * 1.05, markerHeight, 12), markerMaterial);
        marker.position.set(landmark.x, markerHeight / 2, landmark.z);
        worldGroup.add(marker);

        const haloMaterial = new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: 0.1 });
        const halo = new THREE.Mesh(new THREE.SphereGeometry((landmark.radius || 6) * 0.7, 16, 16), haloMaterial);
        halo.position.set(landmark.x, 2.8, landmark.z);
        worldGroup.add(halo);
        visualState.landmarkVisuals.push({ markerMaterial, haloMaterial });
      }

      if (state.debugEnabled || !suppressGenericMarker) {
        const reflectionMaterial = new THREE.MeshStandardMaterial({ color: col, emissive: 0x1b2533, emissiveIntensity: 0.08, transparent: true, opacity: 0.015, roughness: 0.96, metalness: 0.02, depthWrite: false });
        const reflection = new THREE.Mesh(new THREE.CircleGeometry(landmark.radius || 2.7, 24), reflectionMaterial);
        reflection.renderOrder = -1;
        reflection.rotation.x = -Math.PI / 2;
        reflection.position.set(landmark.x, 0.11, landmark.z);
        worldGroup.add(reflection);

        visualState.landmarkVisuals.push({ reflectionMaterial });
      }
    });
  }

  function addDebugRoute(world) {
    while (debugGroup.children.length) {
      debugGroup.remove(debugGroup.children[0]);
    }

    const centerlinePoints = world.route.centerline.map((point) => new THREE.Vector3(point.x, 0.35, point.z));
    const centerline = new THREE.Line(
      new THREE.BufferGeometry().setFromPoints(centerlinePoints),
      new THREE.LineBasicMaterial({ color: 0x8de7ff })
    );
    debugGroup.add(centerline);

    const walkBoundPoints = world.route.walkBounds.map((point) => new THREE.Vector3(point.x, 0.33, point.z));
    walkBoundPoints.push(new THREE.Vector3(world.route.walkBounds[0].x, 0.33, world.route.walkBounds[0].z));
    const walkBoundLine = new THREE.Line(
      new THREE.BufferGeometry().setFromPoints(walkBoundPoints),
      new THREE.LineBasicMaterial({ color: 0xffa763 })
    );
    debugGroup.add(walkBoundLine);

    function addPolygonOutline(polygon, y, color) {
      if (!Array.isArray(polygon) || polygon.length < 3) return;
      const points = polygon.map((point) => new THREE.Vector3(point.x, y, point.z));
      const first = polygon[0];
      points.push(new THREE.Vector3(first.x, y, first.z));
      debugGroup.add(new THREE.Line(
        new THREE.BufferGeometry().setFromPoints(points),
        new THREE.LineBasicMaterial({ color })
      ));
    }

    (world.zones.street || []).forEach((zone) => addPolygonOutline(zone.polygon, 0.3, 0x49c4ff));
    (world.zones.sidewalk || []).forEach((zone) => addPolygonOutline(zone.polygon, 0.34, 0xff9c57));

    world.nodes.forEach((node) => {
      const marker = new THREE.Mesh(new THREE.SphereGeometry(0.45, 12, 12), new THREE.MeshBasicMaterial({ color: 0xd5f3ff }));
      marker.position.set(node.x, 0.5, node.z);
      debugGroup.add(marker);
    });

    (world.buildings || []).forEach((building) => {
      if (!Number.isFinite(building.x) || !Number.isFinite(building.z)) return;
      const marker = new THREE.Mesh(new THREE.SphereGeometry(0.24, 8, 8), new THREE.MeshBasicMaterial({ color: 0x98ff9b }));
      marker.position.set(building.x, 0.62, building.z);
      debugGroup.add(marker);

      if (Number.isFinite(building.yaw)) {
        const arrowLen = Math.max(3, Math.min(6, (building.depth || 6) * 0.6));
        const tip = localPointToWorld(building, 0, arrowLen);
        const arrow = new THREE.Line(
          new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(building.x, 0.66, building.z),
            new THREE.Vector3(tip.x, 0.66, tip.z),
          ]),
          new THREE.LineBasicMaterial({ color: 0xa3f9a7 })
        );
        debugGroup.add(arrow);
      }

      if (Array.isArray(building.footprint) && building.footprint.length >= 3) {
        addPolygonOutline(building.footprint, 0.54, 0x72f194);
      }
    });

    if (world.spawn) {
      const spawnMarker = new THREE.Mesh(new THREE.ConeGeometry(0.35, 1.2, 12), new THREE.MeshBasicMaterial({ color: 0xffdb6b }));
      spawnMarker.position.set(world.spawn.x, (typeof world.spawn.y === 'number' ? world.spawn.y : 0) + 0.95, world.spawn.z);
      spawnMarker.rotation.x = Math.PI;
      debugGroup.add(spawnMarker);
    }

    const playerMarker = new THREE.Mesh(new THREE.SphereGeometry(0.32, 10, 10), new THREE.MeshBasicMaterial({ color: 0xfff9b2 }));
    playerMarker.position.set(player.position.x, 0.72, player.position.z);
    playerMarker.userData.role = 'player-marker';
    debugGroup.add(playerMarker);

    if (routeDebugOverlay) {
      routeDebugOverlay.hidden = !state.debugEnabled;
      refreshDebugRuntimeReadout();
    }
  }



  function refreshDebugRuntimeReadout() {
    if (!state.debugEnabled || !routeDebugOverlay || !state.world || !state.world.route) return;
    const lines = state.world.route.centerline.map((point, index) => (index + 1) + '. ' + (point.label || point.id || ('node-' + index)) + ' [' + point.x + ', ' + point.z + ']');
    const modeLine = state.world.meta && state.world.meta.fallbackMode === 'working-gastown-corridor'
      ? 'Mode: working fallback Gastown corridor (stylized primary playable build)'
      : 'Mode: civic-data-derived world';
    lines.unshift(modeLine);
    const corridor = getCanonicalCorridorSegment(state.world);
    if (corridor) {
      lines.push('Canonical corridor: ' + corridor.start.id + ' -> ' + corridor.end.id + ' tangent [' + corridor.tangent.x.toFixed(3) + ', ' + corridor.tangent.z.toFixed(3) + ']');
      lines.push('Canonical route-right normal: [' + corridor.rightNormal.x.toFixed(3) + ', ' + corridor.rightNormal.z.toFixed(3) + ']');
      const steamClock = (state.world.landmarks || []).find((landmark) => landmark.id === 'steam-clock') || (state.world.nodes || []).find((node) => node.id === 'steam-clock');
      if (steamClock) {
        const steamClockSide = classifyCorridorSide(steamClock, corridor, corridor.end);
        lines.push('Steam Clock side: ' + steamClockSide.side + ' (signed=' + steamClockSide.sideValue.toFixed(3) + ')');
      }
    }
    lines.push('Player: [' + player.position.x.toFixed(2) + ', ' + player.position.z.toFixed(2) + ']');
    routeDebugOverlay.textContent = lines.join('\n');

    const marker = debugGroup.children.find((child) => child.userData && child.userData.role === 'player-marker');
    if (marker) {
      marker.position.set(player.position.x, 0.72, player.position.z);
    }
  }

  function rebuildClouds(weather, timeOfDay) {
    while (cloudGroup.children.length) {
      cloudGroup.remove(cloudGroup.children[0]);
    }

    const coverage = weather.cloudCoverage || 0;
    if (coverage <= 0.08) {
      return;
    }

    const cloudColor = new THREE.Color(timeOfDay.sky || '#8ea4b5');
    cloudColor.lerp(new THREE.Color('#d4d9de'), 0.12 - Math.min(0.08, coverage * 0.04));
    cloudColor.multiplyScalar(1 - Math.min(0.42, weather.cloudDarkness || 0));
    const cloudMaterial = new THREE.MeshBasicMaterial({ color: cloudColor, transparent: true, opacity: Math.min(0.42, 0.12 + (coverage * 0.22)), depthWrite: false, fog: false });
    visualState.weatherMaterials.push(cloudMaterial);

    const count = Math.max(4, Math.round(coverage * 9));
    for (let i = 0; i < count; i += 1) {
      const shell = new THREE.Group();
      const baseX = ((i % 3) - 1) * 36;
      const baseZ = -30 - (Math.floor(i / 3) * 28);
      const width = 26 + (deterministicUnit('cloud-width-' + i) * 20);
      const height = 8 + (deterministicUnit('cloud-height-' + i) * 4);
      for (let puff = 0; puff < 3; puff += 1) {
        const plane = new THREE.Mesh(new THREE.PlaneGeometry(width * (0.7 + (puff * 0.16)), height * (0.8 + (puff * 0.08))), cloudMaterial);
        plane.position.set(baseX + (puff * 5) - 4, (weather.cloudAltitude || 62) + (deterministicUnit('cloud-y-' + i + '-' + puff) * 6), baseZ - (puff * 6));
        plane.rotation.x = -0.42;
        shell.add(plane);
      }
      shell.position.x += (deterministicUnit('cloud-jitter-' + i) - 0.5) * 20;
      cloudGroup.add(shell);
    }
  }

  function ensureLightningOverlay() {
    if (visualState.lightningOverlay) {
      return visualState.lightningOverlay;
    }
    const overlay = new THREE.Mesh(
      new THREE.SphereGeometry(280, 18, 16),
      new THREE.MeshBasicMaterial({ color: 0xdfe8ff, transparent: true, opacity: 0, side: THREE.BackSide, depthWrite: false, fog: false })
    );
    overlay.position.set(0, 20, 0);
    scene.add(overlay);
    visualState.lightningOverlay = overlay;
    return overlay;
  }

  function maybeTriggerLightning(weather) {
    if (!weather || !weather.lightningFrequency || state.lightning && state.lightning.activeUntil > performance.now()) {
      return;
    }
    if (Math.random() > weather.lightningFrequency * 0.012) {
      return;
    }
    const now = performance.now();
    state.lightning = {
      activeUntil: now + 120 + (Math.random() * 140),
      intensity: weather.lightningIntensity || 1,
      thunderAt: now + 380 + (Math.random() * 820),
      thunderPlayed: false,
    };
  }

  function updateLightning(weather) {
    const overlay = ensureLightningOverlay();
    const now = performance.now();
    const flash = state.lightning;
    if (!flash || now >= flash.activeUntil) {
      overlay.material.opacity = 0;
      lightningLight.intensity = 0;
      return;
    }
    const remaining = Math.max(0, flash.activeUntil - now);
    const pulse = Math.min(1, remaining / 220);
    const intensity = (0.08 + (pulse * 0.2)) * (flash.intensity || 1);
    overlay.material.opacity = Math.min(0.2, intensity);
    lightningLight.intensity = intensity * 1.8;

    if (weather && weather.thunderEnabled && !flash.thunderPlayed && now >= flash.thunderAt) {
      flash.thunderPlayed = true;
      if (state.sounds.thunder && typeof state.sounds.thunder.play === 'function') {
        playHowl(state.sounds.thunder);
      }
    }
  }

  function rebuildRain(intensity) {
    while (rainGroup.children.length) {
      rainGroup.remove(rainGroup.children[0]);
    }

    if (intensity <= 0.02) {
      return;
    }

    const qualityFactor = state.lowGraphics ? 0.4 : 1;
    const dropCount = Math.floor((420 + (intensity * 900)) * qualityFactor);
    const geom = new THREE.BufferGeometry();
    const points = new Float32Array(dropCount * 3);
    const velocities = new Float32Array(dropCount);

    for (let i = 0; i < dropCount; i += 1) {
      points[i * 3] = (Math.random() - 0.5) * 150;
      points[(i * 3) + 1] = Math.random() * 42 + 5;
      points[(i * 3) + 2] = (Math.random() - 0.5) * 190 - 40;
      velocities[i] = 16 + (Math.random() * 11) + (intensity * 10);
    }

    geom.setAttribute('position', new THREE.BufferAttribute(points, 3));
    geom.setAttribute('velocity', new THREE.BufferAttribute(velocities, 1));
    const rain = new THREE.Points(geom, new THREE.PointsMaterial({ color: 0xa9c6d9, size: 0.08 + (intensity * 0.06), transparent: true, opacity: 0.22 + (intensity * 0.36) }));
    rain.userData.kind = 'rain-core';
    rainGroup.add(rain);

    if (intensity < 0.72) {
      return;
    }

    const streakCount = Math.floor((8 + ((intensity - 0.72) * 28)) * (state.lowGraphics ? 0.45 : 1));
    for (let i = 0; i < streakCount; i += 1) {
      const streak = new THREE.Mesh(
        new THREE.PlaneGeometry(0.025, 0.7 + (intensity * 0.85)),
        new THREE.MeshBasicMaterial({ color: 0xc7d9e6, transparent: true, opacity: 0.012 + (intensity * 0.012), depthWrite: false, depthTest: true })
      );
      streak.rotation.x = -0.22;
      streak.position.set((Math.random() - 0.5) * 55, 9 + (Math.random() * 14), -8 - (Math.random() * 26));
      streak.userData.fallSpeed = 19 + (Math.random() * 8) + (intensity * 12);
      rainGroup.add(streak);
    }
  }

  function animateRain(delta) {
    rainGroup.children.forEach((node) => {
      if (node.isPoints) {
        const pos = node.geometry.attributes.position;
        const velocities = node.geometry.attributes.velocity;
        for (let i = 0; i < pos.count; i += 1) {
          const speed = velocities ? velocities.getX(i) : 18;
          const y = pos.getY(i) - (speed * delta);
          pos.setY(i, y < 0.5 ? Math.random() * 42 + 5 : y);
        }
        pos.needsUpdate = true;
        return;
      }

      node.position.y -= (node.userData.fallSpeed || 18) * delta;
      if (node.position.y < 0.5) {
        node.position.y = 14 + (Math.random() * 14);
      }
    });
  }

  function distanceToSegment(point, a, b) {
    const vx = b.x - a.x;
    const vz = b.z - a.z;
    const wx = point.x - a.x;
    const wz = point.z - a.z;
    const c1 = (wx * vx) + (wz * vz);
    if (c1 <= 0) return Math.hypot(point.x - a.x, point.z - a.z);
    const c2 = (vx * vx) + (vz * vz);
    if (c2 <= c1) return Math.hypot(point.x - b.x, point.z - b.z);
    const t = c1 / c2;
    const px = a.x + (t * vx);
    const pz = a.z + (t * vz);
    return Math.hypot(point.x - px, point.z - pz);
  }

  function projectToSegment(point, a, b) {
    const abx = b.x - a.x;
    const abz = b.z - a.z;
    const apx = point.x - a.x;
    const apz = point.z - a.z;
    const denominator = (abx * abx) + (abz * abz);
    const t = denominator === 0 ? 0 : Math.max(0, Math.min(1, ((apx * abx) + (apz * abz)) / denominator));
    return { x: a.x + (abx * t), z: a.z + (abz * t) };
  }

  function isPointInPolygon(point, polygon) {
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i, i += 1) {
      const xi = polygon[i].x;
      const zi = polygon[i].z;
      const xj = polygon[j].x;
      const zj = polygon[j].z;
      const intersects = ((zi > point.z) !== (zj > point.z)) && (point.x < (((xj - xi) * (point.z - zi)) / ((zj - zi) || 0.00001)) + xi);
      if (intersects) inside = !inside;
    }
    return inside;
  }

  function closestPointOnPolygon(point, polygon) {
    let best = { x: polygon[0].x, z: polygon[0].z, distance: Number.POSITIVE_INFINITY };
    polygon.forEach((node, index) => {
      const next = polygon[(index + 1) % polygon.length];
      const projected = projectToSegment(point, node, next);
      const distance = Math.hypot(point.x - projected.x, point.z - projected.z);
      if (distance < best.distance) {
        best = { x: projected.x, z: projected.z, distance };
      }
    });
    return best;
  }

  function getGroundY() {
    return (state.world && state.world.bounds && typeof state.world.bounds.floorY === 'number') ? state.world.bounds.floorY : 0;
  }

  function getWorldSpawn() {
    return state.world && state.world.spawn ? state.world.spawn : { x: -23, y: getGroundY(), z: 20, yaw: -0.25 };
  }

  function enforceWorldBounds() {
    const walkBounds = state.world.route.walkBounds;
    const point = { x: player.position.x, z: player.position.z };
    if (isPointInPolygon(point, walkBounds)) {
      state.lastSafePosition.set(player.position.x, player.position.y, player.position.z);
      return;
    }

    const clamped = closestPointOnPolygon(point, walkBounds);
    player.position.x = clamped.x;
    player.position.z = clamped.z;

    if (clamped.distance > state.world.route.hardResetDistance) {
      const fallbackSpawn = getWorldSpawn();
      player.position.copy(state.lastSafePosition.lengthSq() ? state.lastSafePosition : new THREE.Vector3(fallbackSpawn.x, fallbackSpawn.y, fallbackSpawn.z));
      flashBoundaryStatus((state.world.bounds && state.world.bounds.resetMessage) || 'Returned to the route.');
      return;
    }

    flashBoundaryStatus((state.world.bounds && state.world.bounds.edgeMessage) || 'Stayed inside the route corridor.');
  }

  function getFacingLabel(heading) {
    const normalized = heading || getHeadingVector();
    const angle = (Math.atan2(normalized.x, -normalized.z) * (180 / Math.PI) + 360) % 360;
    const cardinals = ['north', 'northeast', 'east', 'southeast', 'south', 'southwest', 'west', 'northwest'];
    return cardinals[Math.round(angle / 45) % cardinals.length];
  }

  function classifyRelativeDirection(target, heading, origin = player.position) {
    if (!target || !heading || !origin) {
      return { bucket: 'ahead', angle: 0, distance: 0, rightDot: 0, forwardDot: 1 };
    }
    const offsetX = target.x - origin.x;
    const offsetZ = target.z - origin.z;
    const distance = Math.hypot(offsetX, offsetZ);
    if (distance < 1e-6) {
      return { bucket: 'ahead', angle: 0, distance, rightDot: 0, forwardDot: 1 };
    }
    const dirX = offsetX / distance;
    const dirZ = offsetZ / distance;
    const right = { x: -heading.z, z: heading.x };
    const forwardDot = (heading.x * dirX) + (heading.z * dirZ);
    const rightDot = (right.x * dirX) + (right.z * dirZ);
    const angle = Math.atan2(rightDot, forwardDot);
    const bucketIndex = Math.round(angle / (Math.PI / 4));
    const directionBuckets = ['ahead', 'ahead-right', 'right', 'behind-right', 'behind', 'behind-left', 'left', 'ahead-left'];
    return {
      bucket: directionBuckets[(bucketIndex + directionBuckets.length) % directionBuckets.length],
      angle,
      distance,
      rightDot,
      forwardDot,
    };
  }

  function getLandmarkContextNote(landmark, world) {
    if (!landmark || !world) {
      return '';
    }
    if (landmark.id === 'steam-clock') {
      return 'on the plaza side';
    }
    if (landmark.id === 'water-cambie-intersection') {
      return 'at the crossing';
    }
    if (landmark.id === 'waterfront-station-threshold') {
      return 'by the station entrance';
    }
    const corridor = getCanonicalCorridorSegment(world);
    if (!corridor) {
      return '';
    }
    const side = classifyCorridorSide(landmark, corridor, corridor.end).side;
    if (side === 'right') {
      return 'on the curb side';
    }
    if (side === 'left') {
      return 'across the corridor';
    }
    return '';
  }

  function describeLandmarkGuidance(landmark, heading, world, origin = player.position) {
    if (!landmark) {
      return null;
    }
    const relative = classifyRelativeDirection(landmark, heading, origin);
    const context = getLandmarkContextNote(landmark, world);
    return {
      landmark,
      relative,
      facing: getFacingLabel(heading),
      text: landmark.label + ' — ' + relative.bucket + (context ? ' ' + context : ''),
      shortText: landmark.label + ' — ' + relative.bucket,
      context,
    };
  }

  function getGuidanceLandmarks(world) {
    if (!world) {
      return [];
    }
    const landmarkIds = ['steam-clock', 'waterfront-station-threshold', 'water-cambie-intersection', 'water-street-mid-block'];
    return landmarkIds.map((id) => getLandmarkById(world, id)).filter(Boolean);
  }

  function updateMinimapContext() {
    const heading = getHeadingVector();
    const facing = getFacingLabel(heading);
    const nearest = minimapState.nearestGuidance;
    const area = state.world ? getMicroAreaForPoint(player.position, state.world) : null;
    if (minimapContextEl) {
      minimapContextEl.textContent = 'Facing ' + facing + (nearest ? ' · ' + nearest.text : area ? ' · ' + area.label : '');
    }
    if (minimapModeStatusEl) {
      const isHeadingUp = minimapState.mode === 'heading-up';
      minimapModeStatusEl.innerHTML = isHeadingUp
        ? 'Heading-up map.'
        : 'North-up map.';
    }
  }

  function getMicroAreaForPoint(point, world) {
    if (!world || !Array.isArray(world.microAreas) || !world.microAreas.length) {
      return null;
    }
    const containing = world.microAreas.find((area) => isPointInPolygon(point, area.polygon));
    if (containing) {
      return containing;
    }
    return world.microAreas.reduce((best, area) => {
      const anchor = area.anchor || area.polygon[0];
      if (!anchor) return best;
      const nextDistance = Math.hypot(anchor.x - point.x, anchor.z - point.z);
      if (!best || nextDistance < best.distance) {
        return { area, distance: nextDistance };
      }
      return best;
    }, null)?.area || null;
  }

  function describeMicroArea(area) {
    if (!area) return '';
    const stop = Array.isArray(area.stopReasons) && area.stopReasons.length ? area.stopReasons[0] : '';
    return area.label + ' — ' + area.identity + (stop ? '. ' + stop : '');
  }

  function updateNearestNode() {
    const heading = getHeadingVector();
    let nearest = null;
    let nearestDist = Number.POSITIVE_INFINITY;

    state.world.nodes.forEach((node) => {
      const d = Math.hypot(node.x - player.position.x, node.z - player.position.z);
      if (d < nearestDist) {
        nearestDist = d;
        nearest = node;
      }
    });

    const guidanceCandidates = getGuidanceLandmarks(state.world);
    const nearestGuidance = guidanceCandidates.reduce((best, landmark) => {
      const next = describeLandmarkGuidance(landmark, heading, state.world);
      if (!next) {
        return best;
      }
      if (!best || next.relative.distance < best.relative.distance) {
        return next;
      }
      return best;
    }, null) || (nearest ? describeLandmarkGuidance(nearest, heading, state.world) : null);
    const microArea = getMicroAreaForPoint(player.position, state.world);

    if (nearest) {
      updateRouteCompletionScore();
      minimapState.nearestNode = nearest;
      minimapState.nearestGuidance = nearestGuidance;
      setLandmark(nearestGuidance ? nearestGuidance.shortText : (microArea ? microArea.label : nearest.label));
      if (routeSegmentEl) {
        routeSegmentEl.textContent = microArea ? microArea.label : nearest.label;
      }
      if (minimapLandmarkEl) {
        minimapLandmarkEl.textContent = nearestGuidance ? nearestGuidance.text : nearest.label;
      }
      if (microArea && state.currentMicroAreaId !== microArea.id && state.isRunning) {
        flashBoundaryStatus('Now exploring ' + microArea.label + ' — ' + microArea.identity + '.');
      }
      state.currentMicroAreaId = microArea ? microArea.id : '';
      autoSelectThreadFromContext();
      updateMinimapContext();
    }
  }

  function getBuildingCollisionRadius(building) {
    return (Math.max(building.width || 8, building.depth || 8) / 2) + 0.8;
  }

  function isSpawnSafe(spawn) {
    if (!state.world) return true;

    const inWalkBounds = isPointInPolygon(spawn, state.world.route.walkBounds);
    if (!inWalkBounds) return false;

    const onStreet = state.world.zones.street.some((zone) => isPointInPolygon(spawn, zone.polygon));
    const onSidewalk = state.world.zones.sidewalk.some((zone) => isPointInPolygon(spawn, zone.polygon));
    if (!onStreet && !onSidewalk) return false;

    const intersectsBuilding = state.world.buildings.some((building) => {
      const distance = Math.hypot(spawn.x - building.x, spawn.z - building.z);
      return distance < getBuildingCollisionRadius(building);
    });

    return !intersectsBuilding;
  }

  function resolveSafeSpawn() {
    const worldSpawn = getWorldSpawn();
    const fallbackCandidates = [
      { x: worldSpawn.x, z: worldSpawn.z },
      { x: worldSpawn.x + 1.2, z: worldSpawn.z + 1.2 },
      { x: worldSpawn.x + 2.1, z: worldSpawn.z + 2.8 },
      { x: worldSpawn.x - 1.8, z: worldSpawn.z + 2.6 },
      { x: -21.2, z: 22.6 },
    ];

    const safePoint = fallbackCandidates.find((candidate) => isSpawnSafe(candidate));
    if (safePoint) {
      return { x: safePoint.x, y: Number.isFinite(worldSpawn.y) ? worldSpawn.y : getGroundY(), z: safePoint.z, yaw: worldSpawn.yaw || -0.25 };
    }

    const routeStart = state.world.route.centerline[0] || { x: -22, z: 18 };
    return { x: routeStart.x, y: Number.isFinite(worldSpawn.y) ? worldSpawn.y : getGroundY(), z: routeStart.z + 3.6, yaw: -0.3 };
  }



  function getHeadingVector() {
    const forward = new THREE.Vector3(0, 0, -1);
    forward.applyAxisAngle(new THREE.Vector3(0, 1, 0), state.yaw);
    const planarLength = Math.hypot(forward.x, forward.z) || 1;
    return { x: forward.x / planarLength, z: forward.z / planarLength };
  }

  function clampMinimapZoom(nextZoom) {
    return Math.max(minimapState.minZoom, Math.min(minimapState.maxZoom, nextZoom));
  }

  function setMinimapZoom(nextZoom) {
    minimapState.zoom = clampMinimapZoom(nextZoom);
    try {
      window.sessionStorage.setItem('gastownMinimapZoom', String(minimapState.zoom));
    } catch (error) {
      // ignore session storage failures
    }
  }

  function isValidMinimapMode(value) {
    return value === 'north-up' || value === 'heading-up';
  }

  function updateMinimapModeButton() {
    if (!minimapModeBtn) {
      return;
    }
    const isHeadingUp = minimapState.mode === 'heading-up';
    minimapModeBtn.textContent = isHeadingUp ? 'Heading up' : 'North up';
    minimapModeBtn.dataset.minimapMode = minimapState.mode;
    minimapModeBtn.setAttribute('aria-pressed', isHeadingUp ? 'true' : 'false');
    minimapModeBtn.setAttribute(
      'aria-label',
      isHeadingUp ? 'Switch minimap to north-up mode' : 'Switch minimap to heading-up mode'
    );
    minimapModeBtn.title = isHeadingUp ? 'Switch minimap to north-up mode' : 'Switch minimap to heading-up mode';
    updateMinimapContext();
  }

  function setMinimapMode(nextMode) {
    if (!isValidMinimapMode(nextMode)) {
      return;
    }
    minimapState.mode = nextMode;
    updateMinimapModeButton();
    try {
      window.sessionStorage.setItem('gastownMinimapMode', minimapState.mode);
    } catch (error) {
      // ignore session storage failures
    }
  }

  function toggleMinimapMode() {
    setMinimapMode(minimapState.mode === 'heading-up' ? 'north-up' : 'heading-up');
  }

  function restoreMinimapZoom() {
    try {
      const stored = parseFloat(window.sessionStorage.getItem('gastownMinimapZoom') || '');
      if (Number.isFinite(stored)) {
        minimapState.zoom = clampMinimapZoom(stored);
      }
    } catch (error) {
      // ignore session storage failures
    }
  }

  function restoreMinimapMode() {
    try {
      const stored = window.sessionStorage.getItem('gastownMinimapMode') || '';
      if (isValidMinimapMode(stored)) {
        minimapState.mode = stored;
      }
    } catch (error) {
      // ignore session storage failures
    }
    updateMinimapModeButton();
  }

  function getWorldMetrics() {
    if (!state.world) {
      return null;
    }

    const points = [];
    const collectPoint = (point) => {
      if (!point || typeof point.x !== 'number' || typeof point.z !== 'number') return;
      points.push(point);
    };

    if (state.world.route && Array.isArray(state.world.route.walkBounds)) {
      state.world.route.walkBounds.forEach(collectPoint);
    }
    if (state.world.zones && Array.isArray(state.world.zones.street)) {
      state.world.zones.street.forEach((zone) => (zone.polygon || []).forEach(collectPoint));
    }
    if (state.world.zones && Array.isArray(state.world.zones.sidewalk)) {
      state.world.zones.sidewalk.forEach((zone) => (zone.polygon || []).forEach(collectPoint));
    }
    if (Array.isArray(state.world.buildings)) {
      state.world.buildings.forEach((building) => {
        if (Array.isArray(building.footprint)) {
          building.footprint.forEach(collectPoint);
          return;
        }
        const halfW = (building.width || 0) / 2;
        const halfD = (building.depth || 0) / 2;
        [
          { x: building.x - halfW, z: building.z - halfD },
          { x: building.x + halfW, z: building.z + halfD },
        ].forEach(collectPoint);
      });
    }

    if (!points.length) {
      return null;
    }

    let minX = Infinity;
    let maxX = -Infinity;
    let minZ = Infinity;
    let maxZ = -Infinity;
    points.forEach((point) => {
      minX = Math.min(minX, point.x);
      maxX = Math.max(maxX, point.x);
      minZ = Math.min(minZ, point.z);
      maxZ = Math.max(maxZ, point.z);
    });
    return {
      minX,
      maxX,
      minZ,
      maxZ,
      width: Math.max(1, maxX - minX),
      height: Math.max(1, maxZ - minZ),
    };
  }

  function getBuildingPolygon(building) {
    if (Array.isArray(building.footprint) && building.footprint.length >= 3) {
      return building.footprint;
    }

    const cx = building.x || 0;
    const cz = building.z || 0;
    const halfW = (building.width || 8) / 2;
    const halfD = (building.depth || 8) / 2;
    const yaw = building.yaw || 0;
    const corners = [
      { x: -halfW, z: -halfD },
      { x: halfW, z: -halfD },
      { x: halfW, z: halfD },
      { x: -halfW, z: halfD },
    ];

    const sin = Math.sin(yaw);
    const cos = Math.cos(yaw);
    return corners.map((corner) => ({
      x: cx + (corner.x * cos) - (corner.z * sin),
      z: cz + (corner.x * sin) + (corner.z * cos),
    }));
  }

  function drawPolygon(points, metrics, padding, fillColor, strokeColor, lineWidth, view, projectionOptions) {
    if (!points || points.length < 3) {
      return;
    }
    const ctx = minimapState.ctx;
    ctx.beginPath();
    points.forEach((point, index) => {
      const mini = projectWorldToMinimap(point, metrics, padding, view, projectionOptions);
      if (index === 0) {
        ctx.moveTo(mini.x, mini.y);
      } else {
        ctx.lineTo(mini.x, mini.y);
      }
    });
    ctx.closePath();
    if (fillColor) {
      ctx.fillStyle = fillColor;
      ctx.fill();
    }
    if (strokeColor) {
      ctx.strokeStyle = strokeColor;
      ctx.lineWidth = lineWidth || 1;
      ctx.stroke();
    }
  }

  function getMinimapView(metrics) {
    const worldCenterX = (metrics.minX + metrics.maxX) / 2;
    const worldCenterZ = (metrics.minZ + metrics.maxZ) / 2;
    const playerX = player.position.x || worldCenterX;
    const playerZ = player.position.z || worldCenterZ;
    const isHeadingUp = minimapState.mode === 'heading-up';
    const centerX = isHeadingUp ? playerX : (((playerX - worldCenterX) * 0.8) + worldCenterX);
    const centerZ = isHeadingUp ? playerZ : (((playerZ - worldCenterZ) * 0.8) + worldCenterZ);

    const zoom = minimapState.zoom || 1;
    const viewWidth = metrics.width / zoom;
    const viewHeight = metrics.height / zoom;

    return {
      centerX,
      centerZ,
      minX: centerX - (viewWidth / 2),
      maxX: centerX + (viewWidth / 2),
      minZ: centerZ - (viewHeight / 2),
      maxZ: centerZ + (viewHeight / 2),
      width: Math.max(1, viewWidth),
      height: Math.max(1, viewHeight),
    };
  }

  function toMinimapPoint(point, metrics, padding, view) {
    const usableW = minimapState.width - (padding * 2);
    const usableH = minimapState.height - (padding * 2);
    const active = view || metrics;
    return {
      x: padding + ((point.x - active.minX) / active.width) * usableW,
      y: minimapState.height - (padding + ((point.z - active.minZ) / active.height) * usableH),
    };
  }

  function getMinimapHeadingRotation(heading) {
    return minimapState.mode === 'heading-up' ? (-Math.atan2(-heading.z, heading.x) - (Math.PI / 2)) : 0;
  }

  function getCanonicalCorridorSegment(world) {
    if (!world) return null;
    const nodes = Array.isArray(world.nodes) ? world.nodes : [];
    const landmarks = Array.isArray(world.landmarks) ? world.landmarks : [];
    const start = nodes.find((node) => node.id === 'water-street-mid-block');
    const end = nodes.find((node) => node.id === 'water-cambie-intersection')
      || landmarks.find((landmark) => landmark.id === 'water-cambie-intersection');
    if (!start || !end) {
      return null;
    }
    const tangent = { x: end.x - start.x, z: end.z - start.z };
    const length = Math.hypot(tangent.x, tangent.z) || 1;
    tangent.x /= length;
    tangent.z /= length;
    return {
      start,
      end,
      tangent,
      rightNormal: { x: tangent.z, z: -tangent.x },
    };
  }

  function getCorridorSideValue(point, corridor, origin = corridor && corridor.start) {
    if (!point || !corridor || !origin) {
      return 0;
    }
    const relX = point.x - origin.x;
    const relZ = point.z - origin.z;
    return (corridor.tangent.x * relZ) - (corridor.tangent.z * relX);
  }

  function classifyCorridorSide(point, corridor, origin) {
    const sideValue = getCorridorSideValue(point, corridor, origin);
    if (Math.abs(sideValue) < 1e-6) {
      return { sideValue, side: 'center' };
    }
    return {
      sideValue,
      side: sideValue < 0 ? 'right' : 'left',
    };
  }

  function getLandmarkById(world, id) {
    if (!world || !id) {
      return null;
    }
    return (Array.isArray(world.landmarks) ? world.landmarks : []).find((landmark) => landmark.id === id)
      || (Array.isArray(world.nodes) ? world.nodes : []).find((node) => node.id === id)
      || (Array.isArray(world.hero_landmarks) ? world.hero_landmarks : []).find((landmark) => landmark.id === id);
  }

  function getZoneById(world, id, zoneType) {
    if (!world || !world.zones || !id) {
      return null;
    }
    const zones = zoneType && Array.isArray(world.zones[zoneType])
      ? world.zones[zoneType]
      : Object.values(world.zones).flatMap((entry) => (Array.isArray(entry) ? entry : []));
    return zones.find((zone) => zone.id === id) || null;
  }

  function getMinimapLandmarkAnchor(landmark, world) {
    if (!landmark || !world) {
      return landmark;
    }
    if (landmark.id !== 'steam-clock') {
      return landmark;
    }

    const corridor = getCanonicalCorridorSegment(world);
    if (!corridor) {
      return landmark;
    }

    const plazaZone = getZoneById(world, 'steam-clock-plaza-sidewalk', 'sidewalk');
    const hero = getLandmarkById(world, 'steam-clock-hero');
    const plazaPoints = plazaZone && Array.isArray(plazaZone.polygon) ? plazaZone.polygon.filter(Boolean) : [];
    const rawAlong = ((landmark.x - corridor.end.x) * corridor.tangent.x) + ((landmark.z - corridor.end.z) * corridor.tangent.z);
    const rawSide = Math.abs(getCorridorSideValue(landmark, corridor, corridor.end));
    const rightmostPlazaPoint = plazaPoints.reduce((best, point) => {
      const sideValue = getCorridorSideValue(point, corridor, corridor.end);
      if (!best || sideValue < best.sideValue) {
        return { point, sideValue };
      }
      return best;
    }, null);
    const rightmostSide = rightmostPlazaPoint ? Math.abs(rightmostPlazaPoint.sideValue) : rawSide;
    const curbTarget = ((world.route && world.route.streetWidth) || 0) / 2;
    const sidewalkTarget = curbTarget + ((((world.route && world.route.sidewalkWidth) || 0) * 0.85));
    const heroTarget = hero && hero.plaza
      ? (hero.plaza.radius || 0) + ((hero.plaza.apronWidth || 0) * 0.35)
      : 0;
    const targetSide = Math.max(rawSide, sidewalkTarget, heroTarget, rawSide + Math.max(1.2, (rightmostSide - rawSide) * 0.45));

    return {
      x: corridor.end.x + (corridor.tangent.x * rawAlong) + (corridor.rightNormal.x * targetSide),
      z: corridor.end.z + (corridor.tangent.z * rawAlong) + (corridor.rightNormal.z * targetSide),
    };
  }

  function getMinimapDebugVectors(world, metrics, padding, view, projectionOptions) {
    const corridor = getCanonicalCorridorSegment(world);
    if (!corridor) {
      return null;
    }
    const steamClock = getLandmarkById(world, 'steam-clock');
    const steamClockAnchor = steamClock ? getMinimapLandmarkAnchor(steamClock, world) : null;
    const tangentAnchor = corridor.end;
    const tangentTip = {
      x: tangentAnchor.x + (corridor.tangent.x * 10),
      z: tangentAnchor.z + (corridor.tangent.z * 10),
    };
    const rightTip = {
      x: tangentAnchor.x + (corridor.rightNormal.x * 10),
      z: tangentAnchor.z + (corridor.rightNormal.z * 10),
    };

    return {
      corridor,
      tangentAnchor: projectWorldToMinimap(tangentAnchor, metrics, padding, view, projectionOptions),
      tangentTip: projectWorldToMinimap(tangentTip, metrics, padding, view, projectionOptions),
      rightTip: projectWorldToMinimap(rightTip, metrics, padding, view, projectionOptions),
      steamClock: steamClock ? projectWorldToMinimap(steamClock, metrics, padding, view, projectionOptions) : null,
      steamClockAnchor: steamClockAnchor ? projectWorldToMinimap(steamClockAnchor, metrics, padding, view, projectionOptions) : null,
      steamClockWorld: steamClock,
      steamClockAnchorWorld: steamClockAnchor,
      steamClockSide: steamClock ? classifyCorridorSide(steamClock, corridor, corridor.end) : null,
      steamClockAnchorSide: steamClockAnchor ? classifyCorridorSide(steamClockAnchor, corridor, corridor.end) : null,
    };
  }

  function projectWorldToMinimap(point, metrics, padding, view, options = {}) {
    const basePoint = toMinimapPoint(point, metrics, padding, view);
    const rotation = options.rotation || 0;
    const anchor = options.anchor;
    if (!rotation || !anchor) {
      return basePoint;
    }
    const offsetX = basePoint.x - anchor.x;
    const offsetY = basePoint.y - anchor.y;
    const rotated = rotateMinimapVector(offsetX, offsetY, rotation);
    return {
      x: anchor.x + rotated.x,
      y: anchor.y + rotated.y,
    };
  }

  function rotateMinimapVector(x, y, angle) {
    const sin = Math.sin(angle);
    const cos = Math.cos(angle);
    return {
      x: (x * cos) - (y * sin),
      y: (x * sin) + (y * cos),
    };
  }

  function getFacingLabel(heading) {
    const angle = Math.atan2(heading.x, heading.z);
    if (angle > -Math.PI / 4 && angle <= Math.PI / 4) return 'north';
    if (angle > Math.PI / 4 && angle <= (3 * Math.PI) / 4) return 'east';
    if (angle <= -Math.PI / 4 && angle > (-3 * Math.PI) / 4) return 'west';
    return 'south';
  }

  function getActiveNpcCounts() {
    return (state.npcs || []).reduce((acc, npc) => {
      acc[npc.role] = (acc[npc.role] || 0) + 1;
      return acc;
    }, {});
  }

  function updateMinimapLegend() {
    if (!minimapLegendEl) return;
    const npcCounts = getActiveNpcCounts();
    const collectibleCount = getCollectibleProps().filter((prop) => !prop.collected).length;
    const items = ['You', 'Route', 'Sidewalk', 'Street', 'Landmark', 'Details left: ' + collectibleCount, 'Locals: ' + (npcCounts.pedestrian || 0), 'Visitors: ' + ((npcCounts.tourist || 0) + (npcCounts.photographer || 0)), 'Riders: ' + ((npcCounts.cyclist || 0) + (npcCounts.skateboarder || 0))];
    minimapLegendEl.innerHTML = items.map((item) => '<li>' + item + '</li>').join('');
  }

  function drawMinimapCompass(playerPoint, rotation) {
    const ctx = minimapState.ctx;
    ctx.save();
    ctx.fillStyle = 'rgba(221, 236, 255, 0.82)';
    ctx.font = '700 11px ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    if (minimapState.mode !== 'heading-up') {
      ctx.fillText('N', minimapState.width / 2, 9);
      ctx.fillText('S', minimapState.width / 2, minimapState.height - 9);
      ctx.fillText('W', 10, minimapState.height / 2);
      ctx.fillText('E', minimapState.width - 10, minimapState.height / 2);
      ctx.restore();
      return;
    }

    const radius = (Math.min(minimapState.width, minimapState.height) / 2) - 11;
    [
      { label: 'N', x: 0, y: -1 },
      { label: 'E', x: 1, y: 0 },
      { label: 'S', x: 0, y: 1 },
      { label: 'W', x: -1, y: 0 },
    ].forEach((entry) => {
      const rotated = rotateMinimapVector(entry.x, entry.y, rotation);
      ctx.fillText(entry.label, playerPoint.x + (rotated.x * radius), playerPoint.y + (rotated.y * radius));
    });
    ctx.restore();
  }

  function drawMinimap() {
    if (!minimapState.ctx || !state.world || !minimapState.worldMetrics) {
      return;
    }

    const ctx = minimapState.ctx;
    const metrics = minimapState.worldMetrics;
    const view = getMinimapView(metrics);
    const pad = 16;
    const heading = getHeadingVector();
    const headingRotation = getMinimapHeadingRotation(heading);
    const playerPoint = toMinimapPoint({ x: player.position.x, z: player.position.z }, metrics, pad, view);
    const projectionOptions = { rotation: headingRotation, anchor: playerPoint };
    ctx.clearRect(0, 0, minimapState.width, minimapState.height);

    ctx.fillStyle = '#08101a';
    ctx.fillRect(0, 0, minimapState.width, minimapState.height);
    ctx.strokeStyle = 'rgba(181, 201, 224, 0.34)';
    ctx.strokeRect(0.5, 0.5, minimapState.width - 1, minimapState.height - 1);

    (state.world.zones.sidewalk || []).forEach((zone) => {
      drawPolygon(zone.polygon, metrics, pad, '#5d7083', 'rgba(211, 226, 242, 0.5)', 1.2, view, projectionOptions);
    });
    (state.world.zones.street || []).forEach((zone) => {
      drawPolygon(zone.polygon, metrics, pad, '#1a2f41', 'rgba(86, 117, 149, 0.68)', 1.15, view, projectionOptions);
    });
    (state.world.microAreas || []).forEach((area) => {
      drawPolygon(area.polygon, metrics, pad, 'rgba(255, 210, 135, 0.72)', 'rgba(255, 210, 135, 0.08)', 0.85, view, projectionOptions);
    });

    if (compassEl) {
      compassEl.textContent = 'Heading: ' + getFacingLabel(getHeadingVector());
    }
    updateMinimapLegend();

    (state.world.buildings || []).forEach((building) => {
      drawPolygon(getBuildingPolygon(building), metrics, pad, '#7d5e53', 'rgba(245, 218, 197, 0.34)', 0.95, view, projectionOptions);
    });

    getCollectibleProps().forEach((prop) => {
      if (!(state.quest.active || !prop.collected)) return;
      const pt = projectWorldToMinimap(prop._position, metrics, pad, view, projectionOptions);
      ctx.fillStyle = prop.collected ? '#6ed7a4' : '#ffd166';
      ctx.beginPath();
      ctx.arc(pt.x, pt.y, 4, 0, Math.PI * 2);
      ctx.fill();
    });

    const hintTarget = getHintTargetPosition(state.progression.nextHint && state.progression.nextHint.target);
    if (hintTarget) {
      const pt = projectWorldToMinimap(hintTarget, metrics, pad, view, projectionOptions);
      ctx.strokeStyle = 'rgba(255, 220, 120, 0.95)';
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.arc(pt.x, pt.y, 8, 0, Math.PI * 2);
      ctx.stroke();
    }

    if (state.world.navigator && Array.isArray(state.world.navigator.focusCorridor) && state.world.navigator.focusCorridor.length) {
      ctx.strokeStyle = 'rgba(255, 194, 112, 0.92)';
      ctx.lineWidth = 2.8;
      state.world.navigator.focusCorridor.forEach((corridor) => {
        if (!Array.isArray(corridor.points) || corridor.points.length < 2) return;
        ctx.beginPath();
        corridor.points.forEach((point, index) => {
          const mini = projectWorldToMinimap(point, metrics, pad, view, projectionOptions);
          if (index === 0) {
            ctx.moveTo(mini.x, mini.y);
          } else {
            ctx.lineTo(mini.x, mini.y);
          }
        });
        ctx.stroke();
      });
      ctx.strokeStyle = 'rgba(74, 35, 6, 0.3)';
      ctx.lineWidth = 1.05;
      state.world.navigator.focusCorridor.forEach((corridor) => {
        if (!Array.isArray(corridor.points) || corridor.points.length < 2) return;
        ctx.beginPath();
        corridor.points.forEach((point, index) => {
          const mini = projectWorldToMinimap(point, metrics, pad, view, projectionOptions);
          if (index === 0) {
            ctx.moveTo(mini.x, mini.y);
          } else {
            ctx.lineTo(mini.x, mini.y);
          }
        });
        ctx.stroke();
      });
    }

    if (state.world.route && Array.isArray(state.world.route.centerline) && state.world.route.centerline.length > 1) {
      ctx.strokeStyle = 'rgba(179, 234, 255, 0.68)';
      ctx.setLineDash([6, 4]);
      ctx.lineWidth = 1.6;
      ctx.beginPath();
      state.world.route.centerline.forEach((point, index) => {
        const mini = projectWorldToMinimap(point, metrics, pad, view, projectionOptions);
        if (index === 0) {
          ctx.moveTo(mini.x, mini.y);
        } else {
          ctx.lineTo(mini.x, mini.y);
        }
      });
      ctx.stroke();
      ctx.setLineDash([]);
    }

    const majorNodes = ['waterfront-station-threshold', 'water-street-mid-block', 'water-cambie-intersection', 'steam-clock', 'maple-tree-square-edge'];
    state.world.nodes.forEach((node) => {
      if (!majorNodes.includes(node.id)) return;
      const markerPoint = node.id === 'steam-clock' ? getMinimapLandmarkAnchor(node, state.world) : node;
      const mini = projectWorldToMinimap(markerPoint, metrics, pad, view, projectionOptions);
      ctx.fillStyle = node.id === 'steam-clock' ? '#d8a968' : node.id === 'water-cambie-intersection' ? '#9cc7e4' : '#b7d4ea';
      ctx.beginPath();
      ctx.arc(mini.x, mini.y, node.id === 'steam-clock' ? 5.2 : 4.1, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = 'rgba(228, 240, 255, 0.92)';
      ctx.font = '600 9px ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif';
      ctx.textAlign = 'left';
      ctx.textBaseline = 'middle';
      if (node.id === 'waterfront-station-threshold') {
        ctx.fillText('Station', mini.x + 6, mini.y - 5);
      }
      if (node.id === 'steam-clock') {
        const steamClockLabelAnchor = {
          x: markerPoint.x + 1.9,
          z: markerPoint.z + (minimapState.mode === 'heading-up' ? 0.4 : -2.1),
        };
        const labelMini = projectWorldToMinimap(steamClockLabelAnchor, metrics, pad, view, projectionOptions);
        ctx.fillText('Steam Clock', labelMini.x + 5, labelMini.y - 4);
      }
      if (node.id === 'water-cambie-intersection') {
        ctx.fillText('Cambie', mini.x + 6, mini.y - 5);
      }
      if (node.id === 'water-street-mid-block') {
        ctx.fillText('Water St', mini.x + 6, mini.y + 7);
      }
    });

    (state.world.microAreas || []).forEach((area) => {
      if (!area.anchor) return;
      const mini = projectWorldToMinimap(area.anchor, metrics, pad, view, projectionOptions);
      ctx.fillStyle = area.id === state.currentMicroAreaId ? '#ffd98f' : 'rgba(255, 217, 143, 0.72)';
      ctx.beginPath();
      ctx.arc(mini.x, mini.y, area.id === state.currentMicroAreaId ? 3.4 : 2.4, 0, Math.PI * 2);
      ctx.fill();
    });

    const dirLength = 13;
    const headingX = minimapState.mode === 'heading-up' ? 0 : heading.x;
    const headingY = minimapState.mode === 'heading-up' ? -1 : -heading.z;
    const sideX = -headingY;
    const sideY = headingX;
    const headingAngle = Math.atan2(headingY, headingX);

    ctx.strokeStyle = 'rgba(110, 212, 255, 0.6)';
    ctx.lineWidth = 4;
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(playerPoint.x + (headingX * 2.4), playerPoint.y + (headingY * 2.4));
    ctx.lineTo(playerPoint.x + (headingX * dirLength), playerPoint.y + (headingY * dirLength));
    ctx.stroke();

    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.arc(playerPoint.x, playerPoint.y - 4.2, 2.8, 0, Math.PI * 2);
    ctx.fill();

    ctx.strokeStyle = '#f4f8ff';
    ctx.lineWidth = 2.2;
    ctx.beginPath();
    ctx.moveTo(playerPoint.x, playerPoint.y - 1.4);
    ctx.lineTo(playerPoint.x, playerPoint.y + 4.3);
    ctx.moveTo(playerPoint.x - 2.5, playerPoint.y + 0.7);
    ctx.lineTo(playerPoint.x + 2.5, playerPoint.y + 0.7);
    ctx.moveTo(playerPoint.x, playerPoint.y + 4.3);
    ctx.lineTo(playerPoint.x - 2.1, playerPoint.y + 7.4);
    ctx.moveTo(playerPoint.x, playerPoint.y + 4.3);
    ctx.lineTo(playerPoint.x + 2.1, playerPoint.y + 7.4);
    ctx.stroke();

    ctx.fillStyle = '#9ce3ff';
    ctx.beginPath();
    ctx.moveTo(playerPoint.x + (headingX * 9.4), playerPoint.y + (headingY * 9.4));
    ctx.lineTo(playerPoint.x + (headingX * 5.3) + (sideX * 1.9), playerPoint.y + (headingY * 5.3) + (sideY * 1.9));
    ctx.lineTo(playerPoint.x + (headingX * 5.3) - (sideX * 1.9), playerPoint.y + (headingY * 5.3) - (sideY * 1.9));
    ctx.closePath();
    ctx.fill();

    ctx.strokeStyle = 'rgba(12, 23, 36, 0.68)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.arc(playerPoint.x, playerPoint.y - 4.2, 2.8, 0, Math.PI * 2);
    ctx.stroke();

    if (state.debugEnabled) {
      const debugVectors = getMinimapDebugVectors(state.world, metrics, pad, view, projectionOptions);
      if (debugVectors) {
        ctx.save();
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        ctx.strokeStyle = 'rgba(255, 201, 105, 0.9)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(debugVectors.tangentAnchor.x, debugVectors.tangentAnchor.y);
        ctx.lineTo(debugVectors.tangentTip.x, debugVectors.tangentTip.y);
        ctx.stroke();

        ctx.strokeStyle = 'rgba(120, 255, 214, 0.95)';
        ctx.beginPath();
        ctx.moveTo(debugVectors.tangentAnchor.x, debugVectors.tangentAnchor.y);
        ctx.lineTo(debugVectors.rightTip.x, debugVectors.rightTip.y);
        ctx.stroke();

        if (debugVectors.steamClock) {
          ctx.fillStyle = 'rgba(255, 120, 160, 0.95)';
          ctx.beginPath();
          ctx.arc(debugVectors.steamClock.x, debugVectors.steamClock.y, 3.2, 0, Math.PI * 2);
          ctx.fill();
        }
        ctx.restore();
      }
    }

    drawMinimapCompass(playerPoint, headingRotation);

    if (minimapState.nearestNode) {
      const nearestPoint = projectWorldToMinimap(minimapState.nearestNode, metrics, pad, view, projectionOptions);
      ctx.strokeStyle = '#8af7cb';
      ctx.lineWidth = 1.2;
      ctx.beginPath();
      ctx.arc(nearestPoint.x, nearestPoint.y, 7.5, 0, Math.PI * 2);
      ctx.stroke();
    }
  }

  function setupAudio(world) {
    const base = (config.audioBaseUrl || '').replace(/\/$/, '');
    const src = (name) => [base + '/' + name + '.mp3', base + '/' + name + '.ogg'];

    try {
      ['quiet_night', 'eerie_drones', 'nightlife_hum', 'commuter_mix'].forEach((id) => {
        state.sounds.beds[id] = createSafeHowl({ src: src('beds/' + id), loop: true, volume: 0, html5: false });
      });

      state.sounds.rainLoop = createSafeHowl({ src: src('weather/rain_pavement'), loop: true, volume: 0 });
      state.sounds.thunder = createSafeHowl({ src: src('weather/thunder_roll'), volume: 0.22 });

      (world.audioZones || []).forEach((zone) => {
        if (!zone || !zone.id || !zone.bed) {
          return;
        }
        state.sounds.zoneBeds[zone.id] = createSafeHowl({ src: src('zones/' + zone.bed), loop: true, volume: 0 });
      });
    } catch (error) {
      warnAudioUnavailable('Gastown audio setup failed; simulator continuing without ambient audio.', error);
    }
  }

  function applyVisualState() {
    if (!state.world) {
      return;
    }

    const mood = state.world.moodPresets[state.activeMood] || state.world.moodPresets.calm || state.world.moodPresets.eerie;
    const weather = state.world.weatherPresets[state.activeWeather] || state.world.weatherPresets.drizzle || state.world.weatherPresets.rain;
    const timeOfDay = state.world.timeOfDayPresets[state.activeTimeOfDay] || state.world.timeOfDayPresets.night;

    const weatherFogBoost = state.activeWeather === 'fog' ? 0.006 : state.activeWeather === 'rain' ? 0.002 : state.activeWeather === 'thunderstorm' ? 0.004 : 0;
    const fogDensity = Math.max(0.0035, (weather.fogDensity || 0.009) + (timeOfDay.fogBoost || 0) + weatherFogBoost);
    scene.fog = new THREE.FogExp2(timeOfDay.sky, fogDensity);
    renderer.setClearColor(timeOfDay.sky, 1);
    renderer.toneMappingExposure = (state.activeTimeOfDay === 'morning' ? 0.9 : state.activeTimeOfDay === 'night' ? 0.62 : state.activeTimeOfDay === 'dusk' ? 0.72 : 0.82) - ((weather.rainIntensity || 0) * 0.04);

    ambient.color.set(timeOfDay.ambientColor);
    ambient.intensity = Math.max(0.2, mood.lightIntensity * (timeOfDay.ambientIntensity || 1) * 0.76);
    keyLight.color.set(timeOfDay.keyColor);
    keyLight.intensity = (timeOfDay.keyIntensity || 0.6) * (0.72 + (mood.lightIntensity * 0.34));
    fillLight.color.set(timeOfDay.fillColor || '#526782');
    fillLight.intensity = (timeOfDay.fillIntensity || 0.22) * 0.82;
    rimLight.color.set(timeOfDay.fillColor || '#6f88a6');
    rimLight.intensity = (state.activeTimeOfDay === 'night' ? 0.24 : state.activeTimeOfDay === 'morning' ? 0.16 : 0.12) + ((weather.rainIntensity || 0) * 0.08);

    const contrast = timeOfDay.buildingContrast || 1;
    visualState.buildingMaterials.forEach((mat) => {
      mat.emissive.set(timeOfDay.buildingEdgeColor || '#1a2230');
      const baseRoughness = (mat.userData && mat.userData.baseRoughness) || 0.82;
      const baseEmissiveIntensity = (mat.userData && mat.userData.baseEmissiveIntensity) || 0.14;
      mat.emissiveIntensity = baseEmissiveIntensity + ((contrast - 1) * 0.24) + ((weather.rainIntensity || 0) * ART_DIRECTION.facadeGlowBoost * 0.2);
      mat.roughness = Math.max(0.5, baseRoughness - ((contrast - 1) * 0.16));
    });
    visualState.glassMaterials.forEach((mat) => {
      const rain = weather.rainIntensity || 0;
      mat.roughness = Math.max(0.05, ((mat.userData && mat.userData.baseRoughness) || 0.16) - (rain * 0.08));
      mat.opacity = Math.min(0.95, ((mat.userData && mat.userData.baseOpacity) || 0.88) + (state.activeTimeOfDay === 'night' ? 0.04 : 0));
      mat.emissiveIntensity = (state.activeTimeOfDay === 'night' ? 0.18 : state.activeTimeOfDay === 'morning' ? 0.11 : 0.08) + (rain * 0.08);
    });
    visualState.emissiveMaterials.forEach((mat) => {
      const base = (mat.userData && mat.userData.baseEmissiveIntensity) || 0.06;
      mat.emissiveIntensity = base + (state.activeTimeOfDay === 'night' ? 0.18 : state.activeTimeOfDay === 'dusk' ? 0.09 : 0.02) + ((weather.rainIntensity || 0) * 0.08);
    });
    visualState.metalMaterials.forEach((mat) => {
      mat.roughness = Math.max(0.22, 0.58 - ((weather.rainIntensity || 0) * 0.18));
      mat.metalness = Math.min(0.72, Math.max(0.18, mat.metalness + ((weather.rainIntensity || 0) * 0.04)));
    });
    visualState.reflectiveMaterials.forEach((mat) => {
      if (!mat) return;
      const rain = weather.rainIntensity || 0;
      mat.roughness = Math.max(0.08, (mat.roughness || 0.5) - (rain * ART_DIRECTION.streetReflectionBoost));
    });

    if (visualState.roadMaterial) {
      visualState.roadMaterial.color.set(state.activeTimeOfDay === 'morning' ? '#1f2b34' : state.activeTimeOfDay === 'dusk' ? '#182129' : '#10171e');
      setSurfaceWetness(visualState.roadMaterial, 0.96, 0.05, (weather.rainIntensity || 0) * 0.5);
      if (visualState.roadMaterial.normalScale) {
        visualState.roadMaterial.normalScale.set(1.35 + ((weather.rainIntensity || 0) * 0.24), 1.1 + ((weather.rainIntensity || 0) * 0.2));
      }
    }
    if (visualState.sidewalkMaterial) {
      visualState.sidewalkMaterial.color.set(state.activeTimeOfDay === 'night' ? '#5f5447' : state.activeTimeOfDay === 'dusk' ? '#6d5f50' : '#766859');
      setSurfaceWetness(visualState.sidewalkMaterial, 0.98, 0.02, (weather.rainIntensity || 0) * 0.22);
      if (visualState.sidewalkMaterial.normalScale) {
        visualState.sidewalkMaterial.normalScale.set(0.7 + ((weather.rainIntensity || 0) * 0.12), 0.7 + ((weather.rainIntensity || 0) * 0.12));
      }
    }
    if (visualState.curbMaterial) {
      visualState.curbMaterial.color.set('#9c876f');
    }
    if (visualState.laneMaterial) {
      visualState.laneMaterial.color.set('#5d5041');
      visualState.laneMaterial.opacity = 1;
    }
    Object.keys(visualState.fallbackSurfaceMaterials).forEach((role) => {
      (visualState.fallbackSurfaceMaterials[role] || []).forEach((mat) => {
        const rain = weather.rainIntensity || 0;
        const baseColor = new THREE.Color((mat.userData && mat.userData.baseColor) || 0x444444);
        if (role === 'road') {
          const target = new THREE.Color(state.activeTimeOfDay === 'night' ? '#131c24' : state.activeTimeOfDay === 'dusk' ? '#1b2831' : '#26333d');
          mat.color.copy(baseColor).lerp(target, 0.72);
          mat.roughness = Math.max(0.48, (mat.userData.baseRoughness || 0.92) - (rain * 0.24));
          mat.metalness = Math.min(0.16, (mat.userData.baseMetalness || 0.02) + (rain * 0.09));
        } else if (role === 'sidewalk' || role === 'plaza') {
          mat.roughness = Math.max(0.58, (mat.userData.baseRoughness || 0.92) - (rain * 0.12));
          mat.metalness = Math.min(0.08, (mat.userData.baseMetalness || 0.02) + (rain * 0.04));
        }
      });
    });
    visualState.lampVisuals.forEach((lampVisual) => {
      const rain = weather.rainIntensity || 0;
      const fog = state.activeWeather === 'fog' ? 0.3 : 0;
      const intensity = state.activeTimeOfDay === 'night'
        ? 1.7 + (rain * 0.8) + fog
        : state.activeTimeOfDay === 'dusk'
          ? 1.08 + (rain * 0.45) + (fog * 0.35)
          : 0.06 + (rain * 0.06);
      lampVisual.globeMaterial.emissiveIntensity = intensity;
      lampVisual.haloMaterial.opacity = Math.min(0.28, intensity * 0.12);
      lampVisual.pointLight.intensity = Math.min(2.8, intensity * 1.35);
      lampVisual.pointLight.distance = state.activeTimeOfDay === 'night' ? 9.5 : 7.2;
    });
    if (state.debugEnabled && window.console && typeof window.console.info === 'function') {
      window.console.info('[Gastown Sim] Lamp emissive updated for', state.activeTimeOfDay, state.activeWeather);
    }

    if (worldStatusEl) {
      worldStatusEl.dataset.artDirection = ART_DIRECTION.label;
    }

    applyLandmarkVisualState(visualState.landmarkVisuals, { mood, weather, timeOfDay });

    rebuildClouds(weather, timeOfDay);
    rebuildRain((weather.rainIntensity || 0) * (timeOfDay.rainVisibility || 1));
    updateLightning(weather);

    playHowl(state.sounds.rainLoop);
    fadeHowl(state.sounds.rainLoop, (weather.rainIntensity || 0) * 0.45, 380);
  }

  function applyMood(moodId) {
    state.activeMood = moodId;
    const preset = state.world.moodPresets[moodId] || state.world.moodPresets.calm || state.world.moodPresets.eerie;

    applyVisualState();

    Object.keys(state.sounds.beds).forEach((id) => {
      const howl = state.sounds.beds[id];
      playHowl(howl);
      fadeHowl(howl, id === preset.ambientBed ? 0.45 * preset.audioDensity : 0, 420);
    });

    if (state.npcVoiceTimer) {
      clearInterval(state.npcVoiceTimer);
    }
    refreshNpcPopulation();
    const barkInterval = Math.max(7000, 18000 - (preset.voiceFreq * 9000));
    state.npcVoiceTimer = setInterval(() => {
      maybeTriggerNpcVoice(preset.voiceFreq);
    }, barkInterval);
  }

  function applyWeather(weatherId) {
    state.activeWeather = weatherId;
    applyVisualState();
    refreshNpcPopulation();
  }

  function applyTimeOfDay(timeOfDayId) {
    state.activeTimeOfDay = timeOfDayId;
    applyVisualState();
  }

  function updateAudioZones() {
    if (!state.world || !state.world.audioZones) return;

    state.world.audioZones.forEach((zone) => {
      const dist = Math.hypot(zone.x - player.position.x, zone.z - player.position.z);
      const normalized = Math.max(0, 1 - (dist / zone.radius));
      const howl = state.sounds.zoneBeds[zone.id];
      if (!howl) return;
      playHowl(howl);
      if (howl && typeof howl.volume === 'function') {
        howl.volume(normalized * 0.4);
      }
    });
  }

  function updateOverviewCamera() {
    if (state.cameraMode !== 'overview') {
      return;
    }
    applyOverviewCameraPose();
  }

  function movePlayer(delta) {
    const movementProfile = getMovementProfile();
    const forward = Number(state.move.forward) - Number(state.move.backward);
    const strafe = Number(state.move.right) - Number(state.move.left);
    const turning = Number(state.turn.right) - Number(state.turn.left);

    if (turning !== 0) {
      state.yaw -= turning * movementProfile.turnSpeed * delta;
      player.rotation.y = state.yaw;
    }

    const input = new THREE.Vector3(strafe, 0, -forward);
    if (input.lengthSq() > 1) {
      input.normalize();
    }

    input.applyAxisAngle(new THREE.Vector3(0, 1, 0), state.yaw);
    const targetVelocity = input.multiplyScalar(movementProfile.maxSpeed);
    const hasInput = targetVelocity.lengthSq() > 0.0001;
    const blend = 1 - Math.exp(-delta * (hasInput ? movementProfile.acceleration : movementProfile.deceleration));
    state.velocity.lerp(targetVelocity, blend);

    player.position.x += state.velocity.x * delta;
    player.position.z += state.velocity.z * delta;
    player.position.y = getGroundY();

    enforceWorldBounds();
    updateNearestNode();
    updateAudioZones();
    updateCameraMotionFeedback(delta, movementProfile);
    updateInteractionTarget();
  }

  function lockPointer() {
    if (state.cameraMode !== 'street') {
      return;
    }
    renderer.domElement.requestPointerLock();
  }

  function onMouseMove(event) {
    if (state.cameraMode !== 'street' || document.pointerLockElement !== renderer.domElement || !state.isRunning) {
      return;
    }

    const sensitivity = 0.0018;
    state.yaw -= event.movementX * sensitivity;
    adjustPitch(-(event.movementY * sensitivity));

    player.rotation.y = state.yaw;
  }

  function onWheelLook(event) {
    if (state.cameraMode === 'overview') {
      event.preventDefault();
      state.overviewAltitude = THREE.MathUtils.clamp(
        state.overviewAltitude + (event.deltaY > 0 ? OVERVIEW_ZOOM_STEP : -OVERVIEW_ZOOM_STEP),
        OVERVIEW_ALTITUDE_MIN,
        OVERVIEW_ALTITUDE_MAX
      );
      applyOverviewCameraPose();
      updateCameraModeUi();
      return;
    }
    if (!canUseLookFallbackControls()) return;
    event.preventDefault();
    adjustPitch(event.deltaY * WHEEL_PITCH_SENSITIVITY);
  }

  function setMoveKey(code, pressed) {
    if (code === 'KeyW' || code === 'ArrowUp') state.move.forward = pressed;
    if (code === 'KeyS' || code === 'ArrowDown') state.move.backward = pressed;
    if (code === 'KeyA') state.move.left = pressed;
    if (code === 'KeyD') state.move.right = pressed;
    if (code === 'ArrowLeft') state.turn.left = pressed;
    if (code === 'ArrowRight') state.turn.right = pressed;
    if (code === 'AltLeft' || code === 'AltRight') state.gait.precise = pressed;
    if (code === 'ShiftLeft' || code === 'ShiftRight') state.gait.traverse = pressed;
  }

  function handlePitchKeyControl(event) {
    if (state.cameraMode !== 'street' || !event.ctrlKey || !canUseLookFallbackControls()) return false;
    if (event.code === 'ArrowUp') {
      adjustPitch(-KEYBOARD_PITCH_STEP);
      return true;
    }
    if (event.code === 'ArrowDown') {
      adjustPitch(KEYBOARD_PITCH_STEP);
      return true;
    }
    return false;
  }

  function resetToStart() {
    stopBandAudio('reset');
    const spawn = resolveSafeSpawn();
    player.position.set(spawn.x, spawn.y, spawn.z);
    state.lastSafePosition.copy(player.position);
    state.yaw = spawn.yaw || 0;
    state.pitch = Number.isFinite(spawn.pitch) ? clampPitch(spawn.pitch) : 0;
    if (state.cameraMode === 'overview') {
      applyOverviewCameraPose();
    } else {
      applyStreetCameraPose();
    }
    updateNearestNode();
    state.motion.bobOffset = 0;
    state.motion.swayOffset = 0;
    state.motion.bobPhase = 0;
    state.motion.footstepTimer = 0;
    if (state.cameraMode === 'overview') {
      applyOverviewCameraPose();
    } else {
      applyStreetCameraPose();
    }
  }

  function setDebugState(enabled) {
    state.debugEnabled = enabled;
    debugGroup.visible = enabled;
    if (routeDebugOverlay) {
      routeDebugOverlay.hidden = !enabled;
    }
  }

  function startSim() {
    unlockAudioFromGesture().finally(() => unlockSteamClockAudio());
    state.isRunning = true;
    setStatus(getPublicGoalText());
    setPointerStatus('Pointer live. Esc releases.');
    lockPointer();
  }

  function enterPlayMode() {
    if (state.cameraMode !== 'street') {
      return;
    }
    if (state.isRunning && document.pointerLockElement === renderer.domElement) {
      return;
    }
    startSim();
  }

  function pauseSim() {
    stopBandAudio('pause');
    state.isRunning = false;
    clearMovementInput();
    if (document.pointerLockElement) {
      document.exitPointerLock();
    }
    setStatus('Paused. Click in to resume.');
    setPointerStatus('Pointer free. Click in.');
  }

  function attachEvents() {
    window.addEventListener('resize', updateSize);
    window.addEventListener('blur', () => { pauseSim(); stopBandAudio('window-blur'); });
    document.addEventListener('visibilitychange', () => { if (document.hidden) { pauseSim(); stopBandAudio('visibility-hidden'); } });
    ['pointerdown', 'click', 'keydown'].forEach((eventName) => {
      document.addEventListener(eventName, () => { state.hasInteracted = true; unlockAudioFromGesture().finally(() => unlockSteamClockAudio()); }, { passive: true });
    });
    renderer.domElement.addEventListener('click', () => {
      if (state.activeDialogNpcId) {
        closeDialog({ resumePointer: true });
        return;
      }
      if (state.cameraMode !== 'street') {
        return;
      }
      if (state.hoveredNpcId && interactWithHoveredNpc()) {
        return;
      }
      unlockAudioFromGesture().finally(() => unlockSteamClockAudio());
      enterPlayMode();
    });
    document.addEventListener('mousemove', onMouseMove);
    renderer.domElement.addEventListener('wheel', onWheelLook, { passive: false });

    document.addEventListener('keydown', (event) => {
      if (event.code === 'KeyM') {
        const now = performance.now();
        if (now - state.tutorial.lastMKeyAt < 450) {
          toggleMinimapMode();
          if (minimapTooltipEl) { minimapTooltipEl.textContent = 'Minimap switched to ' + minimapState.mode + '.'; }
        }
        state.tutorial.lastMKeyAt = now;
      }
      if (event.code === 'KeyV') {
        event.preventDefault();
        toggleCameraMode();
        return;
      }
      if (event.code === 'Escape' && state.activeDialogNpcId) {
        event.preventDefault();
        closeDialog();
        return;
      }
      if (state.activeDialogNpcId) {
        return;
      }
      if (state.cameraMode !== 'street') {
        return;
      }
      if (state.cameraMode === 'street' && event.code === 'KeyE') {
        if (interactWithHoveredNpc()) { event.preventDefault(); return; }
        if (collectNearbyProp()) { event.preventDefault(); return; }
      }
      const consumedPitch = handlePitchKeyControl(event);
      setMoveKey(event.code, true);
      if (consumedPitch || (event.code.startsWith('Arrow') && (state.isRunning || document.pointerLockElement === renderer.domElement))) {
        event.preventDefault();
      }
    }, { passive: false });
    document.addEventListener('keyup', (event) => {
      setMoveKey(event.code, false);
      if (event.code.startsWith('Arrow') && (state.isRunning || document.pointerLockElement === renderer.domElement)) {
        event.preventDefault();
      }
    }, { passive: false });

    if (minimapZoomInBtn) {
      minimapZoomInBtn.addEventListener('click', () => setMinimapZoom(minimapState.zoom + minimapState.zoomStep));
    }
    if (minimapZoomOutBtn) {
      minimapZoomOutBtn.addEventListener('click', () => setMinimapZoom(minimapState.zoom - minimapState.zoomStep));
    }
    if (minimapModeBtn) {
      minimapModeBtn.addEventListener('click', toggleMinimapMode);
    }

    pauseBtn.addEventListener('click', pauseSim);
    resetBtn.addEventListener('click', resetToStart);

    if (timeOfDaySelect) {
      timeOfDaySelect.addEventListener('change', (event) => applyTimeOfDay(event.target.value));
    }
    weatherSelect.addEventListener('change', (event) => applyWeather(event.target.value));
    moodSelect.addEventListener('change', (event) => applyMood(event.target.value));

    ['pointerdown', 'keydown', 'touchstart'].forEach((eventName) => {
      document.addEventListener(eventName, () => { consumeStartupStingQueue(); }, { passive: true });
    });

    document.addEventListener('pointerlockchange', () => {
      if (state.cameraMode === 'street' && document.pointerLockElement === renderer.domElement) {
        setPointerStatus('Pointer live. Esc releases.');
      } else if (state.cameraMode === 'overview') {
        setPointerStatus('Overview camera.');
      } else if (state.isRunning) {
        clearMovementInput();
        state.isRunning = false;
        setStatus('Paused. Click in to resume.');
        setPointerStatus('Pointer free. Click in.');
      } else {
        setPointerStatus('Pointer free.');
      }
    });

    debugToggle.addEventListener('click', () => {
      const open = debugPanel.hasAttribute('hidden');
      if (open) {
        debugPanel.removeAttribute('hidden');
      } else {
        debugPanel.setAttribute('hidden', 'hidden');
      }
      setDebugState(open);
    });

    dialogCloseEls.forEach((button) => button.addEventListener('click', closeDialog));
    if (tutorialOpenBtn) tutorialOpenBtn.addEventListener('click', openTutorialOverlay);
    tutorialCloseEls.forEach((button) => button.addEventListener('click', closeTutorialOverlay));
    if (tutorialStartBtn) tutorialStartBtn.addEventListener('click', () => { closeTutorialOverlay(); resetToStart(); setStatus('Click in and move.'); });
    if (renameWalkerBtn) renameWalkerBtn.addEventListener('click', () => openWalkerNameOverlay(state.walkerName));
    if (walkerStartBtn) walkerStartBtn.addEventListener('click', () => confirmWalkerName(walkerNameInputEl ? walkerNameInputEl.value : state.walkerName));
    if (walkerSkipBtn) walkerSkipBtn.addEventListener('click', () => confirmWalkerName('Walker'));
    if (walkerNameInputEl) walkerNameInputEl.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); confirmWalkerName(walkerNameInputEl.value); } });
    if (lowGraphicsToggle) lowGraphicsToggle.addEventListener('change', (event) => setLowGraphicsMode(event.target.checked));
    if (reopenTutorialToggle) reopenTutorialToggle.addEventListener('change', (event) => { try { window.localStorage.setItem('gastownTutorialReopen', event.target.checked ? '1' : '0'); } catch (error) {} });
    if (dialogModalEl) {
      dialogModalEl.addEventListener('click', (event) => {
        if (event.target === dialogModalEl) {
          closeDialog();
        }
      });
    }
  }


  function getTone() {
    return window.Tone || null;
  }

  async function unlockSteamClockAudio() {
    const Tone = getTone();
    if (!Tone) {
      return false;
    }
    try {
      if (!state.sounds.steamClockChime) {
        state.sounds.steamClockChime = {
          synth: new Tone.PolySynth(Tone.Synth, {
            oscillator: { type: 'triangle8' },
            envelope: { attack: 0.01, decay: 0.34, sustain: 0.1, release: 1.8 },
            volume: -12,
          }).toDestination(),
          bell: new Tone.MetalSynth({
            frequency: 220,
            envelope: { attack: 0.001, decay: 1.2, release: 1.6 },
            harmonicity: 7.1,
            modulationIndex: 18,
            resonance: 3000,
            octaves: 1.4,
            volume: -18,
          }).toDestination(),
        };
      }
      await unlockAudioFromGesture();
      state.steamClockState.enabled = true;
      state.steamClockState.toneReady = true;
      return true;
    } catch (error) {
      state.steamClockState.enabled = false;
      warnAudioUnavailable('Tone.js steam clock chimes unavailable; simulator continuing without musical chimes.', error);
      return false;
    }
  }

  function triggerSteamClockChime(reason) {
    const Tone = getTone();
    if (!Tone || !state.steamClockState.enabled || !state.sounds.steamClockChime) {
      return false;
    }
    try {
      const now = Tone.now();
      let cursor = now + 0.05;
      STEAM_CLOCK_CHIME_MOTIF.forEach((phrase, phraseIndex) => {
        phrase.forEach((note, noteIndex) => {
          state.sounds.steamClockChime.synth.triggerAttackRelease(note, 0.62, cursor + (noteIndex * 0.38), phraseIndex === 0 ? 0.9 : 0.74);
        });
        if (phraseIndex < STEAM_CLOCK_CHIME_MOTIF.length - 1) {
          state.sounds.steamClockChime.bell.triggerAttackRelease('16n', cursor + 1.46, 0.22);
        }
        cursor += 1.78;
      });
      state.sounds.steamClockChime.bell.triggerAttackRelease('2n', now, 0.32);
      state.steamClockState.lastChimeAt = performance.now() / 1000;
      state.steamClockState.nextChimeAt = (performance.now() / 1000) + (reason === 'proximity' ? 42 : 58);
      return true;
    } catch (error) {
      warnAudioUnavailable('Tone.js steam clock chime playback failed; simulator continuing without musical chimes.', error);
      return false;
    }
  }

  function updateSteamClock(delta, nowSeconds) {
    if (state.steamClockState.plume) {
      const pulse = 0.65 + (Math.sin(nowSeconds * 1.8) * 0.12);
      state.steamClockState.plume.scale.setScalar(pulse);
      state.steamClockState.plume.position.y = 9.4 + (Math.sin(nowSeconds * 2.4) * 0.16);
      state.steamClockState.plume.material.opacity = 0.08 + Math.max(0, 0.18 - ((nowSeconds - state.steamClockState.lastChimeAt) * 0.07));
    }

    const steamClock = state.steamClockState.anchor || getSteamClockAnchor(state.world);
    if (!steamClock) {
      return;
    }
    const distance = Math.hypot(player.position.x - steamClock.x, player.position.z - steamClock.z);
    const playerNear = distance < 18;

    if (playerNear && !state.steamClockState.lastPlayerNear && nowSeconds >= state.steamClockState.proximityCooldownUntil) {
      if (triggerSteamClockChime('proximity')) {
        state.steamClockState.proximityCooldownUntil = nowSeconds + 44;
      }
    } else if (state.steamClockState.enabled && nowSeconds >= state.steamClockState.nextChimeAt && distance < 30) {
      triggerSteamClockChime('interval');
    }
    state.steamClockState.lastPlayerNear = playerNear;
  }

  function scheduleSteamClock() {
    if (state.clockTimer) {
      clearInterval(state.clockTimer);
    }
    state.steamClockState.anchor = getSteamClockAnchor(state.world);
    state.steamClockState.nextChimeAt = 28;
    state.clockTimer = window.setInterval(() => {}, 60000);
  }

  function updateProgressiveVisibility() {
    (state.npcs || []).forEach((npc) => {
      if (!npc.mesh) return;
      const distance = Math.hypot(player.position.x - npc.mesh.position.x, player.position.z - npc.mesh.position.z);
      npc.mesh.visible = distance < (state.lowGraphics ? 24 : 42);
    });
  }

  async function init() {
    try {
      const loadedWorld = await window.GastownWorldLoader.load(config.worldDataUrl);
      const dialogData = await loadDialogData().catch(() => null);
      state.world = loadedWorld;
      state.dialogData = dialogData && typeof dialogData === 'object' ? dialogData : {};
      updateAttribution(state.world);
      // Optional systems fail softly so the corridor still boots.
      // addNpcs(state.world); addLandmarks(state.world); setupAudio(state.world);
      const softSystems = [
        ['ground', addGround],
        ['props', addProps],
        ['buildings', addBuildings],
        ['streetscape', addStreetscape],
        ['npcs', addNpcs],
        ['hero landmarks', addHeroLandmarks],
        ['landmarks', addLandmarks],
        ['debug route', addDebugRoute],
        ['audio', setupAudio],
      ];
      softSystems.forEach(([label, fn]) => {
        try { fn(state.world); } catch (error) { if (window.console && window.console.warn) { window.console.warn('[Gastown Sim] Optional system failed during init: ' + label, error); } }
      });
      minimapState.worldMetrics = getWorldMetrics();
      try { if (window.localStorage.getItem('gastownLowGraphics') === '1') { setLowGraphicsMode(true); } } catch (error) {}
      const hadWalkerName = restoreWalkerName();
      state.progression.openingObjectiveStartedAt = window.performance && typeof window.performance.now === 'function' ? window.performance.now() : Date.now();
      setQuestStatus('Get your bearings and see what the street gives you.');
      restoreMinimapZoom();
      restoreMinimapMode();
      updateSize();
      applyTimeOfDay(state.activeTimeOfDay);
      applyMood(state.activeMood);
      applyWeather(state.activeWeather);
      resetToStart();
      setActiveThread('drift', { silent: true });
      pushJournalEntry('Arrived at the station threshold. The walk is already underway; start where the block feels most alive.');
      renderProgressionUi();
      updateNextMeaningfulThing();
      attachEvents();
      if (!hadWalkerName) { openWalkerNameOverlay(''); }
      setDebugState(state.debugEnabled);
      if (state.debugEnabled) {
        debugPanel.removeAttribute('hidden');
      }
      scheduleSteamClock();
      setWorldModeStatus(state.world);
      if (!(state.world.meta && state.world.meta.fallbackMode === 'working-gastown-corridor')) {
        setStatus(getPublicGoalText());
      }
      updateCameraModeUi();
      renderer.setAnimationLoop((time) => {
        const delta = Math.min(0.03, (time - (init.prevTime || time)) / 1000);
        init.prevTime = time;

        if (state.isRunning || state.cameraMode === 'overview') {
          movePlayer(delta);
        } else {
          updateCameraMotionFeedback(delta, getMovementProfile());
        }

        maybeTriggerLightning(state.world.weatherPresets[state.activeWeather] || null);
        updateOverviewCamera();
        updateSteamClock(delta, time / 1000);
        tickBandSystem();
        updateProgressiveVisibility();
        updateNpcs(delta);
        updateInteractionTarget();
        updateLandmarkDiscoveries();
        updateQuestChainsFromProgress();
        animateRain(delta);
        drawMinimap();
        refreshDebugRuntimeReadout();
        renderer.render(scene, camera);
      });
    } catch (error) {
      setStatus('Unable to start simulator: ' + error.message);
    }
  }

  init();
})(window, document);

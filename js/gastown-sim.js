
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
  const questStatusEl = app.querySelector('[data-sim-quest-status]');
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
  const dialogCloseEls = app.querySelectorAll('[data-dialog-close]');
  const dialogFallbackCloseEl = app.querySelector('[data-dialog-close-fallback]');
  const dialogUtils = window.GastownDialog || {};
  const normalizeDialogEntry = typeof dialogUtils.normalizeDialogEntry === 'function'
    ? dialogUtils.normalizeDialogEntry
    : function fallbackNormalizeDialogEntry(entry, options) {
      const fallbackTitle = (options && options.fallbackTitle) || 'Gastown guide';
      const missingLine = (options && options.missingLine) || 'This guide does not have dialog copy yet.';
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
    npcVoiceTimer: null,
    lastNpcVoiceAt: -Infinity,
    npcVoiceIndex: 0,
    clockTimer: null,
    boundaryNoticeTimer: null,
    audioWarningIssued: false,
    worldBuildStatus: null,
    npcs: [],
    props: [],
    guideQuestTriggered: false,
    tutorial: { seen: false, lastMKeyAt: 0 },
    lowGraphics: false,
    quest: { active: false, completed: false, rewardPending: false, items: [] },
    discoveries: {
      landmarks: {},
      props: {},
      lastContextKey: '',
    },
  };

  const DEFAULT_EYE_HEIGHT = 1.7;

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(68, 1, 0.08, 700);
  camera.position.set(0, DEFAULT_EYE_HEIGHT, 0);
  const renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.shadowMap.enabled = true;
  canvasWrap.appendChild(renderer.domElement);

  const player = new THREE.Object3D();
  player.add(camera);
  scene.add(player);

  const ambient = new THREE.AmbientLight(0x7f90b2, 0.7);
  scene.add(ambient);
  const keyLight = new THREE.DirectionalLight(0xaab6cb, 0.62);
  keyLight.position.set(7, 20, 14);
  scene.add(keyLight);
  const fillLight = new THREE.DirectionalLight(0x5f7695, 0.3);
  fillLight.position.set(-11, 10, -12);
  scene.add(fillLight);
  const lightningLight = new THREE.DirectionalLight(0xe7efff, 0);
  lightningLight.position.set(4, 30, 6);
  scene.add(lightningLight);

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

  function setQuestStatus(text) {
    if (questStatusEl) {
      questStatusEl.textContent = text;
    }
  }

  function openTutorialOverlay() {
    if (!tutorialOverlayEl) return;
    tutorialOverlayEl.removeAttribute('hidden');
    tutorialOverlayEl.setAttribute('aria-hidden', 'false');
    setStatus('Tutorial open. Review the controls, then start walking when ready.');
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
      setStatus('Street mode active. Mouse look and movement are live. Press V for overview.');
    }, 1900);
  }

  function getCameraModeLabel() {
    return state.cameraMode === 'overview' ? 'Overview' : 'Street';
  }

  function setPointerStatus(text) {
    if (pointerStatusEl) {
      pointerStatusEl.textContent = '[' + getCameraModeLabel() + ' view] ' + text;
    }
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
      setStatus('Overview mode active. Mouse wheel zooms altitude; press V to return to street view.');
      setPointerStatus('Pointer unlocked. Overview camera detached above player.');
    } else if (state.isRunning) {
      setStatus('Street mode active. Mouse look and movement are live. Press V for overview.');
      if (state.cameraMode === 'street' && document.pointerLockElement === renderer.domElement) {
        setPointerStatus('Pointer locked. Mouse look active. Press Esc to release pointer.');
      } else {
        setPointerStatus('Pointer lock requested… press Esc any time to release.');
      }
    } else {
      setStatus('Street mode ready. Click scene to enter look mode and begin moving, or press V for overview.');
      setPointerStatus('Pointer unlocked. Click scene to enter look mode. Press V for overview.');
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
      setStatus('Approximate fallback world loaded. This playable corridor is believable, but not survey-precise.');
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
      geometry: new THREE.SphereGeometry(0.42, 7, 6),
      material: new THREE.MeshStandardMaterial({ color: 0x1f2328, roughness: 0.96, metalness: 0.04 }),
      y: 0.38,
    },
    cardboard_box: {
      geometry: new THREE.BoxGeometry(0.85, 0.58, 0.72),
      material: new THREE.MeshStandardMaterial({ color: 0x8f6a45, roughness: 0.94, metalness: 0.02 }),
      y: 0.29,
    },
    newspaper_box: {
      geometry: new THREE.BoxGeometry(0.72, 1.18, 0.72),
      material: new THREE.MeshStandardMaterial({ color: 0xb3452f, roughness: 0.72, metalness: 0.18 }),
      y: 0.59,
    },
    utility_box: {
      geometry: new THREE.BoxGeometry(1.18, 1.42, 0.68),
      material: new THREE.MeshStandardMaterial({ color: 0x6f767d, roughness: 0.82, metalness: 0.24 }),
      y: 0.71,
    },
    bench: {
      geometry: new THREE.BoxGeometry(1.5, 0.42, 0.46),
      material: new THREE.MeshStandardMaterial({ color: 0x6b4c33, roughness: 0.88, metalness: 0.08 }),
      y: 0.34,
    },
    planter: {
      geometry: new THREE.CylinderGeometry(0.48, 0.56, 0.72, 10),
      material: new THREE.MeshStandardMaterial({ color: 0x7d684d, roughness: 0.9, metalness: 0.06 }),
      y: 0.36,
    },
  };
  const NPC_ROLE_STYLE = {
    pedestrian: { color: 0x9ab4c6, accent: 0x33414d, height: 1.72 },
    guide: { color: 0xc6aa74, accent: 0x3a2c18, height: 1.78 },
    busker: { color: 0x8c6bc0, accent: 0x2d1f42, height: 1.74 },
    tourist: { color: 0xd3b384, accent: 0x6a4d2b, height: 1.7 },
    photographer: { color: 0xb4c7d8, accent: 0x202933, height: 1.71 },
    skateboarder: { color: 0xe0a96d, accent: 0x473222, height: 1.73 },
    cyclist: { color: 0x8fc1a9, accent: 0x24453b, height: 1.76 },
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
    });
    visualState.groundTextures[kind] = set;
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
    return new THREE.MeshStandardMaterial(Object.assign({
      color: baseColor,
      roughness: roughness,
      metalness: metalness,
      polygonOffset: true,
      polygonOffsetFactor: kind === 'street' ? 1 : 0.5,
      polygonOffsetUnits: kind === 'street' ? 1 : 0.5,
    }, maps));
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

      bands.push({ segment_id: point.id, width: streetWidth * 0.96, length: segmentLength * 1.04, yaw: heading, offset_x: 0, offset_z: 0, tone: 'road_base_dark', opacity: 0.26, elevation: 0.012 });
      bands.push({ segment_id: point.id, width: streetWidth * 0.34, length: Math.max(6.8, segmentLength * 0.82), yaw: heading, offset_x: 0, offset_z: 0, tone: steamZone ? 'intersection_pavers' : 'wheel_track', opacity: steamZone ? 0.24 : 0.2, elevation: 0.016 });
      bands.push({ segment_id: point.id, width: streetWidth * 0.17, length: Math.max(5.8, segmentLength * 0.76), yaw: heading, offset_x: streetWidth * 0.34, offset_z: 0, tone: 'curb_grime', opacity: 0.2, elevation: 0.025 });
      bands.push({ segment_id: point.id, width: streetWidth * 0.17, length: Math.max(5.8, segmentLength * 0.76), yaw: heading, offset_x: -streetWidth * 0.34, offset_z: 0, tone: 'curb_grime', opacity: 0.2, elevation: 0.025 });

      if (steamZone) {
        bands.push({ segment_id: point.id, width: streetWidth * 0.66, length: Math.max(5.2, segmentLength * 0.34), yaw: heading, offset_x: 0, offset_z: 0, tone: 'cobble_break', opacity: 0.12, elevation: 0.02 });
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
    state.velocity.set(0, 0, 0);
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
        'You are standing near ' + contextLabel + ', where the route shifts from general foot traffic into a more theatrical heritage streetscape.',
        discovery.scavengerComplete
          ? 'Since you already finished the scavenger hunt, locals read this stretch by how the details connect back to Maple Tree Square and the Steam Clock.'
          : 'If you keep noticing small details here, the scavenger hunt clues start to make more sense in context.'
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
          'Maple Tree Square loosens the block into a knot of streets, so people stop walking like commuters and start behaving like they have arrived somewhere.',
          'That is why the square feels social even when it is only lightly crowded.'
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
      return 'Gastown guide';
    }
    return (npcState && npcState.id) || 'Gastown guide';
  }

  function getNpcRoleLabel(npcState) {
    const labels = { guide: 'guide', busker: 'busker', tourist: 'tourist', photographer: 'photographer', cyclist: 'cyclist', skateboarder: 'skateboarder', pedestrian: 'pedestrian' };
    return labels[npcState && npcState.role] || 'pedestrian';
  }

  function getNpcConversationFallback(npcState) {
    const role = npcState && npcState.role ? npcState.role : 'pedestrian';
    const fallbackByRole = {
      guide: ['Gastown grew around this working waterfront edge, so Water Street still reads best when the route keeps the heritage storefront cadence intact.', 'The Steam Clock is the memorable landmark, but the facades, paving, and corner pauses are what make the walk feel like Gastown.'],
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
          prompt: 'Share a quick local thought about Gastown, Water Street, or the Steam Clock area.',
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
      actions: [{ type: 'close', label: 'Back to walk' }],
      hasCustomActions: true,
    };
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
        button.addEventListener('click', () => startGuideQuest());
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

  function closeDialog() {
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
    clearMovementInput();
    setInteractPrompt('');
    setStatus('Dialog closed. Click scene to resume.');
    setPointerStatus('Pointer unlocked. Click scene to enter look mode.');
    const focusTarget = renderer.domElement || canvasWrap;
    if (focusTarget && typeof focusTarget.focus === 'function') {
      window.setTimeout(() => focusTarget.focus(), 0);
    }
  }

  function openDialogForNpc(npcState) {
    if (!npcState || !dialogModalEl || !dialogTitleEl || !dialogBodyEl) return;
    clearMovementInput();
    state.isRunning = false;
    state.dialogLastFocusEl = document.activeElement;

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
      missingLine: 'This guide does not have dialog copy yet.',
      defaultCloseLabel: 'Back to walk',
    }));

    if (npcState.role === 'guide') {
      normalized.actions = [
        { type: 'quest', label: state.quest.completed ? 'Review scavenger hunt' : 'Start scavenger hunt' },
        { type: 'response', label: 'Tell me more about Maple Tree Square', responseLines: ['Maple Tree Square marks the early centre of Gastown, where Carrall, Powell, Alexander, and Water come together in a triangular knot.', 'That irregular geometry is part of why the district feels older than the rest of downtown — the street plan still hints at the port-town era.'], followupStatus: 'Guide shared extra Gastown history.' }
      ].concat(normalized.actions || []);
    }

    dialogTitleEl.textContent = normalized.title;
    renderDialogBody(normalized.lines);
    renderDialogActions(normalized);

    const showDialog = () => {
      dialogModalEl.removeAttribute('hidden');
      dialogModalEl.setAttribute('aria-hidden', 'false');
      state.activeDialogNpcId = npcState.id;
      state.activeDialogEntry = normalized;
      setStatus('Dialog open. Loading nearby conversation.');
      setPointerStatus('Pointer unlocked. Dialog controls are active.');
      focusFirstDialogControl();
    };

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
    const items = getCollectibleProps().map((prop) => ({ key: prop.collectibleKey || prop.id, label: prop.collectibleLabel || prop.id, found: !!prop.collected, propId: prop.id }));
    state.quest.active = true;
    state.quest.completed = items.length && items.every((item) => item.found);
    state.quest.items = items;
    setQuestStatus('Scavenger hunt active: find ' + items.map((item) => item.label).join(', ') + '.');
    renderDialogBody(['Scavenger hunt started.', 'Find the highlighted newspaper box, historic plaque, and mural. Watch the minimap for star markers.']);
    updateMinimapLegend();
  }

  function completeGuideQuest() {
    state.quest.completed = true;
    state.quest.active = false;
    setQuestStatus('Scavenger hunt complete: the busker has a bonus Gastown story waiting near the Steam Clock.');
    const busker = (state.npcs || []).find((npc) => npc.role === 'busker');
    if (busker) {
      state.npcConversationCache[busker.id] = {
        title: 'Clock-corner busker',
        lines: ['You found all three clues, so here is your reward: Maple Tree Square is named for the giant maple that once anchored the old village crossroads.', 'Locals joke that the Steam Clock gets the spotlight, but the square carries the origin story.'],
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
    setQuestStatus('Scavenger hunt: ' + foundCount + '/' + state.quest.items.length + ' found.');
    if (foundCount === state.quest.items.length && state.quest.items.length) {
      completeGuideQuest();
    }
  }

  function collectNearbyProp() {
    const match = (state.props || []).find((prop) => prop.collectible && !prop.collected && Math.hypot(player.position.x - prop._position.x, player.position.z - prop._position.z) < 2.2);
    if (!match) return false;
    match.collected = true;
    state.discoveries.props[match.collectibleKey || match.id] = true;
    setStatus('Collected: ' + (match.collectibleLabel || match.id) + '.');
    updateQuestProgress();
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

  function makeNpcVisual(npc) {
    const style = NPC_ROLE_STYLE[npc.role] || NPC_ROLE_STYLE.pedestrian;
    const scaleFactor = Math.max(0.72, Math.min(1.15, npc.silhouetteScale || 1));
    const root = new THREE.Group();
    const bodyMaterial = new THREE.MeshStandardMaterial({ color: style.color, roughness: 0.86, metalness: 0.08 });
    const skinMaterial = new THREE.MeshStandardMaterial({ color: 0xe0c7aa, roughness: 0.92, metalness: 0.02 });
    const accentMaterial = new THREE.MeshStandardMaterial({ color: style.accent, roughness: 0.8, metalness: 0.1 });
    const body = new THREE.Mesh(
      new THREE.CapsuleGeometry(0.22, Math.max(0.6, style.height - 1), 4, 8),
      bodyMaterial
    );
    body.position.y = style.height * 0.5;
    const head = new THREE.Mesh(
      new THREE.SphereGeometry(0.17, 10, 10),
      skinMaterial
    );
    head.position.y = style.height - 0.12;
    const accent = new THREE.Mesh(
      new THREE.BoxGeometry(0.36, 0.14, 0.18),
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
    root.add(body);
    root.add(head);
    root.add(accent);
    root.add(leftArm);
    root.add(rightArm);
    root.add(leftLeg);
    root.add(rightLeg);
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
      heldProp,
      scaleFactor,
    };
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
      state.audioContext = new AudioContextCtor();
    }
    if (state.audioContext.state === 'suspended') {
      state.audioContext.resume().catch(() => {});
    }
    return state.audioContext;
  }

  function ensureNpcLoop(npcState) {
    if (npcState.role !== 'busker' || state.sounds.npcAudio[npcState.id]) {
      return;
    }
    const Tone = getTone();
    if (!Tone) {
      state.sounds.npcAudio[npcState.id] = { muted: true, mode: 'silent_busker' };
      return;
    }
    try {
      state.sounds.npcAudio[npcState.id] = {
        mode: 'busker_soft_duet',
        radius: 11.5,
        lastGestureAt: -Infinity,
        nextGestureAt: 0,
        intervalSeconds: 5.6,
        chordIndex: 0,
        synth: new Tone.PolySynth(Tone.Synth, {
          oscillator: { type: 'triangle6' },
          envelope: { attack: 0.02, decay: 0.3, sustain: 0.18, release: 1.6 },
          volume: -24,
        }).toDestination(),
        accent: new Tone.MembraneSynth({
          pitchDecay: 0.05,
          octaves: 2,
          envelope: { attack: 0.001, decay: 0.22, sustain: 0, release: 0.08 },
          volume: -34,
        }).toDestination(),
        motif: [
          ['G3', 'D4', 'B4'],
          ['E3', 'B3', 'G4'],
          ['C4', 'G4', 'E4'],
          ['D3', 'A3', 'F#4'],
        ],
      };
    } catch (error) {
      state.sounds.npcAudio[npcState.id] = { muted: true, mode: 'silent_busker' };
      warnAudioUnavailable('Busker motif setup failed; simulator continuing without busker audio.', error);
    }
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
      ensureNpcLoop(npcState);
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
      if (rig.head) {
        rig.head.rotation.y = Math.sin((nowSeconds * 0.9) + npc.animPhase) * 0.12;
        if (npc.reactingToPlayer) {
          rig.head.rotation.y += 0.18;
        }
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

      const loop = state.sounds.npcAudio[npc.id];
      if (loop && loop.mode === 'busker_soft_duet' && loop.synth && state.steamClockState.enabled) {
        const distance = Math.hypot(player.position.x - npc.mesh.position.x, player.position.z - npc.mesh.position.z);
        const nearFactor = Math.max(0, 1 - (distance / (loop.radius || 11.5)));
        if (nearFactor > 0.24 && nowSeconds >= (loop.nextGestureAt || 0)) {
          const chord = loop.motif[loop.chordIndex % loop.motif.length];
          try {
            loop.synth.triggerAttackRelease(chord, '2n', getTone().now(), 0.28 + (nearFactor * 0.16));
            if (loop.accent) {
              loop.accent.triggerAttackRelease('G2', '16n', getTone().now() + 0.02, 0.08 + (nearFactor * 0.08));
            }
            loop.lastGestureAt = nowSeconds;
            loop.chordIndex = (loop.chordIndex + 1) % loop.motif.length;
            loop.nextGestureAt = nowSeconds + loop.intervalSeconds + (deterministicUnit(npc.id + '-gesture-' + Math.floor(nowSeconds)) * 0.9);
          } catch (error) {
            warnAudioUnavailable('Busker motif playback failed; simulator continuing without busker audio.', error);
            loop.mode = 'silent_busker';
          }
        }
      }
    });
  }

  function findLookedAtNpc() {
    if (!Array.isArray(state.npcs) || state.npcs.length === 0) return null;
    camera.getWorldDirection(lookTarget);
    camera.getWorldPosition(tempCameraWorldPosition);
    sharedRaycaster.set(tempCameraWorldPosition, lookTarget);
    let best = null;
    state.npcs.forEach((npc) => {
      if (!npc.mesh) return;
      const distance = tempCameraWorldPosition.distanceTo(npc.mesh.position);
      if (distance > (npc.interactRadius || 2.4) + 1.2) {
        return;
      }
      const hits = sharedRaycaster.intersectObject(npc.mesh, true);
      if (!hits.length) {
        return;
      }
      const hitDistance = hits[0].distance;
      if (!best || hitDistance < best.hitDistance) {
        best = { npc: npc, hitDistance: hitDistance, distance: distance };
      }
    });
    return best && best.distance <= (best.npc.interactRadius || 2.4) ? best.npc : null;
  }

  function updateInteractionTarget() {
    if (state.cameraMode !== 'street') {
      state.hoveredNpcId = '';
      setInteractPrompt('');
      return;
    }
    const npc = findLookedAtNpc();
    state.hoveredNpcId = npc ? npc.id : '';
    if (!npc) {
      setInteractPrompt('');
      return;
    }
    const roleLabel = getNpcRoleLabel(npc);
    const context = getPlayerRouteContext();
    setInteractPrompt('Click or press E to talk to the ' + roleLabel + ' near ' + context.label + '.');
  }

  function interactWithHoveredNpc() {
    if (!state.hoveredNpcId) return false;
    const npc = (state.npcs || []).find((candidate) => candidate.id === state.hoveredNpcId);
    if (!npc) return false;
    openDialogForNpc(npc);
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
    return {
      primary: toneToColor(primary),
      accent: toneToColor(accent),
      trim: toneToColor(trim),
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
    visualState.roadMaterial = createGroundMaterial('street', 0x0a0f14, 0.94, 0.1);
    visualState.sidewalkMaterial = createGroundMaterial('sidewalk', 0x85796d, 0.98, 0.02);
    visualState.curbMaterial = new THREE.LineBasicMaterial({ color: 0xc2c8cf, transparent: true, opacity: 0.5 });
    visualState.laneMaterial = new THREE.MeshStandardMaterial({ color: 0x8fa0af, roughness: 0.92, metalness: 0.02, transparent: true, opacity: 0.02, depthWrite: false });
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
            brick: { color: 0x5c4033, roughness: 0.82, metalness: 0.06, opacity: 0.72 },
            road_base_dark: { color: 0x1c232a, roughness: 0.95, metalness: 0.03, opacity: 0.24 },
            wheel_track: { color: 0x0d1217, roughness: 0.62, metalness: 0.14, opacity: 0.2 },
            patch: { color: 0x2f353d, roughness: 0.76, metalness: 0.12, opacity: 0.2 },
            repair_patch_dark: { color: 0x252b32, roughness: 0.72, metalness: 0.14, opacity: 0.22 },
            puddle: { color: 0x1a212a, roughness: 0.34, metalness: 0.28, opacity: 0.14 },
            wet_streak: { color: 0x202731, roughness: 0.48, metalness: 0.2, opacity: 0.18 },
            edge_grime: { color: 0x1b1d20, roughness: 0.8, metalness: 0.08, opacity: 0.18 },
            curb_grime: { color: 0x181c20, roughness: 0.88, metalness: 0.04, opacity: 0.18 },
            cobble_break: { color: 0x55483d, roughness: 0.86, metalness: 0.04, opacity: 0.18 },
            paver_break: { color: 0x5d5144, roughness: 0.84, metalness: 0.05, opacity: 0.18 },
            intersection_pavers: { color: 0x5c5044, roughness: 0.9, metalness: 0.04, opacity: 0.26 },
            default: { color: 0x3a4048, roughness: 0.8, metalness: 0.08, opacity: 0.78 },
          };
          const style = toneStyles[band.tone] || toneStyles.default;
          return new THREE.MeshStandardMaterial({
            color: style.color,
            roughness: style.roughness,
            metalness: style.metalness,
            transparent: true,
            opacity: Math.min(style.opacity, band.opacity || style.opacity),
          });
        })()
      );
      paver.rotation.x = -Math.PI / 2;
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
        new THREE.MeshStandardMaterial({ color: 0x2b2f35, roughness: 0.6, metalness: 0.35 })
      );
      pole.position.set(lamp.x, (lamp.height || 4.6) / 2, lamp.z);
      worldGroup.add(pole);

      const globe = new THREE.Mesh(
        new THREE.SphereGeometry(0.3, 12, 10),
        new THREE.MeshStandardMaterial({ color: 0xe5ddbc, emissive: 0x897b58, emissiveIntensity: 0.52, roughness: 0.3, metalness: 0.06 })
      );
      globe.position.set(lamp.x, (lamp.height || 4.6) + 0.25, lamp.z);
      worldGroup.add(globe);
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
      const massingInset = b.mass_inset || profilePreset.baseInset;
      const geom = new THREE.ExtrudeGeometry(new THREE.Shape(shapePoints.map((point) => new THREE.Vector2(point.x, point.z))), {
        depth: b.height,
        bevelEnabled: false,
      });
      geom.rotateX(-Math.PI / 2);
      const mat = new THREE.MeshStandardMaterial({
        color: colors.primary,
        roughness: 0.8,
        metalness: 0.16,
        emissive: 0x11151f,
        emissiveIntensity: 0.22,
      });
      visualState.buildingMaterials.push(mat);
      const mesh = new THREE.Mesh(geom, mat);
      mesh.scale.set(massingInset, 1, massingInset);
      mesh.position.set(b.x, 0, b.z);
      mesh.rotation.y = b.yaw || 0;
      worldGroup.add(mesh);

      const rooflineType = b.roofline_type || 'flat_cornice';
      if (rooflineType !== 'flat') {
        const roofHeight = Math.max(0.5, (b.cornice_emphasis || 0.2) * 2 + profilePreset.rooflineLift * 0.2);
        const roofGeom = new THREE.CylinderGeometry((Math.max(b.width, b.depth) * 0.5) * 0.92, (Math.max(b.width, b.depth) * 0.5), roofHeight, 6);
        const roofMat = new THREE.MeshStandardMaterial({ color: colors.trim, roughness: 0.78, metalness: 0.12 });
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
      const storefront = new THREE.Mesh(
        new THREE.BoxGeometry((b.width || 8) * 0.92, storefrontBandHeight, Math.max(2, (b.depth || 8) * 0.15)),
        new THREE.MeshStandardMaterial({ color: colors.accent, roughness: 0.72, metalness: 0.1 })
      );
      const storefrontPos = localPointToWorld(b, 0, (b.depth || 8) * 0.34);
      storefront.position.set(storefrontPos.x, storefrontBandHeight / 2 + 0.2, storefrontPos.z);
      storefront.rotation.y = b.yaw || 0;
      worldGroup.add(storefront);

      const bayCount = Math.max(2, Math.min(8, b.window_bay_count || 4));
      const windowRows = Math.max(1, Math.min(5, (b.storefront_rhythm && b.storefront_rhythm.upper_rows) || profilePreset.windowRows));
      const windowMat = new THREE.MeshStandardMaterial({ color: 0x1f2a38, emissive: 0x344d63, emissiveIntensity: 0.14, roughness: 0.42, metalness: 0.22 });
      for (let row = 0; row < windowRows; row += 1) {
        for (let bay = 0; bay < bayCount; bay += 1) {
          const win = new THREE.Mesh(new THREE.PlaneGeometry((b.width || 8) / (bayCount + 1.2), (b.height || 12) / (windowRows * 4.3)), windowMat);
          const xOffset = (((bay + 1) / (bayCount + 1)) - 0.5) * ((b.width || 8) * 0.78);
          const yOffset = storefrontBandHeight + 1.4 + row * ((b.height - storefrontBandHeight - 2) / windowRows);
          const depthOffset = (b.depth || 8) * 0.52;
          const winPos = localPointToWorld(b, xOffset, depthOffset);
          win.position.set(winPos.x, yOffset, winPos.z);
          win.rotation.y = b.yaw || 0;
          worldGroup.add(win);
        }
      }

      if (b.awning_presence || (profilePreset.awningDepth > 0.1)) {
        const awning = new THREE.Mesh(
          new THREE.BoxGeometry((b.width || 8) * 0.86, 0.3, Math.max(0.8, profilePreset.awningDepth)),
          new THREE.MeshStandardMaterial({ color: colors.trim, roughness: 0.64, metalness: 0.08 })
        );
        const awningPos = localPointToWorld(b, 0, (b.depth || 8) * 0.45);
        awning.position.set(awningPos.x, storefrontBandHeight + 0.6, awningPos.z);
        awning.rotation.y = b.yaw || 0;
        worldGroup.add(awning);
      }

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
          const entry = new THREE.Mesh(
            new THREE.BoxGeometry(0.9, 2.4, 0.5),
            new THREE.MeshStandardMaterial({ color: 0x1d222b, roughness: 0.45, metalness: 0.22 })
          );
          const entryPos = localPointToWorld(b, t, (b.depth || 8) * 0.48);
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
        new THREE.LineBasicMaterial({ color: 0x6f8197, transparent: true, opacity: 0.28 })
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

        const plaza = new THREE.Mesh(new THREE.CircleGeometry(plazaRadius, 32), new THREE.MeshStandardMaterial({ color: 0x4c3d32, roughness: 0.92, metalness: 0.02 }));
        plaza.rotation.x = -Math.PI / 2;
        plaza.position.y = 0.03;
        clockRoot.add(plaza);
        const plazaApron = new THREE.Mesh(new THREE.RingGeometry(plazaRadius * 0.66, plazaRadius + 0.65, 32), new THREE.MeshStandardMaterial({ color: 0x7a6a5b, roughness: 0.96, metalness: 0.01 }));
        plazaApron.rotation.x = -Math.PI / 2;
        plazaApron.position.y = 0.031;
        clockRoot.add(plazaApron);

        const steamMaterial = new THREE.MeshBasicMaterial({ color: 0xd8dfdf, transparent: true, opacity: 0.18, depthWrite: false });
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
        const reflectionMaterial = new THREE.MeshStandardMaterial({ color: col, emissive: 0x1b2533, emissiveIntensity: 0.45, transparent: true, opacity: 0.22, roughness: 0.55, metalness: 0.3 });
        const reflection = new THREE.Mesh(new THREE.CircleGeometry(landmark.radius || 2.7, 24), reflectionMaterial);
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
    cloudColor.lerp(new THREE.Color('#f1f3f6'), 0.2 - Math.min(0.16, coverage * 0.08));
    cloudColor.multiplyScalar(1 - Math.min(0.42, weather.cloudDarkness || 0));
    const cloudMaterial = new THREE.MeshBasicMaterial({ color: cloudColor, transparent: true, opacity: Math.min(0.48, 0.14 + (coverage * 0.26)), depthWrite: false, fog: false });
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
        new THREE.MeshBasicMaterial({ color: 0xc7d9e6, transparent: true, opacity: 0.025 + (intensity * 0.025), depthWrite: false })
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
    if (minimapContextEl) {
      minimapContextEl.innerHTML = '<strong>Now facing:</strong> ' + facing + (nearest ? '<br><strong>Nearest landmark:</strong> ' + nearest.text : '');
    }
    if (minimapModeStatusEl) {
      const isHeadingUp = minimapState.mode === 'heading-up';
      minimapModeStatusEl.innerHTML = isHeadingUp
        ? '<strong>Map mode:</strong> Heading-up — the top of the map follows the way you are facing.<br><strong>Guidance:</strong> Landmark callouts stay player-relative (ahead/left/right) for first-person wayfinding.'
        : '<strong>Map mode:</strong> North-up — the top of the map is geographic north.<br><strong>Guidance:</strong> Landmark callouts stay player-relative (ahead/left/right) so the legend still reads like the street.';
    }
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

    if (nearest) {
      minimapState.nearestNode = nearest;
      minimapState.nearestGuidance = nearestGuidance;
      setLandmark('Nearest landmark: ' + (nearestGuidance ? nearestGuidance.text : nearest.label));
      if (routeSegmentEl) {
        const nearestIndex = Math.max(0, state.world.nodes.findIndex((node) => node.id === nearest.id));
        const total = Math.max(1, state.world.nodes.length - 1);
        const progress = Math.round((nearestIndex / total) * 100);
        routeSegmentEl.textContent = 'Route segment: ' + nearest.label + ' (' + progress + '%)';
      }
      if (minimapLandmarkEl) {
        minimapLandmarkEl.textContent = nearestGuidance ? 'Nearest landmark: ' + nearestGuidance.text : 'Nearest landmark: ' + nearest.label;
      }
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
    const items = ['You', 'Route line', 'Sidewalk / plaza', 'Road', 'Landmark', 'Guidance callout', 'Collectibles: ' + collectibleCount, 'Pedestrians: ' + (npcCounts.pedestrian || 0), 'Tourists: ' + ((npcCounts.tourist || 0) + (npcCounts.photographer || 0)), 'Cyclists: ' + (npcCounts.cyclist || 0)];
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

    ambient.color.set(timeOfDay.ambientColor);
    ambient.intensity = Math.max(0.24, mood.lightIntensity * (timeOfDay.ambientIntensity || 1) * 0.84);
    keyLight.color.set(timeOfDay.keyColor);
    keyLight.intensity = (timeOfDay.keyIntensity || 0.6) * (0.72 + (mood.lightIntensity * 0.28));
    fillLight.color.set(timeOfDay.fillColor || '#526782');
    fillLight.intensity = (timeOfDay.fillIntensity || 0.22) * 0.92;

    const contrast = timeOfDay.buildingContrast || 1;
    visualState.buildingMaterials.forEach((mat) => {
      mat.emissive.set(timeOfDay.buildingEdgeColor || '#1a2230');
      mat.emissiveIntensity = 0.14 + ((contrast - 1) * 0.3);
      mat.roughness = Math.max(0.56, 0.86 - ((contrast - 1) * 0.24));
    });

    if (visualState.roadMaterial) {
      visualState.roadMaterial.color.set(timeOfDay.roadColor || '#2b3138');
      setSurfaceWetness(visualState.roadMaterial, 0.86, 0.16, weather.rainIntensity || 0);
      if (visualState.roadMaterial.normalScale) {
        visualState.roadMaterial.normalScale.set(1.35 + ((weather.rainIntensity || 0) * 0.24), 1.1 + ((weather.rainIntensity || 0) * 0.2));
      }
    }
    if (visualState.sidewalkMaterial) {
      visualState.sidewalkMaterial.color.set(timeOfDay.sidewalkColor || '#8f8780');
      setSurfaceWetness(visualState.sidewalkMaterial, 0.92, 0.02, (weather.rainIntensity || 0) * 0.45);
      if (visualState.sidewalkMaterial.normalScale) {
        visualState.sidewalkMaterial.normalScale.set(0.7 + ((weather.rainIntensity || 0) * 0.12), 0.7 + ((weather.rainIntensity || 0) * 0.12));
      }
    }
    if (visualState.curbMaterial) {
      visualState.curbMaterial.color.set(timeOfDay.sidewalkColor || '#8f99a3').multiplyScalar(0.74);
    }
    if (visualState.laneMaterial) {
      visualState.laneMaterial.color.set(timeOfDay.laneColor || '#aab1b8');
      visualState.laneMaterial.opacity = Math.min(0.14, 0.035 + ((timeOfDay.pathBrightness || 0.2) * 0.12));
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
    const speed = 9;
    const turnSpeed = 1.85;
    const forward = Number(state.move.forward) - Number(state.move.backward);
    const strafe = Number(state.move.right) - Number(state.move.left);
    const turning = Number(state.turn.right) - Number(state.turn.left);

    if (turning !== 0) {
      state.yaw -= turning * turnSpeed * delta;
      player.rotation.y = state.yaw;
    }

    const input = new THREE.Vector3(strafe, 0, -forward);
    if (input.lengthSq() > 1) {
      input.normalize();
    }

    input.applyAxisAngle(new THREE.Vector3(0, 1, 0), state.yaw);
    state.velocity.lerp(input.multiplyScalar(speed), 0.18);

    player.position.x += state.velocity.x * delta;
    player.position.z += state.velocity.z * delta;
    player.position.y = getGroundY();

    enforceWorldBounds();
    updateNearestNode();
    updateAudioZones();
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
  }

  function setDebugState(enabled) {
    state.debugEnabled = enabled;
    debugGroup.visible = enabled;
    if (routeDebugOverlay) {
      routeDebugOverlay.hidden = !enabled;
    }
  }

  function startSim() {
    unlockSteamClockAudio();
    state.isRunning = true;
    setStatus('Street mode active. Mouse look and movement are live. Press V for overview.');
    setPointerStatus('Pointer lock requested… press Esc any time to release.');
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
    state.isRunning = false;
    clearMovementInput();
    if (document.pointerLockElement) {
      document.exitPointerLock();
    }
    setStatus('Street mode paused. Click scene to resume look mode and movement, or press V for overview.');
    setPointerStatus('Pointer released. Click scene to re-enter look mode.');
  }

  function attachEvents() {
    window.addEventListener('resize', updateSize);
    renderer.domElement.addEventListener('click', () => {
      if (state.activeDialogNpcId) {
        return;
      }
      if (state.cameraMode !== 'street') {
        return;
      }
      if (state.hoveredNpcId && interactWithHoveredNpc()) {
        return;
      }
      ensureAudioContext();
      unlockSteamClockAudio();
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

    document.addEventListener('pointerlockchange', () => {
      if (state.cameraMode === 'street' && document.pointerLockElement === renderer.domElement) {
        setPointerStatus('Pointer locked. Mouse look active. Press Esc to release pointer.');
      } else if (state.cameraMode === 'overview') {
        setPointerStatus('Pointer unlocked. Overview camera detached above player.');
      } else if (state.isRunning) {
        clearMovementInput();
        state.isRunning = false;
        setStatus('Street mode paused. Click scene to resume look mode and movement, or press V for overview.');
        setPointerStatus('Pointer released. Click scene to re-enter look mode.');
      } else {
        setPointerStatus('Pointer unlocked.');
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
    if (tutorialStartBtn) tutorialStartBtn.addEventListener('click', () => { closeTutorialOverlay(); resetToStart(); setStatus('Tutorial started. Click into the scene, then follow the route toward the Steam Clock.'); });
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
      if (typeof Tone.start === 'function') {
        await Tone.start();
      }
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
      addGround(state.world);
      addProps(state.world);
      addBuildings(state.world);
      addStreetscape(state.world);
      addNpcs(state.world);
      addHeroLandmarks(state.world);
      addLandmarks(state.world);
      addDebugRoute(state.world);
      setupAudio(state.world);
      minimapState.worldMetrics = getWorldMetrics();
      try { if (window.localStorage.getItem('gastownLowGraphics') === '1') { setLowGraphicsMode(true); } } catch (error) {}
      setQuestStatus('Scavenger hunt: talk to the guide to begin.');
      restoreMinimapZoom();
      restoreMinimapMode();
      updateSize();
      applyTimeOfDay(state.activeTimeOfDay);
      applyMood(state.activeMood);
      applyWeather(state.activeWeather);
      resetToStart();
      attachEvents();
      setDebugState(state.debugEnabled);
      if (state.debugEnabled) {
        debugPanel.removeAttribute('hidden');
      }
      scheduleSteamClock();
      setWorldModeStatus(state.world);
      if (!(state.world.meta && state.world.meta.fallbackMode === 'working-gastown-corridor')) {
        setStatus('Street mode ready. Click scene to enter look mode and begin moving, or press V for overview.');
      }
      updateCameraModeUi();
      renderer.setAnimationLoop((time) => {
        const delta = Math.min(0.03, (time - (init.prevTime || time)) / 1000);
        init.prevTime = time;

        if (state.isRunning) {
          movePlayer(delta);
        }

        maybeTriggerLightning(state.world.weatherPresets[state.activeWeather] || null);
        updateOverviewCamera();
        updateSteamClock(delta, time / 1000);
        updateProgressiveVisibility();
        updateNpcs(delta);
        updateInteractionTarget();
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

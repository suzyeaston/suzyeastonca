(function (window, document) {
  'use strict';

  const config = window.seGastownSim || {};
  const app = document.getElementById('gastown-sim-app');
  if (!app || !window.THREE || !window.GastownWorldLoader || !window.Howl || !window.Howler) {
    return;
  }

  const canvasWrap = app.querySelector('[data-sim-canvas]');
  const statusEl = app.querySelector('[data-sim-status]');
  const pointerStatusEl = app.querySelector('[data-sim-pointer-status]');
  const landmarkEl = app.querySelector('[data-sim-landmark]');
  const startBtn = app.querySelector('[data-action="start"]');
  const pauseBtn = app.querySelector('[data-action="pause"]');
  const resetBtn = app.querySelector('[data-action="reset"]');
  const timeOfDaySelect = app.querySelector('[name="time-of-day"]');
  const weatherSelect = app.querySelector('[name="weather"]');
  const moodSelect = app.querySelector('[name="mood"]');
  const debugToggle = app.querySelector('[data-action="debug-toggle"]');
  const debugPanel = app.querySelector('[data-debug-panel]');
  const routeDebugOverlay = app.querySelector('[data-route-debug-overlay]');

  const state = {
    world: null,
    isRunning: false,
    move: { forward: false, backward: false, left: false, right: false },
    yaw: 0,
    pitch: 0,
    velocity: new THREE.Vector3(),
    lastSafePosition: new THREE.Vector3(),
    debugEnabled: new URLSearchParams(window.location.search).get('gastownDebug') === '1',
    activeWeather: config.defaultWeather || 'rain',
    activeTimeOfDay: config.defaultTimeOfDay || 'night',
    activeMood: config.defaultMood || 'eerie',
    sounds: {
      beds: {},
      zoneBeds: {},
      rainLoop: null,
      clockBurst: null,
    },
    barkTimer: null,
    clockTimer: null,
    boundaryNoticeTimer: null,
  };

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(75, 1, 0.1, 800);
  camera.position.set(0, 1.7, 0);
  const renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
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

  const rainGroup = new THREE.Group();
  const worldGroup = new THREE.Group();
  const debugGroup = new THREE.Group();
  debugGroup.visible = state.debugEnabled;
  scene.add(worldGroup);
  scene.add(rainGroup);
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

  function flashBoundaryStatus(text) {
    setStatus(text);
    if (state.boundaryNoticeTimer) {
      clearTimeout(state.boundaryNoticeTimer);
    }
    state.boundaryNoticeTimer = setTimeout(() => {
      if (!state.isRunning) return;
      setStatus('Play mode active. Click scene for mouse look, then move with WASD / arrow keys.');
    }, 1900);
  }

  function setPointerStatus(text) {
    if (pointerStatusEl) {
      pointerStatusEl.textContent = text;
    }
  }

  function setLandmark(text) {
    landmarkEl.textContent = text;
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

  function toShape(points) {
    const shape = new THREE.Shape();
    points.forEach((point, index) => {
      if (index === 0) {
        shape.moveTo(point.x, point.z);
      } else {
        shape.lineTo(point.x, point.z);
      }
    });
    shape.closePath();
    return shape;
  }

  function createZoneMesh(points, material, y) {
    const mesh = new THREE.Mesh(new THREE.ShapeGeometry(toShape(points)), material);
    mesh.rotation.x = -Math.PI / 2;
    mesh.position.y = y;
    worldGroup.add(mesh);
    visualState.groundMeshes.push(mesh);
    return mesh;
  }

  function addGround(world) {
    visualState.roadMaterial = new THREE.MeshStandardMaterial({ color: 0x232a33, roughness: 0.9, metalness: 0.14 });
    visualState.sidewalkMaterial = new THREE.MeshStandardMaterial({ color: 0x34404a, roughness: 0.86, metalness: 0.08 });
    visualState.curbMaterial = new THREE.MeshStandardMaterial({ color: 0x2a3139, roughness: 0.84, metalness: 0.12 });
    visualState.laneMaterial = new THREE.LineBasicMaterial({ color: 0x698095, transparent: true, opacity: 0.42 });

    world.zones.street.forEach((zone) => createZoneMesh(zone.polygon, visualState.roadMaterial, 0));
    world.zones.sidewalk.forEach((zone) => createZoneMesh(zone.polygon, visualState.sidewalkMaterial, 0.12));

    world.zones.street.forEach((zone) => {
      const curb = createZoneMesh(zone.polygon, visualState.curbMaterial, 0.04);
      curb.scale.set(1.01, 1.01, 1.01);
    });

    const routePoints = world.route.centerline.map((point) => new THREE.Vector3(point.x, 0.14, point.z));
    const line = new THREE.Line(new THREE.BufferGeometry().setFromPoints(routePoints), visualState.laneMaterial);
    worldGroup.add(line);
  }

  function addBuildings(world) {
    world.buildings.forEach((b) => {
      const geom = new THREE.BoxGeometry(b.width, b.height, b.depth);
      const mat = new THREE.MeshStandardMaterial({
        color: toneToColor(b.tone),
        roughness: 0.8,
        metalness: 0.16,
        emissive: 0x11151f,
        emissiveIntensity: 0.22,
      });
      visualState.buildingMaterials.push(mat);
      const mesh = new THREE.Mesh(geom, mat);
      mesh.position.set(b.x, b.height / 2, b.z);
      mesh.rotation.y = b.yaw || 0;
      worldGroup.add(mesh);

      const edge = new THREE.LineSegments(
        new THREE.EdgesGeometry(geom),
        new THREE.LineBasicMaterial({ color: 0x6f8197, transparent: true, opacity: 0.28 })
      );
      edge.position.copy(mesh.position);
      edge.rotation.y = mesh.rotation.y;
      worldGroup.add(edge);
    });
  }

  function addLandmarks(world) {
    world.landmarks.forEach((landmark) => {
      const col = landmark.type === 'clock' ? 0xc8a460 : landmark.type === 'split' ? 0x7ea2c7 : 0x8793a1;
      const markerMaterial = new THREE.MeshStandardMaterial({ color: col, emissive: col, emissiveIntensity: 0.2 });
      const markerHeight = landmark.type === 'clock' ? 7.2 : 4.5;
      const markerRadius = landmark.type === 'clock' ? 0.38 : 0.7;
      const marker = new THREE.Mesh(new THREE.CylinderGeometry(markerRadius, markerRadius * 1.05, markerHeight, 12), markerMaterial);
      marker.position.set(landmark.x, markerHeight / 2, landmark.z);
      worldGroup.add(marker);

      const haloMaterial = new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: 0.1 });
      const halo = new THREE.Mesh(new THREE.SphereGeometry((landmark.radius || 6) * 0.7, 16, 16), haloMaterial);
      halo.position.set(landmark.x, 2.8, landmark.z);
      worldGroup.add(halo);

      const reflectionMaterial = new THREE.MeshStandardMaterial({ color: col, emissive: 0x1b2533, emissiveIntensity: 0.45, transparent: true, opacity: 0.22, roughness: 0.55, metalness: 0.3 });
      const reflection = new THREE.Mesh(new THREE.CircleGeometry(landmark.radius || 2.7, 24), reflectionMaterial);
      reflection.rotation.x = -Math.PI / 2;
      reflection.position.set(landmark.x, 0.11, landmark.z);
      worldGroup.add(reflection);

      visualState.landmarkVisuals.push({ markerMaterial, haloMaterial, reflectionMaterial });
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

    world.nodes.forEach((node) => {
      const marker = new THREE.Mesh(new THREE.SphereGeometry(0.45, 12, 12), new THREE.MeshBasicMaterial({ color: 0xd5f3ff }));
      marker.position.set(node.x, 0.5, node.z);
      debugGroup.add(marker);
    });

    if (routeDebugOverlay) {
      const lines = world.route.centerline.map((point, index) => (index + 1) + '. ' + (point.label || point.id || ('node-' + index)) + ' [' + point.x + ', ' + point.z + ']');
      routeDebugOverlay.textContent = lines.join('\n');
      routeDebugOverlay.hidden = !state.debugEnabled;
    }
  }

  function rebuildRain(intensity) {
    while (rainGroup.children.length) {
      rainGroup.remove(rainGroup.children[0]);
    }

    if (intensity <= 0.05) {
      return;
    }

    const count = Math.floor(450 * intensity);
    const geom = new THREE.BufferGeometry();
    const points = new Float32Array(count * 3);

    for (let i = 0; i < count; i += 1) {
      points[i * 3] = (Math.random() - 0.5) * 140;
      points[i * 3 + 1] = Math.random() * 40 + 4;
      points[i * 3 + 2] = (Math.random() - 0.5) * 180 - 45;
    }

    geom.setAttribute('position', new THREE.BufferAttribute(points, 3));
    const rain = new THREE.Points(geom, new THREE.PointsMaterial({ color: 0x9dc3db, size: 0.09, transparent: true, opacity: 0.7 }));
    rainGroup.add(rain);
  }

  function animateRain(delta) {
    rainGroup.children.forEach((points) => {
      const pos = points.geometry.attributes.position;
      for (let i = 0; i < pos.count; i += 1) {
        const y = pos.getY(i) - (18 * delta);
        pos.setY(i, y < 0.5 ? Math.random() * 40 + 5 : y);
      }
      pos.needsUpdate = true;
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
      player.position.copy(state.lastSafePosition.lengthSq() ? state.lastSafePosition : new THREE.Vector3(state.world.spawn.x, state.world.spawn.y, state.world.spawn.z));
      flashBoundaryStatus((state.world.bounds && state.world.bounds.resetMessage) || 'Returned to the route.');
      return;
    }

    flashBoundaryStatus((state.world.bounds && state.world.bounds.edgeMessage) || 'Stayed inside the route corridor.');
  }

  function updateNearestNode() {
    let nearest = null;
    let nearestDist = Number.POSITIVE_INFINITY;

    state.world.nodes.forEach((node) => {
      const d = Math.hypot(node.x - player.position.x, node.z - player.position.z);
      if (d < nearestDist) {
        nearestDist = d;
        nearest = node;
      }
    });

    if (nearest) {
      setLandmark('Nearest landmark: ' + nearest.label);
    }
  }

  function setupAudio(world) {
    const base = (config.audioBaseUrl || '').replace(/\/$/, '');
    const src = (name) => [base + '/' + name + '.mp3', base + '/' + name + '.ogg'];

    ['quiet_night', 'eerie_drones', 'nightlife_hum', 'commuter_mix'].forEach((id) => {
      state.sounds.beds[id] = new Howl({ src: src('beds/' + id), loop: true, volume: 0, html5: false });
    });

    state.sounds.rainLoop = new Howl({ src: src('weather/rain_pavement'), loop: true, volume: 0 });
    state.sounds.clockBurst = new Howl({ src: src('events/steam_clock_burst'), volume: 0.45 });

    world.audioZones.forEach((zone) => {
      state.sounds.zoneBeds[zone.id] = new Howl({ src: src('zones/' + zone.bed), loop: true, volume: 0 });
    });
  }

  function applyVisualState() {
    if (!state.world) {
      return;
    }

    const mood = state.world.moodPresets[state.activeMood] || state.world.moodPresets.eerie;
    const weather = state.world.weatherPresets[state.activeWeather] || state.world.weatherPresets.rain;
    const timeOfDay = state.world.timeOfDayPresets[state.activeTimeOfDay] || state.world.timeOfDayPresets.night;

    const weatherFogBoost = state.activeWeather === 'fog' ? 0.008 : state.activeWeather === 'rain' ? 0.003 : -0.001;
    const fogDensity = Math.max(0.004, (weather.fogDensity || 0.01) + (timeOfDay.fogBoost || 0) + weatherFogBoost);
    scene.fog = new THREE.FogExp2(timeOfDay.sky, fogDensity);
    renderer.setClearColor(timeOfDay.sky, 1);

    ambient.color.set(timeOfDay.ambientColor);
    ambient.intensity = Math.max(0.35, mood.lightIntensity * (timeOfDay.ambientIntensity || 1));
    keyLight.color.set(timeOfDay.keyColor);
    keyLight.intensity = (timeOfDay.keyIntensity || 0.6) * (0.8 + (mood.lightIntensity * 0.4));
    fillLight.color.set(timeOfDay.fillColor || '#526782');
    fillLight.intensity = timeOfDay.fillIntensity || 0.22;

    const contrast = timeOfDay.buildingContrast || 1;
    visualState.buildingMaterials.forEach((mat) => {
      mat.emissive.set(timeOfDay.buildingEdgeColor || '#1a2230');
      mat.emissiveIntensity = 0.14 + ((contrast - 1) * 0.3);
      mat.roughness = Math.max(0.56, 0.86 - ((contrast - 1) * 0.24));
    });

    if (visualState.roadMaterial) {
      visualState.roadMaterial.color.set(timeOfDay.roadColor || '#2b3138');
      visualState.roadMaterial.metalness = 0.1 + ((weather.rainIntensity || 0) * 0.22);
      visualState.roadMaterial.roughness = 0.92 - ((weather.rainIntensity || 0) * 0.26);
    }
    if (visualState.sidewalkMaterial) {
      visualState.sidewalkMaterial.color.set(timeOfDay.sidewalkColor || '#3a444f');
    }
    if (visualState.curbMaterial) {
      visualState.curbMaterial.color.set(timeOfDay.sidewalkColor || '#3a444f').multiplyScalar(0.74);
    }
    if (visualState.laneMaterial) {
      visualState.laneMaterial.color.set(timeOfDay.laneColor || '#5d6a76');
      visualState.laneMaterial.opacity = 0.2 + ((timeOfDay.pathBrightness || 0.25) * 0.5);
    }

    visualState.landmarkVisuals.forEach((landmarkVisual) => {
      landmarkVisual.markerMaterial.emissiveIntensity = (timeOfDay.landmarkGlow || 0.3) * (0.6 + (mood.lightIntensity * 0.5));
      landmarkVisual.haloMaterial.opacity = 0.08 + ((timeOfDay.landmarkGlow || 0.3) * 0.18);
      landmarkVisual.reflectionMaterial.opacity = 0.12 + ((timeOfDay.landmarkGlow || 0.3) * 0.18) + ((weather.rainIntensity || 0) * 0.12);
    });

    rebuildRain((weather.rainIntensity || 0) * (timeOfDay.rainVisibility || 1));

    if (!state.sounds.rainLoop.playing()) {
      state.sounds.rainLoop.play();
    }
    state.sounds.rainLoop.fade(state.sounds.rainLoop.volume(), (weather.rainIntensity || 0) * 0.45, 380);
  }

  function applyMood(moodId) {
    state.activeMood = moodId;
    const preset = state.world.moodPresets[moodId] || state.world.moodPresets.eerie;

    applyVisualState();

    Object.keys(state.sounds.beds).forEach((id) => {
      const howl = state.sounds.beds[id];
      if (!howl.playing()) {
        howl.play();
      }
      howl.fade(howl.volume(), id === preset.ambientBed ? 0.45 * preset.audioDensity : 0, 420);
    });

    if (state.barkTimer) {
      clearInterval(state.barkTimer);
    }
    const barkInterval = Math.max(4500, 15000 - (preset.voiceFreq * 12000));
    state.barkTimer = setInterval(() => {
      if (!state.isRunning || Math.random() > preset.voiceFreq) {
        return;
      }
      const bark = new Howl({
        src: [
          (config.audioBaseUrl || '').replace(/\/$/, '') + '/barks/hey_you.mp3',
          (config.audioBaseUrl || '').replace(/\/$/, '') + '/barks/hey_you.ogg'
        ],
        volume: 0.38 + (Math.random() * 0.2)
      });
      bark.play();
    }, barkInterval);
  }

  function applyWeather(weatherId) {
    state.activeWeather = weatherId;
    applyVisualState();
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
      if (!howl.playing()) {
        howl.play();
      }
      howl.volume(normalized * 0.4);
    });
  }

  function movePlayer(delta) {
    const speed = 9;
    const forward = Number(state.move.forward) - Number(state.move.backward);
    const strafe = Number(state.move.right) - Number(state.move.left);

    const input = new THREE.Vector3(strafe, 0, -forward);
    if (input.lengthSq() > 1) {
      input.normalize();
    }

    input.applyAxisAngle(new THREE.Vector3(0, 1, 0), state.yaw);
    state.velocity.lerp(input.multiplyScalar(speed), 0.18);

    player.position.x += state.velocity.x * delta;
    player.position.z += state.velocity.z * delta;
    player.position.y = Math.max((state.world.bounds && state.world.bounds.floorY) || 0, 1.7);

    enforceWorldBounds();
    updateNearestNode();
    updateAudioZones();
  }

  function lockPointer() {
    renderer.domElement.requestPointerLock();
  }

  function onMouseMove(event) {
    if (document.pointerLockElement !== renderer.domElement || !state.isRunning) {
      return;
    }

    const sensitivity = 0.0018;
    state.yaw -= event.movementX * sensitivity;
    state.pitch -= event.movementY * sensitivity;
    state.pitch = Math.max(-Math.PI / 2.25, Math.min(Math.PI / 2.25, state.pitch));

    player.rotation.y = state.yaw;
    camera.rotation.x = state.pitch;
  }

  function setMoveKey(code, pressed) {
    if (code === 'KeyW' || code === 'ArrowUp') state.move.forward = pressed;
    if (code === 'KeyS' || code === 'ArrowDown') state.move.backward = pressed;
    if (code === 'KeyA' || code === 'ArrowLeft') state.move.left = pressed;
    if (code === 'KeyD' || code === 'ArrowRight') state.move.right = pressed;
  }

  function resetToStart() {
    const spawn = state.world.spawn || { x: 0, y: 1.7, z: 0, yaw: 0 };
    player.position.set(spawn.x, spawn.y, spawn.z);
    state.lastSafePosition.copy(player.position);
    state.yaw = spawn.yaw || 0;
    state.pitch = 0;
    player.rotation.y = state.yaw;
    camera.rotation.x = 0;
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
    state.isRunning = true;
    setStatus('Play mode active. Click scene for mouse look, then move with WASD / arrow keys.');
    setPointerStatus('Pointer lock requested… press Esc any time to release.');
    lockPointer();
  }

  function pauseSim() {
    state.isRunning = false;
    state.move.forward = false;
    state.move.backward = false;
    state.move.left = false;
    state.move.right = false;
    if (document.pointerLockElement) {
      document.exitPointerLock();
    }
    setStatus('Paused. Click Start to continue walking the route.');
    setPointerStatus('Pointer released. Press Start and click scene to re-enter look mode.');
  }

  function attachEvents() {
    window.addEventListener('resize', updateSize);
    renderer.domElement.addEventListener('click', () => {
      if (state.isRunning) {
        lockPointer();
      }
    });
    document.addEventListener('mousemove', onMouseMove);

    document.addEventListener('keydown', (event) => setMoveKey(event.code, true));
    document.addEventListener('keyup', (event) => setMoveKey(event.code, false));

    startBtn.addEventListener('click', startSim);
    pauseBtn.addEventListener('click', pauseSim);
    resetBtn.addEventListener('click', resetToStart);

    if (timeOfDaySelect) {
      timeOfDaySelect.addEventListener('change', (event) => applyTimeOfDay(event.target.value));
    }
    weatherSelect.addEventListener('change', (event) => applyWeather(event.target.value));
    moodSelect.addEventListener('change', (event) => applyMood(event.target.value));

    document.addEventListener('pointerlockchange', () => {
      if (document.pointerLockElement === renderer.domElement) {
        setPointerStatus('Pointer locked. Mouse look active. Press Esc to release pointer.');
      } else if (state.isRunning) {
        state.move.forward = false;
        state.move.backward = false;
        state.move.left = false;
        state.move.right = false;
        state.isRunning = false;
        setStatus('Pointer released. Play mode exited. Press Start to continue the walk.');
        setPointerStatus('Pointer released. Press Start, then click scene to re-enter look mode.');
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
  }

  function scheduleSteamClock() {
    if (state.clockTimer) {
      clearInterval(state.clockTimer);
    }

    state.clockTimer = setInterval(() => {
      if (!state.isRunning) return;
      const steamClock = state.world.landmarks.find((landmark) => landmark.id === 'steam-clock');
      if (!steamClock) return;
      const distance = Math.hypot(player.position.x - steamClock.x, player.position.z - steamClock.z);
      if (distance < 20 && Math.random() > 0.48) {
        state.sounds.clockBurst.play();
      }
    }, 6000);
  }

  async function init() {
    try {
      state.world = await window.GastownWorldLoader.load(config.worldDataUrl);
      addGround(state.world);
      addBuildings(state.world);
      addLandmarks(state.world);
      addDebugRoute(state.world);
      setupAudio(state.world);
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
      setStatus('Prototype ready. Press Start, click the scene, and use Esc to exit pointer lock.');
      setPointerStatus('Pointer unlocked. Click scene after Start to enter look mode.');
      renderer.setAnimationLoop((time) => {
        const delta = Math.min(0.03, (time - (init.prevTime || time)) / 1000);
        init.prevTime = time;

        if (state.isRunning) {
          movePlayer(delta);
        }

        animateRain(delta);
        renderer.render(scene, camera);
      });
    } catch (error) {
      setStatus('Unable to start simulator: ' + error.message);
    }
  }

  init();
})(window, document);

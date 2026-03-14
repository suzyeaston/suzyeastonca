(function (window, document) {
  'use strict';

  const config = window.seGastownSim || {};
  const app = document.getElementById('gastown-sim-app');
  if (!app || !window.THREE || !window.GastownWorldLoader || !window.Howl || !window.Howler) {
    return;
  }

  const canvasWrap = app.querySelector('[data-sim-canvas]');
  const statusEl = app.querySelector('[data-sim-status]');
  const landmarkEl = app.querySelector('[data-sim-landmark]');
  const startBtn = app.querySelector('[data-action="start"]');
  const pauseBtn = app.querySelector('[data-action="pause"]');
  const resetBtn = app.querySelector('[data-action="reset"]');
  const weatherSelect = app.querySelector('[name="weather"]');
  const moodSelect = app.querySelector('[name="mood"]');
  const debugToggle = app.querySelector('[data-action="debug-toggle"]');
  const debugPanel = app.querySelector('[data-debug-panel]');

  const state = {
    world: null,
    isRunning: false,
    move: { forward: false, backward: false, left: false, right: false },
    yaw: 0,
    pitch: 0,
    velocity: new THREE.Vector3(),
    activeWeather: config.defaultWeather || 'rain',
    activeMood: config.defaultMood || 'eerie',
    sounds: {
      beds: {},
      zoneBeds: {},
      rainLoop: null,
      clockBurst: null,
    },
    barkTimer: null,
    clockTimer: null,
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

  const ambient = new THREE.AmbientLight(0x6f7f9f, 0.55);
  scene.add(ambient);
  const keyLight = new THREE.DirectionalLight(0x9da4ba, 0.5);
  keyLight.position.set(7, 20, 14);
  scene.add(keyLight);

  const rainGroup = new THREE.Group();
  scene.add(rainGroup);

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

  function setLandmark(text) {
    landmarkEl.textContent = text;
  }

  function toneToColor(tone) {
    switch (tone) {
      case 'brickWarm': return 0x5f3d33;
      case 'stoneMuted': return 0x4a4f56;
      case 'brickDark':
      default:
        return 0x3f2a2a;
    }
  }

  function addGround(world) {
    const path = world.corridor.centerline;
    let minX = path[0].x;
    let maxX = path[0].x;
    let minZ = path[0].z;
    let maxZ = path[0].z;

    path.forEach((point) => {
      minX = Math.min(minX, point.x);
      maxX = Math.max(maxX, point.x);
      minZ = Math.min(minZ, point.z);
      maxZ = Math.max(maxZ, point.z);
    });

    const width = (maxX - minX) + 48;
    const depth = (maxZ - minZ) + 72;

    const street = new THREE.Mesh(
      new THREE.PlaneGeometry(width, depth),
      new THREE.MeshStandardMaterial({ color: 0x1a1a1d, roughness: 0.95, metalness: 0.08 })
    );
    street.rotation.x = -Math.PI / 2;
    street.position.set((minX + maxX) / 2, 0, (minZ + maxZ) / 2 - 8);
    scene.add(street);
  }

  function addBuildings(world) {
    world.buildingPlanes.forEach((b) => {
      const geom = new THREE.BoxGeometry(b.width, b.height, b.depth);
      const mat = new THREE.MeshStandardMaterial({ color: toneToColor(b.tone), roughness: 0.85 });
      const mesh = new THREE.Mesh(geom, mat);
      mesh.position.set(b.x, b.height / 2, b.z);
      scene.add(mesh);
    });
  }

  function addLandmarks(world) {
    world.landmarks.forEach((landmark) => {
      const col = landmark.type === 'clock' ? 0xc8a460 : landmark.type === 'split' ? 0x7ea2c7 : 0x8793a1;
      const marker = new THREE.Mesh(
        new THREE.CylinderGeometry(0.6, 0.6, 4, 8),
        new THREE.MeshStandardMaterial({ color: col, emissive: 0x111111 })
      );
      marker.position.set(landmark.x, 2.1, landmark.z);
      scene.add(marker);
    });
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
    const rain = new THREE.Points(
      geom,
      new THREE.PointsMaterial({ color: 0x9dc3db, size: 0.09, transparent: true, opacity: 0.7 })
    );
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

  function clampToCorridor(position, corridor) {
    let nearestDistance = Number.POSITIVE_INFINITY;
    corridor.centerline.forEach((point, index) => {
      if (!corridor.centerline[index + 1]) return;
      nearestDistance = Math.min(nearestDistance, distanceToSegment(position, point, corridor.centerline[index + 1]));
    });

    if (nearestDistance <= corridor.softBoundary) {
      return;
    }

    const heading = Math.atan2(corridor.centerline[corridor.centerline.length - 1].x - corridor.centerline[0].x, corridor.centerline[corridor.centerline.length - 1].z - corridor.centerline[0].z);
    const nudge = 0.22;
    position.x -= Math.sin(heading) * nudge;
    position.z -= Math.cos(heading) * nudge;
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

    const ambientBeds = ['quiet_night', 'eerie_drones', 'nightlife_hum', 'commuter_mix'];
    ambientBeds.forEach((id) => {
      state.sounds.beds[id] = new Howl({ src: src('beds/' + id), loop: true, volume: 0, html5: false });
    });

    state.sounds.rainLoop = new Howl({ src: src('weather/rain_pavement'), loop: true, volume: 0 });
    state.sounds.clockBurst = new Howl({ src: src('events/steam_clock_burst'), volume: 0.45 });

    world.audioZones.forEach((zone) => {
      state.sounds.zoneBeds[zone.id] = new Howl({ src: src('zones/' + zone.bed), loop: true, volume: 0 });
    });
  }

  function applyMood(moodId) {
    state.activeMood = moodId;
    const preset = state.world.moodPresets[moodId] || state.world.moodPresets.eerie;

    ambient.intensity = preset.lightIntensity;
    keyLight.intensity = preset.lightIntensity * 0.9;

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
    const preset = state.world.weatherPresets[weatherId] || state.world.weatherPresets.rain;
    scene.fog = new THREE.FogExp2(preset.sky, preset.fogDensity);
    renderer.setClearColor(preset.sky, 1);

    rebuildRain(preset.rainIntensity || 0);

    if (!state.sounds.rainLoop.playing()) {
      state.sounds.rainLoop.play();
    }
    state.sounds.rainLoop.fade(state.sounds.rainLoop.volume(), (preset.rainIntensity || 0) * 0.45, 380);
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

    clampToCorridor(player.position, state.world.corridor);
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
    state.yaw = spawn.yaw || 0;
    state.pitch = 0;
    player.rotation.y = state.yaw;
    camera.rotation.x = 0;
    setLandmark('Nearest landmark: Station threshold');
  }

  function startSim() {
    state.isRunning = true;
    setStatus('Walking mode active. Click scene to lock pointer. WASD / arrows to move.');
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
    setStatus('Paused. Pointer unlocked.');
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

    weatherSelect.addEventListener('change', (event) => applyWeather(event.target.value));
    moodSelect.addEventListener('change', (event) => applyMood(event.target.value));

    debugToggle.addEventListener('click', () => {
      const isOpen = debugPanel.hasAttribute('hidden');
      if (isOpen) {
        debugPanel.removeAttribute('hidden');
      } else {
        debugPanel.setAttribute('hidden', 'hidden');
      }
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
      setupAudio(state.world);
      updateSize();
      applyMood(state.activeMood);
      applyWeather(state.activeWeather);
      resetToStart();
      attachEvents();
      scheduleSteamClock();
      setStatus('Prototype ready. Hit Start to enter first-person mode.');
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

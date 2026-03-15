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
  const routeSegmentEl = app.querySelector('[data-sim-route-segment]');
  const minimapZoomInBtn = app.querySelector('[data-action="minimap-zoom-in"]');
  const minimapZoomOutBtn = app.querySelector('[data-action="minimap-zoom-out"]');
  const osmAttributionEl = app.querySelector('[data-gastown-osm-attribution]');

  const state = {
    world: null,
    isRunning: false,
    move: { forward: false, backward: false, left: false, right: false },
    turn: { left: false, right: false },
    yaw: 0,
    pitch: 0,
    velocity: new THREE.Vector3(),
    lastSafePosition: new THREE.Vector3(),
    debugEnabled: new URLSearchParams(window.location.search).get('gastownDebug') === '1',
    activeWeather: config.defaultWeather || 'rain',
    activeTimeOfDay: config.defaultTimeOfDay || 'morning',
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

  const minimapState = {
    ctx: minimapCanvas ? minimapCanvas.getContext('2d') : null,
    width: minimapCanvas ? minimapCanvas.width : 0,
    height: minimapCanvas ? minimapCanvas.height : 0,
    worldMetrics: null,
    nearestNode: null,
    zoom: 1.2,
    minZoom: 0.8,
    maxZoom: 3.2,
    zoomStep: 0.2,
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
      setStatus('Play mode active. Mouse look and movement are live.');
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
    visualState.roadMaterial = new THREE.MeshStandardMaterial({ color: 0x1e232b, roughness: 0.93, metalness: 0.16 });
    visualState.sidewalkMaterial = new THREE.MeshStandardMaterial({ color: 0x6a3f37, roughness: 0.89, metalness: 0.06 });
    visualState.curbMaterial = new THREE.MeshStandardMaterial({ color: 0xc4ccd3, roughness: 0.83, metalness: 0.1 });
    visualState.laneMaterial = new THREE.LineBasicMaterial({ color: 0xa5bdcf, transparent: true, opacity: 0.56 });

    world.zones.street.forEach((zone) => createZoneMesh(zone.polygon, visualState.roadMaterial, 0));
    world.zones.sidewalk.forEach((zone) => createZoneMesh(zone.polygon, visualState.sidewalkMaterial, 0.12));

    world.zones.street.forEach((zone) => {
      const curb = createZoneMesh(zone.polygon, visualState.curbMaterial, 0.05);
      curb.scale.set(1.008, 1.008, 1.008);
    });

    const routePoints = world.route.centerline.map((point) => new THREE.Vector3(point.x, 0.14, point.z));
    const line = new THREE.Line(new THREE.BufferGeometry().setFromPoints(routePoints), visualState.laneMaterial);
    worldGroup.add(line);

    world.route.centerline.forEach((point, index) => {
      if (index % 2 !== 0 || index === world.route.centerline.length - 1) {
        return;
      }
      const next = world.route.centerline[index + 1];
      if (!next) return;
      const heading = Math.atan2(next.x - point.x, next.z - point.z);
      const stripe = new THREE.Mesh(
        new THREE.PlaneGeometry(0.22, 2.4),
        new THREE.MeshStandardMaterial({ color: 0xb9c7d5, roughness: 0.78, metalness: 0.22, transparent: true, opacity: 0.38 })
      );
      stripe.rotation.x = -Math.PI / 2;
      stripe.rotation.y = heading;
      stripe.position.set(point.x, 0.11, point.z);
      worldGroup.add(stripe);
    });

    (world.streetscape && world.streetscape.surfaceBands || []).forEach((band) => {
      const segment = world.route.centerline.find((point) => point.id === band.segment_id);
      if (!segment) return;
      const paver = new THREE.Mesh(
        new THREE.PlaneGeometry(band.width || world.route.streetWidth, band.length || 14),
        new THREE.MeshStandardMaterial({
          color: band.tone === 'brick' ? 0x634237 : 0x454b53,
          roughness: 0.88,
          metalness: 0.05,
          transparent: true,
          opacity: 0.92,
        })
      );
      paver.rotation.x = -Math.PI / 2;
      paver.rotation.y = band.yaw || 0;
      paver.position.set(segment.x + (band.offset_x || 0), 0.16, segment.z + (band.offset_z || 0));
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

  function addBuildings(world) {
    world.buildings.forEach((b) => {
      const profilePreset = facadeProfilePresets[b.facade_profile] || facadeProfilePresets.gastown_heritage_masonry;
      const colors = paletteToColors(b);
      const massingInset = b.mass_inset || profilePreset.baseInset;
      const shapePoints = Array.isArray(b.footprint) && b.footprint.length >= 3
        ? b.footprint
        : [
          { x: -b.width / 2, z: -b.depth / 2 },
          { x: b.width / 2, z: -b.depth / 2 },
          { x: b.width / 2, z: b.depth / 2 },
          { x: -b.width / 2, z: b.depth / 2 },
        ];
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
      storefront.position.set(b.x, storefrontBandHeight / 2 + 0.2, b.z + ((b.depth || 8) * 0.34));
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
          win.position.set(b.x + xOffset * Math.cos(b.yaw || 0), yOffset, b.z + depthOffset * Math.cos((b.yaw || 0) - Math.PI / 2) + xOffset * Math.sin(b.yaw || 0));
          win.rotation.y = b.yaw || 0;
          worldGroup.add(win);
        }
      }

      if (b.awning_presence || (profilePreset.awningDepth > 0.1)) {
        const awning = new THREE.Mesh(
          new THREE.BoxGeometry((b.width || 8) * 0.86, 0.3, Math.max(0.8, profilePreset.awningDepth)),
          new THREE.MeshStandardMaterial({ color: colors.trim, roughness: 0.64, metalness: 0.08 })
        );
        awning.position.set(b.x, storefrontBandHeight + 0.6, b.z + ((b.depth || 8) * 0.45));
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
          col.position.set(
            b.x + localX * Math.cos(b.yaw || 0),
            colHeight / 2,
            b.z + ((b.depth || 10) * 0.5) * Math.cos((b.yaw || 0) - Math.PI / 2) + localX * Math.sin(b.yaw || 0)
          );
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
          entry.position.set(
            b.x + t * Math.cos(b.yaw || 0),
            1.25,
            b.z + ((b.depth || 8) * 0.48) * Math.cos((b.yaw || 0) - Math.PI / 2) + t * Math.sin(b.yaw || 0)
          );
          worldGroup.add(entry);

          if (b.id === 'waterfront-station-civic') {
            const door = new THREE.Mesh(
              new THREE.PlaneGeometry(1.3, 3),
              new THREE.MeshStandardMaterial({ color: 0x161d27, emissive: 0x2f3d4f, emissiveIntensity: 0.2, roughness: 0.34, metalness: 0.25 })
            );
            door.position.set(entry.position.x, 1.6, entry.position.z + 0.23);
            door.rotation.y = b.yaw || 0;
            worldGroup.add(door);

            const transom = new THREE.Mesh(
              new THREE.PlaneGeometry(1.35, 0.55),
              new THREE.MeshStandardMaterial({ color: 0x45576c, emissive: 0x29394b, emissiveIntensity: 0.16, roughness: 0.42, metalness: 0.2 })
            );
            transom.position.set(entry.position.x, 3.2, entry.position.z + 0.24);
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
        canopy.position.set(b.x, storefrontBandHeight + 1.8, b.z + ((b.depth || 10) * 0.52));
        canopy.rotation.y = b.yaw || 0;
        worldGroup.add(canopy);

        const recess = new THREE.Mesh(
          new THREE.BoxGeometry((b.width || 40) * 0.7, Math.max(3.2, storefrontBandHeight * 0.82), 1.1),
          new THREE.MeshStandardMaterial({ color: 0x18202a, roughness: 0.76, metalness: 0.08 })
        );
        recess.position.set(b.x, Math.max(1.9, storefrontBandHeight * 0.4) + 0.3, b.z + ((b.depth || 10) * 0.49));
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
        const base = new THREE.Mesh(
          new THREE.CylinderGeometry(1.1, 1.35, 3.2, 12),
          new THREE.MeshStandardMaterial({ color: 0x4b3d34, roughness: 0.72, metalness: 0.08 })
        );
        base.position.set(hero.x, 1.6, hero.z);
        worldGroup.add(base);

        const body = new THREE.Mesh(
          new THREE.BoxGeometry(1.45, 5.4, 1.45),
          new THREE.MeshStandardMaterial({ color: 0x5d4b40, roughness: 0.66, metalness: 0.18 })
        );
        body.position.set(hero.x, 5.2, hero.z);
        worldGroup.add(body);

        const face = new THREE.Mesh(
          new THREE.CircleGeometry(0.62, 24),
          new THREE.MeshStandardMaterial({ color: 0xf0dfb2, emissive: 0x6b5b3d, emissiveIntensity: 0.36 })
        );
        face.position.set(hero.x + 0.74, 5.8, hero.z);
        face.rotation.y = -Math.PI / 2;
        worldGroup.add(face);

        const faceSouth = face.clone();
        faceSouth.position.set(hero.x, 5.8, hero.z + 0.74);
        faceSouth.rotation.y = 0;
        worldGroup.add(faceSouth);

        const cap = new THREE.Mesh(
          new THREE.ConeGeometry(1.1, 1.2, 10),
          new THREE.MeshStandardMaterial({ color: 0x2a2a30, roughness: 0.58, metalness: 0.34 })
        );
        cap.position.set(hero.x, 8.35, hero.z);
        worldGroup.add(cap);

        [-0.45, 0.45].forEach((offset) => {
          const pipe = new THREE.Mesh(
            new THREE.CylinderGeometry(0.08, 0.08, 1.5, 8),
            new THREE.MeshStandardMaterial({ color: 0x78767d, roughness: 0.54, metalness: 0.58 })
          );
          pipe.position.set(hero.x + offset, 6.7, hero.z + 0.58);
          pipe.rotation.x = Math.PI / 2.8;
          worldGroup.add(pipe);
        });

        const plinth = new THREE.Mesh(
          new THREE.CircleGeometry((hero.ground_emphasis_radius || 3.2), 28),
          new THREE.MeshStandardMaterial({ color: 0x43392f, roughness: 0.84, metalness: 0.06 })
        );
        plinth.rotation.x = -Math.PI / 2;
        plinth.position.set(hero.x, 0.15, hero.z);
        worldGroup.add(plinth);
      }
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
      minimapState.nearestNode = nearest;
      setLandmark('Nearest landmark: ' + nearest.label);
      if (routeSegmentEl) {
        const nearestIndex = Math.max(0, state.world.nodes.findIndex((node) => node.id === nearest.id));
        const total = Math.max(1, state.world.nodes.length - 1);
        const progress = Math.round((nearestIndex / total) * 100);
        routeSegmentEl.textContent = 'Route segment: ' + nearest.label + ' (' + progress + '%)';
      }
      if (minimapLandmarkEl) {
        minimapLandmarkEl.textContent = 'Nearest: ' + nearest.label;
      }
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
    const worldSpawn = state.world.spawn || { x: -23, y: 1.7, z: 20, yaw: -0.25 };
    const fallbackCandidates = [
      { x: worldSpawn.x, z: worldSpawn.z },
      { x: worldSpawn.x + 1.2, z: worldSpawn.z + 1.2 },
      { x: worldSpawn.x + 2.1, z: worldSpawn.z + 2.8 },
      { x: worldSpawn.x - 1.8, z: worldSpawn.z + 2.6 },
      { x: -21.2, z: 22.6 },
    ];

    const safePoint = fallbackCandidates.find((candidate) => isSpawnSafe(candidate));
    if (safePoint) {
      return { x: safePoint.x, y: worldSpawn.y || 1.7, z: safePoint.z, yaw: worldSpawn.yaw || -0.25 };
    }

    const routeStart = state.world.route.centerline[0] || { x: -22, z: 18 };
    return { x: routeStart.x, y: worldSpawn.y || 1.7, z: routeStart.z + 3.6, yaw: -0.3 };
  }



  function getHeadingVector() {
    const baseForward = new THREE.Vector3(0, 0, -1);
    const direction = baseForward.clone().applyAxisAngle(new THREE.Vector3(0, 1, 0), state.yaw);
    return { x: direction.x, z: direction.z };
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

  function drawPolygon(points, metrics, padding, fillColor, strokeColor, lineWidth, view) {
    if (!points || points.length < 3) {
      return;
    }
    const ctx = minimapState.ctx;
    ctx.beginPath();
    points.forEach((point, index) => {
      const mini = toMinimapPoint(point, metrics, padding, view);
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
    const centerX = ((playerX - worldCenterX) * 0.8) + worldCenterX;
    const centerZ = ((playerZ - worldCenterZ) * 0.8) + worldCenterZ;

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

  function drawMinimap() {
    if (!minimapState.ctx || !state.world || !minimapState.worldMetrics) {
      return;
    }

    const ctx = minimapState.ctx;
    const metrics = minimapState.worldMetrics;
    const view = getMinimapView(metrics);
    const pad = 16;
    ctx.clearRect(0, 0, minimapState.width, minimapState.height);

    ctx.fillStyle = '#08101a';
    ctx.fillRect(0, 0, minimapState.width, minimapState.height);
    ctx.strokeStyle = 'rgba(181, 201, 224, 0.34)';
    ctx.strokeRect(0.5, 0.5, minimapState.width - 1, minimapState.height - 1);

    (state.world.zones.sidewalk || []).forEach((zone) => {
      drawPolygon(zone.polygon, metrics, pad, '#3f4d60', 'rgba(136, 161, 188, 0.4)', 1, view);
    });
    (state.world.zones.street || []).forEach((zone) => {
      drawPolygon(zone.polygon, metrics, pad, '#1b2735', 'rgba(125, 148, 173, 0.45)', 1.1, view);
    });

    (state.world.buildings || []).forEach((building) => {
      drawPolygon(getBuildingPolygon(building), metrics, pad, '#6f5047', 'rgba(219, 194, 173, 0.24)', 0.8, view);
    });

    if (state.world.route && Array.isArray(state.world.route.centerline) && state.world.route.centerline.length > 1) {
      ctx.strokeStyle = 'rgba(158, 212, 240, 0.48)';
      ctx.setLineDash([5, 4]);
      ctx.lineWidth = 1.2;
      ctx.beginPath();
      state.world.route.centerline.forEach((point, index) => {
        const mini = toMinimapPoint(point, metrics, pad, view);
        if (index === 0) {
          ctx.moveTo(mini.x, mini.y);
        } else {
          ctx.lineTo(mini.x, mini.y);
        }
      });
      ctx.stroke();
      ctx.setLineDash([]);
    }

    const majorNodes = ['station-threshold', 'water-mid', 'steam-clock'];
    state.world.nodes.forEach((node) => {
      if (!majorNodes.includes(node.id)) return;
      const mini = toMinimapPoint(node, metrics, pad, view);
      ctx.fillStyle = node.id === 'steam-clock' ? '#d8a968' : '#b7d4ea';
      ctx.beginPath();
      ctx.arc(mini.x, mini.y, node.id === 'steam-clock' ? 4.6 : 3.8, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = 'rgba(228, 240, 255, 0.92)';
      ctx.font = '600 9px ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif';
      ctx.textAlign = 'left';
      ctx.textBaseline = 'middle';
      if (node.id === 'station-threshold') {
        ctx.fillText('Station', mini.x + 6, mini.y - 5);
      }
      if (node.id === 'steam-clock') {
        ctx.fillText('Steam Clock', mini.x + 6, mini.y - 5);
      }
    });

    const playerPoint = toMinimapPoint({ x: player.position.x, z: player.position.z }, metrics, pad, view);
    const heading = getHeadingVector();
    const dirLength = 16;
    const headingX = heading.x;
    const headingY = -heading.z;

    ctx.fillStyle = 'rgba(133, 205, 245, 0.3)';
    ctx.beginPath();
    ctx.moveTo(playerPoint.x, playerPoint.y);
    const headingAngle = Math.atan2(headingY, headingX);
    ctx.arc(playerPoint.x, playerPoint.y, dirLength, headingAngle - 0.5, headingAngle + 0.5);
    ctx.closePath();
    ctx.fill();

    const arrowTipX = playerPoint.x + (headingX * 10);
    const arrowTipY = playerPoint.y + (headingY * 10);
    const sideX = -headingY;
    const sideY = headingX;

    ctx.fillStyle = '#f2f6ff';
    ctx.beginPath();
    ctx.moveTo(arrowTipX, arrowTipY);
    ctx.lineTo(playerPoint.x - (headingX * 6) + (sideX * 4.2), playerPoint.y - (headingY * 6) + (sideY * 4.2));
    ctx.lineTo(playerPoint.x - (headingX * 6) - (sideX * 4.2), playerPoint.y - (headingY * 6) - (sideY * 4.2));
    ctx.closePath();
    ctx.fill();

    ctx.strokeStyle = '#9ce3ff';
    ctx.lineWidth = 1.4;
    ctx.beginPath();
    ctx.arc(playerPoint.x, playerPoint.y, 2.3, 0, Math.PI * 2);
    ctx.stroke();

    ctx.fillStyle = 'rgba(221, 236, 255, 0.82)';
    ctx.font = '700 11px ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('N', minimapState.width / 2, 9);
    ctx.fillText('S', minimapState.width / 2, minimapState.height - 9);
    ctx.fillText('W', 10, minimapState.height / 2);
    ctx.fillText('E', minimapState.width - 10, minimapState.height / 2);

    if (minimapState.nearestNode) {
      const nearestPoint = toMinimapPoint(minimapState.nearestNode, metrics, pad, view);
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
    if (code === 'KeyA') state.move.left = pressed;
    if (code === 'KeyD') state.move.right = pressed;
    if (code === 'ArrowLeft') state.turn.left = pressed;
    if (code === 'ArrowRight') state.turn.right = pressed;
  }

  function resetToStart() {
    const spawn = resolveSafeSpawn();
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
    setStatus('Play mode active. Mouse look and movement are live.');
    setPointerStatus('Pointer lock requested… press Esc any time to release.');
    lockPointer();
  }

  function enterPlayMode() {
    if (state.isRunning && document.pointerLockElement === renderer.domElement) {
      return;
    }
    startSim();
  }

  function pauseSim() {
    state.isRunning = false;
    state.move.forward = false;
    state.move.backward = false;
    state.move.left = false;
    state.move.right = false;
    state.turn.left = false;
    state.turn.right = false;
    if (document.pointerLockElement) {
      document.exitPointerLock();
    }
    setStatus('Paused. Click scene to resume look mode and movement.');
    setPointerStatus('Pointer released. Click scene to re-enter look mode.');
  }

  function attachEvents() {
    window.addEventListener('resize', updateSize);
    renderer.domElement.addEventListener('click', () => {
      enterPlayMode();
    });
    document.addEventListener('mousemove', onMouseMove);

    document.addEventListener('keydown', (event) => {
      setMoveKey(event.code, true);
      if (event.code.startsWith('Arrow') && (state.isRunning || document.pointerLockElement === renderer.domElement)) {
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
        state.turn.left = false;
        state.turn.right = false;
        state.isRunning = false;
        setStatus('Paused. Pointer released. Click scene to resume look mode and movement.');
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
      updateAttribution(state.world);
      addGround(state.world);
      addBuildings(state.world);
      addStreetscape(state.world);
      addHeroLandmarks(state.world);
      addLandmarks(state.world);
      addDebugRoute(state.world);
      setupAudio(state.world);
      minimapState.worldMetrics = getWorldMetrics();
      restoreMinimapZoom();
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
      setStatus('Prototype ready. Click scene to enter look mode and begin moving.');
      setPointerStatus('Pointer unlocked. Click scene to enter look mode.');
      renderer.setAnimationLoop((time) => {
        const delta = Math.min(0.03, (time - (init.prevTime || time)) / 1000);
        init.prevTime = time;

        if (state.isRunning) {
          movePlayer(delta);
        }

        animateRain(delta);
        drawMinimap();
        renderer.render(scene, camera);
      });
    } catch (error) {
      setStatus('Unable to start simulator: ' + error.message);
    }
  }

  init();
})(window, document);

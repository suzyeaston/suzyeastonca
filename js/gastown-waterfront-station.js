/*
 * Waterfront Station exterior, Cordova Street.
 *
 * Reference-led, original procedural geometry: not a survey-accurate digital twin.
 * Architectural cues: 1914 CPR Beaux-Arts station, red masonry, Ionic colonnade,
 * cornices and balustrade, arched glazing, gold CPR inscription, and the three
 * black domed "THE STATION" entrance canopies.
 * No third-party photography or textures are embedded in the simulator.
 */
(function (window) {
  'use strict';

  const REFERENCE = Object.freeze({
    id: 'waterfront-station-exterior-v1',
    label: 'Waterfront Station / CPR',
    architecture: 'Beaux-Arts, 1914',
    sources: [
      'https://www.cadillacfairview.com/office/properties/the-station/',
      'https://www.translink.ca/translink/schedules-and-maps/skytrain',
      'https://srarch.net/projects/waterfront-station-renovation'
    ]
  });

  // Position the facade across the near-side walkway of the authored corridor.
  // This is a local world-space stage, not a claimed GIS building footprint.
  function anchorFor(world) {
    if (world && world.station && Number.isFinite(world.station.x) && Number.isFinite(world.station.z) && Number.isFinite(world.station.yaw)) {
      return { x: world.station.x, z: world.station.z, yaw: world.station.yaw };
    }
    const start = (world && world.spawn) || (world && world.route && world.route.centerline && world.route.centerline[0]) || {};
    const yaw = Number.isFinite(start.yaw) ? start.yaw + 0.1 : -0.77;
    const x = Number.isFinite(start.x) ? start.x : -16.48;
    const z = Number.isFinite(start.z) ? start.z : -57.65;
    // Give the 17-metre roofline room in the initial first-person view.
    const frontageDistance = 22;
    return {
      // Spawn is outside the facade along the object's +Z axis.
      x: x - (Math.sin(yaw) * frontageDistance),
      z: z - (Math.cos(yaw) * frontageDistance),
      yaw
    };
  }

  function toWorld(world, x, z) {
    const anchor = anchorFor(world);
    return {
      // Match Three.js Group.rotation.y exactly. The old 2D conversion used
      // the opposite sign, putting commuters on the wrong side of the station.
      x: anchor.x + (x * Math.cos(anchor.yaw)) + (z * Math.sin(anchor.yaw)),
      z: anchor.z - (x * Math.sin(anchor.yaw)) + (z * Math.cos(anchor.yaw))
    };
  }

  function isWalkableForecourt(world, point) {
    if (!world || !world.spawn || !point) return false;
    const anchor = anchorFor(world);
    const dx = point.x - anchor.x;
    const dz = point.z - anchor.z;
    const localX = dx * Math.cos(anchor.yaw) - dz * Math.sin(anchor.yaw);
    const localZ = dx * Math.sin(anchor.yaw) + dz * Math.cos(anchor.yaw);
    // A walkable frontage that joins the original corridor but stops at the doors.
    // Public exterior connection around the east end, not a playable interior.
    const mainFrontage = Math.abs(localX) <= 55 && localZ >= 4.7 && localZ <= 31;
    const lotSidePassage = localX >= 52.5 && localX <= 64
      && localZ >= -10 && localZ <= 9;
    return mainFrontage || lotSidePassage;
  }

  // A bounded, public-facing *interpretation* of the historic main hall.
  // Coordinates use the same transform as the station shell and are not
  // surveyed interior floor plans. The far end is deliberately closed.
  function isWalkableInterior(world, point) {
    if (!world || !world.spawn || !point) return false;
    const a = anchorFor(world);
    const dx = point.x - a.x, dz = point.z - a.z;
    const x = dx * Math.cos(a.yaw) - dz * Math.sin(a.yaw);
    const z = dx * Math.sin(a.yaw) + dz * Math.cos(a.yaw);
    return Math.abs(x) <= 5.4 && z >= -13.25 && z <= 5.1;
  }

  function sideEntranceFor(world) {
    if (!world || !world.spawn) return null;
    return {
      threshold: toWorld(world, 55.1, -4.0),
      approach: toWorld(world, 60.1, -4.0),
      forecourt: toWorld(world, 23.0, 8.0),
      label: 'Waterfront Station east side access'
    };
  }

  function arrivalYaw(world, point) {
    const anchor = anchorFor(world);
    const spawn = point || (world && world.spawn) || { x: anchor.x, z: anchor.z + 13 };
    return Math.atan2(-(anchor.x - spawn.x), -(anchor.z - spawn.z));
  }

  // Original procedural brick pattern, not an unlicensed photo.
  function brickTexture(THREE) {
    if (!window.document || !window.document.createElement) return null;
    const canvas = window.document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 512;
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;
    ctx.fillStyle = '#9e887b';
    ctx.fillRect(0, 0, 512, 512);
    const brickW = 76, brickH = 23;
    for (let row = 0; row < 24; row += 1) {
      const shift = row % 2 ? brickW / 2 : 0;
      for (let col = -1; col < 8; col += 1) {
        const shade = (row * 17 + col * 31 + 113) % 7;
        const palette = ['#a24738', '#984333', '#b45341', '#ad5140', '#8c3e35', '#b45b46', '#9d493b'];
        ctx.fillStyle = palette[shade];
        const bx = col * brickW + shift + 2, by = row * brickH + 2;
        ctx.fillRect(bx, by, brickW - 3, brickH - 3);
        ctx.fillStyle = 'rgba(234,178,143,0.13)';
        ctx.fillRect(bx + 3, by + 3, brickW - 11, 2);
      }
    }
    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(7, 2.4);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;
    return texture;
  }

  function textTexture(THREE, text, options) {
    const settings = options || {};
    const canvas = window.document.createElement('canvas');
    canvas.width = 1024;
    canvas.height = 128;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, 1024, 128);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = settings.font || 'bold 68px Georgia, serif';
    ctx.fillStyle = settings.color || '#dec8a6';
    ctx.fillText(text, 512, 64, 985);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    return texture;
  }

  function create(THREE, world) {
    if (!THREE || !THREE.Group || !world || !world.spawn) return null;
    const anchor = anchorFor(world);
    const root = new THREE.Group();
    root.name = 'Waterfront Station exterior / 1914 CPR station';
    root.position.set(anchor.x, 0, anchor.z);
    root.rotation.y = anchor.yaw;

    const brick = new THREE.MeshStandardMaterial({
      color: 0xffeee1, map: brickTexture(THREE), roughness: 0.87, metalness: 0.01,
      emissive: 0x43170f, emissiveIntensity: 0.15
    });
    const stone = new THREE.MeshStandardMaterial({ color: 0xd5cbbd, roughness: 0.83, metalness: 0.02 });
    const stoneShadow = new THREE.MeshStandardMaterial({ color: 0xb4a99a, roughness: 0.84, metalness: 0.02 });
    const brass = new THREE.MeshStandardMaterial({ color: 0xbc9c60, roughness: 0.45, metalness: 0.34 });
    const iron = new THREE.MeshStandardMaterial({ color: 0x14191f, roughness: 0.48, metalness: 0.3 });
    const canopyBlack = new THREE.MeshStandardMaterial({ color: 0x12181f, roughness: 0.58, metalness: 0.08, side: THREE.DoubleSide });
    const glass = new THREE.MeshStandardMaterial({
      color: 0x57717c, emissive: 0x182d36, emissiveIntensity: 0.14,
      roughness: 0.16, metalness: 0.42, transparent: true, opacity: 0.87
    });
    const pavement = new THREE.MeshStandardMaterial({ color: 0x8d8177, roughness: 0.88, metalness: 0.02 });

    function box(name, x, y, z, w, h, d, material) {
      const mesh = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), material);
      mesh.name = name;
      mesh.position.set(x, y, z);
      mesh.castShadow = false;
      mesh.receiveShadow = true;
      root.add(mesh);
      return mesh;
    }
    function cylinder(name, x, y, z, top, bottom, height, material, sides) {
      const mesh = new THREE.Mesh(new THREE.CylinderGeometry(top, bottom, height, sides || 16), material);
      mesh.name = name;
      mesh.position.set(x, y, z);
      mesh.castShadow = false;
      mesh.receiveShadow = true;
      root.add(mesh);
      return mesh;
    }
    function sign(text, x, y, z, w, h, color, font) {
      const texture = textTexture(THREE, text, { color, font });
      const material = new THREE.MeshBasicMaterial({ map: texture, transparent: true, side: THREE.DoubleSide, depthWrite: false });
      const panel = new THREE.Mesh(new THREE.PlaneGeometry(w, h), material);
      panel.position.set(x, y, z);
      panel.renderOrder = 2;
      panel.name = text;
      root.add(panel);
      return panel;
    }
    function archWindow(x, width, height) {
      const radius = width / 2;
      const centerY = height - radius;
      const shape = new THREE.Shape();
      shape.moveTo(-radius, 0);
      shape.lineTo(radius, 0);
      shape.lineTo(radius, centerY);
      shape.absarc(0, centerY, radius, 0, Math.PI, false);
      shape.lineTo(-radius, 0);
      const pane = new THREE.Mesh(new THREE.ShapeGeometry(shape), glass);
      pane.position.set(x, 0.5, 0.19);
      pane.name = 'Arched ground-floor glazing';
      root.add(pane);
      const arch = new THREE.Mesh(new THREE.TorusGeometry(radius, 0.12, 8, 22, Math.PI), stone);
      arch.position.set(x, centerY + 0.5, 0.3);
      root.add(arch);
      [-radius, radius].forEach(side => box('Arched stone jamb', x + side, centerY * 0.5 + 0.5, 0.3, 0.18, centerY, 0.2, stone));
      box('Window sill', x, 0.5, 0.36, width + 0.34, 0.2, 0.32, stone);
      box('Window mullion', x, height * 0.5 + 0.2, 0.34, 0.09, height - 0.6, 0.13, iron);
    }

    // Massing / wing sections. Three street-side storeys with raised parapet.
    // Articulated shell around the central public hall, rather than a solid
    // box swallowing the walker. Keep the original recognizable roofline.
    box('Red-brick CPR station body', -30.5, 8.1, -8.5, 45, 16.2, 17, brick);
    box('CPR station east wing', 30.5, 8.1, -8.5, 45, 16.2, 17, brick);
    box('CPR station hall upper masonry', 0, 13.7, -8.5, 16, 5, 17, brick);
    box('CPR station hall rear masonry', 0, 5.6, -16.35, 16, 11.2, 1.3, brick);
    box('Projecting central entry block', -15.5, 7.2, -0.15, 22, 14.4, 1.2, brick);
    box('Projecting east entry block', 15.5, 7.2, -0.15, 22, 14.4, 1.2, brick);
    box('Entry block crosshead above hall', 0, 9.5, -0.15, 9, 9.3, 1.2, brick);
    box('Ground-floor ashlar plinth', -30.5, 0.5, -0.12, 45.5, 1, 0.62, stoneShadow);
    box('Ground-floor ashlar east plinth', 30.5, 0.5, -0.12, 45.5, 1, 0.62, stoneShadow);
    box('Primary stone belt course', 0, 10.3, 0.32, 107.5, 0.62, 0.82, stone);
    box('Upper stone belt course', 0, 15.5, -0.05, 107.4, 0.46, 0.84, stone);
    box('Projecting entablature', 0, 11.0, 1.35, 53.5, 0.96, 3.8, stone);
    box('Entablature frieze', 0, 11.65, 1.53, 53.2, 0.44, 3.7, stoneShadow);
    box('Pediment and cornice', 0, 16.55, -0.06, 109, 0.65, 18.7, stone);
    box('Upper coping', 0, 17.07, 0.2, 108.5, 0.18, 18, stoneShadow);
    box('Central raised parapet', 0, 17.75, -0.3, 23, 1.25, 0.78, stone);
    box('Lower roof', 0, 16.5, -8.5, 106, 0.25, 17, iron);

    // The historic concourse is a reference-inspired playable stage, not a
    // transit operator's engineering plan. Ceiling, columns, floor rhythm and
    // public orientation are modeled; restricted/fare-paid areas stay closed.
    const hallFloor = new THREE.MeshStandardMaterial({ color: 0xd2bea0, roughness: 0.83 });
    const hallTrim = new THREE.MeshStandardMaterial({ color: 0xeee2cf, roughness: 0.8 });
    const hallDark = new THREE.MeshStandardMaterial({ color: 0x594f46, roughness: 0.8 });
    const warmLight = new THREE.MeshBasicMaterial({ color: 0xffe8b4 });
    box('Waterfront public concourse tiled floor', 0, 0.046, -6.6, 14.7, 0.09, 19.5, hallFloor);
    box('Waterfront concourse coffered ceiling', 0, 11.0, -7.8, 14.6, 0.26, 16.5, hallTrim);
    for (let z = -13; z <= -1; z += 3) {
      box('Concourse stone floor joint', 0, 0.098, z, 14, 0.008, 0.035, hallDark);
      box('Concourse ceiling cross beam', 0, 10.72, z, 14.4, 0.4, 0.35, hallTrim);
    }
    for (let x = -6; x <= 6; x += 3) {
      box('Concourse longitudinal tile joint', x, 0.099, -6.8, 0.03, 0.008, 18.6, hallDark);
    }
    for (const x of [-6.65, 6.65]) {
      box('Historic hall dado wall', x, 1.4, -7.5, 0.26, 2.8, 14.8, stone);
      box('Historic hall wall plaster', x, 6.55, -7.5, 0.28, 7.55, 14.8, hallTrim);
      for (const z of [-11.8, -6.8, -1.8]) {
        cylinder('Concourse pilaster', x, 5.15, z, 0.34, 0.38, 8.2, stone, 12);
        box('Concourse pilaster capital', x, 9.37, z, 0.85, 0.38, 0.85, stoneShadow);
      }
    }
    for (const z of [-10.5, -4.3]) {
      cylinder('Waterfront concourse pendant stem', 0, 10.0, z, 0.07, 0.07, 1.75, brass, 10);
      const lamp = new THREE.Mesh(new THREE.SphereGeometry(0.68, 14, 10), warmLight);
      lamp.name = 'Historic concourse pendant glow';
      lamp.position.set(0, 8.9, z);
      root.add(lamp);
    }
    // Reference-led interior detail: the real waiting hall reads as an airy
    // Beaux-Arts space, with ivory coffers, blush frieze, tall Ionic bays and
    // contrasting terrazzo borders, not a featureless grey concrete box.
    // Coordinates are still authored approximations, not measured floor plans.
    const terrazzo = new THREE.MeshStandardMaterial({ color: 0xb7b3a8, roughness: 0.9 });
    const rose = new THREE.MeshStandardMaterial({ color: 0xa77b81, roughness: 0.86 });
    const inset = new THREE.MeshStandardMaterial({ color: 0xc3c8c5, roughness: 0.87 });
    const mural = new THREE.MeshStandardMaterial({ color: 0x99715e, roughness: 0.82 });
    // Pink/grey geometric terrazzo banding flows toward the interior arches.
    for (const x of [-5.6, 5.6]) {
      box('Concourse terrazzo longitudinal border', x, 0.113, -6.7, 0.75, 0.018, 17.2, rose);
    }
    for (const z of [-12.7, -8.7, -4.7, -0.7]) {
      box('Concourse terrazzo cross border', 0, 0.114, z, 11.9, 0.018, 0.34, rose);
    }
    for (const x of [-3.4, 0, 3.4]) {
      for (const z of [-11.1, -7.1, -3.1]) {
        box('Concourse terrazzo inset', x, 0.114, z, 1.8, 0.015, 1.65, terrazzo);
      }
    }
    // A repeated, two-axis coffer grid and inset panels make the ceiling
    // readable even in low graphics mode without downloading image textures.
    for (const x of [-5.4, -3.6, -1.8, 0, 1.8, 3.6, 5.4]) {
      box('Waterfront ceiling longitudinal coffer rib', x, 10.65, -7.6,
        0.22, 0.48, 16.4, hallTrim);
    }
    for (let z = -14; z <= -1; z += 1.8) {
      box('Waterfront ceiling transverse coffer rib', 0, 10.63, z,
        13.1, 0.47, 0.22, hallTrim);
    }
    for (const x of [-4.5, -2.7, -0.9, 0.9, 2.7, 4.5]) {
      for (let z = -13.1; z <= -1.9; z += 1.8) {
        box('Waterfront recessed blue-grey coffer panel', x, 10.92, z,
          1.42, 0.012, 1.42, inset);
      }
    }
    // Full-height warm ivory hall columns, each with circular bases/capitals
    // and a terracotta-pink frieze overhead.
    for (const side of [-1, 1]) {
      const x = side * 5.72;
      box('Concourse rose frieze', side * 6.26, 9.1, -7.4,
        0.19, 1.28, 14.3, rose);
      box('Concourse cream crown molding', side * 6.29, 9.83, -7.4,
        0.3, 0.22, 14.5, hallTrim);
      for (const z of [-12.2, -8.1, -4.0, 0.1]) {
        cylinder('Waterfront hall Ionic column', x, 4.9, z,
          0.36, 0.44, 8.3, hallTrim, 16);
        cylinder('Waterfront hall column plinth', x, 0.4, z,
          0.64, 0.7, 0.75, stone, 16);
        box('Waterfront hall column capital', x, 9.11, z,
          1.05, 0.35, 1.05, hallTrim);
      }
      for (const z of [-10.3, -6.2, -2.1]) {
        box('Heritage mural inset panel', side * 6.4, 8.98, z,
          0.09, 0.72, 1.7, mural);
      }
    }
    // Arcade-inspired terminal wall: three recessed arch bays are a closer
    // visual match to the station photographs than the former black billboard.
    const archDark = new THREE.MeshStandardMaterial({ color: 0x514a44, roughness: 0.84 });
    for (const x of [-3.75, 0, 3.75]) {
      box('Concourse arch recessed opening', x, 3.15, -13.9,
        2.7, 6.3, 0.1, archDark);
      const arch = new THREE.Mesh(
        new THREE.TorusGeometry(1.38, 0.2, 8, 20, Math.PI),
        hallTrim
      );
      arch.position.set(x, 6.3, -13.73);
      arch.name = 'Historic concourse arcade arch';
      root.add(arch);
      for (const side of [-1, 1]) {
        cylinder('Arcade opening ivory pier', x + side * 1.48, 3.08, -13.71,
          0.19, 0.24, 6.15, hallTrim, 10);
      }
    }
    const heritageClock = new THREE.Mesh(
      new THREE.CylinderGeometry(0.56, 0.56, 0.08, 20), brass
    );
    heritageClock.name = 'Waterfront historic arcade clock surround';
    heritageClock.position.set(0, 8.75, -13.56);
    heritageClock.rotation.x = Math.PI / 2;
    root.add(heritageClock);
    sign('WATERFRONT', 0, 6.24, -13.69, 2.45, 0.34,
      '#f5f0e3', 'bold 62px Arial, sans-serif');
    root.userData.heritageHallReference = 'SRA renovation / user-supplied interior photos';
    root.userData.ornamentProcedural = true;

    // Retain terminal wayfinding as a small sign rather than a black wall.
    sign('WATERFRONT STATION', 0, 2.35, -13.68, 3.0, 0.4, '#f7e7c4', 'bold 70px Georgia, serif');
    sign('PUBLIC CONCOURSE', 0, 1.85, -13.67, 2.5, 0.28, '#e9d8b5', 'bold 65px Arial, sans-serif');
    // Leave a pedestrian-width central entrance gap in every foreground
    // surface. Glass panels at either side remain visible as open doors.
    root.userData.interiorApproximate = true;
    root.userData.publicHallOnly = true;

    // Parking-lot side of the station: glass doors and the exterior
    // pedestrian connector seen in the user-supplied Waterfront reference.
    // The interior is NOT modeled; E at the side access leads to the
    // walkable Cordova front entrance.
    box('Station East side access door recess', 53.12, 2.08, -4.0,
      0.16, 4.14, 3.58, iron);
    box('Station East side entrance glass double doors', 53.25, 1.88, -4.0,
      0.12, 3.6, 2.78, glass);
    [-5.55, -2.45].forEach(z => box('Station East entrance stone jamb',
      53.35, 2.04, z, 0.32, 4.08, 0.23, stone));
    box('Station East entrance stone header', 53.38, 4.12, -4.0,
      0.38, 0.35, 3.52, stone);
    box('Station East entrance small awning', 54.25, 4.4, -4.0,
      2.45, 0.28, 4.2, stoneShadow);
    box('Station East side entry door handle', 53.40, 1.82, -3.46,
      0.17, 0.36, 0.08, brass);
    const sideLabel = sign('STATION SIDE ACCESS', 53.54, 4.80, -4.0,
      3.7, 0.42, '#f5e5be', 'bold 60px Arial, sans-serif');
    sideLabel.rotation.y = Math.PI / 2;
    box('Station East to Cordova pedestrian connection', 58.5, 0.063, 1.0,
      12.2, 0.125, 14.2, pavement);
    box('Station east entry tactile threshold', 55.7, 0.13, -4.0,
      2.3, 0.055, 3.0, stoneShadow);

    // Street-level detail pass: readable arrival threshold and inset glass,
    // without closing the accessible walking corridor or inventing new doors.
    const warmGlass = new THREE.MeshStandardMaterial({
      color: 0x65818c, emissive: 0x6c4c25, emissiveIntensity: 0.16,
      roughness: 0.23, metalness: 0.19
    });
    box('Station East glazed door warm inner reflection', 53.17, 2.0, -4.0,
      0.045, 3.30, 2.43, warmGlass);
    [-4.79, -3.21].forEach(z => box('Station East double door vertical rail',
      53.49, 1.91, z, 0.08, 3.42, 0.045, brass));
    box('Station East canopy dark reveal', 54.4, 4.24, -4.0,
      2.2, 0.1, 4.0, iron);
    box('Station East access tactile paving edge', 56.9, 0.146, -4,
      0.12, 0.014, 3.25, brass);
    // Thin joint lines keep large flat concrete areas from reading as one slab.
    for (let i = 0; i < 4; i += 1) {
      box('Station East side path expansion joint ' + i,
        54.2 + i * 2.5, 0.132, 1.0,
        0.035, 0.008, 13.6, stoneShadow);
    }

    // Colonnade: pale Ionic columns and scroll-like capitals.
    const colX = [-24, -17.2, -10.4, -3.5, 3.5, 10.4, 17.2, 24];
    colX.forEach((x, index) => {
      cylinder('Ionic column ' + (index + 1), x, 5.85, 2.25, 0.47, 0.59, 9.5, stone, 18);
      cylinder('Column base', x, 0.92, 2.25, 0.73, 0.78, 0.35, stoneShadow, 18);
      cylinder('Column upper ring', x, 10.56, 2.25, 0.63, 0.56, 0.22, stone, 18);
      box('Ionic capital abacus', x, 10.86, 2.25, 1.62, 0.27, 1.1, stone);
      [-0.58, 0.58].forEach(dx => {
        const volute = new THREE.Mesh(new THREE.TorusGeometry(0.15, 0.063, 8, 12), stoneShadow);
        volute.position.set(x + dx, 10.66, 2.86);
        root.add(volute);
      });
    });

    // Incised historic railway name, above the central entrance.
    sign('CANADIAN PACIFIC RAILWAY', 0, 11.58, 3.47, 36, 0.95, '#75634a', '62px Georgia, serif');

    // Upper-storey sash windows / vertical brick rhythm.
    for (let x = -48; x <= 48; x += 4.8) {
      box('Upper window surround', x, 13.58, 0.63, 2.18, 3.24, 0.15, stone);
      box('Upper dark glazing', x, 13.57, 0.72, 1.72, 2.76, 0.06, glass);
      box('Vertical sash mullion', x, 13.56, 0.77, 0.11, 2.72, 0.11, iron);
      box('Upper sash rail', x, 13.3, 0.8, 1.76, 0.1, 0.11, stoneShadow);
      box('Window keystone', x, 15.23, 0.81, 0.37, 0.24, 0.18, stone);
    }
    [-48, -43, -38, -33, 33, 38, 43, 48].forEach(x => archWindow(x, 2.9, 4.3));

    // The Cordova Street doors sit behind modern black domed THE STATION awnings.
    [-7, 0, 7].forEach((x, i) => {
      // The middle portal is physically open; no opaque face spans the
      // entrance at x=0. Outer portals remain reference-led glazing.
      if (i !== 1) {
        box('Portal shadow', x, 2.25, 0.6, 3.45, 4.5, 0.13, iron);
        box('Door glazed pair', x, 1.93, 0.72, 2.55, 3.58, 0.12, glass);
      }
      [-0.68, 0.68].forEach(dx => {
        box('Entry gate glass panel', x + (i === 1 ? Math.sign(dx) * 1.52 : dx),
          1.91, 0.81, 1.14, 3.18, 0.08, glass);
        box('Entry gate stile', x + (i === 1 ? Math.sign(dx) * 1.02 : dx * 0.1), 1.9, 0.9, 0.08, 3.42, 0.12, iron);
        cylinder('Door handle', x + (i === 1 ? Math.sign(dx) * 1.1 : dx + (dx > 0 ? -0.29 : 0.29)), 1.62, 0.96, 0.025, 0.025, 0.28, brass, 8);
      });
      box('Portal transom', x, 3.72, 0.82, 3.2, 0.17, 0.22, stone);
      const dome = new THREE.Mesh(new THREE.SphereGeometry(1, 24, 12, 0, Math.PI * 2, 0, Math.PI / 2), canopyBlack);
      dome.scale.set(2.04, 0.86, 1.32);
      dome.position.set(x, 4.28, 1.99);
      dome.name = 'Black dome entrance canopy ' + (i + 1);
      root.add(dome);
      box('Canopy street-facing valance', x, 4.17, 3.05, 3.85, 0.46, 0.18, canopyBlack);
      sign('THE STATION', x, 4.19, 3.16, 3.68, 0.34, '#f7f7ec', 'bold 78px Arial, sans-serif');
    });

    // Decorative terra cotta panels and parapet balustrade.
    for (let x = -52; x <= 52; x += 2.6) {
      box('Balustrade post', x, 17.54, 0.12, 0.22, 0.65, 0.28, stone);
      if (Math.abs(x) > 28) box('Street-facing brick pilaster', x, 8.0, 0.25, 0.42, 13.9, 0.4, stoneShadow);
    }
    box('Balustrade coping', 0, 17.96, 0.13, 106.2, 0.16, 0.45, stone);

    // Entrance threshold / flush pedestrian paving. Physical transit fare gates
    // are *inside* the station, not freestanding fences across Cordova Street.
    box('Stone entrance landing', 0, 0.055, 3.9, 49, 0.11, 4.8, pavement);
    box('Cordova station forecourt', 0, 0.038, 14, 49, 0.075, 15.3, pavement);
    for (let x = -24; x <= 24; x += 4) {
      box('Forecourt paving joint', x, 0.079, 14, 0.038, 0.008, 15.1, stoneShadow);
    }
    for (let z = 8; z <= 21; z += 4) {
      box('Forecourt cross joint', 0, 0.079, z, 48.6, 0.008, 0.04, stoneShadow);
    }
    [-17.2, -3.5, 10.4].forEach(x => box('Landing stone seam', x, 0.12, 4.1, 0.06, 0.012, 4.1, stoneShadow));
    sign('601 WEST CORDOVA', 29.5, 5.15, 0.48, 6.5, 0.49, '#d4c8b2', 'bold 61px Georgia, serif');

    const blueSign = new THREE.MeshStandardMaterial({ color: 0x1256a2, roughness: 0.48 });
    box('Transit wayfinding sign', -28.5, 3.42, 4.1, 1.35, 0.83, 0.16, blueSign);
    sign('T', -28.5, 3.42, 4.24, 0.86, 0.69, '#ffffff', 'bold 90px Arial, sans-serif');
    cylinder('Transit sign pole', -28.5, 1.52, 4.05, 0.06, 0.06, 3.04, iron);

    // Angel of Victory / Winged Victory memorial, 601 West Cordova.
    // City Public Art Registry: artwork 24, east of the front entrance.
    // An original low-poly *interpretation*, not a scan or sculptural replica.
    // Preserve the recognizable iconography: patinated winged angel lifting a
    // fallen soldier, on a granite pedestal with commemorative bronze plaque.
    // The historical memorial is solemn, not an interactive NPC.
    const memorial = new THREE.Group();
    memorial.name = 'Angel of Victory / Coeur de Lion MacCarthy (interpretation)';
    memorial.position.set(40.5, 0, 11.0);
    root.add(memorial);
    const granite = new THREE.MeshStandardMaterial({ color: 0xa5a49f, roughness: 0.94 });
    const graniteEdge = new THREE.MeshStandardMaterial({ color: 0x858680, roughness: 0.95 });
    const patina = new THREE.MeshStandardMaterial({
      color: 0x47776e, roughness: 0.76, metalness: 0.48,
      side: THREE.DoubleSide
    });
    const patinaDeep = new THREE.MeshStandardMaterial({
      color: 0x335f59, roughness: 0.77, metalness: 0.52,
      side: THREE.DoubleSide
    });
    const plaque = new THREE.MeshStandardMaterial({ color: 0x554b37, roughness: 0.68, metalness: 0.58 });
    function monumentBox(name,x,y,z,w,h,d,material){
      const piece = new THREE.Mesh(new THREE.BoxGeometry(w,h,d),material);
      piece.name=name;piece.position.set(x,y,z);memorial.add(piece);
      return piece;
    }
    function monumentSphere(name,x,y,z,r,material,sx,sy,sz){
      const piece = new THREE.Mesh(new THREE.SphereGeometry(r,12,8),material);
      piece.name=name;piece.position.set(x,y,z);
      piece.scale.set(sx||1,sy||1,sz||1);memorial.add(piece);
      return piece;
    }
    function monumentLink(name,a,b,r,material){
      const dx=b[0]-a[0],dy=b[1]-a[1],dz=b[2]-a[2];
      const length=Math.hypot(dx,dy,dz);
      const mesh=new THREE.Mesh(new THREE.CylinderGeometry(r*0.78,r,length,10),material);
      mesh.name=name;
      mesh.position.set((a[0]+b[0])/2,(a[1]+b[1])/2,(a[2]+b[2])/2);
      // Cylinder's local +Y axis follows the limb via a real quaternion.
      // Older unit-test THREE doubles do not have Quaternion.setFromUnitVectors.
      if(mesh.quaternion&&typeof mesh.quaternion.setFromUnitVectors==='function'){
        mesh.quaternion.setFromUnitVectors(new THREE.Vector3(0,1,0),
          new THREE.Vector3(dx/length,dy/length,dz/length));
      }else{
        mesh.rotation.z=-Math.atan2(dx,dy);
      }
      memorial.add(mesh);return mesh;
    }
    monumentBox('Angel of Victory granite base',0,0.38,0,2.7,0.76,2.3,graniteEdge);
    monumentBox('Angel of Victory granite pedestal',0,1.32,0,2.16,1.23,1.79,granite);
    monumentBox('Angel of Victory pedestal upper molding',0,2.01,0,2.5,0.16,2.01,graniteEdge);
    monumentBox('Angel of Victory memorial plaque',0,1.38,0.906,1.55,0.82,0.05,plaque);
    const plaqueText = textTexture(THREE,'1914 - 1918  /  1939 - 1945',{
      font:'bold 45px Georgia, serif',color:'#dfd4b6'
    });
    const plaqueFront=new THREE.Mesh(new THREE.PlaneGeometry(1.43,0.22),
      new THREE.MeshBasicMaterial({map:plaqueText,transparent:true,side:THREE.DoubleSide}));
    plaqueFront.name='Angel of Victory war remembrance dates';
    plaqueFront.position.set(0,1.30,0.945);
    memorial.add(plaqueFront);
    // Bronze figures above the base; about seven feet of figurative sculpture.
    monumentSphere('Angel draped torso',0.05,3.22,-0.05,0.55,patina,0.76,1.42,0.63);
    monumentSphere('Angel head',0.02,4.16,-0.04,0.28,patina,0.82,1.07,0.85);
    monumentSphere('Angel robe folds',0.04,2.58,0.03,0.62,patinaDeep,0.85,0.9,0.8);
    for(const side of [-1,1]){
      const wing=new THREE.Shape();
      wing.moveTo(side*0.20,3.58);
      wing.lineTo(side*0.62,4.26);
      wing.lineTo(side*0.90,4.99);
      wing.lineTo(side*1.08,5.63);
      wing.lineTo(side*1.38,5.34);
      wing.lineTo(side*1.57,4.92);
      wing.lineTo(side*1.48,4.44);
      wing.lineTo(side*1.14,3.95);
      wing.lineTo(side*0.56,3.48);
      const feather=new THREE.Mesh(new THREE.ShapeGeometry(wing),patina);
      feather.name='Angel of Victory raised bronze wing';
      feather.position.z=-0.28;
      memorial.add(feather);
      for(let t=0;t<4;t++){
        monumentLink('Angel of Victory wing feather rib',
          [side*(0.30+t*0.07),3.65,-0.26],
          [side*(1.12+t*0.12),4.70+t*0.20,-0.26],0.026,patinaDeep);
      }
    }
    monumentLink('Angel raised arm',[0.30,3.82,0.02],[0.67,5.08,0.12],0.13,patina);
    monumentSphere('Angel raised hand',0.69,5.14,0.12,0.12,patina);
    monumentLink('Angel supporting arm',[-0.30,3.54,0.20],[-0.88,3.15,0.62],0.14,patina);
    // The carried figure is visibly distinct and horizontal, not a second
    // standing pedestrian or a generic winged trophy.
    monumentSphere('Fallen soldier supported torso',-0.62,3.20,0.58,0.34,
      patinaDeep,1.67,0.69,0.76);
    monumentSphere('Fallen soldier head',-1.30,3.03,0.64,0.22,patinaDeep);
    monumentLink('Fallen soldier lower legs',[-0.12,3.12,0.57],[0.66,2.80,0.60],0.12,patinaDeep);
    monumentLink('Fallen soldier hanging arm',[-0.85,3.06,0.77],[-0.98,2.42,0.92],0.105,patinaDeep);
    memorial.userData.reference = {
      title:'Angel of Victory',artist:'Coeur de Lion MacCarthy',
      source:'https://covapp.vancouver.ca/PublicArtRegistry/ArtworkDetail.aspx?ArtworkId=24',
      installed:'1921 (registry); unveiled 1922 (Veterans Affairs Canada)',
      placement:'east of station front entrance',
      fidelity:'original, approximate procedural interpretation'
    };
    root.userData.memorialIncluded = true;

    root.userData.reference = REFERENCE;
    root.userData.sideEntrance = sideEntranceFor(world);
    return root;
  }

  // Small authored crowd at the exterior doors, using the simulator's existing
  // skeletal sway/stride and waypoint patrol system (no extra AI calls).
  function commutersFor(world) {
    if (!world || !world.spawn) return [];
    const walks = [
      [[-22, 7], [-8, 7], [0, 8]],
      [[-4, 6.8], [5, 6.8], [13, 8.0]],
      [[20, 8.3], [8, 8.3], [-7, 9.2]],
      [[-16, 10.6], [-2, 10.6], [11, 10.6]],
      [[14, 5.8], [6, 6.2], [0, 7.5]],
      [[-9, 8.7], [-18, 8.7], [-25, 7.8]],
      [[-2, 4.9], [-8, 5.8], [-13, 7]],
      [[22, 6.3], [15, 7.5], [6, 7.5]]
    ];
    return walks.map((points, index) => {
      const path = points.map(([x, z]) => toWorld(world, x, z));
      const start = path[index % path.length];
      return {
        id: 'waterfront-commuter-' + index,
        role: 'commuter',
        behavior: 'route_walk',
        dialogId: 'pedestrian_route_tip',
        silhouetteScale: 0.86 + (index % 4) * 0.045,
        interactRadius: 1.8,
        idleSpot: start,
        patrol: path,
        yaw: (index % 2 ? 0.5 : -0.5)
      };
    });
  }

  window.GastownWaterfrontStation = Object.freeze({
    reference: REFERENCE,
    anchorFor,
    toWorld,
    arrivalYaw,
    arrivalPitch: () => 0.095,
    isWalkableForecourt,
    isWalkableInterior,
    sideEntranceFor,
    create,
    commutersFor
  });
})(window);

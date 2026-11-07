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
    const start = (world && world.spawn) || (world && world.route && world.route.centerline && world.route.centerline[0]) || {};
    const yaw = Number.isFinite(start.yaw) ? start.yaw + 0.1 : -0.77;
    const x = Number.isFinite(start.x) ? start.x : -16.48;
    const z = Number.isFinite(start.z) ? start.z : -57.65;
    const frontageDistance = 13;
    return {
      x: x + (Math.sin(yaw) * frontageDistance),
      z: z - (Math.cos(yaw) * frontageDistance),
      yaw
    };
  }

  function toWorld(world, x, z) {
    const anchor = anchorFor(world);
    return {
      x: anchor.x + (x * Math.cos(anchor.yaw)) - (z * Math.sin(anchor.yaw)),
      z: anchor.z + (x * Math.sin(anchor.yaw)) + (z * Math.cos(anchor.yaw))
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
      color: 0xffffff, map: brickTexture(THREE), roughness: 0.89, metalness: 0.01
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
    box('Red-brick CPR station body', 0, 8.1, -8.5, 68, 16.2, 17, brick);
    box('Projecting central entry block', 0, 7.2, -0.15, 53, 14.4, 1.2, brick);
    box('Ground-floor ashlar plinth', 0, 0.5, -0.12, 68.5, 1, 0.62, stoneShadow);
    box('Primary stone belt course', 0, 10.3, 0.32, 69.5, 0.62, 0.82, stone);
    box('Upper stone belt course', 0, 15.5, -0.05, 69.4, 0.46, 0.84, stone);
    box('Projecting entablature', 0, 11.0, 1.35, 53.5, 0.96, 3.8, stone);
    box('Entablature frieze', 0, 11.65, 1.53, 53.2, 0.44, 3.7, stoneShadow);
    box('Pediment and cornice', 0, 16.55, -0.06, 71, 0.65, 18.7, stone);
    box('Upper coping', 0, 17.07, 0.2, 70.5, 0.18, 18, stoneShadow);
    box('Central raised parapet', 0, 17.75, -0.3, 23, 1.25, 0.78, stone);
    box('Lower roof', 0, 16.5, -8.5, 68, 0.25, 17, iron);

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
    for (let x = -31.1; x <= 31.1; x += 4.2) {
      box('Upper window surround', x, 13.58, 0.63, 2.18, 3.24, 0.15, stone);
      box('Upper dark glazing', x, 13.57, 0.72, 1.72, 2.76, 0.06, glass);
      box('Vertical sash mullion', x, 13.56, 0.77, 0.11, 2.72, 0.11, iron);
      box('Upper sash rail', x, 13.3, 0.8, 1.76, 0.1, 0.11, stoneShadow);
      box('Window keystone', x, 15.23, 0.81, 0.37, 0.24, 0.18, stone);
    }
    [-31.1, -27.6, 27.6, 31.1].forEach(x => archWindow(x, 2.9, 4.3));

    // The Cordova Street doors sit behind modern black domed THE STATION awnings.
    [-7, 0, 7].forEach((x, i) => {
      box('Portal shadow', x, 2.25, 0.6, 3.45, 4.5, 0.13, iron);
      box('Door glazed pair', x, 1.93, 0.72, 2.55, 3.58, 0.12, glass);
      [-0.68, 0.68].forEach(dx => {
        box('Entry gate glass panel', x + dx, 1.91, 0.81, 1.14, 3.18, 0.08, glass);
        box('Entry gate stile', x + dx * 0.1, 1.9, 0.9, 0.08, 3.42, 0.12, iron);
        cylinder('Door handle', x + dx + (dx > 0 ? -0.29 : 0.29), 1.62, 0.96, 0.025, 0.025, 0.28, brass, 8);
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
    for (let x = -32; x <= 32; x += 2.4) {
      box('Balustrade post', x, 17.54, 0.12, 0.22, 0.65, 0.28, stone);
      if (Math.abs(x) > 24) box('Street-facing brick pilaster', x, 8.0, 0.25, 0.42, 13.9, 0.4, stoneShadow);
    }
    box('Balustrade coping', 0, 17.96, 0.13, 68.2, 0.16, 0.45, stone);

    // Entrance threshold / flush pedestrian paving. Physical transit fare gates
    // are *inside* the station, not freestanding fences across Cordova Street.
    box('Stone entrance landing', 0, 0.055, 3.9, 49, 0.11, 4.8, pavement);
    [-17.2, -3.5, 10.4].forEach(x => box('Landing stone seam', x, 0.12, 4.1, 0.06, 0.012, 4.1, stoneShadow));
    sign('601 WEST CORDOVA', 29.5, 5.15, 0.48, 6.5, 0.49, '#d4c8b2', 'bold 61px Georgia, serif');

    const blueSign = new THREE.MeshStandardMaterial({ color: 0x1256a2, roughness: 0.48 });
    box('Transit wayfinding sign', -28.5, 3.42, 4.1, 1.35, 0.83, 0.16, blueSign);
    sign('T', -28.5, 3.42, 4.24, 0.86, 0.69, '#ffffff', 'bold 90px Arial, sans-serif');
    cylinder('Transit sign pole', -28.5, 1.52, 4.05, 0.06, 0.06, 3.04, iron);

    root.userData.reference = REFERENCE;
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
    create,
    commutersFor
  });
})(window);

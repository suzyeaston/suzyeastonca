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
    // Connected public forecourt and eastern accessible-side walking approach.
    const mainFrontage = Math.abs(localX) <= 55 && localZ >= 4.7 && localZ <= 31;
    const lotSidePassage = localX >= 52.5 && localX <= 64
      && localZ >= -10 && localZ <= 9;
    return mainFrontage || lotSidePassage;
  }

  // A bounded, public-facing *interpretation* of the historic main hall.
  // Coordinates use the same transform as the station shell and are not
  // surveyed interior floor plans. The far end is deliberately closed.
  function localFor(world, point) {
    const a = anchorFor(world);
    const dx = point.x - a.x, dz = point.z - a.z;
    return {
      x: dx * Math.cos(a.yaw) - dz * Math.sin(a.yaw),
      z: dx * Math.sin(a.yaw) + dz * Math.cos(a.yaw)
    };
  }

  // A single open, staff-facing public transfer route through the rear arcade.
  // The modeled fare-paid threshold is narrative only; no actual fare is checked.
  function isWalkableInterior(world, point) {
    if (!world || !world.spawn || !point) return false;
    const { x, z } = localFor(world, point);
    const grandHall = Math.abs(x) <= 18.9 && z >= -33.6 && z <= 5.1;
    const eastPassage = x >= 18.3 && x <= 56.5 && z >= -6.15 && z <= -1.85;
    const westCoastConnector = Math.abs(x) <= 3.3 && z >= -41.1 && z < -31.5;
    // Photo reference: the LEFT rear arch is a separate short exit vestibule.
    // Its illustrated transition connects to the already-modeled Station East
    // doorway outside, rather than inventing a pedestrian rail-yard shortcut.
    const stationEastExitVestibule = x >= -15.5 && x <= -8.5
      && z >= -35.4 && z <= -31.5;
    // Branch toward WCE behind the left public passage, keeping the two
    // eastern Rogue shopfront arches free of misleading transit doorways.
    // This spur is illustrative: the true fare-gated circulation differs.
    const wceSpur = x >= -12.1 && x <= 0.5
      && z >= -37.4 && z <= -34.6;
    return grandHall || eastPassage || westCoastConnector ||
      stationEastExitVestibule || wceSpur;
  }

  function isWalkableWestCoastExpress(world, point) {
    if (!world || !world.spawn || !point) return false;
    const local = localFor(world, point);
    // Roofed boarding platform only: keep the yellow rail edge and all tracks
    // outside walkable bounds. The far side of the station rear wall is north.
    return point.x >= -75 && point.x <= 35
      && point.z >= -13.9 && point.z <= -5.75
      && local.z <= -35.0;
  }

  function surfaceYFor(world, point) {
    if (!world || !point) return 0;
    const local = localFor(world, point);
    // An eight-metre sloped internal passage joins the 0m heritage hall
    // to the platform at -1m without a camera or character teleport.
    if (Math.abs(local.x) <= 3.3 && local.z <= -33.1 && local.z >= -41.1) {
      return -Math.max(0, Math.min(1, (-local.z - 33.1) / 8));
    }
    if (isWalkableInterior(world, point)) return 0;
    return isWalkableWestCoastExpress(world, point) ? -1 : 0;
  }

  function parkingExitFor(world) {
    if (!world || !world.spawn) return null;
    const sideDoor = sideEntranceFor(world);
    return {
      threshold: toWorld(world, -12.0, -32.0),
      approach: toWorld(world, -12.0, -30.5),
      destination: sideDoor.approach,
      label: 'Station East surface parking exit (left rear arch)',
      approximateTransition: true
    };
  }

  function isAtParkingExit(world, point) {
    if (!world || !point) return false;
    const {x,z}=localFor(world,point);
    return x >= -15.3 && x <= -8.7 && z <= -34.0 && z >= -35.4;
  }

  function sideEntranceFor(world) {
    if (!world || !world.spawn) return null;
    return {
      threshold: toWorld(world, 55.1, -4.0),
      approach: toWorld(world, 60.1, -4.0),
      forecourt: toWorld(world, 23.0, 8.0),
      interior: toWorld(world, 18.0, -4.0),
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

    // Large, single-volume public hall. Authoring scale approximates the
    // *character* of the 1914 station and is not a measured interior plan.
    // The east wing is split at ground level so the side-entry corridor has
    // real visual and pedestrian continuity with the main concourse.
    box('Red-brick CPR station body', -38, 8.1, -17, 30, 16.2, 34, brick);
    for (const side of [-1, 1]) {
      if (side > 0) {
        box('CPR station east wing', 38, 8.1, -20.5, 30, 16.2, 27, brick);
        box('East wing corridor header', 38, 10.6, -3.75, 30, 11.2, 7.5, brick);
        box('East wing Cordova edge', 38, 3.0, -0.42, 30, 6, 0.85, brick);
      }
    }
    box('CPR station hall upper masonry', 0, 15.4, -17, 46, 2.2, 34, brick);
    // The rear wall has TWO genuine apertures: left station-side parking exit
    // and centre WCE transfer. Only the RIGHT bay is backed by masonry/retail.
    // Preserve all historic exterior brick; cut the left interior doorway.
    for (const [x,width] of [[-19.45,7.1],[-6.25,4.1],[13.65,18.7]]) {
      box('CPR station hall rear masonry', x, 7.8, -33.75, width, 15.6, 0.5, brick);
    }
    box('Waterfront rear arcade heritage lintel', 0, 13.55, -33.75, 8.6, 4.1, 0.5, brick);
    box('Waterfront station-side left arcade lintel', -12.0, 13.55, -33.75, 8.6, 4.1, 0.5, brick);
    box('Projecting central entry block', -15.5, 7.2, -0.15, 22, 14.4, 1.2, brick);
    box('Projecting east entry block', 15.5, 7.2, -0.15, 22, 14.4, 1.2, brick);
    box('Entry block crosshead above hall', 0, 9.5, -0.15, 9, 9.3, 1.2, brick);
    box('Ground-floor ashlar plinth', -38, 0.5, -0.12, 30, 1, 0.62, stoneShadow);
    box('Ground-floor ashlar east plinth', 38, 0.5, -0.12, 30, 1, 0.62, stoneShadow);
    box('Primary stone belt course', 0, 10.3, 0.32, 107.5, 0.62, 0.82, stone);
    box('Upper stone belt course', 0, 15.5, -0.05, 107.4, 0.46, 0.84, stone);
    // Keep the colonnade's shallow stone entablature above the capitals,
    // without letting a deep arcade-size slab read as a huge street awning.
    // The true roof stays over the historic masonry envelope.
    box('Projecting entablature', 0, 11.0, 1.35, 53.5, 0.96, 2.9, stone);
    box('Entablature frieze', 0, 11.65, 1.53, 53.2, 0.44, 2.8, stoneShadow);
    box('Pediment and cornice', 0, 16.55, -17.4, 108.5, 0.65, 33.2, stone);
    box('Upper coping', 0, 17.07, -17.4, 108.5, 0.18, 33.2, stoneShadow);
    box('Central raised parapet', 0, 17.75, -0.3, 23, 1.25, 0.78, stone);
    box('Lower roof', 0, 16.5, -17.4, 106, 0.25, 33.2, iron);

    // Inside the 1914 CPR concourse the visible finish is warm plaster,
    // pale coffered cornices, rose friezes and terrazzo, NOT bare red brick.
    // Basic materials give the hall a daylight floor even in low graphics.
    const hallFloor = new THREE.MeshStandardMaterial({ color: 0xe0d8cb, roughness: 0.8, emissive: 0x322c24, emissiveIntensity: 0.23 });
    const hallTrim = new THREE.MeshBasicMaterial({ color: 0xf4e9d6 });
    const hallDark = new THREE.MeshStandardMaterial({ color: 0x685f55, roughness: 0.8 });
    const terrazzo = new THREE.MeshStandardMaterial({ color: 0xd5cec0, roughness: 0.78 });
    const rose = new THREE.MeshBasicMaterial({ color: 0xb9909d });
    const inset = new THREE.MeshBasicMaterial({ color: 0xc9d7d8 });
    const mural = new THREE.MeshBasicMaterial({ color: 0xb08a61 });
    const archDark = new THREE.MeshBasicMaterial({ color: 0x7b5844 });
    const warmLight = new THREE.MeshBasicMaterial({ color: 0xffe8b4 });
    const yellow = new THREE.MeshStandardMaterial({ color: 0xf0c331, roughness: 0.65 });
    // A single contiguous 44 m wide, 34 m deep public floor, with the
    // central Cordova entrance aligned to the east pedestrian connector.
    box('Waterfront public concourse tiled floor', 0, 0.046, -16.0, 44.4, 0.09, 35.7, hallFloor);
    box('Waterfront east entrance passage floor', 39, 0.046, -4, 34.5, 0.09, 4.5, hallFloor);
    box('Waterfront concourse coffered ceiling', 0, 14.45, -17, 44.2, 0.26, 34, hallTrim);
    box('Waterfront east connector plaster ceiling', 39, 5.12, -4, 34.5, 0.22, 6, hallTrim);
    for (let z = -30; z <= -1; z += 4.2) {
      box('Concourse stone floor joint', 0, 0.098, z, 40, 0.008, 0.05, hallDark);
      box('Concourse ceiling cross beam', 0, 14.15, z, 42, 0.4, 0.37, hallTrim);
      box('Concourse terrazzo cross border', 0, 0.114, z, 38.4, 0.018, 0.34, rose);
    }
    for (const x of [-17.5, 17.5]) {
      box('Concourse terrazzo longitudinal border', x, 0.113, -16.4, 0.75, 0.018, 33.8, rose);
    }
    for (const x of [-12, -6, 0, 6, 12]) {
      box('Concourse longitudinal tile joint', x, 0.099, -16, 0.035, 0.008, 32.7, hallDark);
      for (let z = -28; z <= -4; z += 6) {
        box('Concourse terrazzo inset', x, 0.114, z, 3, 0.015, 2.9, terrazzo);
      }
    }
    // Broad 2-axis ceiling coffers, their recessed blue-grey panels visible
    // from the entrances. Wider bays allow the 14 m ceiling to read properly.
    for (let x = -20; x <= 20; x += 2.5) {
      box('Waterfront ceiling longitudinal coffer rib', x, 14.08, -17,
        0.22, 0.48, 33.5, hallTrim);
    }
    for (let z = -32; z <= -2; z += 2.5) {
      box('Waterfront ceiling transverse coffer rib', 0, 14.07, z,
        42, 0.47, 0.22, hallTrim);
    }
    for (let x = -18.75; x <= 18.75; x += 2.5) {
      for (let z = -30.75; z <= -3.25; z += 2.5) {
        box('Waterfront recessed blue-grey coffer panel', x, 14.33, z,
          2.0, 0.012, 2.0, inset);
      }
    }
    // Side wall sections are open to public circulation at the east entry.
    for (const side of [-1, 1]) {
      const x = side * 21.5;
      box('Concourse cream crown molding', side * 21.87, 13.25, -17,
        0.33, 0.29, 33, hallTrim);
      box('Concourse rose frieze', side * 21.83, 12.2, -17,
        0.18, 1.5, 33.2, rose);
      for (let z = -30; z <= -12; z += 6) {
        box('Historic hall dado wall', x, 1.4, z, 0.3, 2.8, 5.3, stone);
        box('Historic hall wall plaster', x, 7.0, z, 0.3, 8.4, 5.3, hallTrim);
        box('Heritage mural inset panel', side * 21.67, 12.2, z,
          0.11, 0.76, 2.1, mural);
      }
      // The side-entry bay, z=-4, stays completely open at floor level.
      for (let z = -29; z <= 1; z += 6) {
        cylinder('Waterfront hall Ionic column', side * 19.9, 6.65, z,
          0.48, 0.58, 11.6, hallTrim, 16);
        cylinder('Waterfront hall column plinth', side * 19.9, 0.44, z,
          0.71, 0.75, 0.84, stone, 16);
        box('Waterfront hall column capital', side * 19.9, 12.55, z,
          1.2, 0.39, 1.2, hallTrim);
      }
    }
    // Heritage interior finishes. These light-colored veneers live entirely
    // inside the masonry shell; the public exterior keeps its original brick.
    // Segmented bays leave the existing east-side circulation opening usable.
    const plaster = new THREE.MeshBasicMaterial({ color: 0xe8decf });
    const upperIvory = new THREE.MeshBasicMaterial({ color: 0xf5eddd });
    const roseShadow = new THREE.MeshBasicMaterial({ color: 0xa77e91 });
    const gilding = new THREE.MeshBasicMaterial({ color: 0xc7a16d });
    const daylight = new THREE.MeshBasicMaterial({ color: 0xd9e8e8 });
    const daylightShade = new THREE.MeshBasicMaterial({ color: 0xb6d2d6 });
    const timber = new THREE.MeshStandardMaterial({ color: 0x604332, roughness: 0.72 });
    const interiorGlass = new THREE.MeshStandardMaterial({
      color: 0x7b9b9d, roughness: 0.16, metalness: 0.17,
      transparent: true, opacity: 0.56, depthWrite: false
    });

    // Two longitudinal rows of classical bays. Toward Cordova the east
    // station-side arcade reads on the LEFT when the camera faces the street.
    for (const side of [-1,1]) {
      const surfaceX = side*21.19;
      for (const z of [-29,-23,-17,-11]) {
        box('Waterfront ivory interior wall bay',surfaceX,5.7,z,
          0.17,11.4,5.9,plaster);
        box('Waterfront rose upper heritage frieze bay',side*21.04,12.20,z,
          0.12,1.55,5.6,rose);
        box('Waterfront ivory cornice below pink frieze',side*20.94,11.33,z,
          0.3,0.26,5.8,upperIvory);
        box('Waterfront detailed upper crown molding',side*20.94,13.14,z,
          0.32,0.34,5.85,upperIvory);
        box('Heritage gilded mural picture frame',side*20.95,12.27,z,
          0.18,0.91,3.7,gilding);
        box('Heritage landscape mural inset',side*20.83,12.27,z,
          0.08,0.70,3.40,mural);
        // Originally-painted Canadian landscapes: small abstract relief
        // silhouettes, not an unlicensed reproduction of Langford's work.
        for (const dz of [-0.9,0.15,1.1]) {
          box('Canadian landscape mural relief',side*20.75,12.07,z+dz,
            0.05,0.11,0.82,upperIvory);
        }
      }
      // Daylit tall sash windows on the west side of the photographed hall.
      // Lit panes are decorative; no corridor or wall bounds are opened here.
      if (side<0) {
        for (const z of [-28,-22,-16,-10,-4]) {
          box('Waterfront tall station window daylight',-20.98,6.4,z,
            0.09,6.2,3.5,daylight);
          for (const dz of [-1.78,0,1.78]) {
            box('Waterfront sash window white mullion',-20.86,6.4,z+dz,
              0.11,6.32,0.12,upperIvory);
          }
          for (const y of [4.3,6.6,8.75]) {
            box('Waterfront sash window glazing bar',-20.85,y,z,
              0.11,0.10,3.58,upperIvory);
          }
          box('Waterfront tall window lower shadow',-20.76,2.9,z,
            0.11,0.22,3.8,roseShadow);
        }
      } else {
        // The real east concourse has glazed retail frontage, not a huge
        // uninterrupted masonry wall. Keep displays behind the column line.
        for (const [z,shop] of [[-27,'ROGUE'],[-19,'STATION NEWS'],[-11,'STARBUCKS']]) {
          box('Waterfront shopfront wooden arcade',20.98,4.6,z,
            0.22,6.65,4.30,upperIvory);
          box('Waterfront shopfront glass doors',20.78,3.3,z,
            0.10,4.50,3.35,interiorGlass);
          for(const dz of [-1.7,0,1.7]){
            box('Waterfront retail mullion',20.71,3.2,z+dz,
              0.13,4.65,0.13,timber);
          }
          box('Waterfront retail gold-lit fascia',20.63,6.15,z,
            0.18,0.69,3.90,timber);
          const shopText=sign(shop,20.52,6.15,z,3.67,0.34,
            '#fff0ce','bold 62px Arial, sans-serif');
          shopText.rotation.y=-Math.PI/2;
          for (const y of [8.05,9.2]) {
            box('Waterfront upper retail transom glazing',20.87,y,z,
              0.10,0.88,3.4,daylightShade);
            box('Waterfront upper retail sash rail',20.68,y+0.43,z,
              0.11,0.10,3.5,upperIvory);
          }
        }
      }
    }
    // The deep rear of the reference concourse is a classical THREE-ARCH
    // arcade with pink frieze, clock and adjoining retail: no brick bunker.
    for (const x of [-18.2,-6.2,6.2,18.2]) {
      box('Waterfront rear plaster pier',x,5.4,-33.18,
        3.6,10.8,0.13,plaster);
    }
    box('Waterfront rear rose clock frieze',0,12.28,-33.15,
      43.5,1.45,0.12,rose);
    box('Waterfront rear ivory molded crown',0,13.18,-33.04,
      43.5,0.34,0.35,upperIvory);
    box('Waterfront rear ivory picture rail',0,11.25,-33.03,
      43.5,0.33,0.30,upperIvory);
    for (const x of [-15.6,-8.2,8.2,15.6]) {
      box('Waterfront rear golden heritage panel frame',x,12.32,-32.96,
        4.85,0.93,0.09,gilding);
      box('Waterfront rear landscape painting',x,12.32,-32.84,
        4.55,0.72,0.07,mural);
    }
    // A recognizable illuminated heritage clock, centered above the arches.
    const clockFace=new THREE.Mesh(new THREE.CylinderGeometry(0.58,0.58,0.07,24),upperIvory);
    clockFace.name='Waterfront gold-rimmed heritage clock face';
    clockFace.rotation.x=Math.PI/2;
    clockFace.position.set(0,12.12,-32.84);
    root.add(clockFace);
    const hour=box('Waterfront heritage clock hour hand',
      0.13,12.24,-32.73,0.06,0.36,0.025,hallDark);
    hour.rotation.z=0.65;
    const minute=box('Waterfront heritage clock minute hand',
      -0.12,12.25,-32.72,0.04,0.49,0.025,hallDark);
    minute.rotation.z=-0.38;
    // Rogue is ONE restaurant occupying the TWO RIGHT arches, as clearly
    // shown in the user's photo and the 2018 east-facing historical view.
    // The left arch is a distinct bright passage, not a second restaurant.
    for (const rogueX of [0,12]) {
      box('Waterfront Rogue illuminated arcade window',rogueX,4.38,-33.03,
        6.8,8.65,0.06,timber);
      box('Waterfront Rogue amber restaurant interior',rogueX,4.4,-32.94,
        6.1,7.75,0.055,gilding);
      sign('ROGUE',rogueX,5.4,-32.80,5.6,0.88,'#422b20',
        'bold 90px Georgia, serif');
      box('Waterfront Rogue dark timber base',rogueX,0.9,-32.82,
        6.6,1.65,0.19,timber);
    }

    // A real opening at x=-12 leads to a luminous short vestibule. The
    // simulator transitions at its far threshold to the mapped Station East
    // side door. No black wall or inaccessible fake doorway.
    const exitWall=new THREE.MeshBasicMaterial({color:0xece6d9});
    const exitLight=new THREE.MeshBasicMaterial({color:0xf5f0e5});
    const exitSky=new THREE.MeshBasicMaterial({color:0xcbdfe4});
    box('Station East left-arch exit vestibule floor',-12,0.08,-34.35,
      6.8,0.14,3.0,terrazzo);
    for (const x of [-15.4,-8.6]) {
      box('Station East left-arch ivory vestibule side wall',x,3.6,-34.4,
        0.13,7.2,3.0,exitWall);
      box('Station East left-arch timber handrail',x+(x<0?0.12:-0.12),1.0,-34.4,
        0.08,0.14,2.5,timber);
    }
    box('Station East left-arch vestibule ceiling',-12,7.55,-34.4,
      7.1,0.17,3.0,exitLight);
    // Lighted transverse circulation to the existing WCE platform path.
    // This is a film-set connector, not a claim about the actual public route.
    box('WCE separate connecting spur terrazzo floor',-5.9,0.09,-35.95,
      12.4,0.14,2.95,terrazzo);
    box('WCE separate connecting spur ceiling',-5.9,5.5,-35.95,
      12.4,0.19,2.95,exitLight);
    box('WCE separate connecting spur back wall',-5.9,2.7,-37.55,
      12.4,5.3,0.12,exitWall);
    const wceSpurSign=sign('WCE TRANSFER',-5.6,4.8,-35.51,
      4.5,0.38,'#eef6f7','bold 58px Arial, sans-serif');
    wceSpurSign.rotation.y=Math.PI;
    box('Station East left-arch exit daylight beyond threshold',-12,3.8,-35.82,
      6.65,7.3,0.06,exitSky);
    box('Station East left-arch exterior door frame',-12,3.7,-35.72,
      6.8,0.18,0.19,upperIvory);
    const parkingWayfinding=sign('EXIT  /  STATION EAST PARKING',-12,8.48,-32.79,
      6.95,0.50,'#fff7df','bold 57px Arial, sans-serif');
    parkingWayfinding.name='Station East parking exit left-arch wayfinding';
    const cordovaDirection=sign('CORDOVA MAIN EXIT',0.0,4.71,0.0,
      5.3,0.42,'#f6f1e5','bold 62px Arial, sans-serif');
    cordovaDirection.rotation.y=Math.PI;
    root.userData.heritageInteriorPhotoPass2026=true;
    root.userData.stationEastLotWayfinding='left rear arch next to Rogue on the right';
    root.userData.parkingExitInteractiveTransition=true;
    for (const z of [-24.4, -13.2, -2.0]) {
      cylinder('Waterfront concourse pendant stem', 0, 13.05, z,
        0.075, 0.075, 2.0, brass, 10);
      const lamp = new THREE.Mesh(new THREE.SphereGeometry(0.85, 14, 10), warmLight);
      lamp.name = 'Historic concourse pendant glow';
      lamp.position.set(0, 11.7, z);
      root.add(lamp);
    }
    // Only the leftmost arch is a public walkway. The two arches to the
    // right are glazed Rogue dining bays, never a central WCE portal.
    for (const x of [-12.0, 0, 12.0]) {
      if (x !== -12) box('Concourse arch recessed opening', x, 4.65, -33.36,
        7.1, 9.3, 0.1, archDark);
      const arch = new THREE.Mesh(
        new THREE.TorusGeometry(3.6, 0.28, 8, 24, Math.PI),
        hallTrim
      );
      arch.name = 'Historic concourse arcade arch';
      arch.position.set(x, 9.3, -33.1);
      root.add(arch);
      for (const edge of [-1, 1]) {
        cylinder('Arcade opening ivory pier', x + edge * 3.7, 4.55, -33.09,
          0.27, 0.32, 9.1, hallTrim, 12);
      }
    }
    const heritageClock = new THREE.Mesh(
      new THREE.CylinderGeometry(0.68, 0.68, 0.09, 24), brass
    );
    heritageClock.name = 'Waterfront historic arcade clock surround';
    heritageClock.position.set(0, 12.1, -33.07);
    heritageClock.rotation.x = Math.PI / 2;
    root.add(heritageClock);
    // Modern transit wayfinding lives beneath the restored CPR arches.
    // Contrasting navy/cream panels stay above head height on the public side.
    const transitNavy = new THREE.MeshStandardMaterial({ color: 0x193849, roughness: 0.72 });
    const transitBlue = new THREE.MeshStandardMaterial({ color: 0x266a9d, roughness: 0.66 });
    const fareBlue = new THREE.MeshStandardMaterial({ color: 0x2c7b86, roughness: 0.7 });
    const displayGlass = new THREE.MeshStandardMaterial({ color: 0x152938, roughness: 0.16, metalness: 0.15 });
    for (const [x, label, tone] of [
      [-12, 'STATION EAST / WCE', transitBlue]
    ]) {
      box('Waterfront blue directional fascia', x, 8.85, -33.0, 7.4, 1.0, 0.14, tone);
      sign(label, x, 8.87, -32.89, 7.0, 0.55, '#ffffff', 'bold 68px Arial, sans-serif');
    }
    sign('WATERFRONT STATION', 0, 12.0, -33.06, 8.0, 0.47,
      '#ead8bd', 'bold 66px Georgia, serif');

    // The hall in the photographs has visible ticketing and circulation,
    // not a black wall. These tactile machines flank a broad clear aisle.
    for (const x of [-15, -11, 11, 15]) {
      box('Waterfront fare gate narrow housing', x, 0.60, -27.6,
        0.47, 1.2, 1.45, stoneShadow);
      box('Waterfront Compass green fare reader', x, 1.22, -27.25,
        0.33, 0.18, 0.36, fareBlue);
      box('Waterfront fare gate glass wing', x + (x < 0 ? 0.52 : -0.52),
        0.86, -27.4, 0.7, 0.85, 0.08, glass);
    }
    for (const x of [-16.8, 16.8]) {
      box('Waterfront ticket machine enclosure', x, 1.25, -20.7,
        1.48, 2.48, 0.9, transitNavy);
      box('Waterfront ticket machine display', x, 1.71, -20.16,
        1.05, 0.8, 0.08, displayGlass);
      box('Waterfront ticket machine green reader', x, 0.98, -20.15,
        0.44, 0.18, 0.09, fareBlue);
    }

    // Historic skylight impression: luminous glazing between the coffers,
    // still entirely original procedural materials (no Street View texture).
    const clerestory = new THREE.MeshBasicMaterial({ color: 0xdce8e7 });
    for (const x of [-13.75, 13.75]) {
      for (const z of [-27.5, -17.5, -7.5]) {
        box('Waterfront luminous upper concourse glazing', x, 14.0, z,
          6.8, 0.022, 3.35, clerestory);
        for (const dx of [-2.35, 0, 2.35]) {
          box('Waterfront skylight mullion', x + dx, 13.98, z,
            0.10, 0.06, 3.35, hallTrim);
        }
      }
    }
    // Far-left/right retail fascia provides storefront scale and warm color.
    for (const x of [-18.15, 18.15]) {
      box('Waterfront concourse retail display', x, 2.45, -12,
        1.75, 3.15, 5.5, displayGlass);
      box('Waterfront concourse retail timber header', x, 4.28, -12,
        1.85, 0.48, 5.7, brass);
    }

    // WCE connection: visibly pass through the centre arch to an illuminated
    // sloped passage and onto the existing separate commuter platform. The
    // player can walk it in both directions; no ungrounded rail-yard shortcut.
    const transferTile = new THREE.MeshStandardMaterial({ color: 0xc9d2d1, roughness: 0.85 });
    const transferRail = new THREE.MeshStandardMaterial({ color: 0xb0babe, roughness: 0.36, metalness: 0.5 });
    const transferGlass = new THREE.MeshBasicMaterial({ color: 0xdce9ef, transparent: true, opacity: 0.45, side: THREE.DoubleSide });
    const ramp = box('West Coast Express concourse-to-platform continuous ramp',
      0, -0.50, -37.1, 6.2, 0.17, 8.5, transferTile);
    ramp.rotation.x = -Math.atan2(1, 8);
    for (const x of [-3.25, 3.25]) {
      box('WCE access glazed corridor wall', x, 2.45, -37.25,
        0.16, 4.9, 8.3, transferGlass);
      box('WCE passage handrail', x * 0.92, 0.77, -37.0,
        0.08, 0.1, 8.2, transferRail).rotation.x = -Math.atan2(1, 8);
      for (const z of [-33.5, -35.5, -37.5, -39.5, -40.9]) {
        box('WCE sloped passage handrail post', x * 0.92,
          0.7 - Math.max(0, Math.min(1, (-z - 33.1) / 8)), z,
          0.095, 1.4, 0.095, transferRail);
      }
    }
    box('West Coast Express white overhead passage', 0, 4.82, -37.1,
      7.1, 0.27, 8.5, hallTrim);
    for (const z of [-34.8, -37.0, -39.2]) {
      box('West Coast Express passage strip light', 0, 4.65, z,
        4.4, 0.05, 0.19, clerestory);
    }
    box('West Coast Express fare-paid threshold canopy', 0, 5.17, -37.5,
      6.7, 0.34, 0.65, yellow);
    sign('WEST COAST EXPRESS / PLATFORMS', 0, 4.77, -37.11,
      6.4, 0.43, '#ffffff', 'bold 61px Arial, sans-serif');
    root.userData.westCoastExpressConnection = 'illustrative left passage spur and ramp to platform';
    root.userData.realStationExitUnverified = true;
    root.userData.rogueEastWingBays = [0,12];
    root.userData.leftArcadeSeparateFromRogue = true;
    root.userData.wceConnectionSurveyed = false;
    root.userData.interiorApproximate = true;
    root.userData.publicHallOnly = true;
    root.userData.heritageHallReference = 'SRA renovation / user-supplied interior photos';
    root.userData.ornamentProcedural = true;
    root.userData.interiorHallDimensions = { width: 44.4, depth: 35.7, ceiling: 14.45 };
    root.userData.eastEntryConnectsToHall = true;

    // Reference-led glazed arched side access, not a windowless red-brick
    // tunnel. The real Station East portal has tall dark green frames and an
    // open passage behind it. Keep the authored walking centreline unobstructed.
    const entranceIvory = new THREE.MeshStandardMaterial({color:0xe4d8c6,roughness:0.87});
    const entranceGreen = new THREE.MeshStandardMaterial({color:0x274746,roughness:0.54,metalness:0.24});
    const entranceGlass = new THREE.MeshStandardMaterial({
      color:0x87aeb4,transparent:true,opacity:0.29,roughness:0.18,
      metalness:0.16,side:THREE.DoubleSide,depthWrite:false
    });
    // Both corridor elevations face cream plaster and pilasters, so they
    // no longer read as the bare brick walls of a subterranean tunnel.
    for (const side of [-1, 1]) {
      const z=-4+side*2.98;
      box('Station East arched arcade cream inner wall',37.4,2.4,z,
        28.1,4.8,0.16,entranceIvory);
      box('Station East arcade stone dado',37.4,0.88,z+(-side*0.10),
        28.1,1.65,0.18,stone);
      for (const x of [27.2,35.8,44.4,51.0]) {
        box('Station East arcade cream pilaster',x,2.4,z-side*0.18,
          0.45,4.8,0.38,entranceIvory);
        box('Station East arcade Ionic-style capital',x,4.8,z-side*0.2,
          0.78,0.26,0.52,stone);
      }
    }
    // Repeated arched ceiling frames lead toward a glazed daylight-filled
    // vestibule. Unlike the previous rectangular brick corridor, these
    // arches read as public heritage architecture from either end.
    for (const x of [26.0,35.2,44.4,52.9]) {
      const arcadeArch = new THREE.Mesh(
        new THREE.TorusGeometry(2.02,0.18,8,24,Math.PI),entranceIvory);
      arcadeArch.name='Waterfront Station East cream passage arch';
      arcadeArch.position.set(x,4.65,-4);
      arcadeArch.rotation.y=Math.PI/2;
      root.add(arcadeArch);
      for (const z of [-6.05,-1.95]) {
        box('Station East arcade jamb',x,2.3,z,0.25,4.6,0.34,entranceIvory);
      }
    }
    // Green-framed glass transom at the outer portal, matching the look of
    // the photographed Station East side entrance rather than a blank wall.
    for (const z of [-5.46,-4.5,-3.5,-2.54]) {
      box('Station East green transom mullion',53.44,5.88,z,
        0.12,2.00,0.075,entranceGreen);
    }
    box('Station East green transom sill',53.45,4.84,-4,
      0.14,0.18,3.90,entranceGreen);
    box('Station East green transom lintel',53.45,6.92,-4,
      0.14,0.18,3.90,entranceGreen);
    box('Station East entrance glazed transom',53.4,5.88,-4,
      0.052,1.90,3.80,entranceGlass);
    for (const z of [-5.54,-2.46]) {
      box('Station East deep green door stile',53.51,2.04,z,
        0.16,3.87,0.12,entranceGreen);
    }
    root.userData.sideEntranceStyle='cream arched arcade, dark green framed glazing';
    // East Station / parking-lot entrance shares the historic hall volume.
    // This is a plausible visual public connector, not a surveyed doorway.
    // No opaque pane spans the passage: visitors may walk in from the lot.
    const sideDoorGlass = new THREE.MeshStandardMaterial({
      color:0x37615e,roughness:0.19,metalness:0.12,
      transparent:true,opacity:0.66,side:THREE.DoubleSide
    });
    for (const dz of [-1.53, 1.53]) {
      box('Station East open leaf glass door', 53.3, 1.91, -4.0 + dz,
        0.10, 3.5, 0.54, sideDoorGlass);
      box('Station East entrance stone jamb', 53.36, 2.04,
        -4.0 + dz * 1.15, 0.3, 4.08, 0.26, stone);
    }
    box('Station East entrance stone header', 53.38, 4.12, -4.0,
      0.38, 0.35, 4.5, stone);
    box('Station East entrance small awning', 54.25, 4.4, -4.0,
      2.45, 0.28, 4.8, stoneShadow);
    const sideLabel = sign('STATION SIDE ACCESS', 53.54, 4.82, -4.0,
      3.7, 0.42, '#f5e5be', 'bold 60px Arial, sans-serif');
    sideLabel.rotation.y = Math.PI / 2;
    box('Station East to Cordova pedestrian connection', 58.5, 0.063, 1.0,
      12.2, 0.125, 14.2, pavement);
    box('Waterfront east entry tactile threshold', 55.7, 0.13, -4.0,
      2.3, 0.055, 3.0, stoneShadow);
    box('Station East access tactile paving edge', 56.9, 0.146, -4.0,
      0.12, 0.014, 3.25, brass);
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
    sign('CANADIAN PACIFIC RAILWAY', 0, 11.58, 2.94, 36, 0.95, '#75634a', '62px Georgia, serif');

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
    // The central 49 m slab left the west/east station wings over an empty
    // background after the lot/rail grade pass. Fill ONLY the public frontage
    // close to the building; do not raise the road or bridge the lower alley.
    for (const x of [-39.75, 39.75]) {
      box('Station wing public forecourt paving', x, 0.038, 11.4,
        30.5, 0.075, 13.2, pavement);
    }
    root.userData.stationWingPavingAtStreetGrade = true;
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

  // Interior crowds use the same deterministic NPC system and idle sway as
  // outdoor commuters, but remain on connected, public pedestrian pathways.
  function interiorCommutersFor(world) {
    if (!world || !world.spawn) return [];
    const paths = [
      [[-7,-5],[-7,-13],[-6,-22],[-8,-27]],
      [[8,-28],[7,-20],[8,-11],[7,-4]],
      [[-3,-26],[-2,-18],[0,-10],[1,-5]],
      [[4,-6],[5,-12],[4,-20],[3,-26]],
      [[-11,-14],[-9,-19],[-10,-24],[-12,-19]],
      [[12,-24],[13,-17],[11,-10],[10,-6]],
      [[-5,-17],[0,-15],[6,-17],[2,-21]],
      [[-9,-9],[-5,-8],[-2,-11],[-6,-13]],
      [[9,-10],[6,-8],[4,-13],[8,-15]],
      [[-2,-29],[-2,-31],[0,-32],[2,-31]]
    ];
    return paths.map((route,index) => {
      const patrol=route.map(([x,z])=>toWorld(world,x,z));
      return {
        id:'waterfront-indoor-commuter-'+index,
        role:'commuter', behavior:'route_walk',
        dialogId:'pedestrian_route_tip',
        silhouetteScale:0.84+(index%4)*0.045,
        interactRadius:1.75, idleSpot:patrol[0], patrol,
        yaw:index%2?0.25:-0.25
      };
    });
  }

  function platformCommutersFor(world) {
    if (!world || !world.spawn) return [];
    // The original illustrative long platform overlaps the rear heritage
    // footprint at its western end; use only the clear east boarding apron.
    const patrols = [
      [[8,-9.3],[12,-9.5],[18,-9.1]],
      [[23,-10.2],[29,-10.0],[33,-10.3]],
      [[4,-11.1],[7,-10.7],[11,-10.9]]
    ];
    return patrols.map((route,index)=>{
      const patrol=route.map(([x,z])=>({x,z}));
      return {
        id:'waterfront-wce-platform-commuter-'+index,
        role:'commuter',behavior:'route_walk',
        dialogId:'pedestrian_route_tip',
        silhouetteScale:0.87+index*0.03,interactRadius:1.8,
        idleSpot:patrol[0],patrol,yaw:0.4
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
    parkingExitFor,
    isAtParkingExit,
    isWalkableWestCoastExpress,
    surfaceYFor,
    sideEntranceFor,
    create,
    commutersFor,
    interiorCommutersFor,
    platformCommutersFor
  });
})(window);

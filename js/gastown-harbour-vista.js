/*
 * Gastown harbour backdrop: original procedural, reference-led geometry.
 *
 * Real map anchors, not a photogrammetric survey:
 *  - Canada Place: 999 Canada Place, 49.28821948, -123.11216186
 *    https://placesthatmatter.ca/location/canada-place/
 *  - The terminal extends northeast from the shoreline and can berth cruise
 *    vessels on its east/west/north sides (Port of Vancouver/Canadian Sailing Directions).
 *  - The North Shore skyline is a decorative *compressed* panorama with
 *    recognizable west-to-east massing (Cypress / Grouse / Seymour).
 *  - Docked ship is representative, NOT a claim about live ship schedules.
 *
 * Game coordinate convention: +X east, +Z south, -Z north, +Y up.
 * Scenic sea and mountains sit outside the playable street corridor.
 * Do not place downloadable source/reference photographs in the site bundle.
 */
(function (window) {
  'use strict';

  const CANADA_PLACE_COORD = Object.freeze({ lat: 49.2882194814286, lon: -123.11216185791017 });
  const R = 6371008.8, DEG = Math.PI / 180;
  const REFS = Object.freeze({
    canadaPlace: 'https://placesthatmatter.ca/location/canada-place/',
    publicView: 'https://guidelines.vancouver.ca/policy-statement-central-waterfront-port-lands.pdf',
    mountains: 'https://mineralsed.ca/site/assets/files/3451/vancouvergeotourguide2009_me.pdf',
    cruise: 'https://www.portvancouver.com/'
  });

  function projectScene(lon, lat, origin) {
    return {
      x: (lon - origin.lon) * R * DEG * Math.cos(origin.lat * DEG),
      z: -(lat - origin.lat) * R * DEG
    };
  }
  function positionFor(world) {
    const origin = world && world.meta && world.meta.gis && world.meta.gis.origin;
    if (!origin || !Number.isFinite(origin.lat) || !Number.isFinite(origin.lon)) return null;
    return projectScene(CANADA_PLACE_COORD.lon, CANADA_PLACE_COORD.lat, origin);
  }
  function box(THREE, parent, name, x, y, z, w, h, d, mat) {
    const mesh = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
    mesh.name = name;
    mesh.position.set(x, y, z);
    mesh.receiveShadow = false;
    mesh.castShadow = false;
    parent.add(mesh);
    return mesh;
  }
  function cylinder(THREE, parent, name, x, y, z, radius, height, mat, sides) {
    const mesh = new THREE.Mesh(new THREE.CylinderGeometry(radius, radius, height, sides || 12), mat);
    mesh.position.set(x, y, z);
    mesh.name = name;
    parent.add(mesh);
    return mesh;
  }
  function standard(THREE, color, roughness, metalness) {
    return new THREE.MeshStandardMaterial({
      color, roughness: roughness || 0.8, metalness: metalness || 0, fog: false
    });
  }
  function textSign(THREE, parent, text, x, y, z, w, h) {
    if (!window.document || !window.document.createElement) return;
    const canvas = window.document.createElement('canvas');
    canvas.width = 1024; canvas.height = 128;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.clearRect(0, 0, 1024, 128);
    ctx.fillStyle = '#193341';
    ctx.fillRect(0, 0, 1024, 128);
    ctx.fillStyle = '#f6f4e8';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.font = 'bold 74px Arial, sans-serif';
    ctx.fillText(text, 512, 64, 990);
    const tex = new THREE.CanvasTexture(canvas);
    tex.colorSpace = THREE.SRGBColorSpace;
    const face = new THREE.Mesh(
      new THREE.PlaneGeometry(w, h),
      new THREE.MeshBasicMaterial({ map: tex, side: THREE.DoubleSide, fog: false })
    );
    face.position.set(x, y, z);
    face.rotation.y = Math.PI / 2;
    face.name = text;
    parent.add(face);
  }

  // Distance-compressed scenic north-shore panorama: match peak ordering
  // and characteristic layered silhouette, not precise DEM/survey elevations.
  function mountainHeight(x, far) {
    const peaks = far ? [
      [-630, 34],[-480, 58],[-360, 82],[-282, 92],[-216, 70],
      [-128, 79],[-70, 104],[-22, 109],[42, 85],[130, 86],
      [214, 102],[288, 113],[340, 95],[431, 74],[565, 60],[690, 43]
    ] : [
      [-630, 20],[-485, 33],[-340, 47],[-235, 53],[-144, 56],
      [-60, 62],[50, 59],[152, 60],[248, 64],[354, 56],[500, 41],[690, 29]
    ];
    for (let i = 1; i < peaks.length; i++) {
      if (x <= peaks[i][0]) {
        const left = peaks[i - 1], right = peaks[i];
        const t = Math.max(0, Math.min(1, (x - left[0]) / (right[0] - left[0])));
        const eased = t * t * (3 - 2 * t);
        const base = left[1] + (right[1] - left[1]) * eased;
        return base + 1.55 * Math.sin(x * 0.11) + 0.72 * Math.sin(x * 0.31);
      }
    }
    return peaks[peaks.length - 1][1];
  }
  function ridge(THREE, name, z, tint, far) {
    const shape = new THREE.Shape();
    const minX = -630, maxX = 690, baseY = -20;
    shape.moveTo(minX, baseY);
    shape.lineTo(maxX, baseY);
    for (let x = maxX; x >= minX; x -= 8) shape.lineTo(x, mountainHeight(x, far));
    shape.closePath();
    const mesh = new THREE.Mesh(new THREE.ShapeGeometry(shape), new THREE.MeshBasicMaterial({
      color: tint, side: THREE.DoubleSide, fog: false, depthWrite: true
    }));
    mesh.name = name;
    mesh.position.z = z;
    return mesh;
  }
  function snowCaps(THREE, root, z) {
    const snow = new THREE.MeshBasicMaterial({ color: 0xe3eef0, fog: false, side: THREE.DoubleSide });
    const exposed = new THREE.MeshBasicMaterial({ color: 0xa8babe, fog: false, side: THREE.DoubleSide });
    [-365, -285, -65, -5, 228, 290].forEach((peak, index) => {
      const high = mountainHeight(peak, true) + 3.8;
      const cap = new THREE.Shape();
      cap.moveTo(peak - 25, high - 20);
      cap.lineTo(peak - 8, high - 5);
      cap.lineTo(peak, high);
      cap.lineTo(peak + 11, high - 8);
      cap.lineTo(peak + 27, high - 20);
      cap.lineTo(peak + 6, high - 13);
      cap.lineTo(peak - 4, high - 10);
      cap.closePath();
      const mesh = new THREE.Mesh(new THREE.ShapeGeometry(cap), index % 2 ? snow : exposed);
      mesh.position.z = z + 0.7;
      mesh.name = 'North Shore snowline';
      root.add(mesh);
    });
  }

  function waterSurface(THREE, root) {
    const water = new THREE.Mesh(
      new THREE.PlaneGeometry(1320, 850),
      new THREE.MeshStandardMaterial({ color: 0x28607a, roughness: 0.5, metalness: 0.27, fog: false })
    );
    water.name = 'Burrard Inlet water';
    water.rotation.x = -Math.PI / 2;
    water.position.set(0, -0.46, -555); // shoreline approx. -130; far edge -980
    root.add(water);

    // One lightweight line-segments geometry, rather than a texture download.
    const ripples = [];
    for (let row = 0; row < 28; row++) {
      const z = -150 - row * 16.4;
      for (let col = 0; col < 13; col++) {
        const x = -370 + col * 59 + (row % 2) * 18;
        const length = 7 + ((col * 9 + row * 7) % 15);
        ripples.push(x, -0.405, z, x + length, -0.405, z + 1.2);
      }
    }
    const geom = new THREE.BufferGeometry();
    geom.setAttribute('position', new THREE.Float32BufferAttribute(ripples, 3));
    const lines = new THREE.LineSegments(geom, new THREE.LineBasicMaterial({
      color: 0x7fa8ae, opacity: 0.28, transparent: true, depthWrite: false, fog: false
    }));
    lines.name = 'Harbour water reflections';
    root.add(lines);

    // Shore/working-port transition behind the historic CPR station.
    box(THREE, root, 'Waterfront rail-yard apron', 0, 0.03, -113, 690, 0.13, 52, standard(THREE, 0x555d60));
    const rail = standard(THREE, 0x6f7675, 0.5, 0.3);
    const sleeper = standard(THREE, 0x4f423b, 0.86);
    for (let i = 0; i < 5; i++) {
      const z = -97 - i * 8;
      box(THREE, root, 'Waterfront track north rail', 0, 0.19, z, 670, 0.1, 0.14, rail);
      box(THREE, root, 'Waterfront track south rail', 0, 0.19, z - 1.4, 670, 0.1, 0.14, rail);
      for (let c = 0; c < 20; c++) box(THREE, root, 'Track timber sleeper', -315 + c * 33, 0.12, z - 0.7, 1.6, 0.06, 2.7, sleeper);
    }
  }

  function canadaPlace(THREE, root, anchor) {
    // The actual pier projects NNE from downtown into Burrard Inlet.
    // Use an east/north direction that matches the aerial reference.
    const forward = { x: 0.57, z: -0.822 };
    const length = 218, halfLength = length / 2;
    const start = { x: anchor.x - forward.x * halfLength, z: anchor.z - forward.z * halfLength };
    const yaw = Math.atan2(forward.x, forward.z); // Three.js local +Z to NNE
    const pier = new THREE.Group();
    pier.name = 'Canada Place pier / white sails';
    pier.position.set(start.x, 0, start.z);
    pier.rotation.y = yaw;
    root.add(pier);

    const concrete = standard(THREE, 0xd1d7d5, 0.82);
    const white = new THREE.MeshBasicMaterial({ color: 0xf0f3ef, side: THREE.DoubleSide, fog: false });
    const whiteTrim = standard(THREE, 0xe3e7e7, 0.71);
    const glass = standard(THREE, 0x416577, 0.32, 0.2);
    const post = standard(THREE, 0xa8b9ba, 0.73, 0.2);
    box(THREE, pier, 'Canada Place white terminal deck', 0, 4.5, length * 0.5, 53, 8, length, concrete);
    box(THREE, pier, 'Canada Place terminal glazing west', 26.8, 10.8, 108, 0.5, 10, 214, glass);
    box(THREE, pier, 'Canada Place terminal glazing east', -26.8, 10.8, 108, 0.5, 10, 214, glass);
    box(THREE, pier, 'Canada Place roof promenade', 0, 16.3, 108, 54, 0.9, 214, whiteTrim);
    for (let i=0; i<18; i++) {
      const z=8+i*12;
      for (const x of [-26.5,26.5]) cylinder(THREE, pier, 'Pier steel support', x, 0.2, z, 0.55, 11, post, 10);
    }
    // Five tent-like tensile sail groups and their paired masts.
    // Real sails are curved membranes; shape curves keep this readable from afar.
    for (let i = 0; i < 5; i++) {
      const z = 26 + i * 41;
      for (const side of [-1, 1]) {
        const x0 = side * 1.5, x1 = side * 24.5;
        const peakX = side * 2.5;
        const topY = 55 + (i % 2) * 5;
        const sail = new THREE.Shape();
        sail.moveTo(x0, 17);
        sail.lineTo(x1, 17);
        sail.quadraticCurveTo(side * 24, 34, peakX, topY);
        sail.quadraticCurveTo(side * 11, 32, x0, 17);
        sail.closePath();
        const mesh = new THREE.Mesh(new THREE.ShapeGeometry(sail), white);
        mesh.name = 'Canada Place white sail '+(i+1);
        mesh.position.z=z;
        pier.add(mesh);
        cylinder(THREE,pier,'Canada Place sail mast',peakX,36,z,0.36,39,whiteTrim,12);
      }
    }
    textSign(THREE,pier,'CANADA PLACE',-27.1,13.1,95,16,2.4);
    pier.userData.mapAnchor = anchor;
    pier.userData.representative = true;
    return { pier, yaw, start, length };
  }

  function dockedCruiseShip(THREE, root, pier) {
    // Representative 200m vessel on Canada Place's east berth.
    // Its exact position/occupancy changes in the real world.
    const ship = new THREE.Group();
    ship.name = 'Representative cruise ship at Canada Place east berth';
    ship.position.set(-65, -0.22, 118);
    pier.pier.add(ship);
    const navy = standard(THREE, 0x233a52, 0.54, 0.09);
    const pearl = standard(THREE, 0xe8edf0, 0.76);
    const glass = new THREE.MeshBasicMaterial({ color: 0x385668, fog: false });
    const deck = standard(THREE, 0xcad5d9, 0.85);
    const orange = standard(THREE, 0xe39a48, 0.5);
    const dark = standard(THREE, 0x293d4a, 0.67);
    const white = standard(THREE, 0xf4f2e8, 0.76);
    box(THREE, ship, 'Cruise ship blue hull', 0, 4.0, 0, 29, 9, 181, navy);
    // Taper the bow so it doesn't read as a floating apartment building.
    const bow = new THREE.Mesh(new THREE.ConeGeometry(14.5, 24, 4), navy);
    bow.rotation.x = Math.PI / 2;
    bow.rotation.z = Math.PI / 4;
    bow.position.set(0, 4.2, 96);
    bow.name = 'Cruise ship pointed bow';
    ship.add(bow);
    for (let deckIndex=0; deckIndex<7; deckIndex++) {
      const w=27.7 - deckIndex * 0.95;
      const d=171 - deckIndex * 3.6;
      const y=9.0 + deckIndex * 2.65;
      box(THREE,ship,'Cruise ship white passenger deck '+(deckIndex+1),0,y,0,w,2.45,d,pearl);
      box(THREE,ship,'Cruise ship dark window belt '+(deckIndex+1),w/2+0.055,y+0.25,0,0.14,0.72,d-8,glass);
      box(THREE,ship,'Cruise ship port window belt '+(deckIndex+1),-w/2-0.055,y+0.25,0,0.14,0.72,d-8,glass);
      if(deckIndex>=3) box(THREE,ship,'Cruise balcony rail '+(deckIndex+1),0,y+1.28,0,w+0.7,0.12,d-4,deck);
    }
    box(THREE,ship,'Cruise ship bridge',0,29.9,57,27,5.3,22,white);
    box(THREE,ship,'Cruise ship bridge windscreen',0,31.1,68.2,24,2.2,0.34,glass);
    cylinder(THREE,ship,'Cruise ship funnel',0,34,-27,4.5,12,navy,14);
    cylinder(THREE,ship,'Cruise ship radar mast',0,40,39,0.43,17,white,10);
    for (const side of [-1,1]) {
      for(let i=0;i<5;i++) {
        const boat=cylinder(THREE,ship,'Orange cruise ship lifeboat',side*15.0,15.1,-62+i*27,1.65,7.2,orange,10);
        boat.rotation.x=Math.PI/2;
      }
    }
    ship.userData = {
      representative: true,
      liveBerthAssignment: false,
      berth: 'Canada Place east berth (illustrative)',
      lengthApproxMetres: 205
    };
    return ship;
  }
  function harbourTraffic(THREE, root) {
    const shell = standard(THREE, 0xe2e7e5, 0.64);
    const teal = standard(THREE, 0x356675, 0.62);
    const dark = standard(THREE, 0x294a5e, 0.57);
    for (const [name,x,z,yaw] of [
      ['North Vancouver SeaBus silhouette',172,-270,0.48],
      ['Working harbour tug silhouette',-240,-365,-0.85]
    ]) {
      const ferry=new THREE.Group();
      ferry.position.set(x,0,z);
      ferry.rotation.y=yaw;
      ferry.name=name;
      const long=name.includes('SeaBus')?26:18;
      box(THREE,ferry,'Vessel hull',0,1.15,0,12,3.1,long,dark);
      box(THREE,ferry,'Vessel cabin',0,4.0,-1,9.8,3.8,long*0.62,shell);
      box(THREE,ferry,'Vessel window band',0,4.4,long*0.3,9.2,0.75,0.18,teal);
      root.add(ferry);
    }
  }
  function create(THREE, world) {
    if(!THREE||!world||!world.meta||!world.meta.gis) return null;
    const anchor=positionFor(world);
    if(!anchor) return null;
    const root=new THREE.Group();
    root.name='North Shore mountains / Burrard Inlet / Canada Place harbour vista';
    waterSurface(THREE,root);
    const back=ridge(THREE,'North Shore distant blue peaks',-620,0x8297a1,true);
    root.add(back);
    snowCaps(THREE,root,-619);
    const foreground=ridge(THREE,'North Vancouver forested lower slopes',-505,0x42636b,false);
    root.add(foreground);
    const city=standard(THREE,0x8f9996,0.9);
    for(let i=0;i<52;i++) {
      const x=-390+i*15.5;
      const h=3+((i*13+7)%9);
      box(THREE,root,'North Vancouver shore silhouette',x,h/2-0.6,-489,10.2,h,9,city);
    }
    const pier=canadaPlace(THREE,root,anchor);
    dockedCruiseShip(THREE,root,pier);
    harbourTraffic(THREE,root);
    root.userData={
      canadaPlace:anchor,
      coordinateSource:CANADA_PLACE_COORD,
      representativeShip:true,
      mountainElevationSurveyed:false,
      materialReferences:REFS,
      northAxis:'-Z'
    };
    return root;
  }
  window.GastownHarbourVista=Object.freeze({
    create,projectScene,positionFor,reference:CANADA_PLACE_COORD
  });
})(window);

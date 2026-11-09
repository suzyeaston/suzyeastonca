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
    // The previous uniform rectangular water plane began at Z=-116.
    // Beyond the lot fence the railway ground ended at Z=-106, creating an
    // apparent blank drop-off as the player looked east. Trace an approximate
    // eastward-receding waterfront edge rather than a constant latitude.
    // This is NOT a surveyed shoreline or a real-time berth/yard map.
    const shore = [
      { x: -660, z: -158 }, { x: -240, z: -151 },
      { x: 0, z: -149 }, { x: 165, z: -170 },
      { x: 390, z: -205 }, { x: 660, z: -240 }
    ];
    const horizontalPatch = (name, points, elevation, color) => {
      const outline = new THREE.Shape();
      points.forEach((p, i) => {
        if (!i) outline.moveTo(p.x, -p.z);
        else outline.lineTo(p.x, -p.z);
      });
      outline.closePath();
      const mesh = new THREE.Mesh(new THREE.ShapeGeometry(outline),
        new THREE.MeshBasicMaterial({ color, fog: false, side: THREE.DoubleSide }));
      mesh.name = name;
      mesh.rotation.x = -Math.PI / 2;
      mesh.position.y = elevation;
      root.add(mesh);
      return mesh;
    };
    // A below-grade contextual apron fills the previously unrendered space
    // beyond the parking rail fence. It never covers the playable paved lot.
    horizontalPatch('CPR waterfront continuous land apron (approximate GIS context)',
      [{ x: -660, z: 240 }, { x: 660, z: 240 },
        ...shore.slice().reverse()], -0.21, 0x525d5d);
    const water = horizontalPatch('Burrard Inlet water',
      [{ x: -660, z: -1040 }, { x: 660, z: -1040 },
        ...shore.slice().reverse()], -0.11, 0x266d91);
    root.userData.approximateShoreline = true;
    root.userData.shorelineEdge = shore;
    root.userData.waterfrontGroundContinuity = true;

    // One lightweight line-segments geometry, rather than a texture download.
    const ripples = [];
    for (let row = 0; row < 28; row++) {
      const z = -150 - row * 16.4;
      for (let col = 0; col < 13; col++) {
        const x = -370 + col * 59 + (row % 2) * 18;
        const length = 7 + ((col * 9 + row * 7) % 15);
        ripples.push(x, -0.035, z, x + length, -0.035, z + 1.2);
      }
    }
    const geom = new THREE.BufferGeometry();
    geom.setAttribute('position', new THREE.Float32BufferAttribute(ripples, 3));
    const lines = new THREE.LineSegments(geom, new THREE.LineBasicMaterial({
      color: 0x7fa8ae, opacity: 0.28, transparent: true, depthWrite: false, fog: false
    }));
    lines.name = 'Harbour water reflections';
    root.add(lines);

    // Keep the shore apron as a narrow land transition rather than another
    // set of perpendicular ghost tracks crossing the visible harbour.
    box(THREE,root,'Waterfront rail-yard apron',0,0.03,-128,690,0.13,22,
      standard(THREE,0x555d60));
    const waveMaterial=new THREE.MeshBasicMaterial({
      color:0x62bfd0,transparent:true,opacity:0.24,depthWrite:false,fog:false
    });
    for(let i=0;i<16;i++){
      const z=-148-i*13.5;
      const ribbon=box(THREE,root,'Sea surface cross-harbour reflection '+i,
        75, -0.025, z, 140+i*5, 0.018, 0.38, waveMaterial);
      ribbon.rotation.y=0.04;
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

  function waterfrontRailYard(THREE, root, world) {
    // Station East is a wedge north of Cordova, while CPR tracks run on a
    // diagonal behind its north fence. Older models placed all cars on fixed
    // global Z values, so looking from the fence toward The Landing showed a
    // blank asphalt void. Derive the rails from the photographed back edge.
    // The placement remains representative, not a surveyed rail alignment.
    const lot=world&&world.parkingLot;
    if(!lot||!Array.isArray(lot.polygon)||lot.polygon.length<7)return;
    const pts=[lot.polygon[6],lot.polygon[5],lot.polygon[4],lot.polygon[3],
      {x:116,z:-106},{x:-45,z:-106}];
    const shape=new THREE.Shape();
    pts.forEach((p,i)=>i?shape.lineTo(p.x,-p.z):shape.moveTo(p.x,-p.z));
    shape.closePath();
    const surface=new THREE.Mesh(new THREE.ShapeGeometry(shape),
      new THREE.MeshBasicMaterial({color:0x616562,side:THREE.DoubleSide,fog:false}));
    surface.name='Fenced waterfront rail service yard (non-walkable)';
    surface.rotation.x=-Math.PI/2;
    surface.position.y=0.075;
    root.add(surface);

    const edgeA=lot.polygon[5],edgeB=lot.polygon[4];
    const length=Math.hypot(edgeB.x-edgeA.x,edgeB.z-edgeA.z)||1;
    const dir={x:(edgeB.x-edgeA.x)/length,z:(edgeB.z-edgeA.z)/length};
    const outside={x:dir.z,z:-dir.x};
    const yaw=Math.atan2(-dir.z,dir.x);
    const mid={x:(edgeB.x+edgeA.x)*0.5,z:(edgeB.z+edgeA.z)*0.5};
    // Move the long rails toward The Landing so a left/right turn from the
    // public parking aisle reveals several tracks instead of a black wall.
    const origin={x:mid.x+dir.x*30,z:mid.z+dir.z*30};
    const trackPoint=(longitudinal,offset)=>({
      x:origin.x+dir.x*longitudinal+outside.x*offset,
      z:origin.z+dir.z*longitudinal+outside.z*offset
    });
    const placeBox=(name,along,offset,y,w,h,d,material)=>{
      const p=trackPoint(along,offset);
      const part=box(THREE,root,name,p.x,y,p.z,w,h,d,material);
      part.rotation.y=yaw;
      return part;
    };
    const ballast=standard(THREE,0x595d5d,0.96);
    const steel=standard(THREE,0xc8c6bb,0.34,0.67);
    const sleeper=standard(THREE,0x59483a,0.91);
    // The previous 24-metre tie spacing made these read as seven long bars
    // rather than recognizable rails. Instance realistic, repeated ties in a
    // single draw call, preserving the fenced 20-track illustrative yard.
    const nearTrackCount = 7;
    const tieSpacingMetres = 0.72;
    const railLengthMetres = 150;
    const tieStart = -railLengthMetres / 2 + 0.5;
    const tiesPerTrack = Math.floor((railLengthMetres - 1) / tieSpacingMetres) + 1;
    // Test stubs and older minimal Three.js integrations may not expose
    // instancing: retain readable rails and a small tie fallback.
    const useInstancing = typeof THREE.InstancedMesh === 'function'
      && typeof THREE.Object3D === 'function';
    let ties = null, tieDummy = null, tieIndex = 0;
    if (useInstancing) {
      ties = new THREE.InstancedMesh(
        new THREE.BoxGeometry(0.24, 0.105, 2.62),
        sleeper,
        nearTrackCount * tiesPerTrack
      );
      ties.name = 'CP N-Yard near-field instanced timber sleepers';
      ties.castShadow = false;
      ties.receiveShadow = true;
      tieDummy = new THREE.Object3D();
    }
    // All rail geometry stays outside the lot fence and non-walkable.
    for (let i = 0; i < nearTrackCount; i++) {
      const offset = 8 + i * 7.1;
      placeBox('CP N-Yard track ballast', 0, offset, 0.14,
        railLengthMetres, 0.13, 3.55, ballast);
      for (const side of [-0.75, 0.75]) {
        placeBox('CP N-Yard steel rail head', 0, offset + side, 0.345,
          railLengthMetres, 0.13, 0.095, steel);
        placeBox('CP N-Yard steel rail web', 0, offset + side, 0.258,
          railLengthMetres, 0.075, 0.052, steel);
      }
      if (useInstancing) {
        for (let t = 0; t < tiesPerTrack; t++) {
          const position = trackPoint(tieStart + t * tieSpacingMetres, offset);
          tieDummy.position.set(position.x, 0.238, position.z);
          tieDummy.rotation.set(0, yaw, 0);
          tieDummy.updateMatrix();
          ties.setMatrixAt(tieIndex++, tieDummy.matrix);
        }
      } else {
        // Keep this fallback bounded for non-WebGL test doubles.
        for (let t = -68; t <= 68; t += 6) {
          placeBox('CP N-Yard fallback timber sleeper', t, offset,
            0.238, 0.24, 0.105, 2.62, sleeper);
          tieIndex++;
        }
      }
    }
    if (ties) {
      ties.instanceMatrix.needsUpdate = true;
      if (typeof ties.computeBoundingSphere === 'function') ties.computeBoundingSphere();
      root.add(ties);
    }
    root.userData.nearTrackTieCount = tieIndex;
    root.userData.nearTrackTieSpacingMetres = tieSpacingMetres;

    // Vancouver's CPR N-Yard is a 20-track facility east of the station.
    // The seven detailed near-field tracks above are preserved; this is a
    // lightweight, batched *illustration* of the other thirteen tracks and
    // the west-end switching ladder. The alignment is not a rail survey.
    // Keep everything at grade so the harbour and cruise ship stay visible.
    const fanRails = [];
    const fanTies = [];
    const appendSegment = (array, a, b, y) => {
      array.push(a.x, y, a.z, b.x, y, b.z);
    };
    const lerp = (a, b, t) => ({
      x: a.x + (b.x - a.x) * t,
      z: a.z + (b.z - a.z) * t
    });
    for (let branch = 0; branch < 13; branch++) {
      // The low west-end ladder converges without placing tall railcars
      // across the sightline from the Station East parking lot.
      const west = trackPoint(-28, 50.6 + branch * 0.33);
      const switchEnd = trackPoint(10, 55.0 + branch * 4.38);
      const east = trackPoint(76 - branch * 2.0, 55.0 + branch * 4.38);
      for (const side of [-0.73, 0.73]) {
        const shifted = point => ({
          x: point.x + outside.x * side,
          z: point.z + outside.z * side
        });
        appendSegment(fanRails, shifted(west), shifted(switchEnd), 0.33);
        appendSegment(fanRails, shifted(switchEnd), shifted(east), 0.33);
      }
      // A single batched line geometry paints sleepers; no dozens of
      // individual cast-shadow meshes on an 8 GB laptop.
      for (let index = 0; index <= 14; index++) {
        const t = index / 14;
        const center = t < 0.46
          ? lerp(west, switchEnd, t / 0.46)
          : lerp(switchEnd, east, (t - 0.46) / 0.54);
        appendSegment(fanTies,
          { x: center.x - outside.x * 1.43, z: center.z - outside.z * 1.43 },
          { x: center.x + outside.x * 1.43, z: center.z + outside.z * 1.43 },
          0.19);
      }
    }
    function addBatchedYardLines(name, vertices, color) {
      const geometry = new THREE.BufferGeometry();
      geometry.setAttribute('position', new THREE.Float32BufferAttribute(vertices, 3));
      const lines = new THREE.LineSegments(geometry, new THREE.LineBasicMaterial({
        color, fog: false, depthWrite: false
      }));
      lines.name = name;
      root.add(lines);
    }
    addBatchedYardLines('CP N-Yard batched switching ladder rails', fanRails, 0xe0d8c6);
    addBatchedYardLines('CP N-Yard batched sleepers', fanTies, 0x9b8977);
    root.userData.yardTrackCount = 20;
    root.userData.yardRailFanBatched = true;

    const freightColors=[0xba644b,0x518c83,0xc99d47,0x8b6168,0x487f9f,
      0xb2a49b,0xa94d39,0x508470].map(color=>standard(THREE,color,0.83));
    const dark=standard(THREE,0x292e34,0.81);
    const wheelMat=standard(THREE,0x181b20,0.87);
    // The parking-lot view looks WEST toward Canada Place's sails/cruise
    // berth. Four densely filled container trains formed a solid coloured
    // wall over the ship. Keep the tracks and a couple of plausible freight
    // strings farther east, while opening a clear western sightline.
    // We never imply that the active yard is empty in real life.
    const rows=[2,5];
    let containerCount=0;
    rows.forEach((row,rowIndex)=>{
      const offset=8+row*7.1;
      placeBox('Station East visible railcar spine',26,offset,0.88,80,0.61,3.25,dark);
      const centers=rowIndex===0?[8,38]:[19,49];
      centers.forEach((longitudinal,index)=>{
        // Single-height freight leaves the upper cruise decks and white
        // sails visible from the public side of the parking-lot fence.
        placeBox('Visible Station East freight container',longitudinal,offset,
          2.63,22.1,2.98,2.85,freightColors[(rowIndex*3+index)%freightColors.length]);
        containerCount++;
      });
    });
    [2,4,6].forEach((row,index)=>{
      const position=trackPoint(8+index*23,8+row*7.1);
      const wagon=new THREE.Group();
      wagon.name='CP freight railcar in waterfront yard '+(index+1);
      wagon.position.set(position.x,0,position.z);
      wagon.rotation.y=yaw;
      box(THREE,wagon,'Rail wagon long frame',0,0.69,0,23.0,0.54,2.82,dark);
      for(const x of [-8.8,8.8]){
        for(const z of [-1.03,1.03]){
          const w=cylinder(THREE,wagon,'Freight steel wheel',x,0.43,z,0.34,0.14,wheelMat,8);
          w.rotation.x=Math.PI/2;
        }
      }
      box(THREE,wagon,'Waterfront double-length intermodal container',0,2.28,0,
        22.5,2.8,2.6,freightColors[(index*2)%freightColors.length]);
      root.add(wagon);
    });
    // The City of Vancouver's railways layer records CPR alignments across
    // the waterfront (indicative, not survey-accurate). The federal/municipal
    // waterfront rail study describes a 20-track N-Yard east of the station.
    // Extend existing at-grade tracks east of the little parking-lot model,
    // where looking along the fence previously showed blank scenery.
    // Low-profile lines and ties preserve the cruise ship/sea sightline.
    const corridorRailVertices = [], corridorTieVertices = [];
    function addCorridorStroke(vertices, a, b, y) {
      vertices.push(a.x, y, a.z, b.x, y, b.z);
    }
    for (let row = 0; row < 9; row++) {
      const begin = { x: 68, z: -19 - row * 4.3 };
      const end = { x: 490, z: -102 - row * 4.3 };
      const dx = end.x - begin.x, dz = end.z - begin.z;
      const length = Math.hypot(dx, dz);
      const cross = { x: -dz / length, z: dx / length };
      for (const railOffset of [-0.74, 0.74]) {
        const a = { x: begin.x + cross.x * railOffset,
          z: begin.z + cross.z * railOffset };
        const b = { x: end.x + cross.x * railOffset,
          z: end.z + cross.z * railOffset };
        addCorridorStroke(corridorRailVertices, a, b, 0.30);
      }
      for (let step = 0; step <= 46; step++) {
        const t = step / 46;
        const x = begin.x + dx * t, z = begin.z + dz * t;
        addCorridorStroke(corridorTieVertices,
          { x: x - cross.x * 1.38, z: z - cross.z * 1.38 },
          { x: x + cross.x * 1.38, z: z + cross.z * 1.38 }, 0.14);
      }
    }
    addBatchedYardLines('CP N-Yard eastward railway GIS corridor continuation',
      corridorRailVertices, 0xb9b6ae);
    addBatchedYardLines('CP N-Yard eastward timber railway sleepers',
      corridorTieVertices, 0x84766a);
    root.userData.eastboundRailCorridorVisible = true;
    root.userData.railwayDataReference =
      'https://opendata.vancouver.ca/explore/assets/railways/';
    root.userData.railCorridorSurveyed = false;

    root.userData.nearbyContainers=containerCount;
    root.userData.tracksAlignedToLotFence=true;
    root.userData.openCanadaPlaceSightline=true;

    const stationWhite=standard(THREE,0xe1e5e3,0.76);
    const glass=standard(THREE,0x5f8494,0.38,0.16);
    const rust=standard(THREE,0x7b4b42,0.83);
    const terminal=new THREE.Group();
    terminal.name='Waterfront SeaBus south terminal building';
    terminal.position.set(-43,0,-148);
    box(THREE,terminal,'SeaBus terminal white roof',0,6,0,46,1.1,23,stationWhite);
    box(THREE,terminal,'SeaBus terminal glass departure hall',0,3.25,0,41,5.2,17,glass);
    box(THREE,terminal,'SeaBus terminal sheltered pier',0,0.9,-15,42,1.4,27,stationWhite);
    for(const x of [-18,-9,0,9,18]){
      box(THREE,terminal,'SeaBus terminal white roof truss',x,6.6,0,0.35,1,21,stationWhite);
    }
    root.add(terminal);
    box(THREE,root,'Waterfront Station red elevated transfer walkway',
      -38,11,-93,78,5,8,rust);
    for(const x of [-74,-53,-32,-11]){
      box(THREE,root,'Red bridge open truss bay',x,12,-88.6,1.3,7.1,0.25,rust);
    }
    root.userData.hasFreightYard=true;
  }

  function containerPortCranes(THREE, root) {
    // Silhouette of the distant North Shore working-port container cranes,
    // not surveyed terminal equipment or live vessel/berth assignments.
    const quay=standard(THREE,0x667271,0.84);
    const orange=standard(THREE,0xc17d47,0.59,0.12);
    const steel=standard(THREE,0x4b626b,0.76,0.24);
    const cargoColors=[0x58717b,0x9f5741,0x7c8581,0x596b60,0x857463];
    box(THREE,root,'North Shore container terminal quay',-151,0.95,-419,293,2,32,quay);
    for(let j=0;j<16;j++) {
      const x=-290+j*18.5;
      for(let tier=0;tier<((j*3)%3)+1;tier++){
        box(THREE,root,'Distant stacked shipping container',x,2.2+tier*2.5,-413,
          14,2.3,5.2,standard(THREE,cargoColors[(j+tier)%cargoColors.length],0.91));
      }
    }
    for(let i=0;i<4;i++){
      const g=new THREE.Group();
      g.name='North Shore orange container gantry crane '+(i+1);
      g.position.set(-274+i*68,0,-396-i*5);
      root.add(g);
      // An open gantry, angled outreach boom and suspended hoist cable.
      for(const x of [-12,12]){
        box(THREE,g,'Crane tall vertical',x,26,0,1.15,52,1.2,orange);
        box(THREE,g,'Crane base foot',x,1,0,5,2.1,5,steel);
      }
      box(THREE,g,'Crane overhead crossbeam',0,52,0,44,1.5,1.2,orange);
      const boom=box(THREE,g,'Crane projecting orange boom',-22,55,0,62,1.35,1.3,orange);
      boom.rotation.z=-0.19;
      box(THREE,g,'Crane hanging hoist cable',-40,35,0,0.14,35,0.14,steel);
      box(THREE,g,'Crane apex',0,64,0,2.1,24,1,orange);
    }
  }

  function harbourTraffic(THREE, root) {
    // The SeaBus is not a huge distant office-like box: twin pontoons,
    // bright passenger saloon, blue-yellow banding and wraparound windows.
    // Scenic route/position, not a live TransLink departure or GPS feed.
    const white=standard(THREE,0xf0f3ef,0.68);
    const navy=standard(THREE,0x244d79,0.57,0.14);
    const gold=standard(THREE,0xe0af39,0.61);
    const windowGlass=new THREE.MeshBasicMaterial({color:0x385b72,fog:false});
    const ferry=new THREE.Group();
    ferry.name='North Vancouver SeaBus silhouette';
    ferry.position.set(-13,-0.02,-195);
    ferry.rotation.y=0.20;
    box(THREE,ferry,'SeaBus twin catamaran starboard hull',4.0,1.2,0,3.15,2.3,35,navy);
    box(THREE,ferry,'SeaBus twin catamaran port hull',-4.0,1.2,0,3.15,2.3,35,navy);
    box(THREE,ferry,'SeaBus bright white passenger deck',0,3.2,0,11.6,2.3,32,white);
    box(THREE,ferry,'SeaBus passenger cabin',0,5.6,0,10.1,3.1,28,white);
    for(const x of [-5.1,5.1]){
      box(THREE,ferry,'SeaBus blue passenger window belt',x,5.9,0,0.15,1.12,26,windowGlass);
      box(THREE,ferry,'SeaBus golden hull stripe',x,2.7,0,0.14,0.37,32,gold);
    }
    for(const z of [-13.9,13.9]){
      box(THREE,ferry,'SeaBus broad front windscreen',0,5.85,z,8.8,1.34,0.15,windowGlass);
      box(THREE,ferry,'SeaBus white end deck',0,3.2,z*1.14,9.1,1.0,3.6,white);
    }
    box(THREE,ferry,'SeaBus flat white roof',0,7.22,0,11.3,0.39,30,white);
    box(THREE,ferry,'SeaBus rooftop comms antenna',0,8.65,0,0.16,2.6,0.16,navy);
    root.add(ferry);

    const tug=new THREE.Group();
    tug.name='Working harbour tug silhouette';
    tug.position.set(-240,0,-365);
    tug.rotation.y=-0.85;
    box(THREE,tug,'Harbour tug blue hull',0,1.15,0,10.8,3.1,19,navy);
    box(THREE,tug,'Harbour tug white bridge',0,4.2,-1.0,8.6,3.5,9.5,white);
    box(THREE,tug,'Harbour tug windows',0,4.5,3.8,8.0,0.7,0.17,windowGlass);
    root.add(tug);
    return ferry;
  }
  // A very slow cinematic crossing puts a real, readable SeaBus in shot.
  function animate(root,timeSeconds) {
    const bus=root && root.userData && root.userData.seaBus;
    if(!bus||!Number.isFinite(timeSeconds))return;
    bus.position.x = -13 + Math.sin(timeSeconds*0.045)*11;
    bus.position.y = -0.02 + Math.sin(timeSeconds*0.8)*0.09;
    bus.rotation.y = 0.20 + Math.sin(timeSeconds*0.03)*0.025;
  }

  // A public-sidewalk viewing pose across the open Station East surface lot.
  // It does not falsely imply a pedestrian route through the rail yard,
  // station interior, cruise terminal or private lot.
  function lookoutFor(world) {
    const line = world && world.route && world.route.centerline;
    const place = positionFor(world);
    if (!place || !Array.isArray(line) || line.length < 8) return null;
    const index = 5; // 500 W Cordova, alongside the Station East gap
    const center = line[index], before = line[index - 1], after = line[index + 1];
    const tx = after.x - before.x, tz = after.z - before.z;
    const len = Math.hypot(tx, tz);
    if (!(len > 0.1)) return null;
    // North-side civic sidewalk, 8 metres left of southeast-bound road.
    const x = center.x + (tz / len) * 8.0;
    const z = center.z - (tx / len) * 8.0;
    const dx = place.x - x, dz = place.z - z;
    return {
      x, y: 1.7, z,
      yaw: Math.atan2(-dx, -dz),
      pitch: -0.012, // eye-line now catches the water rather than sky alone
      facing: 'north-northwest toward Canada Place and the North Shore',
      label: 'Station East / West Cordova harbour view (sidewalk)',
      publicSidewalkApproximation: true
    };
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
    waterfrontRailYard(THREE,root,world);
    containerPortCranes(THREE,root);
    const pier=canadaPlace(THREE,root,anchor);
    dockedCruiseShip(THREE,root,pier);
    const seaBus=harbourTraffic(THREE,root);
    root.userData=Object.assign({}, root.userData, {
      seaBus,
      canadaPlace:anchor,
      coordinateSource:CANADA_PLACE_COORD,
      representativeShip:true,
      mountainElevationSurveyed:false,
      materialReferences:REFS,
      northAxis:'-Z'
    });
    return root;
  }
  window.GastownHarbourVista=Object.freeze({
    create,animate,projectScene,positionFor,lookoutFor,reference:CANADA_PLACE_COORD
  });
})(window);

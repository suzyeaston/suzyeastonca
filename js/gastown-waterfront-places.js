/*
 * Waterfront public realm: Station East Surface Parking (555 W Cordova) and
 * The Landing / Kelly-Douglas Warehouse (375 Water).
 *
 * Grounded references:
 * https://heritagevancouver.org/top10-watch-list/2021/8-555-west-cordova/
 * https://impark.com/vancouver-tourism/  (Station East Lot 531)
 * https://www.heritagesitefinder.ca/location/375-water-st-vancouver-bc
 *
 * The lot's outline, parked cars, parking controls, facade details, building
 * height and people are representative art-direction geometry, NOT surveyed
 * facilities or real-time occupancy. Do not model the proposed office tower
 * as built: the 2026 project was a development application.
 */
(function (window) {
  'use strict';
  const REFERENCE = Object.freeze({ parking: 'Station East / 555 West Cordova, Impark Lot 531', landing: 'Kelly-Douglas Warehouse / The Landing, 375 Water Street' });

  function inPolygon(p, poly) {
    let inside = false;
    for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      const a = poly[i], b = poly[j];
      if (((a.z > p.z) !== (b.z > p.z))
        && p.x < ((b.x - a.x) * (p.z - a.z) / ((b.z - a.z) || 1e-9)) + a.x) inside = !inside;
    }
    return inside;
  }
  function distSegment(p, a, b) {
    const x = b.x - a.x, z = b.z - a.z, len = x * x + z * z || 1;
    const t = Math.max(0, Math.min(1, ((p.x - a.x) * x + (p.z - a.z) * z) / len));
    return Math.hypot(p.x - a.x - t * x, p.z - a.z - t * z);
  }
  function polygonMesh(THREE, points, mat, elevation) {
    const shape = new THREE.Shape();
    points.forEach((p, i) => i ? shape.lineTo(p.x, -p.z) : shape.moveTo(p.x, -p.z));
    shape.closePath();
    const geometry = new THREE.ShapeGeometry(shape);
    const mesh = new THREE.Mesh(geometry, mat);
    mesh.rotation.x = -Math.PI / 2;
    mesh.position.y = elevation;
    mesh.receiveShadow = true;
    return mesh;
  }
  function addBox(THREE, group, name, x, y, z, width, height, depth, material, yaw) {
    const mesh = new THREE.Mesh(new THREE.BoxGeometry(width, height, depth), material);
    mesh.name = name;
    mesh.position.set(x, y, z);
    mesh.rotation.y = yaw || 0;
    mesh.castShadow = false;
    mesh.receiveShadow = true;
    group.add(mesh);
    return mesh;
  }
  function addCylinder(THREE, group, name, x, y, z, rad, height, material) {
    const mesh = new THREE.Mesh(new THREE.CylinderGeometry(rad, rad, height, 10), material);
    mesh.name = name;
    mesh.position.set(x, y, z);
    group.add(mesh);
    return mesh;
  }
  function addSegment(THREE, group, name, a, b, y, thickness, material) {
    const dx = b.x - a.x, dz = b.z - a.z;
    const length = Math.hypot(dx, dz);
    const yaw = Math.atan2(-dz, dx);
    return addBox(THREE, group, name, (a.x + b.x) / 2, y, (a.z + b.z) / 2, length, thickness, thickness, material, yaw);
  }
  function sign(THREE, group, label, position, width, height, yaw, options) {
    if (!window.document) return;
    const canvas = window.document.createElement('canvas');
    canvas.width = 1024; canvas.height = 256;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.fillStyle = (options && options.background) || '#183959';
    ctx.fillRect(0, 0, 1024, 256);
    ctx.fillStyle = (options && options.color) || '#f9f8eb';
    ctx.font = 'bold 102px Arial, sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(label, 512, 128, 965);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    const material = new THREE.MeshBasicMaterial({ map: texture, side: THREE.DoubleSide });
    const mesh = new THREE.Mesh(new THREE.PlaneGeometry(width, height), material);
    mesh.position.set(position.x, position.y, position.z);
    mesh.rotation.y = yaw || 0;
    mesh.name = label;
    group.add(mesh);
  }


  // The Cordova-side edge [0,1] is the open driveway. Edges 3-5 face the
  // working waterfront. These are intentionally representative outlines,
  // shared by drawing, movement and the fence-view preset.
  function entranceFor(lot) {
    const a=lot.polygon[0], b=lot.polygon[1];
    return { x:(a.x+b.x)/2, z:(a.z+b.z)/2 };
  }
  function fenceLookoutFor(lot) {
    const a=lot.polygon[4], b=lot.polygon[5], middle={x:(a.x+b.x)/2,z:(a.z+b.z)/2};
    const toward={x:lot.center.x-middle.x,z:lot.center.z-middle.z};
    const n=Math.hypot(toward.x,toward.z)||1;
    return {x:middle.x+2.6*toward.x/n,z:middle.z+2.6*toward.z/n};
  }
  function parkingCars(lot) {
    const u={x:0.704,z:-0.710}, v={x:0.710,z:0.704};
    const start=entranceFor(lot), end=fenceLookoutFor(lot), cars=[];
    for(const lane of [-10,9]) {
      for(let i=-3;i<=3;i++) {
        const p={x:lot.center.x+u.x*(i*6.7)+v.x*lane,z:lot.center.z+u.z*(i*6.7)+v.z*lane};
        const border=Math.min(...lot.polygon.map((q,j)=>distSegment(p,q,lot.polygon[(j+1)%lot.polygon.length])));
        // A genuinely walkable central aisle from Cordova to the harbour fence.
        if(!inPolygon(p,lot.polygon)||border<3.7||distSegment(p,start,end)<4.8)continue;
        cars.push(p);
      }
    }
    return cars;
  }
  function isWalkableLot(world, point) {
    const lot=world&&world.parkingLot;
    if(!lot||!lot.polygon||!point||!inPolygon(point,lot.polygon))return false;
    // The low perimeter curb is open at the entrance; the rear fence is not.
    for(const edgeIndex of [2,3,4,5]) {
      const a=lot.polygon[edgeIndex], b=lot.polygon[(edgeIndex+1)%lot.polygon.length];
      if(distSegment(point,a,b)<1.05)return false;
    }
    // Parked cars are scenery with real collision clearance for the walker.
    const yaw=2.36, cos=Math.cos(yaw), sin=Math.sin(yaw);
    return !parkingCars(lot).some(car=>{
      const dx=point.x-car.x,dz=point.z-car.z;
      return Math.abs(dx*cos-dz*sin)<1.43 && Math.abs(dx*sin+dz*cos)<2.46;
    });
  }
  function lookoutFor(world, harbourTarget) {
    const lot=world&&world.parkingLot;
    if(!lot||!Array.isArray(lot.polygon)||lot.polygon.length<7)return null;
    const point=fenceLookoutFor(lot);
    const target=harbourTarget||{x:point.x-80,z:point.z-260};
    return {
      x:point.x,y:1.7,z:point.z,
      yaw:Math.atan2(-(target.x-point.x),-(target.z-point.z)),
      pitch:0.055,
      label:'Station East / waterfront fence lookout',
      approximate:true,
      publicAccessNotVerified:true
    };
  }
  function asphaltTexture(THREE) {
    if(!window.document||!window.document.createElement)return null;
    const canvas=window.document.createElement('canvas');
    canvas.width=128; canvas.height=128;
    const ctx=canvas.getContext('2d');
    if(!ctx)return null;
    ctx.fillStyle='#41474a';ctx.fillRect(0,0,128,128);
    // Deterministic fine aggregate and repairs, no network texture requests.
    for(let i=0;i<1120;i++){
      const x=(i*53+Math.floor(i/13)*17)%128;
      const y=(i*97+Math.floor(i/7)*11)%128;
      const grey=57+(i*19)%49;
      ctx.fillStyle='rgba('+grey+','+(grey+3)+','+(grey+5)+','+(0.08+(i%4)*0.055)+')';
      ctx.fillRect(x,y,1+(i%3),1+(i%2));
    }
    const tex=new THREE.CanvasTexture(canvas);
    tex.colorSpace=THREE.SRGBColorSpace;
    tex.wrapS=tex.wrapT=THREE.RepeatWrapping;
    if(tex.repeat&&typeof tex.repeat.set==='function')tex.repeat.set(0.13,0.13);
    return tex;
  }
  function addHarbourFence(THREE, root, lot) {
    const postMat=new THREE.MeshStandardMaterial({color:0x29363d,roughness:0.72,metalness:0.35});
    const railMat=new THREE.MeshStandardMaterial({color:0x566268,roughness:0.76,metalness:0.22});
    const capMat=new THREE.MeshStandardMaterial({color:0x9fa3a1,roughness:0.85});
    for(const i of [2,3,4,5]) {
      const a=lot.polygon[i],b=lot.polygon[(i+1)%lot.polygon.length];
      const n=Math.max(2,Math.ceil(Math.hypot(b.x-a.x,b.z-a.z)/3.3));
      for(let j=0;j<=n;j++){
        const t=j/n,x=a.x+(b.x-a.x)*t,z=a.z+(b.z-a.z)*t;
        addCylinder(THREE,root,'Waterfront fence steel upright',x,1.13,z,0.065,2.18,postMat);
        addBox(THREE,root,'Fence post cap',x,2.25,z,0.18,0.09,0.18,capMat);
      }
      for(const y of [0.46,1.35,2.12]){
        addSegment(THREE,root,'Rail-yard lookout open metal fence rail',a,b,y,0.065,railMat);
      }
    }
    const view=fenceLookoutFor(lot);
    // A low viewing marker establishes the experience without hiding the harbour.
    addCylinder(THREE,root,'Harbour view wayfinding post',view.x+2,0.82,view.z+1,0.08,1.58,postMat);
    sign(THREE,root,'RAIL YARD / BURRARD INLET',{x:view.x+2,y:1.78,z:view.z+1},3.2,0.66,-0.64,
      {background:'#274251',color:'#fff4dc'});
  }

  function makeCar(THREE, group, point, index) {
    const paint = [0x50616b, 0x8b9da8, 0x9b3d32, 0xc7c7bf, 0x344b65, 0x8a7567, 0x455c55, 0xe0d0a9][index % 8];
    const body = new THREE.MeshStandardMaterial({ color: paint, roughness: 0.48, metalness: 0.25 });
    const glass = new THREE.MeshStandardMaterial({ color: 0x263b48, roughness: 0.22, metalness: 0.3 });
    const dark = new THREE.MeshStandardMaterial({ color: 0x171c22, roughness: 0.8 });
    const lights = new THREE.MeshBasicMaterial({ color: 0xf4d6a2 });
    const car = new THREE.Group();
    car.name = 'Parked vehicle ' + (index + 1);
    car.position.set(point.x, 0, point.z);
    car.rotation.y = 2.36;
    addBox(THREE, car, 'Vehicle body', 0, 0.58, 0, 1.8, 0.66, 4.1, body);
    addBox(THREE, car, 'Vehicle cabin', 0, 1.15, -0.16, 1.55, 0.61, 2.18, glass);
    [-0.96, 0.96].forEach(x => [-1.27, 1.26].forEach(z => {
      const wheel = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.35, 0.16, 12), dark);
      wheel.rotation.z = Math.PI / 2;
      wheel.position.set(x, 0.33, z);
      car.add(wheel);
    }));
    [-0.58, 0.58].forEach(x => addBox(THREE, car, 'Vehicle headlight', x, 0.61, -2.08, 0.28, 0.14, 0.08, lights));
    group.add(car);
  }

  function createParking(THREE, world) {
    const lot = world && world.parkingLot;
    if (!lot || !Array.isArray(lot.polygon) || lot.polygon.length < 3) return null;
    const root = new THREE.Group();
    root.name = 'Station East surface lot / 555 W Cordova (approximate)';
    const asphalt = new THREE.MeshStandardMaterial({ color: 0xc6c9c8, map:asphaltTexture(THREE), roughness: 0.88, metalness: 0.035 });
    const edge = new THREE.MeshStandardMaterial({ color: 0x939897, roughness: 0.89 });
    const stripe = new THREE.MeshBasicMaterial({ color: 0xe6d79a });
    const yellow = new THREE.MeshBasicMaterial({ color: 0xffd057 });
    const iron = new THREE.MeshStandardMaterial({ color: 0x363e43, roughness: 0.65, metalness: 0.22 });
    root.add(polygonMesh(THREE, lot.polygon, asphalt, 0.19));
    for (let i = 0; i < lot.polygon.length; i++) {
      const a=lot.polygon[i], b=lot.polygon[(i+1)%lot.polygon.length];
      if (i === 0) {
        // Open a visible 12m driveway in the Cordova-facing curb.
        const along=t=>({x:a.x+(b.x-a.x)*t,z:a.z+(b.z-a.z)*t});
        addSegment(THREE,root,'Cordova driveway curb',a,along(0.35),0.24,0.13,edge);
        addSegment(THREE,root,'Cordova driveway curb',along(0.65),b,0.24,0.13,edge);
      } else {
        addSegment(THREE, root, 'Lot perimeter curb', a, b, 0.24, 0.13, edge);
      }
    }
    // Keep the central foot route completely clear, rather than planting a
    // car in front of the player's path to the fence.
    const v={x:0.710,z:0.704};
    const stalls=parkingCars(lot);
    stalls.forEach((p,index)=>{
      makeCar(THREE,root,p,index);
      const a={x:p.x-v.x*2.45,z:p.z-v.z*2.45};
      const b={x:p.x+v.x*2.45,z:p.z+v.z*2.45};
      addSegment(THREE,root,'Painted parking bay',a,b,0.275,0.07,stripe);
      addBox(THREE,root,'Concrete parking wheel stop',p.x+v.x*2.0,0.35,p.z+v.z*2.0,1.5,0.27,0.29,edge,2.36);
    });
    // Parking equipment belongs at the Cordova entrance, not 100 metres
    // north of the lot (an old sign-position bug).
    const gate=entranceFor(lot);
    addBox(THREE,root,'Lot pay station',gate.x-3.8,1.0,gate.z+1.1,0.8,2,0.65,iron);
    addBox(THREE,root,'Pay screen',gate.x-3.8,1.4,gate.z+1.47,0.53,0.5,0.05,
      new THREE.MeshBasicMaterial({color:0x59a6b9}));
    for(const offset of [-6.2,6.2])addCylinder(THREE,root,'Parking entrance bollard',
      gate.x+offset*0.61,0.65,gate.z+offset*0.79,0.13,1.3,yellow);
    sign(THREE,root,'P  STATION EAST',{x:gate.x-5.0,y:2.6,z:gate.z+3.6},
      5.7,1.05,0.7,{background:'#164b73'});
    addHarbourFence(THREE,root,lot);
    root.userData.provenance=lot.source;
    root.userData.parkedCars=stalls.length;
    root.userData.walkableFromCordova=true;
    root.userData.fenceLookout=true;
    return root;
  }

  function facadeOnEdge(THREE, root, a, b, rows, glass, stone, trim) {
    const dx=b.x-a.x, dz=b.z-a.z, length=Math.hypot(dx,dz);
    if(length<3) return;
    const yaw = Math.atan2(-dz, dx);
    const count = Math.max(2, Math.floor(length / 3.9));
    for(let level=0; level<rows; level++){
      const y=5.0+level*2.75;
      for(let i=0;i<count;i++){
        const t=(i+0.5)/count;
        const x=a.x+dx*t, z=a.z+dz*t;
        addBox(THREE,root,'The Landing warehouse sash window',x,y,z,2.0,1.62,0.15,glass,yaw);
        addBox(THREE,root,'Stone window sill',x,y-0.88,z,2.35,0.16,0.26,stone,yaw);
        if(i===0 || i===count-1) addBox(THREE,root,'Brick-window pier',x,y,z,0.16,2.15,0.22,trim,yaw);
      }
    }
    addSegment(THREE,root,'The Landing parapet cornice',a,b,23.9,0.54,stone);
  }
  function createLanding(THREE, world) {
    const landing=world && world.landing;
    if(!landing || !Array.isArray(landing.footprint) || landing.footprint.length<3) return null;
    const root = new THREE.Group();
    root.name = 'The Landing / Kelly-Douglas Warehouse at 375 Water St';
    const brick = new THREE.MeshStandardMaterial({ color: 0xa45b48, roughness: 0.87, emissive:0x2e100a, emissiveIntensity:0.25 });
    const stone = new THREE.MeshStandardMaterial({ color:0xd3c7b5, roughness:0.87 });
    const trim = new THREE.MeshStandardMaterial({ color:0x855144, roughness:0.88 });
    const glass = new THREE.MeshStandardMaterial({ color:0x5b8290, roughness:0.3, metalness:0.26, emissive:0x234354, emissiveIntensity:0.3 });
    const shape = new THREE.Shape();
    landing.footprint.forEach((p,i) => i ? shape.lineTo(p.x,-p.z):shape.moveTo(p.x,-p.z));
    shape.closePath();
    const geom = new THREE.ExtrudeGeometry(shape, { depth:landing.height||24, bevelEnabled:false });
    geom.rotateX(-Math.PI/2);
    const mass = new THREE.Mesh(geom, brick);
    mass.name = 'The Landing seven-storey civic footprint';
    root.add(mass);
    const pts=landing.footprint;
    // West/parking and northern heritage warehouse walls. Dimensions of the
    // modeled floors remain approximate; the underlying outline is civic data.
    facadeOnEdge(THREE,root,pts[0],pts[1],6,glass,stone,trim);
    facadeOnEdge(THREE,root,pts[1],pts[4],6,glass,stone,trim);
    facadeOnEdge(THREE,root,pts[5],pts[8],6,glass,stone,trim);
    const a=pts[0], b=pts[1], center={x:(a.x+b.x)/2,y:4.0,z:(a.z+b.z)/2};
    sign(THREE,root,'THE LANDING',center,Math.min(7,Math.hypot(b.x-a.x,b.z-a.z)*0.8),0.9,Math.atan2(- (b.x-a.x), b.z-a.z),{background:'#5d3026'});
    root.userData.provenance = landing.source;
    return root;
  }

  function create(THREE, world) {
    const group=new THREE.Group();
    group.name='Waterfront Station / surface lot / The Landing public realm';
    const lot=createParking(THREE,world);
    if(lot) group.add(lot);
    const landing=createLanding(THREE,world);
    if(landing) group.add(landing);
    return group;
  }

  window.GastownWaterfrontPlaces=Object.freeze({ reference:REFERENCE, create, createParking, createLanding, inPolygon, isWalkableLot, entranceFor, lookoutFor, parkingCars });
})(window);

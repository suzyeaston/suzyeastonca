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
    // Standing 2.6m from the fence made the steel posts fill the lens.
    // Move the cinematic lookout into the open central aisle to reveal the
    // trains, SeaBus terminal and inlet through the railings.
    return {x:middle.x+6.8*toward.x/n,z:middle.z+6.8*toward.z/n};
  }
  // Stalls run across the two parking rows. Car lengths must follow v
  // (into the parking stall), not u (along the entire row).
  const PARKED_CAR_YAW = Math.atan2(0.710, 0.704);
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
    const yaw=PARKED_CAR_YAW, cos=Math.cos(yaw), sin=Math.sin(yaw);
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
      pitch:-0.012, // level harbour horizon with enough down-angle for the inlet
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
        // The reference has tightly spaced black metal pickets. The former
        // three-rail outline read as an empty industrial handrail.
        if(j<n){
          for(let k=1;k<=5;k++){
            const t2=(j+k/6)/n;
            addCylinder(THREE,root,'Waterfront fence vertical black picket',
              a.x+(b.x-a.x)*t2,1.12,a.z+(b.z-a.z)*t2,0.028,2.02,postMat);
          }
        }
      }
      for(const y of [0.46,1.35,2.12]){
        addSegment(THREE,root,'Rail-yard lookout open metal fence rail',a,b,y,0.065,railMat);
      }
    }
    const view=fenceLookoutFor(lot);
    // A low viewing marker establishes the experience without hiding the harbour.
    addCylinder(THREE,root,'Harbour view wayfinding post',view.x+2,0.82,view.z+1,0.08,1.58,postMat);
    sign(THREE,root,'RAIL YARD / BURRARD INLET',{x:view.x+7.3,y:1.44,z:view.z+1.6},1.92,0.31,-0.64,
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
    car.rotation.y = PARKED_CAR_YAW;
    addBox(THREE, car, 'Vehicle body', 0, 0.58, 0, 1.8, 0.66, 4.1, body);
    addBox(THREE, car, 'Vehicle cabin', 0, 1.15, -0.16, 1.55, 0.61, 2.18, glass);
    [-0.96, 0.96].forEach(x => [-1.27, 1.26].forEach(z => {
      const wheel = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.35, 0.16, 12), dark);
      wheel.rotation.z = Math.PI / 2;
      wheel.position.set(x, 0.33, z);
      car.add(wheel);
    }));
    [-0.58, 0.58].forEach(x => {
      addBox(THREE, car, 'Vehicle headlight', x, 0.61, -2.08, 0.28, 0.14, 0.08, lights);
      addBox(THREE, car, 'Vehicle rear lamp', x, 0.59, 2.08, 0.3, 0.12, 0.09,
        new THREE.MeshBasicMaterial({color:0x8f3034}));
    });
    addBox(THREE,car,'Vehicle front bumper',0,0.35,-2.12,1.72,0.22,0.16,dark);
    addBox(THREE,car,'Vehicle rear bumper',0,0.35,2.12,1.72,0.22,0.16,dark);
    group.add(car);
  }

  // Reference-led, deliberately approximate street furniture. The yellow
  // barricades, driveway gantry, speed sign and painted markings are what make
  // this an identifiable Vancouver parking lot instead of a grey rectangle.
  // All objects stay at the perimeter, outside the clear entrance-to-fence walk.
  function addLotReferenceDetails(THREE, root, lot) {
    const yellow = new THREE.MeshStandardMaterial({color:0xf5bf2c,roughness:0.85});
    const wornYellow = new THREE.MeshStandardMaterial({color:0xc49b24,roughness:0.96});
    const concrete = new THREE.MeshStandardMaterial({color:0xa6a8a3,roughness:0.96});
    const iron = new THREE.MeshStandardMaterial({color:0x353d43,roughness:0.63,metalness:0.34});
    const white = new THREE.MeshBasicMaterial({color:0xe2e1d6});
    const dark = new THREE.MeshBasicMaterial({color:0x31353a});
    const start = entranceFor(lot), end = fenceLookoutFor(lot);
    const sideA=lot.polygon[0], sideB=lot.polygon[1];
    const length=Math.hypot(sideB.x-sideA.x,sideB.z-sideA.z)||1;
    const axis={x:(sideB.x-sideA.x)/length,z:(sideB.z-sideA.z)/length};
    const toCenter={x:lot.center.x-start.x,z:lot.center.z-start.z};
    const inwardLength=Math.hypot(toCenter.x,toCenter.z)||1;
    const inward={x:toCenter.x/inwardLength,z:toCenter.z/inwardLength};
    const point=(along,inside)=>({x:start.x+axis.x*along+inward.x*inside,
      z:start.z+axis.z*along+inward.z*inside});

    // Two upright columns and a raised overhead 2.0 m clearance structure.
    // The driving corridor remains open: NO horizontal ground-level gate arm.
    const left=point(-5.0,1.2),right=point(5.0,1.2);
    for(const [i,p] of [left,right].entries()){
      addBox(THREE,root,'Station East yellow gantry footing',p.x,0.29,p.z,0.78,0.5,0.78,concrete);
      addBox(THREE,root,'Station East clearance gantry upright',p.x,1.42,p.z,0.15,2.7,0.15,iron);
      addBox(THREE,root,'Reflective yellow gantry sleeve',p.x,0.85,p.z,0.23,0.9,0.23,yellow);
    }
    addSegment(THREE,root,'Station East overhead 2m clearance gantry',left,right,2.75,0.18,iron);
    const gantryMid=point(0,1.2);
    addSegment(THREE,root,'Station East yellow warning header',point(-2.4,1.2),point(2.4,1.2),2.56,0.39,yellow);
    const signYaw=Math.atan2(-inward.x,-inward.z);
    sign(THREE,root,'MAX HEIGHT 2.00m',{x:gantryMid.x,y:2.6,z:gantryMid.z},4.65,0.29,signYaw,
      {background:'#f5c432',color:'#171a1c'});
    const speed=point(-6.2,1.1);
    addCylinder(THREE,root,'8 km/h speed sign pole',speed.x,1.35,speed.z,0.055,2.5,iron);
    sign(THREE,root,'8 km/h',{x:speed.x,y:2.5,z:speed.z},1.0,0.85,signYaw,
      {background:'#f2f3e9',color:'#171b20'});
    const warning=point(5.8,1.3);
    sign(THREE,root,'WARNING',{x:warning.x,y:2.15,z:warning.z},1.25,0.56,signYaw,
      {background:'#f2ce42',color:'#202124'});

    // Painted entry arrows and yellow curb hatching reference the real photos.
    for(const inside of [4,9]){
      const tail=point(0,inside+2),tip=point(0,inside-0.9);
      addSegment(THREE,root,'Lot inbound white painted arrow stem',tail,tip,0.23,0.14,white);
      const l={x:tip.x+axis.x*0.9+inward.x*1.25,z:tip.z+axis.z*0.9+inward.z*1.25};
      const r={x:tip.x-axis.x*0.9+inward.x*1.25,z:tip.z-axis.z*0.9+inward.z*1.25};
      addSegment(THREE,root,'Lot inbound painted arrow head',tip,l,0.23,0.14,wornYellow);
      addSegment(THREE,root,'Lot inbound painted arrow head',tip,r,0.23,0.14,wornYellow);
    }
    for(let i=-3;i<=3;i++){
      const a=point(i*0.75,2.0),b=point(i*0.75+1.9,5.1);
      if(i%2===0)addSegment(THREE,root,'Station East entry yellow hatching',a,b,0.232,0.095,wornYellow);
    }

    // Short yellow Jersey barriers sit beside the harbour fence. They avoid
    // the centre walkway and cannot be mistaken for port pedestrian access.
    let count=0;
    for(const edgeIndex of [3,4,5]){
      const a=lot.polygon[edgeIndex],b=lot.polygon[(edgeIndex+1)%lot.polygon.length];
      const n=Math.max(1,Math.floor(Math.hypot(b.x-a.x,b.z-a.z)/5.5));
      const yaw=Math.atan2(-(b.z-a.z),b.x-a.x);
      for(let j=0;j<n;j++){
        const t=(j+0.5)/n,p={x:a.x+(b.x-a.x)*t,z:a.z+(b.z-a.z)*t};
        if(distSegment(p,start,end)<6.5 || Math.hypot(p.x-end.x,p.z-end.z)<5.2)continue;
        const span=Math.min(3.0,Math.hypot(b.x-a.x,b.z-a.z)/n-0.45);
        if(span<0.8)continue;
        addBox(THREE,root,'Yellow waterfront concrete barricade',p.x,0.48,p.z,span,0.78,0.66,yellow,yaw);
        addBox(THREE,root,'Barricade scuffed concrete foot',p.x,0.16,p.z,span+0.16,0.2,0.9,concrete,yaw);
        addBox(THREE,root,'Barricade worn horizontal stripe',p.x,0.64,p.z,span-0.08,0.055,0.68,wornYellow,yaw);
        count++;
      }
    }
    // Small striped wheel stops at the edge of the driveway (not on its path).
    for(const side of [-1,1]){
      const p=point(side*7.2,4.2);
      addBox(THREE,root,'Yellow parking wheel stop',p.x,0.27,p.z,1.55,0.25,0.36,yellow,
        Math.atan2(-axis.z,axis.x));
    }
    root.userData.yellowBarricades=count;
    root.userData.referenceProps='painted bays / open gantry / 8 kmh / yellow concrete barriers';
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
      addBox(THREE,root,'Concrete parking wheel stop',p.x+v.x*2.0,0.35,p.z+v.z*2.0,1.5,0.27,0.29,edge,PARKED_CAR_YAW);
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
    addLotReferenceDetails(THREE,root,lot);
    root.userData.provenance=lot.source;
    root.userData.parkedCars=stalls.length;
    root.userData.walkableFromCordova=true;
    root.userData.fenceLookout=true;
    return root;
  }

  function facadeOnEdge(THREE, root, a, b, rows, glass, stone, trim) {
    const dx=b.x-a.x, dz=b.z-a.z, length=Math.hypot(dx,dz);
    if(length<7) return;
    // Historic 1905 Kelly-Douglas: individually spaced factory sash windows,
    // stone lintels and deep horizontal cornice, never blue office curtain wall.
    const yaw=Math.atan2(-dz,dx);
    const count=Math.max(2,Math.floor(length/3.9));
    for(let level=0;level<rows;level++){
      const y=5.05+level*2.78;
      for(let i=0;i<count;i++){
        const t=(i+0.5)/count, x=a.x+dx*t, z=a.z+dz*t;
        addBox(THREE,root,'The Landing warehouse sash window',x,y,z,2.0,1.72,0.15,glass,yaw);
      }
      // Continuous limestone courses read clearly at camera distance and
      // save hundreds of individual draw calls on an 8 GB machine.
      addSegment(THREE,root,'Limestone sill course',a,b,y-0.94,0.16,stone);
      addSegment(THREE,root,'Limestone lintel course',a,b,y+0.95,0.13,trim);
    }
    addSegment(THREE,root,'The Landing ground-floor masonry stringcourse',a,b,4.03,0.22,stone);
    addSegment(THREE,root,'The Landing parapet cornice',a,b,23.9,0.54,stone);
  }
  function createLanding(THREE, world) {
    const landing=world && world.landing;
    if(!landing || !Array.isArray(landing.footprint) || landing.footprint.length<3) return null;
    const root = new THREE.Group();
    root.name = 'The Landing / Kelly-Douglas Warehouse at 375 Water St';
    // The Kelly-Douglas/Steamworks block is pale weathered masonry in the
    // user's Cordova Street View, not a second red-brick station.
    const brick = new THREE.MeshStandardMaterial({ color: 0xaaa79d, roughness: 0.91 });
    const stone = new THREE.MeshStandardMaterial({ color:0xc9c8bf, roughness:0.85 });
    const trim = new THREE.MeshStandardMaterial({ color:0x8d8a80, roughness:0.88 });
    const sashCanvas=window.document&&window.document.createElement('canvas');
    let sashTexture=null;
    if(sashCanvas) {
      sashCanvas.width=128;sashCanvas.height=160;
      const ctx=sashCanvas.getContext('2d');
      if(ctx){
        ctx.fillStyle='#284353';ctx.fillRect(0,0,128,160);
        ctx.fillStyle='#66879b';ctx.fillRect(4,5,21,150);ctx.fillRect(78,5,12,150);
        ctx.fillStyle='#334751';ctx.fillRect(25,5,53,150);
        ctx.fillStyle='#b5b7ac';
        ctx.fillRect(0,0,8,160);ctx.fillRect(120,0,8,160);
        ctx.fillRect(60,0,6,160);ctx.fillRect(0,77,128,6);ctx.fillRect(0,151,128,9);
        sashTexture=new THREE.CanvasTexture(sashCanvas);
        sashTexture.colorSpace=THREE.SRGBColorSpace;
      }
    }
    const glass = new THREE.MeshStandardMaterial({
      color:0xb1bec4,map:sashTexture,roughness:0.37,metalness:0.14,
      emissive:0x162a39,emissiveIntensity:0.12
    });
    const shape = new THREE.Shape();
    landing.footprint.forEach((p,i) => i ? shape.lineTo(p.x,-p.z):shape.moveTo(p.x,-p.z));
    shape.closePath();
    const geom = new THREE.ExtrudeGeometry(shape, { depth:landing.height||24, bevelEnabled:false });
    geom.rotateX(-Math.PI/2);
    const mass = new THREE.Mesh(geom, brick);
    mass.name = 'The Landing seven-storey civic footprint';
    root.add(mass);
    const pts=landing.footprint;
    // Respect every actual perimeter edge: spanning [1,4] used to stretch
    // fake windows across polygon corners, leaving the true road facade blank.
    for(let edge=0;edge<pts.length;edge++){
      facadeOnEdge(THREE,root,pts[edge],pts[(edge+1)%pts.length],6,glass,stone,trim);
    }
    // The Water Street frontage holds the arched entry visible in photographs
    // of 375 Water. This is an illustrative doorway on a GIS-backed wall, not
    // a claim about surveyed entrance coordinates or interior floor plans.
    const frontA=pts[6], frontB=pts[7];
    const fdx=frontB.x-frontA.x, fdz=frontB.z-frontA.z;
    const flength=Math.hypot(fdx,fdz)||1;
    const fyaw=Math.atan2(-fdz,fdx);
    const entry={x:(frontA.x+frontB.x)*0.5+fdz/flength*0.94,
      z:(frontA.z+frontB.z)*0.5-fdx/flength*0.94};
    const darkEntry=new THREE.MeshStandardMaterial({color:0x243e48,transparent:true,opacity:0.25,depthWrite:false,depthTest:false,roughness:0.18,metalness:0.16});
    const bronze=new THREE.MeshStandardMaterial({color:0x464c45,roughness:0.7,metalness:0.32});
    const entrancePane=addBox(THREE,root,'The Landing arched Water Street entry glazing',
      entry.x,1.56,entry.z,2.7,3.0,0.18,darkEntry,fyaw);
    entrancePane.renderOrder=4;
    // The doorway is a visual portal in the solid city mesh. Keep its
    // glazing almost clear and allow the automatic transition BEFORE the
    // player reaches the solid building footprint.
    // Keep the center of the entrance visually unobstructed.
    // A central mullion in the earlier mockup looked like a sealed door.
    const tangent={x:fdx/flength,z:fdz/flength};
    const outward={x:fdz/flength,z:-fdx/flength};
    for(const side of [-1,1]){
      addBox(THREE,root,'The Landing double-door brass pull handle',
        entry.x+tangent.x*side*0.22+outward.x*0.18,1.32,
        entry.z+tangent.z*side*0.22+outward.z*0.18,
        0.048,0.52,0.065,new THREE.MeshStandardMaterial({color:0xc9a563,metalness:0.7,roughness:0.24}),fyaw);
    }
    addBox(THREE,root,'The Landing carved golden 375 lintel',
      entry.x,3.29,entry.z,3.2,0.22,0.24,
      new THREE.MeshStandardMaterial({color:0xbb9a61,metalness:0.45,roughness:0.48}),fyaw);
    for(const offset of [-1.5,1.5])addBox(THREE,root,
      'The Landing limestone door jambs',
      entry.x+tangent.x*offset,1.72,entry.z+tangent.z*offset,
      0.3,3.45,0.34,stone,fyaw);
    // The lobby portal is activated on the sidewalk *before* entering the
    // solid warehouse mass. Do not ever teleport the avatar into its mesh.
    if (typeof THREE.TorusGeometry === 'function') {
      const arch=new THREE.Mesh(new THREE.TorusGeometry(1.38,0.13,7,24,Math.PI),stone);
      arch.name='The Landing entrance arch';
      arch.rotation.y=fyaw;
      arch.position.set(entry.x,3.05,entry.z);
      root.add(arch);
    }
    // The Landing's arched sidewalk canopy is unmistakable in the user's
    // Water Street reference. Use curved metal ribs and a translucent roof,
    // not a generic flat floating storefront awning.
    if(typeof THREE.TorusGeometry==='function'){
      const canopyFrame=new THREE.MeshStandardMaterial({
        color:0x303638,roughness:0.56,metalness:0.52
      });
      const canopyGlazing=new THREE.MeshStandardMaterial({
        color:0xa4b9b5,transparent:true,opacity:0.25,
        roughness:0.26,metalness:0.08,side:THREE.DoubleSide,depthWrite:false
      });
      const outward={x:fdz/flength,z:-fdx/flength};
      for(let rib=0;rib<6;rib++){
        const distance=0.45+rib*0.55;
        const arc=new THREE.Mesh(new THREE.TorusGeometry(1.42,0.05,6,18,Math.PI),canopyFrame);
        arc.name='The Landing sidewalk barrel canopy curved iron rib';
        arc.position.set(entry.x+outward.x*distance,4.42,entry.z+outward.z*distance);
        arc.rotation.y=fyaw;root.add(arc);
      }
      for(let strip=-2;strip<=2;strip++){
        const panel=addBox(THREE,root,
          'The Landing translucent overhead canopy glazing',
          entry.x+outward.x*1.8,4.85+0.48*Math.cos(strip*0.45),
          entry.z+outward.z*1.8,0.55,0.045,3.0,canopyGlazing,fyaw);
        panel.userData.approximate=true;
      }
      for(const t of [-1.24,1.24]){
        const support=new THREE.Mesh(new THREE.CylinderGeometry(0.055,0.065,3.8,8),canopyFrame);
        support.name='The Landing Victorian canopy sidewalk support';
        support.position.set(entry.x+outward.x*2.7 + fdx/flength*t,1.9,
          entry.z+outward.z*2.7 + fdz/flength*t);
        root.add(support);
      }
    }
    sign(THREE,root,'THE LANDING',
      {x:entry.x,y:4.48,z:entry.z},3.65,0.48,fyaw+Math.PI,
      {background:'#384741',color:'#f9f2db'});
    // Keep exterior historic; the speculative lab exists only inside the game.
    const a=pts[0], b=pts[1], center={x:(a.x+b.x)/2,y:4.0,z:(a.z+b.z)/2};
    sign(THREE,root,'THE LANDING',center,Math.min(7,Math.hypot(b.x-a.x,b.z-a.z)*0.8),0.9,
      Math.atan2(-(b.x-a.x),b.z-a.z),{background:'#5d5148'});
    sign(THREE,root,'STEAMWORKS',{x:center.x,y:2.7,z:center.z},8.7,0.9,
      Math.atan2(-(b.x-a.x),b.z-a.z),{background:'#6d3028',color:'#f1d1af'});
    // Low Steamworks patio enclosure on the parking-facing edge, anchored
    // to the existing warehouse facade rather than to an invented building.
    const toward=world && world.parkingLot ? world.parkingLot.center : center;
    const dx=toward.x-center.x,dz=toward.z-center.z;
    const d=Math.hypot(dx,dz)||1;
    const p={x:center.x+dx/d*3.0,z:center.z+dz/d*3.0};
    const patioYaw=Math.atan2(-(b.z-a.z),b.x-a.x);
    const awning=new THREE.MeshStandardMaterial({color:0x996b43,roughness:0.85});
    const frame=new THREE.MeshStandardMaterial({color:0x30373a,roughness:0.72,metalness:0.25});
    addBox(THREE,root,'Steamworks patio copper awning',p.x,2.88,p.z,8.5,0.22,3.2,awning,patioYaw);
    for(const offset of [-3.6,3.6]){
      const axis={x:(b.x-a.x)/Math.hypot(b.x-a.x,b.z-a.z),z:(b.z-a.z)/Math.hypot(b.x-a.x,b.z-a.z)};
      const px=p.x+axis.x*offset,pz=p.z+axis.z*offset;
      addBox(THREE,root,'Steamworks outdoor patio frame',px,1.36,pz,0.14,2.7,0.14,frame);
    }
    root.userData.provenance = landing.source;
    return root;
  }

  // An optional fictional sixth-floor room, spatially separate from the GIS
  // street. No real office floor plan or claim of public access is implied.
  function landingEntranceFor(world) {
    const pts=world && world.landing && world.landing.footprint;
    if(!pts || pts.length<8)return null;
    const a=pts[6],b=pts[7],dx=b.x-a.x,dz=b.z-a.z,len=Math.hypot(dx,dz)||1;
    const outward={x:dz/len,z:-dx/len};
    const door={x:(a.x+b.x)*0.5+outward.x*0.94,z:(a.z+b.z)*0.5+outward.z*0.94};
    return {door,threshold:{x:door.x+outward.x*4.0,z:door.z+outward.z*4.0},
      outward,lobbySpawn:{x:850,z:858},lobbyExit:{x:850,z:859},
      elevatorLobby:{x:845,z:851},elevatorCar:{x:880,z:850},
      roomSpawn:{x:800,z:808},roomExit:{x:800,z:809.2}};
  }

  // One small, photo-informed, low-poly likeness for the street, lobby and lab.
  // Illustrated NPC only: no portrait texture, scanned mesh or voice imitation.
  // The negative Z direction is the character's face (as in the original rig).
  function createKrisAvatar(THREE) {
    const avatar=new THREE.Group();
    avatar.name='KK | photo-inspired fictional Gastown greeter';
    const fabric=(color,roughness=0.92)=>new THREE.MeshStandardMaterial({color,roughness});
    const black=fabric(0x17191b),shirt=fabric(0x252528);
    const vest=fabric(0x8a9294),vestShade=fabric(0x596366);
    const pocket=fabric(0x3b4244),pants=fabric(0x151619);
    const skin=fabric(0xb87c60),ink=fabric(0x393434);
    const beard=fabric(0x29231f),beardHi=fabric(0x413730);
    const metal=new THREE.MeshStandardMaterial({color:0x858f91,metalness:0.55,roughness:0.48});
    const bracelet=fabric(0x3c8883);
    const eye=fabric(0x1e1b1a);
    function add(name,geometry,material,x,y,z) {
      const mesh=new THREE.Mesh(geometry,material);
      mesh.name=name;mesh.position.set(x,y,z);
      avatar.add(mesh);
      return mesh;
    }
    function box(name,x,y,z,w,h,d,mat) {
      return add(name,new THREE.BoxGeometry(w,h,d),mat,x,y,z);
    }
    function rounded(name,x,y,z,sx,sy,sz,mat) {
      const mesh=add(name,new THREE.SphereGeometry(1,10,8),mat,x,y,z);
      mesh.scale.set(sx,sy,sz);
      return mesh;
    }
    function tube(name,x,y,z,top,bottom,height,mat) {
      return add(name,new THREE.CylinderGeometry(top,bottom,height,9),mat,x,y,z);
    }

    // Black shoes and trousers, rather than the previous all-denim outfit.
    for (const side of [-1,1]) {
      box('KK black trouser leg',side*0.19,0.51,0,0.27,0.91,0.29,pants);
      box('KK black shoe',side*0.19,0.085,-0.08,0.30,0.17,0.43,black);
    }
    box('KK dark shirt',0,1.30,0,0.73,0.78,0.37,shirt);
    box('KK grey vest back',0,1.31,0.225,0.79,0.77,0.09,vestShade);
    for (const side of [-1,1]) {
      box('KK open grey sleeveless vest front',side*0.245,1.31,-0.22,0.27,0.74,0.105,vest);
      box('KK contrasting vest seam',side*0.107,1.33,-0.283,0.026,0.70,0.02,vestShade);
      box('KK dark vest pocket',side*0.255,1.10,-0.288,0.19,0.18,0.024,pocket);
      box('KK vest pocket flap',side*0.255,1.21,-0.309,0.20,0.035,0.026,vestShade);
      // Short sleeves reveal inked forearms, based on the supplied image.
      rounded('KK dark sleeve',side*0.49,1.49,0,0.19,0.20,0.19,shirt);
      tube('KK tattooed forearm',side*0.51,1.17,0,0.115,0.090,0.52,skin);
      for (let i=0;i<4;i++) {
        tube('KK tattoo bands',side*0.51,1.00+i*0.105,-0.004,0.118,0.115,0.024,ink);
      }
      // Geometric ink strokes, a readable hint rather than a copied tattoo.
      box('KK forearm geometric tattoo',side*0.51,1.20,-0.115,0.09,0.20,0.014,ink);
      tube('KK turquoise wrist bracelet',side*0.51,0.91,0,0.111,0.111,0.048,bracelet);
      rounded('KK hand',side*0.51,0.81,-0.015,0.105,0.12,0.10,skin);
    }
    box('KK neck',0,1.75,0,0.19,0.23,0.20,skin);
    rounded('KK portrait head',0,1.95,0,0.263,0.285,0.240,skin);
    rounded('KK nose',0,1.94,-0.238,0.055,0.075,0.055,skin);
    for(const side of [-1,1]){
      rounded('KK eye behind spectacles',side*0.125,2.005,-0.241,0.025,0.023,0.015,eye);
      rounded('KK thick beard cheek',side*0.15,1.78,-0.177,0.13,0.17,0.13,beard);
      // Real individual eyeglass rims rather than one bar across the face.
      const cx=side*0.135,front=-0.283;
      box('KK rectangular eyeglass upper rim',cx,2.066,front,0.225,0.027,0.035,black);
      box('KK rectangular eyeglass lower rim',cx,1.927,front,0.225,0.024,0.035,black);
      for(const dx of [-0.112,0.112])box('KK rectangular eyeglass side rim',cx+dx,1.996,front,0.023,0.15,0.035,black);
      box('KK glasses temple',side*0.241,2.025,-0.145,0.03,0.027,0.24,black);
      rounded('KK moustache half',side*0.073,1.825,-0.253,0.09,0.046,0.065,beardHi);
    }
    box('KK glasses bridge',0,2.018,-0.285,0.065,0.029,0.04,black);
    // Long, full beard reaches the shirt, with a shaped rather than cubic tip.
    rounded('KK full beard upper',0,1.705,-0.185,0.212,0.18,0.18,beard);
    tube('KK long pointed beard',0,1.52,-0.207,0.178,0.072,0.43,beard);
    rounded('KK beard tip',0,1.317,-0.207,0.075,0.060,0.075,beardHi);

    // Low black cap worn backwards; its small bill faces positive Z.
    rounded('KK black cap crown',0,2.185,0.020,0.268,0.102,0.247,black);
    box('KK cap band',0,2.139,0.015,0.43,0.048,0.40,black);
    const bill=rounded('KK backwards cap bill',0,2.125,0.259,0.205,0.025,0.17,black);
    bill.rotation.x=-0.09;
    box('KK vest zip pull',0,1.58,-0.299,0.028,0.088,0.018,metal);
    avatar.userData.photoInspired=true;
    return avatar;
  }

  function createLabInterior(THREE) {
    const root=new THREE.Group();
    root.name='Fictional Multimodal Lab | sixth-floor imagination at The Landing';
    const wall=new THREE.MeshStandardMaterial({color:0xd5c7ae,roughness:0.91});
    const cream=new THREE.MeshStandardMaterial({color:0xf4f2ec,roughness:0.86});
    const oak=new THREE.MeshStandardMaterial({color:0xc89c66,roughness:0.83});
    const dark=new THREE.MeshStandardMaterial({color:0x222a2f,roughness:0.48});
    const blue=new THREE.MeshBasicMaterial({color:0x93bbcd,side:THREE.DoubleSide});
    const mountain=new THREE.MeshBasicMaterial({color:0x557383,side:THREE.DoubleSide});
    const glass=new THREE.MeshStandardMaterial({color:0x9ec9d6,transparent:true,opacity:0.3,roughness:0.2,depthWrite:false});
    const wood=new THREE.MeshStandardMaterial({color:0xb98c60,roughness:0.79});
    const denim=new THREE.MeshStandardMaterial({color:0x36536c,roughness:0.92}); // seated lab coder's trousers
    const skin=new THREE.MeshStandardMaterial({color:0xc99372,roughness:0.92});
    const green=new THREE.MeshStandardMaterial({color:0x3d614a,roughness:0.89});
    function cube(name,x,y,z,w,h,d,mat) {
      const m=new THREE.Mesh(new THREE.BoxGeometry(w,h,d),mat);
      m.name=name;m.position.set(x,y,z);root.add(m);return m;
    }
    cube('Multimodal Lab sixth-floor timber floor',800,-0.16,800,16,0.3,20,oak);
    cube('Multimodal Lab pale left wall',792.1,3.7,800,0.28,7.4,20,cream);
    cube('Multimodal Lab pale right wall',807.9,3.7,800,0.28,7.4,20,cream);
    cube('Multimodal Lab entry wall',800,3.7,810,16,7.4,0.25,cream);
    cube('Multimodal Lab timber ceiling',800,7.35,800,16,0.26,20,oak);
    for(let z=791;z<=808;z+=2.1)cube('Multimodal Lab exposed wood ceiling beam',800,7.04,z,15.6,0.25,0.31,oak);
    for(let z=793;z<=806;z+=4.2)cube('Multimodal Lab suspended linear light',800,6.65,z,6.7,0.095,0.14,
      new THREE.MeshBasicMaterial({color:0xfff5db}));
    for(const x of [795.5,804.5])for(const z of [798,805])cube('Multimodal Lab white timber support column',
      x,3.2,z,0.25,6.4,0.25,cream);
    cube('Multimodal Lab glowing media wall',805.9,3.3,802.2,0.15,2.3,3.9,
      new THREE.MeshBasicMaterial({color:0x327cae}));
    sign(THREE,root,'MML  ·  Multimodal Media Lab',
      {x:805.77,y:4.82,z:804.8},3.5,0.58,Math.PI/2,
      {background:'#f5f4f1',color:'#236ca2'});
    for(const z of [796,801,806])for(const x of [793.2,806.8])cube('Sixth floor gallery pilaster',x,3.65,z,0.44,7.3,0.5,wall);
    cube('Window wall lower railing',800,0.6,790.1,16,1.15,0.24,cream);
    cube('Panoramic North Shore view',800,4.3,790.08,15.2,5.8,0.03,blue);
    // A graphical mountains-and-harbour silhouette, not a photo texture.
    for(let i=0;i<8;i++)cube('North Shore mountain ridge silhouette',793+i*1.95,1.7+(i%3)*0.28,790.01,2.15,1.6+(i%3)*0.42,0.05,mountain);
    cube('Burrard Inlet blue horizon',800,1.38,789.98,15.1,0.36,0.07,blue);
    for(const x of [793,795,797,799,801,803,805,807]){
      cube('Arched harbour window iron mullion',x,4.12,789.91,0.065,5.5,0.09,dark);
    }
    cube('Harbour window horizontal transom',800,4.4,789.89,15.1,0.09,0.12,dark);
    cube('Window arch upper beam',800,7.02,789.89,15.0,0.18,0.14,dark);
    cube('Window glazing',800,4,789.8,15,5.65,0.04,glass);
    for(const x of [795,805]) {
      cube('Multimodal Lab green indoor plant stalk',x,0.8,792.6,0.13,1.2,0.13,green);
      const leaves=new THREE.Mesh(new THREE.SphereGeometry(0.64,8,6),green);
      leaves.name='Lab leafy indoor plant';leaves.position.set(x,1.9,792.6);root.add(leaves);
    }
    // Suggestive grand piano silhouette under the window as in the supplied photo.
    cube('Black grand piano body',798.5,0.98,795.1,3.2,0.28,2.1,dark);
    cube('Black grand piano raised lid',798.2,1.7,794.65,3.0,0.10,1.6,dark);
    cube('Piano keyboard',799.5,1.03,796.2,1.75,0.08,0.36,cream);
    for(const x of [797.5,799.5])for(const z of [794.35,795.8])cube('Grand piano leg',x,0.47,z,0.12,0.85,0.14,dark);
    cube('Lab communal workshop table',801,0.96,802,4.7,0.16,2.4,wood);
    for(let i=0;i<4;i++){
      const x=795.1+i*2.35;
      cube('Multimodal Lab gathering cocktail table '+i,x,1.09,799.9,0.85,0.09,0.85,cream);
      cube('Multimodal Lab gathering table support '+i,x,0.57,799.9,0.075,1.04,0.075,dark);
    }
    // Photos of the actual venue show a bright event space with exposed
    // timber ceiling, white pillars, daylight windows and blue MML brand.
    // This is a fictionalized walkthrough, not an actual surveyed floorplan.
    for(const x of [799.1,802.9])for(const z of [801.3,802.7])cube('Worktable leg',x,0.48,z,0.14,0.93,0.14,dark);
    cube('Creative coding laptop screen',801.5,1.31,801.65,1.05,0.64,0.08,dark);
    // Fictional working artist, seated at a lit creative-coding laptop.
    // The computer and worker are visible upon arrival, without interaction.
    const coder=new THREE.Group();
    coder.name='MML fictional creative technologist at computer';
    coder.position.set(801.55,0,803.35);coder.rotation.y=Math.PI;
    root.add(coder);
    function coderPart(name,x,y,z,w,h,d,material){
      const part=new THREE.Mesh(new THREE.BoxGeometry(w,h,d),material);
      part.name=name;part.position.set(x,y,z);coder.add(part);return part;
    }
    const coderShirt=new THREE.MeshStandardMaterial({color:0x755d9b,roughness:0.87});
    const coderHair=new THREE.MeshStandardMaterial({color:0x27252b,roughness:0.93});
    coderPart('Creative technologist seated torso',0,1.12,0,0.7,0.73,0.38,coderShirt);
    coderPart('Creative technologist seated legs',0,0.57,-0.24,0.66,0.22,0.8,denim);
    coderPart('Creative technologist chair seat',0,0.63,0.08,0.9,0.15,0.85,dark);
    coderPart('Creative technologist chair back',0,1.17,0.43,0.85,1.1,0.13,dark);
    const coderHead=new THREE.Mesh(new THREE.SphereGeometry(0.25,12,10),skin);
    coderHead.name='Creative technologist focused at screen';
    coderHead.position.set(0,1.7,-0.02);coder.add(coderHead);
    coderPart('Creative technologist hair',0,1.91,0.02,0.47,0.13,0.46,coderHair);
    const coderHands=[];
    for(const side of [-1,1]){
      coderPart('Creative technologist forearm',side*0.41,0.99,-0.44,0.17,0.17,0.65,coderShirt);
      const hand=coderPart('Creative technologist typing hand',side*0.41,0.99,-0.73,0.18,0.10,0.18,skin);
      coderHands.push(hand);
    }
    const screenGlow=new THREE.MeshBasicMaterial({color:0x52d9d0});
    cube('Multimodal Lab laptop luminous code display',801.5,1.32,801.59,0.89,0.49,0.012,screenGlow);
    root.userData.coder=coder;
    root.userData.coderHands=coderHands;
    // Reuse the same recognizable model used on the street and in the lobby.
    const greeter=createKrisAvatar(THREE);
    greeter.name='KK | fictional welcoming lab NPC';
    greeter.position.set(803.0,0,804.7);greeter.visible=false;root.add(greeter);
    cube('Exit to Water Street door',800,1.65,809.83,2.6,3.3,0.1,glass);
    cube('Exit door glass',800,1.7,809.74,2.24,2.8,0.09,glass);
    sign(THREE,root,'MULTIMODAL LAB  ·  FLOOR 6',
      {x:800,y:4.5,z:809.64},5.4,0.48,0,{background:'#213b45',color:'#e4f5e9'});
    sign(THREE,root,'E: RETURN TO WATER STREET',
      {x:800,y:2.05,z:809.59},3.8,0.29,0,{background:'#294744',color:'#f3e9c9'});
    root.userData.fictionalInterior=true;
    root.userData.greeter=greeter;
    return root;
  }

  // Separate 3D vignettes prevent the real warehouse's full solid mass from
  // swallowing Suzy and give the entrance, lift and destination readable walls.
  function createLandingJourney(THREE) {
    const root=new THREE.Group();
    root.name='The Landing entrance lobby and staged lift (fictional reconstruction)';
    const cream=new THREE.MeshStandardMaterial({color:0xe5d8c1,roughness:0.9});
    const stone=new THREE.MeshStandardMaterial({color:0xb6a99a,roughness:0.86});
    const metal=new THREE.MeshStandardMaterial({color:0x555c5e,metalness:0.57,roughness:0.4});
    const gold=new THREE.MeshStandardMaterial({color:0xb99855,metalness:0.58,roughness:0.3});
    const glass=new THREE.MeshStandardMaterial({color:0xb2d3d9,transparent:true,opacity:0.28,depthWrite:false,side:THREE.DoubleSide,roughness:0.16,metalness:0.02});
    const tile=new THREE.MeshStandardMaterial({color:0x89745e,roughness:0.79});
    // The vestibule and floor display both use this dark stone. A missing
    // material here used to throw before Station East and the lot were rendered.
    const dark=new THREE.MeshStandardMaterial({color:0x222a2f,roughness:0.48});
    function block(name,x,y,z,w,h,d,material){
      const m=new THREE.Mesh(new THREE.BoxGeometry(w,h,d),material);
      m.name=name;m.position.set(x,y,z);root.add(m);return m;
    }
    // Entrance facing south, with the lift down the hallway on the LEFT.
    block('The Landing lobby stone floor',850,-0.13,850,16,0.24,20,tile);
    block('The Landing cream lobby west wall',842,3.3,850,0.2,6.6,20,cream);
    block('The Landing cream lobby east wall',858,3.3,850,0.2,6.6,20,cream);
    block('The Landing lobby north back wall',850,3.3,840,16,6.6,0.2,cream);
    // Split the lobby south wall around the central 3.6m entrance, with a lintel
    // above it; the former full-width wall literally sealed the doors.
    block('The Landing entrance wall west wing',845.7,3.3,860,7.4,6.6,0.2,stone);
    block('The Landing entrance wall east wing',854.3,3.3,860,7.4,6.6,0.2,stone);
    block('The Landing entrance lintel above door',850,5.1,860,3.6,3.0,0.2,stone);
    block('The Landing ceiling',850,6.65,850,16,0.22,20,cream);
    // The ground floor entry deliberately looks back toward Water Street.
    const entranceLeft=block('Glass main entrance left hinged door',848.68,1.76,859.85,0.95,3.5,0.09,glass);
    const entranceRight=block('Glass main entrance right hinged door',851.32,1.76,859.85,0.95,3.5,0.09,glass);
    // No central stile: the pair is already fully opened to invite Suzy in.
    for(const side of [-1,1])block('Main door brass pull',850+side*1.32,1.44,859.59,0.04,0.52,0.08,gold);
    block('Reception desk',854,1.04,853,3.6,1.1,1.0,stone);
    block('Reception desk brass trim',854,1.61,853.45,3.6,0.10,0.13,gold);
    // Lift on player-left as they walk inward (negative X at X=845).
    block('Elevator vestibule black stone surround',842.26,2.45,850,0.36,4.9,4.5,dark);
    const liftLeft=block('Elevator left sliding leaf',842.02,2.0,849.34,0.12,3.75,1.33,metal);
    const liftRight=block('Elevator right sliding leaf',842.02,2.0,850.66,0.12,3.75,1.33,metal);
    // Door leaves move sideways when KK presses the lift call button.
    block('Elevator call-button plate',842.01,1.36,852.25,0.15,0.54,0.32,gold);
    block('Elevator floor indicator',842.0,4.4,850,0.10,0.36,1.5,dark);
    for(const z of [843,855])block('Lobby art deco arch stone pillar',843.4,2.6,z,0.6,5.2,0.7,stone);
    for(const z of [845,850,855])block('Lobby gold wall sconce',842.3,3.5,z,0.24,0.55,0.28,gold);
    sign(THREE,root,'THE LANDING  ·  375 WATER',
      {x:850,y:4.7,z:859.48},4.9,0.43,0,{background:'#3a4140',color:'#f4e6be'});
    sign(THREE,root,'LIFTS  ⇦',
      {x:848,y:3.55,z:857.7},2.2,0.44,0,{background:'#3c4243',color:'#f7e7bf'});
    // This is the same model cloned later for the outdoor first greeting.
    const kk=createKrisAvatar(THREE);
    kk.name='KK fictional lobby escort';
    kk.position.set(851.3,0,855.1);root.add(kk);
    // The lift cabin is off-map so the street remains uninterrupted.
    block('The Landing elevator car floor',880,-0.12,850,5,0.23,5,tile);
    block('Elevator cabin left panel',877.5,2.15,850,0.18,4.3,5,metal);
    block('Elevator cabin right panel',882.5,2.15,850,0.18,4.3,5,metal);
    block('Elevator cabin rear wall',880,2.15,847.5,5,4.3,0.18,metal);
    const carLeft=block('Elevator cabin left sliding leaf',878.77,2.1,852.5,2.42,4.2,0.12,metal);
    const carRight=block('Elevator cabin right sliding leaf',881.23,2.1,852.5,2.42,4.2,0.12,metal);
    block('Elevator cabin ceiling',880,4.25,850,5,0.2,5,cream);
    block('Elevator cabin button panel',882.27,1.7,850.9,0.08,2.3,0.5,gold);
    for(let n=1;n<=6;n++)block('Elevator floor button '+n,882.19,0.8+n*0.29,850.83,0.13,0.09,0.11,glass);
    const display=sign(THREE,root,'6  ·  MULTIMODAL LAB',
      {x:880,y:3.7,z:852.32},2.9,0.37,0,{background:'#2d3637',color:'#f4e9cf'});
    root.userData={kk,lobby:true,elevator:true,fictionalInterior:true,
      entranceLeft,entranceRight,liftLeft,liftRight,carLeft,carRight,
      entranceLeftX:entranceLeft.position.x,entranceRightX:entranceRight.position.x,
      liftLeftZ:liftLeft.position.z,liftRightZ:liftRight.position.z,
      carLeftX:carLeft.position.x,carRightX:carRight.position.x};
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

  window.GastownWaterfrontPlaces=Object.freeze({ reference:REFERENCE, create, createParking, createLanding, createLabInterior, createLandingJourney, createKrisAvatar, landingEntranceFor, inPolygon, isWalkableLot, entranceFor, lookoutFor, parkingCars });
})(window);

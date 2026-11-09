const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const ROOT = path.join(__dirname, '..');
const read = file => fs.readFileSync(path.join(ROOT, file), 'utf8');
const world = JSON.parse(read('assets/world/gastown-water-street.json'));

function boot() {
  const ctx = { fillRect() {}, clearRect() {}, fillText() {} };
  const win = { document: { createElement: () => ({ width:0, height:0, getContext: () => ctx }) } };
  new Function('window', read('js/gastown-waterfront-places.js'))(win);
  new Function('window', read('js/gastown-harbour-vista.js'))(win);
  return win;
}
function threeStub() {
  class Vec { set(x,y,z) { this.x=x;this.y=y;this.z=z;return this; } }
  class Geo {
    constructor(...args) { this.args=args; }
    rotateX() { return this; }
    setAttribute() { return this; }
  }
  class Shape { moveTo(){} lineTo(){} quadraticCurveTo(){} closePath(){} }
  class Mat { constructor(options){ this.options=options; } }
  class Mesh {
    constructor(geometry,material) {
      this.geometry=geometry;this.material=material;this.position=new Vec();
      this.rotation={};this.scale=new Vec();this.children=[];this.name='';this.userData={};
    }
  }
  class Group {
    constructor(){this.children=[];this.name='';this.position=new Vec();this.rotation={};this.userData={};}
    add(child){this.children.push(child);}
  }
  class Texture { constructor(){this.repeat={set(){}};} }
  return {
    Group,Mesh,Shape,CanvasTexture:Texture,
    MeshStandardMaterial:Mat,MeshBasicMaterial:Mat,LineBasicMaterial:Mat,
    BoxGeometry:Geo,CylinderGeometry:Geo,ConeGeometry:Geo,PlaneGeometry:Geo,
    ShapeGeometry:Geo,ExtrudeGeometry:Geo,BufferGeometry:Geo,Float32BufferAttribute:Geo,
    LineSegments:Mesh,SRGBColorSpace:'srgb',DoubleSide:2,RepeatWrapping:1000,
  };
}
function all(root) {
  const found=[root];
  for(let i=0;i<found.length;i++)found.push(...(found[i].children||[]));
  return found;
}
function inPoly(p,poly) {
  let inside=false;
  for(let i=0,j=poly.length-1;i<poly.length;j=i++) {
    const a=poly[i],b=poly[j];
    if((a.z>p.z)!==(b.z>p.z)
       &&p.x<((b.x-a.x)*(p.z-a.z))/((b.z-a.z)||1e-9)+a.x)inside=!inside;
  }
  return inside;
}

test('Cordova sidewalk and Lot 531 share a walkable, car-free route to the fence',()=>{
  const {GastownWaterfrontPlaces: lot, GastownHarbourVista: vista}=boot();
  const start=lot.entranceFor(world.parkingLot);
  const end=lot.lookoutFor(world,vista.positionFor(world));
  assert.ok(inPoly(start,world.route.walkBounds),'the Cordova-facing driveway overlaps civic walk bounds');
  assert.ok(lot.isWalkableLot(world,start));
  assert.ok(lot.isWalkableLot(world,end),'the fence viewpoint must be physically walkable');
  for(let i=0;i<=32;i++) {
    const t=i/32,p={x:start.x+(end.x-start.x)*t,z:start.z+(end.z-start.z)*t};
    assert.ok(lot.isWalkableLot(world,p),'walk blocked at '+i+' of 32: '+JSON.stringify(p));
  }
  assert.ok(!lot.isWalkableLot(world,{x:50,z:15}),'outside the harbour fence is not walkable');
  assert.ok(!lot.isWalkableLot(world,{x:10,z:10}),'public lot does not grant access to the whole port');
  for(const car of lot.parkingCars(world.parkingLot)) {
    assert.equal(lot.isWalkableLot(world,car),false,'parked vehicles should be solid obstacles');
  }
  assert.ok(lot.parkingCars(world.parkingLot).length>=5);
});

test('parking and warehouse visual details match the Cordova reference without blocking the entrance',()=>{
  const {GastownWaterfrontPlaces: places}=boot();
  const root=places.create(threeStub(),world);
  assert.equal(root.children.length,2);
  const parking=root.children[0],allParking=all(parking),names=allParking.map(node=>node.name);
  assert.equal(parking.userData.walkableFromCordova,true);
  assert.equal(parking.userData.fenceLookout,true);
  assert.ok(names.filter(name=>name==='Waterfront fence steel upright').length>=20);
  assert.ok(names.includes('RAIL YARD / BURRARD INLET'));
  assert.ok(names.includes('P  STATION EAST'));
  assert.ok(!names.includes('Parking gate arm'),'the entry is not obstructed by a gate bar');
  const warehouse=all(root.children[1]);
  const mass=warehouse.find(x=>x.name==='The Landing seven-storey civic footprint');
  assert.equal(mass.material.options.color,0xaaa79d,'the warehouse must be light masonry, not red brick');
  assert.ok(warehouse.some(x=>x.name==='STEAMWORKS'));
});

test('parking cars point into their spaces, not along the rows',()=>{
  const {GastownWaterfrontPlaces:places}=boot();
  const group=places.createParking(threeStub(),world);
  const cars=group.children.filter(item=>/^Parked vehicle /.test(item.name));
  assert.ok(cars.length>=5);
  const v={x:0.710,z:0.704};
  for(const car of cars) {
    const yaw=car.rotation.y;
    const axis={x:Math.sin(yaw),z:Math.cos(yaw)};
    const alignment=axis.x*v.x+axis.z*v.z;
    assert.ok(alignment>0.99,'car long axis should run into the stall');
  }
  const stops=group.children.filter(item=>item.name==='Concrete parking wheel stop');
  assert.equal(stops.length,cars.length);
  for(const stop of stops)assert.ok(Math.abs(stop.rotation.y-cars[0].rotation.y)<0.001);
});

test('harbour skyline includes the fenced service yard and four orange gantry cranes',()=>{
  const {GastownHarbourVista:vista}=boot();
  const root=vista.create(threeStub(),world),nodes=all(root);
  const names=nodes.map(n=>n.name);
  assert.ok(names.includes('Fenced waterfront rail service yard (non-walkable)'));
  assert.equal(names.filter(name=>/North Shore orange container gantry crane/.test(name)).length,4);
  assert.ok(names.includes('Distant stacked shipping container'));
  assert.ok(names.includes('Burrard Inlet water'));
  assert.ok(names.includes('Canada Place pier / white sails'));
  assert.ok(nodes.length<400,'keep scenic detail within the existing laptop rendering budget');
});

test('main runtime recognizes lot movement and the UI offers the fence destination',()=>{
  const js=read('js/gastown-sim.js');
  const php=read('page-gastown-sim.php');
  assert.match(js,/waterfrontPlaces\.isWalkableLot\(state\.world, point\)/);
  assert.match(js,/waterfrontPlaces\.isWalkableLot\(state\.world, spawn\)/);
  assert.match(js,/state\.lastSafePosition\)/);
  assert.match(js,/waterfrontPlaces\.lookoutFor\(state\.world, target\)/);
  assert.match(php,/data-action="harbour-lookout"/);
  assert.match(php,/>Harbour lookout<\/button>/);
  assert.match(php,/data-action="rail-yard-view"/);
  assert.match(js,/railYardLookBtn\.addEventListener\('click', \(\) => goToLotView\('yard'\)\)/);
  assert.match(js,/shot === 'yard'/);
  assert.match(js,/CP N-Yard: twenty illustrative rail alignments/);
});

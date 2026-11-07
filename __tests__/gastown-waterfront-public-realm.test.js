const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const ROOT=path.resolve(__dirname,'..');
const read=p=>fs.readFileSync(path.join(ROOT,p),'utf8');
const world=JSON.parse(read('assets/world/gastown-water-street.json'));
const distance=(a,b)=>Math.hypot(a.x-b.x,a.z-b.z);
function extract(src,start,end){
 const a=src.indexOf(start),b=src.indexOf(end,a+start.length);
 assert.ok(a>=0 && b>a,'Extracted runtime function: '+start);
 return src.slice(a,b);
}
function harness(){
 const canvas={width:1024,height:256,getContext:()=>({fillRect(){},fillText(){}})};
 const win={document:{createElement:()=>canvas}};
 new Function('window',read('js/gastown-waterfront-station.js'))(win);
 new Function('window',read('js/gastown-waterfront-places.js'))(win);
 return win;
}
function threeStub(){
 class Vec{set(x,y,z){this.x=x;this.y=y;this.z=z;return this}}
 class Geometry{constructor(...args){this.args=args;} rotateX(v){this.xRotation=v;return this}}
 class Shape{moveTo(){}lineTo(){}closePath(){}}
 class Material{constructor(args){this.options=args}}
 class Mesh{constructor(g,m){this.geometry=g;this.material=m;this.name='';this.position=new Vec();this.rotation={};this.scale=new Vec();this.userData={}}}
 class Group{constructor(){this.children=[];this.position=new Vec();this.rotation={};this.userData={};this.name=''} add(v){this.children.push(v)}}
 class CanvasTexture{constructor(){this.colorSpace=null;this.repeat={set(){}}}}
 return {Vec,Geometry,Shape,Mesh,Group,CanvasTexture,
   BoxGeometry:Geometry,CylinderGeometry:Geometry,SphereGeometry:Geometry,TorusGeometry:Geometry,
   ShapeGeometry:Geometry,ExtrudeGeometry:Geometry,PlaneGeometry:Geometry,
   MeshStandardMaterial:Material,MeshBasicMaterial:Material,
   SRGBColorSpace:'sRGB',DoubleSide:2,RepeatWrapping:1000};
}
test('Station, Lot 531 and The Landing are distinct, ordered places on the real block',()=>{
 assert.equal(world.parkingLot.id,'station-east-parking');
 assert.equal(world.parkingLot.address,'555 West Cordova Street');
 assert.equal(world.parkingLot.lotNumber,'531');
 assert.equal(world.parkingLot.approximate,true);
 assert.equal(world.landing.address,'375 Water Street');
 assert.equal(world.landing.sourceFootprintId,'feature-192');
 assert.ok(world.landing.footprint.length>=8);
 const a=world.station,b=world.parkingLot.center,c=world.landing;
 const dx=c.x-a.x,dz=c.z-a.z,t=((b.x-a.x)*dx+(b.z-a.z)*dz)/(dx*dx+dz*dz);
 assert.ok(t>0.2&&t<0.8,'lot is physically between station and the Landing');
 assert.ok(distance(a,b)>40&&distance(b,c)<85);
 assert.ok(!world.buildings.some(building=>building.id==='feature-192'),'do not double-render the Landing as a generic box');
});
test('Three.js facade and pedestrian local-to-world transforms agree',()=>{
 const station=harness().GastownWaterfrontStation;
 const anchor=station.anchorFor(world);
 const front=station.toWorld(world,0,10);
 assert.ok(Math.abs(front.x-anchor.x-10*Math.sin(anchor.yaw))<1e-8);
 assert.ok(Math.abs(front.z-anchor.z-10*Math.cos(anchor.yaw))<1e-8);
 const spawn=world.spawn;
 assert.ok(station.isWalkableForecourt(world,spawn),'spawn and forecourt share the station orientation');
 const bearing=station.arrivalYaw(world);
 const vector={x:-Math.sin(bearing),z:-Math.cos(bearing)};
 const towards={x:(anchor.x-spawn.x)/distance(anchor,spawn),z:(anchor.z-spawn.z)/distance(anchor,spawn)};
 assert.ok(vector.x*towards.x+vector.z*towards.z>0.999);
 for(const npc of station.commutersFor(world)){
   assert.ok(npc.patrol.every(p=>Number.isFinite(p.x)&&Number.isFinite(p.z)));
 }
});
test('original procedural public realm includes a real open lot and warehouse, not a fake office tower',()=>{
 const places=harness().GastownWaterfrontPlaces;
 const root=places.create(threeStub(),world);
 assert.equal(root.children.length,2);
 const parking=root.children[0],landing=root.children[1];
 assert.match(parking.name,/Station East surface lot/);
 assert.match(landing.name,/Kelly-Douglas/);
 assert.ok(parking.userData.parkedCars>=5);
 assert.ok(parking.children.some(p=>p.name==='P  STATION EAST'));
 assert.ok(landing.children.some(p=>p.name==='THE LANDING'));
 assert.ok(landing.children.some(p=>p.name==='The Landing seven-storey civic footprint'));
 assert.equal(landing.children.filter(p=>p.name==='The Landing warehouse sash window').length>=75,true);
});
test('true north, east and south compass bearings match 3D world coordinates',()=>{
 const source=read('js/gastown-sim.js');
 const fn=extract(source,'  function getFacingLabel(heading)', '  function classifyRelativeDirection(');
 const label=new Function(fn+'; return getFacingLabel;')();
 assert.equal(label({x:0,z:-1}),'north');
 assert.equal(label({x:1,z:0}),'east');
 assert.equal(label({x:0,z:1}),'south');
 assert.equal(label({x:-1,z:0}),'west');
 assert.equal(label({x:Math.SQRT1_2,z:-Math.SQRT1_2}),'northeast');
 const viewSrc=extract(source,'  function getMinimapView(metrics)', '  function toMinimapPoint(');
 const view=new Function('minimapState','player',viewSrc+'; return getMinimapView;')(
  {width:240,height:240,zoom:1},{position:{x:0,z:0}}
 )({minX:-200,maxX:200,minZ:-200,maxZ:200});
 assert.equal(view.width,view.height,'square map uses identical scale in both GIS axes');
 assert.equal(view.centerX,0);
 assert.equal(view.centerZ,0);
 assert.ok(view.width>160&&view.width<220,'local view includes station and lot, not the entire city');
 assert.match(source,/new THREE\.Vector2\(point\.x, -point\.z\)/);
 assert.match(source,/shape\.moveTo\(point\.x, -point\.z\)/);
 assert.match(source,/mesh\.rotation\.y = -\(b\.yaw \|\| 0\)/);
});
test('Esc reminder and named minimap legend remain visible on the page',()=>{
 const page=read('page-gastown-sim.php'),css=read('assets/css/gastown-sim.css');
 const runtime=read('js/gastown-sim.js'),fn=read('functions.php'),manifest=read('scripts/theme_deploy_manifest.py');
 assert.match(page,/ESC = RELEASE MOUSE/);
 assert.match(page,/data-action="sim-release-pointer"/);
 for(const text of ['Station East lot','The Landing','Steam Clock','Waterfront'])assert.ok(page.includes(text));
 assert.match(page,/data-sim-compass/);
 assert.match(runtime,/updatePointerHelp\(\)/);
 assert.match(runtime,/document\.exitPointerLock\(\)/);
 assert.doesNotMatch(css,/\.gastown-minimap-compass, \.gastown-minimap-legend, .*display: none !important/);
 assert.match(fn,/'se-gastown-waterfront-places'/);
 assert.ok(manifest.includes('"js/gastown-waterfront-places.js"'));
});


test('Waterfront, Station East and The Landing stay LEFT when walking SE along Cordova',()=>{
  // Three.js is right-handed: +X east, +Y up, -Z north.
  // For a southeast-facing player (forward +X/+Z), screen right is SW
  // (-Z,+X); the real station and adjacent sites must be screen-left.
  const route=world.route.centerline;
  function signedScreenRight(point) {
    let nearest=null;
    for(let i=1;i<route.length;i++){
      const a=route[i-1],b=route[i],dx=b.x-a.x,dz=b.z-a.z;
      const q=dx*dx+dz*dz||1;
      const u=Math.max(0,Math.min(1,((point.x-a.x)*dx+(point.z-a.z)*dz)/q));
      const center={x:a.x+u*dx,z:a.z+u*dz};
      const dist=distance(point,center);
      const len=Math.sqrt(q);
      const right={x:-dz/len,z:dx/len};
      const lateral=(point.x-center.x)*right.x+(point.z-center.z)*right.z;
      if(!nearest||dist<nearest.dist)nearest={dist,lateral};
    }
    return nearest;
  }
  assert.ok(route[1].x>route[0].x && route[1].z>route[0].z,
    'walking from the station toward Gastown must head southeast in scene space');
  for(const [name,point] of Object.entries({
    'Waterfront Station':world.station,
    'Station East parking':world.parkingLot.center,
    'The Landing':world.landing,
  })){
    const site=signedScreenRight(point);
    assert.ok(site.lateral < -5, name+' must be on the LEFT of the waterfront-to-Gastown walk; got '+JSON.stringify(site));
  }
});

test('north-up minimap and camera heading use the same right-handed scene axes',()=>{
  const src=read('js/gastown-sim.js');
  const mapFn=extract(src,'  function toMinimapPoint(', '  function getMinimapHeadingRotation(');
  const renderMapPoint=new Function('minimapState',mapFn+'; return toMinimapPoint;')(
    {width:240,height:240}
  );
  const metrics={minX:-100,maxX:100,minZ:-100,maxZ:100,width:200,height:200};
  const north=renderMapPoint({x:0,z:-40},metrics,16,metrics);
  const south=renderMapPoint({x:0,z:40},metrics,16,metrics);
  assert.ok(north.y<south.y,'smaller world Z (north) must render above south on the minimap');
  const rotationFn=extract(src,'  function getMinimapHeadingRotation(', '  function getCanonicalCorridorSegment(');
  const rotate=new Function('minimapState',rotationFn+'; return getMinimapHeadingRotation;')(
    {mode:'heading-up'}
  );
  assert.ok(Math.abs(rotate({x:0,z:-1}))<1e-8,'north-facing player must need zero map rotation');
  const e=rotate({x:1,z:0});
  assert.ok(Math.abs(e+Math.PI/2)<1e-8,'east-facing player rotates the map 90° clockwise into heading-up');
  assert.match(src,/const headingY = minimapState\.mode === 'heading-up' \? -1 : heading\.z;/);
});

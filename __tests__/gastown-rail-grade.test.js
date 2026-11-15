'use strict';
const test=require('node:test'),assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path');
const read=f=>fs.readFileSync(path.join(__dirname,'..',f),'utf8');
const world=JSON.parse(read('assets/world/gastown-water-street.json'));
function stub(){
 class V{set(x,y,z){this.x=x;this.y=y;this.z=z;return this}}
 class G{constructor(...args){this.args=args}setAttribute(k,v){this[k]=v;return this}}
 class Shape{moveTo(){}lineTo(){}closePath(){}}
 class M{constructor(opts){this.options=opts}}
 class N{constructor(g,m){this.geometry=g;this.material=m;this.name='';this.children=[];this.position=new V();this.rotation={};this.userData={}}}
 class Group{constructor(){this.name='';this.position=new V();this.rotation={};this.userData={};this.children=[]}add(x){this.children.push(x)}}
 return {Mesh:N,Group,Shape,ShapeGeometry:G,BoxGeometry:G,BufferGeometry:G,Float32BufferAttribute:G,LineSegments:N,LineBasicMaterial:M,MeshStandardMaterial:M};
}
function scene(){
 const window={};
 new Function('window',read('js/gastown-rail-grade.js'))(window);
 return window.GastownRailGrade.create(stub(),world);
}
function inside(p,poly){
 let odd=false;
 for(let i=0,j=poly.length-1;i<poly.length;j=i++){
  const a=poly[i],b=poly[j];
  if((a.z>p.z)!==(b.z>p.z)&&p.x<(b.x-a.x)*(p.z-a.z)/(b.z-a.z)+a.x)odd=!odd;
 }return odd;
}
test('the service alley follows the supplied Google Street View positions',()=>{
 const root=scene();
 assert.ok(root);
 assert.equal(root.userData.lotElevation,0.19);
 assert.equal(root.userData.laneElevation,-3.60);
 assert.equal(root.userData.elevatedStreetAndLot,true);
 assert.equal(root.userData.lowerServiceRoad,true);
 assert.ok(root.userData.gradeSeparationMetres>3.5);
 assert.ok(root.userData.loadingDeckClearance>3.5);
 assert.ok(root.children.some(n=>n.name==='Raised Cordova station loading deck underside'));
 assert.equal(root.children.filter(n=>n.name==='Elevated service deck support column').length,2);
 assert.equal(root.userData.railElevation,-1.35);
 assert.ok(root.userData.laneElevation<root.userData.railElevation,
   'the service road is under the elevated rail and public deck');
 assert.ok(root.userData.railElevation<root.userData.lotElevation,
   'passenger tracks remain below the public parking deck');
 assert.equal(root.userData.illustrativeNotSurveyed,true);
 assert.equal(root.userData.serviceLaneNotPublic,true);
 assert.equal(root.userData.mappedStreetViewPoints.length,3);
 const [west,centre,east]=root.userData.mappedStreetViewPoints;
 assert.ok(Math.abs(west.x-67.19)<0.5&&Math.abs(west.z-77.04)<0.5);
 assert.ok(Math.abs(centre.x-79.84)<0.5&&Math.abs(centre.z-79.76)<0.5);
 assert.ok(Math.abs(east.x-100.70)<0.5&&Math.abs(east.z-85.71)<0.5);
 assert.equal(root.userData.roadSegments,3);
 assert.ok(root.children.filter(n=>n.name==='The Landing surveyed-view service alley').length>=3);
});
test('utility fencing stays beside Landing and never stretches over parking or train tracks',()=>{
 const root=scene();
 assert.ok(root.userData.wireFencePostCount>=20);
 assert.ok(root.children.some(n=>n.name==='Landing railway fence diagonal wire mesh'));
 assert.ok(root.children.some(n=>n.name==='Fenced rail-serving utility yard gravel'));
 assert.ok(root.children.some(n=>n.name==='Low brick railway utility and service building'));
 const rootNodes=[root];for(let i=0;i<rootNodes.length;i++)rootNodes.push(...(rootNodes[i].children||[]));
 assert.ok(rootNodes.some(n=>n.name==='Service utility shed red brick'));
 const wires=root.children.filter(n=>n.name==='Landing railway fence diagonal wire mesh');
 assert.ok(wires.every(n=>n.geometry.position.args[0].length>400));
 for(const p of root.userData.fenceBoundary){
   assert.equal(inside(p,world.parkingLot.polygon),false,'wire fence outside public lot');
   assert.equal(inside(p,world.landing.footprint),false,'wire fence outside the building');
   assert.ok(p.z>65,'fence stays beside Landing, south of the rail corridor');
 }
 for(const p of root.userData.alleyCenterline){
   assert.equal(inside(p,world.parkingLot.polygon),false,'service road outside lot');
   assert.equal(inside(p,world.landing.footprint),false,'service road not through building');
 }
 assert.ok(root.children.length<90,'batched wire mesh and bounded low-poly details');
});
test('duplicate tracks and false railway fence crossover are absent',()=>{
 const root=scene();
 assert.equal(root.userData.railwayTrackCount,0);
 assert.equal(root.userData.railRoutes.length,0);
 assert.equal(root.userData.duplicateRailGeometryRemoved,true);
 assert.equal(root.userData.crossesExistingRailway,false);
 assert.equal(root.children.filter(n=>/rail head|rail web|railway sleepers/i.test(n.name)).length,0);
});
test('harbour and WCE share a lower grade and parking gameplay anchors are unchanged',()=>{
 const harbour=read('js/gastown-harbour-vista.js');
 const wce=read('js/gastown-west-coast-express.js');
 const sim=read('js/gastown-sim.js');
 assert.match(harbour,/firstRailSceneChild=root\.children\.length/);
 assert.match(harbour,/node\.position\.y-=1\.35/);
 assert.match(harbour,/railStartBeyondStationEast=true/);
 assert.match(harbour,/southApronNoLongerUnderLot = true/);
 assert.match(wce,/root\.position\.set\(0,-1\.35,0\)/);
 assert.match(wce,/coach\.position\.set\(center,0,-18\)/);
 assert.match(sim,/safelyCreate\('Station East rail-grade transition'/);
 assert.match(read('functions.php'),/'se-gastown-rail-grade'/);
 assert.match(read('scripts/theme_deploy_manifest.py'),/"js\/gastown-rail-grade\.js"/);
 const parking=read('js/gastown-waterfront-places.js');
 assert.match(parking,/upperDeck=0\.19, lowerAlley=-3\.60/);
 assert.match(parking,/Station East elevated parking deck retaining face/);
 assert.match(parking,/Station East raised parking deck buttress/);
 assert.match(parking,/lowerLandingAlleyElevation=lowerAlley/);
 assert.match(parking,/exposedRetainingWallHeight=wallHeight/);
 assert.equal(world.parkingLot.center.x,46);
 assert.equal(world.landing.address,'375 Water Street');
});

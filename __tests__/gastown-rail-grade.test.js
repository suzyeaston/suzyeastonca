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
test('grade separation from lot to Landing back alley, mesh fence and rail tracks',()=>{
 const root=scene();
 assert.ok(root);
 assert.equal(root.userData.lotElevation,0.19);
 assert.equal(root.userData.laneElevation,-0.55);
 assert.equal(root.userData.railElevation,-1.35);
 assert.ok(root.userData.railElevation<root.userData.laneElevation);
 assert.ok(root.userData.laneElevation<root.userData.lotElevation);
 assert.equal(root.userData.serviceLaneNotPublic,true);
 assert.equal(root.userData.illustrativeNotSurveyed,true);
 assert.ok(root.userData.wireFencePostCount>=25);
 assert.ok(root.children.filter(n=>n.name==='The Landing back service alley').length>=2);
 assert.ok(root.children.some(n=>n.name==='Station East grade-step retaining wall'));
 const wire=root.children.find(n=>n.name==='Landing railway fence diagonal wire mesh');
 assert.ok(wire);
 assert.ok(wire.geometry.position.args[0].length>1000);
 assert.ok(root.children.length<90,'bounded mesh budget for 8GB laptops');
});
test('two lower near-field rail alignments never invade parking polygon',()=>{
 const root=scene();assert.equal(root.userData.railwayTrackCount,2);
 for(const route of root.userData.railRoutes){
  for(let k=0;k<route.length-1;k++){
   for(let i=0;i<=50;i++){
    const a=route[k],b=route[k+1],t=i/50;
    assert.equal(inside({x:a.x+(b.x-a.x)*t,z:a.z+(b.z-a.z)*t},
      world.parkingLot.polygon),false);
   }
  }
 }
 assert.ok(root.children.some(n=>n.name==='Landing-side batched railway sleepers'));
});
test('CPR rail cars and WCE platform shift together; public areas are unchanged',()=>{
 const harbour=read('js/gastown-harbour-vista.js');
 const wce=read('js/gastown-west-coast-express.js');
 const sim=read('js/gastown-sim.js');
 assert.match(harbour,/firstRailSceneChild=root\.children\.length/);
 assert.match(harbour,/node\.position\.y-=1\.35/);
 assert.match(harbour,/freightGroup\.position\.y=-1\.35/);
 assert.match(wce,/root\.position\.set\(station\.x,-1\.35,station\.z\)/);
 assert.match(sim,/safelyCreate\('Station East rail-grade transition'/);
 assert.match(read('functions.php'),/'se-gastown-rail-grade'/);
 assert.match(read('scripts/theme_deploy_manifest.py'),/"js\/gastown-rail-grade\.js"/);
 assert.equal(world.parkingLot.center.x,46);
 assert.equal(world.landing.address,'375 Water Street');
});

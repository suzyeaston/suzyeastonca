'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read=file=>fs.readFileSync(path.join(__dirname,'..',file),'utf8');
const world=JSON.parse(read('assets/world/gastown-water-street.json'));
function stub(){
  class V {set(x,y,z){this.x=x;this.y=y;this.z=z;return this}}
  class G {constructor(...args){this.args=args}setAttribute(k,v){this[k]=v;return this}}
  class M {constructor(options){this.options=options}}
  class Mesh {constructor(g,m){this.geometry=g;this.material=m;this.name='';this.position=new V();this.rotation={};this.scale=new V();this.children=[];this.userData={}}}
  class Group {constructor(){this.name='';this.children=[];this.position=new V();this.rotation={};this.userData={}}add(v){this.children.push(v)}}
  class Texture {}
  return {Group,Mesh,BoxGeometry:G,PlaneGeometry:G,BufferGeometry:G,
    Float32BufferAttribute:G,CanvasTexture:Texture,MeshStandardMaterial:M,
    MeshBasicMaterial:M,LineBasicMaterial:M,LineSegments:Mesh,
    SRGBColorSpace:'srgb',DoubleSide:2};
}
function scene(){
  const window={document:{createElement:()=>({getContext:()=>({fillRect(){},fillText(){}})})}};
  new Function('window',read('js/gastown-west-coast-express.js'))(window);
  return window.GastownWestCoastExpress.create(stub(),world);
}
function inside(p,poly){
  let flag=false;
  for(let i=0,j=poly.length-1;i<poly.length;j=i++){
    const a=poly[i],b=poly[j];
    if((a.z>p.z)!==(b.z>p.z)&&
      p.x<(b.x-a.x)*(p.z-a.z)/(b.z-a.z)+a.x)flag=!flag;
  }
  return flag;
}
test('passenger train uses city-grid rails north of the historic Waterfront Station',()=>{
  const root=scene();assert.ok(root);
  assert.equal(root.position.x,0);
  assert.equal(root.position.z,0);
  assert.equal(root.position.y,-1.35,'WCE platform below public parking level');
  assert.equal(root.rotation.y,0,'station facade yaw must not rotate railway');
  assert.equal(root.userData.stationAnchor.x,world.station.x);
  assert.equal(root.userData.stationAnchor.z,world.station.z);
  assert.equal(root.userData.railCoordinateFrame,'world-eastbound');
  assert.equal(root.userData.hasIndependentBuildingYaw,true);
  assert.equal(root.userData.stagedTrainNotLive,true);
  assert.equal(root.userData.tracksSurveyed,false);
  assert.equal(root.userData.vehicleCount,4);
  assert.ok(root.children.some(n=>n.name==='West Coast Express Waterfront boarding platform'));
  assert.ok(root.children.some(n=>n.name==='West Coast Express yellow tactile safety strip'));
  assert.ok(root.children.some(n=>n.name==='West Coast Express platform roof canopy'));
  assert.ok(root.children.some(n=>n.name.includes('F59PH locomotive')));
  assert.equal(root.children.filter(n=>n.name.startsWith('West Coast Express bi-level coach')).length,3);
  const consist=root.children.filter(n=>n.name.startsWith('West Coast Express bi-level coach'));
  for(const coach of consist){
    assert.equal(coach.children.filter(n=>n.name==='WEST COAST EXPRESS').length,2,
      'bilateral coach branding remains visible');
  }
  const loco=root.children.find(n=>n.name.includes('F59PH locomotive'));
  assert.equal(loco.children.filter(n=>n.name==='WEST COAST EXPRESS').length,2);
  assert.equal(loco.children.find(n=>n.name==='WEST COAST EXPRESS' &&
    n.position.z<0).rotation.y,Math.PI,'opposite side branding must not be mirrored');
  const platform=root.children.find(n=>n.name==='West Coast Express Waterfront boarding platform');
  assert.ok(platform.position.z<world.station.z,'platform north of station');
  assert.ok(Math.abs(platform.position.z-loco.position.z)<9,
    'train and platform belong on opposite sides of the same boarding edge');

});
test('three WCE tracks stay parallel, turn east and miss Station East and The Landing',()=>{
  const root=scene();
  assert.equal(root.userData.routeTracks.length,3);
  for(const route of root.userData.routeTracks){
    assert.ok(route[0].x<route[route.length-1].x);
    for(let k=0;k<route.length-1;k++){
      const a=route[k],b=route[k+1];
      assert.ok(b.x>a.x,'eastbound tracks do not double back');
      assert.ok(b.z<=a.z,'WCE approach does not turn back toward Water Street');
      for(let i=0;i<=100;i++){
        const t=i/100,p={x:a.x+(b.x-a.x)*t,z:a.z+(b.z-a.z)*t};
        assert.equal(inside(p,world.parkingLot.polygon),false);
        assert.equal(inside(p,world.landing.footprint),false);
      }
    }
  }
  for(let row=1;row<3;row++){
    for(let i=0;i<root.userData.routeTracks[row].length;i++){
      const a=root.userData.routeTracks[row-1][i];
      const b=root.userData.routeTracks[row][i];
      assert.ok(Math.abs(a.z-b.z)>=5.7,'distinct tracks cannot overlap');
    }
  }
});
test('track ties are batched, module is deployed, and scene failure stays isolated',()=>{
  const root=scene(),sim=read('js/gastown-sim.js');
  const php=read('functions.php'),manifest=read('scripts/theme_deploy_manifest.py');
  const qa=read('.github/workflows/gastown-gis-qa.yml');
  assert.ok(root.children.length<110,'bounded draw-call budget');
  assert.ok(root.children.some(n=>n.name==='WCE batched timber sleeper fallback'));
  assert.match(sim,/safelyCreate\('West Coast Express platform'/);
  assert.match(sim,/westCoastExpress\.create\(THREE, world\)/);
  assert.match(php,/'se-gastown-west-coast-express'/);
  assert.match(manifest,/"js\/gastown-west-coast-express.js"/);
  assert.match(qa,/node --check js\/gastown-west-coast-express.js/);
});

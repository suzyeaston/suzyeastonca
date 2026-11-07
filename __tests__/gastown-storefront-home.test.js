const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const world = JSON.parse(read('assets/world/gastown-water-street.json'));

function pointToSegmentDistance(point, a, b) {
  const dx=b.x-a.x, dz=b.z-a.z;
  const t=Math.max(0,Math.min(1,((point.x-a.x)*dx+(point.z-a.z)*dz)/(dx*dx+dz*dz||1)));
  return Math.hypot(point.x-a.x-t*dx,point.z-a.z-t*dz);
}
function distanceToRoute(point) {
  return Math.min(...world.route.centerline.slice(1).map((to,i)=>
    pointToSegmentDistance(point,world.route.centerline[i],to)));
}
function pointInPolygon(point,poly) {
  let inside=false;
  for(let i=0,j=poly.length-1;i<poly.length;j=i++) {
    const a=poly[i],b=poly[j];
    if(((a.z>point.z)!==(b.z>point.z)) &&
      point.x<((b.x-a.x)*(point.z-a.z))/((b.z-a.z)||1e-9)+a.x)inside=!inside;
  }
  return inside;
}
function facadeEdges(b) {
  const points=b.footprint_local;
  if(!Array.isArray(points) || points.length<3) return [];
  const yaw=b.yaw||0,c=Math.cos(yaw),s=Math.sin(yaw);
  const toWorld=p=>({x:b.x+p.x*c-p.z*s,z:b.z+p.x*s+p.z*c});
  return points.map((p,i)=>{
    const a=toWorld(p),z=toWorld(points[(i+1)%points.length]);
    const midpoint={x:(a.x+z.x)/2,z:(a.z+z.z)/2};
    return {a,b:z,midpoint,length:Math.hypot(a.x-z.x,a.z-z.z),distance:distanceToRoute(midpoint)};
  }).filter(e=>e.length>2.2&&e.distance<34);
}
test('Old 3D solid-box storefront slabs are gone',()=>{
  const source=read('js/gastown-sim.js');
  assert.doesNotMatch(source,/new THREE\.BoxGeometry\(\(b\.width \|\| 8\) \* 0\.92, storefrontBandHeight/);
  assert.doesNotMatch(source,/const storefrontPos = localPointToWorld\(b, 0, \(b\.depth \|\| 8\) \* 0\.34\)/);
  assert.match(source,/Civic footprint-edge storefront trim/);
  assert.match(source,/new THREE\.BoxGeometry\(edge\.length \* 0\.94, storefrontBandHeight, 0\.10\)/);
  assert.match(source,/const safeRectangularFacade = b\.width <= 42 && b\.depth <= 28/);
});

test('Every frontage panel is based on civic boundary edges, away from roadway centre',()=>{
  const candidates=world.buildings.flatMap(b=>{
    const edges=facadeEdges(b),nearest=Math.min(Infinity,...edges.map(e=>e.distance));
    return edges.filter(e=>e.distance<nearest+5.5).sort((a,b)=>a.distance-b.distance).slice(0,6).map(e=>({...e,id:b.id}));
  });
  assert.ok(candidates.length>=12,'retain readable street-side frontages after deleting solid boxes');
  for(const e of candidates) {
    assert.ok(e.distance>4.8, e.id+' frontage intrudes into the road centre');
    assert.ok(e.length>2.2 && e.length*0.94<e.length);
    assert.ok(!pointInPolygon(e.midpoint,world.zones.street[0].polygon),
      e.id+' storefront occupies the roadway');
  }
});

test('Home front page features the playable simulation above small project previews',()=>{
  const page=read('page-home.php');
  const spotlight=read('parts/home-gastown-spotlight.php');
  const css=read('assets/css/home-feature-previews.css');
  const manifest=read('scripts/theme_deploy_manifest.py');
  const top=page.indexOf("get_template_part( 'parts/home-gastown-spotlight' )");
  const previews=page.indexOf("get_template_part( 'parts/home-feature-previews' )");
  const projects=page.indexOf('id="mission-select"');
  assert.ok(top>0 && previews>top && projects>previews);
  assert.match(spotlight, /home_url\( '\/gastown-sim\/' \)/);
  assert.match(spotlight, /ENTER GASTOWN/);
  assert.match(spotlight, /Canada Place/);
  assert.match(spotlight, /SCHEMATIC \/ NOT TO SCALE/);
  assert.match(spotlight, /aria-labelledby="home-gastown-spotlight-title"/);
  assert.match(css, /\.home-gastown-spotlight__play:focus-visible/);
  assert.match(css, /@media \(max-width: 520px\)/);
  assert.ok(manifest.includes('"parts/home-gastown-spotlight.php"'));
});

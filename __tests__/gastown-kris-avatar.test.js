'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

const places=fs.readFileSync(path.join(__dirname,'../js/gastown-waterfront-places.js'),'utf8');
const game=fs.readFileSync(path.join(__dirname,'../js/gastown-sim.js'),'utf8');

// Minimal Three.js test double: checks the actual exported low-poly builder,
// not just a string mentioning the features.
function mockThree() {
  class Vector {
    constructor(){this.x=0;this.y=0;this.z=0;}
    set(x,y,z){this.x=x;this.y=y;this.z=z;}
  }
  class Group {
    constructor(){this.children=[];this.userData={};this.position=new Vector();this.rotation={};}
    add(child){this.children.push(child);}
  }
  class Mesh extends Group {
    constructor(geometry,material){super();this.geometry=geometry;this.material=material;this.scale=new Vector();}
  }
  class Geometry {
    constructor(...args){this.args=args;}
  }
  class Material {
    constructor(options){this.options=options;}
  }
  return {Group,Mesh,BoxGeometry:Geometry,SphereGeometry:Geometry,
    CylinderGeometry:Geometry,MeshStandardMaterial:Material};
}
function avatar(){
  const sandbox={window:{}};
  vm.runInNewContext(places,sandbox,{filename:'gastown-waterfront-places.js'});
  assert.equal(typeof sandbox.window.GastownWaterfrontPlaces.createKrisAvatar,'function');
  return sandbox.window.GastownWaterfrontPlaces.createKrisAvatar(mockThree());
}
const matching=(model,name)=>model.children.filter(c=>c.name===name);

test('Kris character builds without textures or external assets',()=>{
  const model=avatar();
  assert.equal(model.userData.photoInspired,true);
  assert.ok(model.children.length>65,'face, vest, tattoo sleeves and accessories should be detailed');
  assert.equal(matching(model,'KK black trouser leg').length,2);
  assert.equal(matching(model,'KK open grey sleeveless vest front').length,2);
  assert.equal(matching(model,'KK tattooed forearm').length,2);
  assert.equal(matching(model,'KK rectangular eyeglass upper rim').length,2);
  assert.equal(matching(model,'KK backwards cap bill').length,1);
  assert.equal(matching(model,'KK long pointed beard').length,1);
  assert.equal(matching(model,'KK turquoise wrist bracelet').length,2);
});
test('eyeglasses and beard face forward and backwards cap brim points back',()=>{
  const model=avatar();
  const lenses=matching(model,'KK rectangular eyeglass upper rim');
  assert.ok(lenses.every(m=>m.position.z<0));
  assert.ok(matching(model,'KK long pointed beard')[0].position.z<0);
  assert.ok(matching(model,'KK backwards cap bill')[0].position.z>0);
  const arms=matching(model,'KK tattooed forearm');
  assert.ok(arms[0].position.x<0&&arms[1].position.x>0);
});
test('outdoor clone, lobby guide and sixth-floor greeter share one model',()=>{
  assert.match(places,/const greeter=createKrisAvatar\(THREE\)/);
  assert.match(places,/const kk=createKrisAvatar\(THREE\)/);
  assert.match(game,/const outdoor=journey\.userData\.kk\.clone\(true\)/);
  assert.match(places,/root\.userData\.greeter=greeter/);
  assert.match(places,/root\.userData=\{kk,lobby:true,elevator:true/);
  assert.doesNotMatch(places,/KK blue denim jacket|KK denim jacket/);
});
test('existing Landing voice and elevator choreography stay unchanged',()=>{
  assert.match(game,/landingSpeak\('hi snoozy!/i);
  assert.match(game,/landingSpeak\('bye snoooozie!'\)/);
  assert.match(game,/function updateLandingEscort\(delta,nowSeconds\)/);
  assert.match(game,/lab\.escort\.position\.set\(879\.35,0,849\.45\)/);
});

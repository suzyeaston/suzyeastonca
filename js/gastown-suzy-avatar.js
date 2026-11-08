/*
 * Suzy: deliberately stylized, lightweight procedural avatar.
 * Visual identity references the artist's own Futureproof performance photos:
 * silver-lavender half-up hair, clear glasses, dark jacket, pale denim,
 * microphone and a portable music rig. This is an original low-poly
 * character, not a photorealistic likeness or a biometric reconstruction.
 *
 * First person remains the default. Film/third-person view shows the model.
 */
(function (window) {
  'use strict';
  const COLORS = Object.freeze({
    hair: 0xc6b8ce,
    hairShade: 0x9785a5,
    jacket: 0x1c2027,
    jacketEdge: 0x323846,
    denim: 0xbac9cf,
    shoes: 0x14181d,
    shirt: 0x34343b,
    skin: 0xe0bea7,
    glasses: 0xdceaf2,
    lens: 0xa6c6d5,
    mic: 0x303e47
  });
  function mat(THREE, color, metalness) {
    return new THREE.MeshStandardMaterial({color,roughness:metalness?0.34:0.83,metalness:metalness||0});
  }
  function mesh(THREE, group, name, geometry, material, p, r) {
    const item=new THREE.Mesh(geometry,material);
    item.name=name;
    if(p)item.position.set(p[0],p[1],p[2]);
    if(r)item.rotation.set(r[0],r[1],r[2]);
    item.castShadow=true;
    group.add(item);
    return item;
  }
  function box(THREE, group, name, size, color, p, r) {
    return mesh(THREE,group,name,new THREE.BoxGeometry(...size),color,p,r);
  }
  function sphere(THREE, group, name, radius, color, p, scale) {
    const item=mesh(THREE,group,name,new THREE.SphereGeometry(radius,12,9),color,p);
    if(scale)item.scale.set(scale[0],scale[1],scale[2]);
    return item;
  }
  function joint(THREE, group, name, top, length, radius, material, angle) {
    const pivot=new THREE.Group();
    pivot.name=name;
    pivot.position.set(top[0],top[1],top[2]);
    group.add(pivot);
    mesh(THREE,pivot,name+' sleeve',new THREE.CylinderGeometry(radius*0.9,radius,length,9),material,
      [0,-length*0.5,0], angle?[0,0,angle]:null);
    return pivot;
  }
  function create(THREE) {
    if(!THREE||!THREE.Group)return null;
    const avatar=new THREE.Group();
    avatar.name='SUZY / playable artist / original low-poly avatar';
    avatar.userData.identity='Suzy Easton';
    avatar.userData.artistControlled=true;
    avatar.userData.visualReference='Futureproof 2026 performance portraits';
    const jacket=mat(THREE,COLORS.jacket),jacketEdge=mat(THREE,COLORS.jacketEdge);
    const skin=mat(THREE,COLORS.skin),hair=mat(THREE,COLORS.hair),hairShade=mat(THREE,COLORS.hairShade);
    const top=mat(THREE,COLORS.shirt),denim=mat(THREE,COLORS.denim);
    const shoes=mat(THREE,COLORS.shoes),glass=mat(THREE,COLORS.glasses,0.26);
    const mic=mat(THREE,COLORS.mic,0.42);
    // Figure proportions are cinematic and simplified, not claims about
    // height or body measurements. World units are metres.
    box(THREE,avatar,'Black overshirt torso',[0.56,0.68,0.25],jacket,[0,1.17,0]);
    box(THREE,avatar,'Charcoal undershirt',[0.24,0.57,0.03],top,[0,1.17,-0.142]);
    box(THREE,avatar,'Open jacket left lapel',[0.095,0.52,0.045],jacketEdge,[-0.18,1.25,-0.15],[0,0,-0.13]);
    box(THREE,avatar,'Open jacket right lapel',[0.095,0.52,0.045],jacketEdge,[0.18,1.25,-0.15],[0,0,0.13]);
    box(THREE,avatar,'Lavender hair collar',[0.30,0.15,0.17],hair,[0,1.55,0.07]);
    box(THREE,avatar,'Light denim waist',[0.44,0.19,0.25],denim,[0,0.79,0]);
    const legs=[
      joint(THREE,avatar,'Left denim leg',[-0.14,0.77,0],0.65,0.119,denim),
      joint(THREE,avatar,'Right denim leg',[0.14,0.77,0],0.65,0.119,denim)
    ];
    [-0.14,0.14].forEach((x,i)=>box(THREE,legs[i],'Black walking shoe',[0.23,0.13,0.38],shoes,[0,-0.66,-0.065]));
    const arms=[
      joint(THREE,avatar,'Left black jacket sleeve',[-0.34,1.43,0],0.55,0.122,jacket,-0.02),
      joint(THREE,avatar,'Right black jacket sleeve',[0.34,1.43,0],0.55,0.122,jacket,0.02)
    ];
    [-1,1].forEach((sign,i)=>sphere(THREE,arms[i],'Hand holding musical possibility',0.093,skin,[0,-0.59,0],[0.8,1.05,0.9]));
    mesh(THREE,avatar,'Neck',new THREE.CylinderGeometry(0.12,0.13,0.17,10),skin,[0,1.57,0]);
    sphere(THREE,avatar,'Head',0.22,skin,[0,1.79,-0.015],[0.86,1.15,0.88]);
    sphere(THREE,avatar,'Silver lavender crown',0.223,hair,[0,1.96,0.025],[0.91,0.47,0.9]);
    sphere(THREE,avatar,'Messy lavender bun',0.17,hairShade,[0.045,2.11,0.15],[1.05,0.76,0.89]);
    sphere(THREE,avatar,'Bun stray tuft',0.081,hair,[0.14,2.21,0.17],[0.75,1.6,0.8]);
    for(const x of [-0.2,0.19]){
      const lock=mesh(THREE,avatar,'Long pale face-framing hair',
        new THREE.CylinderGeometry(0.035,0.075,0.42,7),hair,[x,1.67,-0.112],[0,0,x>0?0.13:-0.15]);
      lock.castShadow=false;
    }
    const lens=mat(THREE,COLORS.lens,0.08);
    // Glasses on the forward (-Z) face; separate rims and transparent-ish lenses.
    [-0.092,0.092].forEach(x=>{
      mesh(THREE,avatar,'Translucent spectacle rim',
        new THREE.TorusGeometry(0.097,0.012,5,16),glass,[x,1.804,-0.219]);
      const l=mesh(THREE,avatar,'Glasses lens',
        new THREE.CircleGeometry(0.088,16),lens,[x,1.804,-0.221]);
      l.material.transparent=true;
      l.material.opacity=0.16;
    });
    box(THREE,avatar,'Glasses nose bridge',[0.044,0.012,0.03],glass,[0,1.81,-0.227]);
    box(THREE,avatar,'Small smile',[0.087,0.009,0.012],top,[0,1.714,-0.204]);
    // Compact handheld microphone in right hand. It also makes this an
    // unmistakably musical protagonist when framed against the harbour.
    const instrument=new THREE.Group();
    instrument.name='Handheld dynamic microphone';
    instrument.position.set(0,-0.53,-0.08);
    instrument.rotation.z=-0.35;
    arms[1].add(instrument);
    mesh(THREE,instrument,'Microphone handle',new THREE.CylinderGeometry(0.027,0.025,0.30,9),mic,[0,0.045,0]);
    sphere(THREE,instrument,'Microphone grille',0.066,mat(THREE,0x78868c,0.58),[0,0.23,0],[0.85,0.76,0.85]);
    avatar.userData.rig='microphone / local AI orchestra';
    avatar.userData.limbs={arms,legs};
    avatar.visible=false;
    return avatar;
  }
  function animate(avatar, seconds, walking) {
    if(!avatar||!avatar.userData||!avatar.userData.limbs)return;
    const active=Math.max(0,Math.min(1,walking||0));
    const stride=Math.sin((Number.isFinite(seconds)?seconds:0)*9)*0.36*active;
    const {arms,legs}=avatar.userData.limbs;
    legs[0].rotation.x=stride;
    legs[1].rotation.x=-stride;
    arms[0].rotation.x=-stride*0.64-0.04;
    arms[1].rotation.x=stride*0.4-0.48; // mic held toward the face
    avatar.position.y=Math.abs(Math.sin((Number.isFinite(seconds)?seconds:0)*9))*0.018*active;
  }
  window.GastownSuzyAvatar=Object.freeze({COLORS,create,animate});
})(window);

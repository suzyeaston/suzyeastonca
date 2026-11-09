/*
 * Suzy: deliberately stylized, lightweight procedural avatar.
 * Visual identity references the artist's own Futureproof performance photos:
 * silver-blonde half-up hair, clear glasses, dark jacket, pale denim,
 * microphone and a portable music rig. This is an original low-poly
 * character, not a photorealistic likeness or a biometric reconstruction.
 *
 * First person remains the default. Film/third-person view shows the model.
 */
(function (window) {
  'use strict';
  const COLORS = Object.freeze({
    hair: 0xd5d2c9,
    hairShade: 0xb3aca7,
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
    box(THREE,avatar,'Long platinum hair across shoulders',[0.37,0.27,0.17],hair,[0,1.54,0.075]);
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
    // The first model's enormous purple tuft read as animal ears and left a
    // bald tan hemisphere visible from behind. Build the hair around an
    // actual scalp volume, with side fringe and a restrained messy half-up bun.
    sphere(THREE,avatar,'Silver blonde full hair cap',0.228,hair,[0,1.83,0.042],[0.98,1.16,1.03]);
    sphere(THREE,avatar,'Face with sculpted chin',0.215,skin,[0,1.765,-0.069],[0.85,1.16,0.81]);
    sphere(THREE,avatar,'Cheek left',0.048,skin,[-0.141,1.730,-0.189],[1.0,0.63,0.42]);
    sphere(THREE,avatar,'Cheek right',0.048,skin,[0.141,1.730,-0.189],[1.0,0.63,0.42]);
    sphere(THREE,avatar,'Silver blonde hair crown',0.219,hair,[0,1.953,0.035],[1.0,0.48,0.94]);
    sphere(THREE,avatar,'Low half-up loose bun',0.126,hairShade,[0.062,2.042,0.206],[1.18,0.95,0.89]);
    // Distinctive soft bangs sweep across the forehead; no pointed spikes.
    for(let i=0;i<7;i++){
      const x=-0.16+i*0.052;
      const length=0.07+(i%3)*0.017;
      const strand=mesh(THREE,avatar,'Wispy layered bangs '+i,
        new THREE.CylinderGeometry(0.016,0.029,length,7),i%3?hair:hairShade,
        [x,1.932-length*0.2,-0.170],[0,0,-0.14]);
      strand.castShadow=false;
    }
    for(const x of [-0.204,0.202]){
      const lock=mesh(THREE,avatar,'Silver-blonde face framing lengths',
        new THREE.CylinderGeometry(0.024,0.060,0.52,9),hair,
        [x,1.678,-0.080],[0,0,x>0?0.11:-0.10]);
      lock.castShadow=false;
      sphere(THREE,avatar,'Soft loose hair ends',0.060,hair,
        [x*1.10,1.432,-0.085],[0.9,1.2,0.65]);
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
    // Features read clearly when the film camera moves around in front:
    // distinct dark eyes behind clear frames, natural brows, nose and lips.
    const features=mat(THREE,0x443a3b);
    const lip=mat(THREE,0x9d696c);
    for(const x of [-0.088,0.088]){
      sphere(THREE,avatar,'Warm brown visible eye',0.028,features,
        [x,1.795,-0.225],[1.0,0.60,0.37]);
      box(THREE,avatar,'Natural eyebrow',[0.075,0.013,0.011],features,
        [x,1.858,-0.211],[0,0,x>0?0.10:-0.10]);
    }
    sphere(THREE,avatar,'Sculpted nose',0.035,skin,[0,1.752,-0.234],[0.65,1.18,0.90]);
    // A nine-point, gently upturned mouth survives the low-poly front view.
    // The old flat lip ellipsoid was largely buried inside the chin mesh.
    // Keep every point just forward of the cheeks, not pasted onto glasses.
    for (let i = -4; i <= 4; i++) {
      const x = i * 0.019;
      const lift = 0.001 + 0.021 * Math.pow(Math.abs(i) / 4, 1.65);
      sphere(THREE, avatar, 'Mouth with soft smile arc ' + (i + 4),
        0.012, lip, [x, 1.667 + lift, -0.253],
        [1.02, 0.72, 0.57]);
    }

    // The orange/cream checkerboard straps in Suzy's reference photo are
    // distinctive from the previous all-black bag. The backpack carries the
    // same checker motif, with black edging and a front pocket. Procedural
    // texture is local; no photograph or face scan enters the website bundle.
    const bag=mat(THREE,0x16191d);
    const bagTrim=mat(THREE,0x29292d);
    const stitch=mat(THREE,0xc9d1db);
    const checker=mat(THREE,0xd46d44);
    if(window.document&&THREE.CanvasTexture){
      const cloth=window.document.createElement('canvas');
      cloth.width=256;cloth.height=256;
      const ink=cloth.getContext&&cloth.getContext('2d');
      if(ink){
        ink.fillStyle='#be512f';ink.fillRect(0,0,256,256);
        const tiles=8,tile=256/tiles;
        for(let row=0;row<tiles;row++)for(let col=0;col<tiles;col++){
          ink.fillStyle=(row+col)%2===0?'#e9d7ba':'#b84a2c';
          ink.fillRect(col*tile,row*tile,tile,tile);
        }
        // Subtle woven grid, not a bright neon checkerboard.
        if(ink.beginPath&&ink.moveTo&&ink.lineTo&&ink.stroke){
          ink.strokeStyle='rgba(37,27,27,.12)';ink.lineWidth=1;
          for(let v=0;v<256;v+=8){
            ink.beginPath();ink.moveTo(v,0);ink.lineTo(v,256);ink.stroke();
          }
        }
        const fabric=new THREE.CanvasTexture(cloth);
        fabric.colorSpace=THREE.SRGBColorSpace;
        if(checker.color&&typeof checker.color.setHex==='function')checker.color.setHex(0xffffff);
        checker.map=fabric;
        checker.needsUpdate=true;
      }
    }
    const backpack=new THREE.Group();
    backpack.name='Suzy orange checkerboard Vans backpack';
    backpack.position.set(0,1.20,0.245);
    avatar.add(backpack);
    sphere(THREE,backpack,'Rounded orange checkerboard Vans daypack shell',0.305,checker,
      [0,-0.04,0.13],[0.89,1.17,0.60]);
    sphere(THREE,backpack,'Orange checkerboard Vans front pocket',0.213,checker,
      [0,-0.165,0.287],[1.02,0.61,0.28]);
    for(const x of [-0.205,0.205]){
      box(THREE,backpack,'Backpack black edge webbing',[0.084,0.66,0.078],bagTrim,
        [x,-0.01,-0.06],[x>0?0.16:-0.16,0,0]);
      box(THREE,backpack,'Orange checker shoulder strap',[0.065,0.64,0.014],checker,
        [x,-0.01,-0.106],[x>0?0.16:-0.16,0,0]);
    }
    box(THREE,backpack,'Backpack top carry handle',[0.16,0.047,0.07],bagTrim,[0,0.335,0.04]);
    box(THREE,backpack,'Backpack pocket zipper',[0.28,0.017,0.012],stitch,[0,-0.075,0.357]);
    const patch=box(THREE,backpack,'Vans backpack white woven label',[0.17,0.080,0.02],stitch,[0,0.113,0.325]);
    patch.userData.wordmark='VANS';
    // Texture is optional for test doubles that have no browser canvas.
    if(window.document&&THREE.CanvasTexture&&THREE.PlaneGeometry){
      const canvas=window.document.createElement('canvas');
      canvas.width=192;canvas.height=96;
      const ctx=canvas.getContext&&canvas.getContext('2d');
      if(ctx){
        ctx.fillStyle='#efefef';ctx.fillRect(0,0,192,96);
        ctx.fillStyle='#131519';ctx.font='900 64px Arial';
        ctx.textAlign='center';ctx.textBaseline='middle';
        ctx.fillText('VANS',96,50,178);
        const texture=new THREE.CanvasTexture(canvas);
        texture.colorSpace=THREE.SRGBColorSpace;
        const mark=new THREE.Mesh(new THREE.PlaneGeometry(0.165,0.078),
          new THREE.MeshBasicMaterial({map:texture,side:THREE.DoubleSide}));
        mark.name='VANS woven patch lettering';
        mark.position.set(0,0.113,0.34);
        backpack.add(mark);
      }
    }
    avatar.userData.backpack='Orange/ivory checkerboard Vans backpack and straps';
    // Compact handheld microphone in right hand. It also makes this an
    // unmistakably musical protagonist when framed against the harbour.
    const instrument=new THREE.Group();
    instrument.name='Handheld dynamic microphone';
    instrument.position.set(0,-0.53,-0.08);
    instrument.rotation.z=-0.35;
    arms[1].add(instrument);
    mesh(THREE,instrument,'Microphone handle',new THREE.CylinderGeometry(0.027,0.025,0.30,9),mic,[0,0.045,0]);
    sphere(THREE,instrument,'Microphone grille',0.066,mat(THREE,0x78868c,0.58),[0,0.23,0],[0.85,0.76,0.85]);
    // Weather-responsive personal umbrella, shaped as an upward canopy rather
    // than an inverted bowl. It is hidden inside buildings and in dry weather.
    const umbrella = new THREE.Group();
    umbrella.name = 'Suzy upright Vancouver rain umbrella';
    const umbrellaFabric = mat(THREE,0x273548,0.68);
    const umbrellaMetal = mat(THREE,0x8796a0,0.45);
    const canopy = new THREE.Mesh(new THREE.CylinderGeometry(0,0.83,0.38,12,1,true),umbrellaFabric);
    canopy.name = 'Umbrella upright rain canopy';
    canopy.position.set(0.43,2.31,0.08);
    umbrella.add(canopy);
    mesh(THREE,umbrella,'Umbrella metal shaft',
      new THREE.CylinderGeometry(0.018,0.018,0.88,8),
      umbrellaMetal,[0.43,1.81,0.08]);
    mesh(THREE,umbrella,'Umbrella hand grip',
      new THREE.CylinderGeometry(0.04,0.04,0.18,8),
      umbrellaFabric,[0.43,1.41,0.08]);
    umbrella.visible=false;
    avatar.add(umbrella);
    avatar.userData.umbrella=umbrella;
    avatar.userData.rig='microphone / local AI orchestra';
    avatar.userData.limbs={arms,legs};
    avatar.visible=false;
    return avatar;
  }
  function animate(avatar, seconds, walking, groundOffset, mode='walking') {
    if(!avatar||!avatar.userData||!avatar.userData.limbs)return;
    const active=Math.max(0,Math.min(1,walking||0));
    const t=Number.isFinite(seconds)?seconds:0;
    const {arms,legs}=avatar.userData.limbs;
    if(mode==='flying'){
      // Hold a superhero flight silhouette. No footsteps, swinging legs or
      // walking bob while Suzy and her cape are airborne.
      legs[0].rotation.x=-0.12;legs[1].rotation.x=-0.12;
      arms[0].rotation.x=-0.85;arms[1].rotation.x=-0.85;
      arms[0].rotation.z=-0.22;arms[1].rotation.z=0.22;
      avatar.position.y=0;
      return;
    }
    arms[0].rotation.z=0;arms[1].rotation.z=0;
    const running=mode==='running';
    const stride=Math.sin(t*(running?14:9))*(running?0.59:0.36)*active;
    legs[0].rotation.x=stride;
    legs[1].rotation.x=-stride;
    arms[0].rotation.x=-stride*(running?0.85:0.64)-0.04;
    arms[1].rotation.x=stride*(running?0.75:0.4)-0.48;
    avatar.position.y=(Number.isFinite(groundOffset)?groundOffset:0)
      +Math.abs(Math.sin(t*(running?14:9)))*(running?0.034:0.018)*active;
  }
  function setUmbrellaWeather(avatar, isRainingOutside) {
    if (!avatar || !avatar.userData || !avatar.userData.umbrella) return;
    avatar.userData.umbrella.visible=!!isRainingOutside;
  }
  window.GastownSuzyAvatar=Object.freeze({COLORS,create,animate,setUmbrellaWeather});
})(window);

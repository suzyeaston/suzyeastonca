(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.GastownStreetLife = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  // Creative local-world scene, not a live GIS or vehicle tracking feed.
  function peopleFor(world, station) {
    if (!world || !world.parkingLot || !station || !station.sideEntranceFor) return [];
    const side = station.sideEntranceFor(world).approach;
    const p = world.parkingLot.center;
    const paths = [
      [['lot-chat-1', 'parking-smalltalk'], [{x:side.x+2,z:side.z+1},{x:side.x+7,z:side.z+4}]],
      [['lot-chat-2', 'parking-smalltalk'], [{x:side.x+6,z:side.z+5},{x:side.x+3,z:side.z+2}]],
      [['lot-highfive-1', 'parking-highfive'], [{x:p.x-4,z:p.z+2},{x:p.x-1.6,z:p.z+2.5}]],
      [['lot-highfive-2', 'parking-highfive'], [{x:p.x+1,z:p.z+2},{x:p.x+2,z:p.z+3}]],
      [['lot-walker-1', ''], [{x:p.x-6,z:p.z-7},{x:p.x+3,z:p.z-2}]],
      [['lot-walker-2', ''], [{x:p.x+6,z:p.z-8},{x:p.x+1,z:p.z-5}]]
    ];
    return paths.map(([identity, patrol], i) => ({
      id: identity[0], role: 'commuter', voiceCue: identity[1],
      name: identity[1] === 'parking-highfive' ? 'Friendly stranger' : 'Station East walker',
      dialogId: identity[1] === 'parking-highfive' ? 'parking_high_five' : 'parking_small_talk',
      behavior: 'route_walk', interactRadius: 2.7, silhouetteScale: 0.9 + (i % 3) * 0.04,
      patrol, idleSpot: patrol[0],
      companionGroup: i < 2 ? 'parking-chat' : i < 4 ? 'parking-highfive' : undefined
    }));
  }

  function bubble(THREE, label) {
    if (typeof document === 'undefined') return null;
    const canvas = document.createElement('canvas'); canvas.width = 256; canvas.height = 96;
    const ctx = canvas.getContext('2d'); if (!ctx) return null;
    ctx.fillStyle = '#0c1b23'; ctx.strokeStyle = '#a8d8eb'; ctx.lineWidth = 4;
    ctx.beginPath(); ctx.moveTo(20, 10); ctx.lineTo(236, 10); ctx.quadraticCurveTo(246, 10, 246, 20);
    ctx.lineTo(246, 67); ctx.quadraticCurveTo(246, 76, 236, 76);
    ctx.lineTo(142, 76); ctx.lineTo(128, 91); ctx.lineTo(116, 76);
    ctx.lineTo(20, 76); ctx.quadraticCurveTo(10, 76, 10, 65);
    ctx.lineTo(10, 20); ctx.quadraticCurveTo(10, 10, 20, 10); ctx.closePath(); ctx.fill(); ctx.stroke();
    ctx.fillStyle = '#f4f7f8'; ctx.font = 'bold 26px sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(label, 128, 44);
    const texture = new THREE.CanvasTexture(canvas); texture.colorSpace = THREE.SRGBColorSpace;
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({map:texture, transparent:true, depthWrite:false}));
    sprite.position.set(0, 2.65, 0); sprite.scale.set(2.6, 0.98, 1);
    return sprite;
  }

  function makeBus(THREE) {
    const root = new THREE.Group(); root.name = 'Illustrated 50 Waterfront bus (non-live)';
    const blue = new THREE.MeshStandardMaterial({color:0x195786, roughness:0.6});
    const grey = new THREE.MeshStandardMaterial({color:0xd0d7d8, roughness:0.64});
    const yellow = new THREE.MeshStandardMaterial({color:0xf9bb31});
    const glass = new THREE.MeshStandardMaterial({color:0x203646, roughness:0.25, metalness:0.19});
    const tyre = new THREE.MeshStandardMaterial({color:0x181e24, roughness:0.9});
    const lamp = new THREE.MeshBasicMaterial({color:0xffecc1});
    function box(w,h,d,x,y,z,material) {
      const m = new THREE.Mesh(new THREE.BoxGeometry(w,h,d),material);
      m.position.set(x,y,z); root.add(m);
    }
    box(2.55,2.75,10.65,0,1.85,0,grey);
    box(2.59,0.75,10.68,0,0.96,0,blue);
    box(2.60,0.18,10.70,0,1.42,0,yellow);
    box(2.60,0.12,10.71,0,3.25,0,blue);
    for (const side of [-1,1]) {
      for (let i=-4;i<=4;i++) box(0.045,0.86,0.92,side*1.29,2.33,i*1.1,glass);
      for (const z of [-3.5,3.5]) {
        const wheel = new THREE.Mesh(new THREE.CylinderGeometry(0.55,0.55,0.18,12),tyre);
        wheel.rotation.z = Math.PI/2; wheel.position.set(side*1.35,0.60,z); root.add(wheel);
      }
    }
    box(2.3,1.5,0.05,0,2.3,-5.35,glass);
    box(2.32,0.36,0.07,0,3.12,-5.43,tyre);
    for (const x of [-0.82,0.82]) box(0.27,0.13,0.06,x,1.15,-5.4,lamp);
    if (typeof document !== 'undefined') {
      const cv = document.createElement('canvas'); cv.width = 512; cv.height = 96;
      const cx = cv.getContext('2d');
      if (cx) {
        cx.fillStyle = '#091313'; cx.fillRect(0,0,512,96);
        cx.fillStyle = '#ffd158'; cx.font='bold 46px monospace'; cx.fillText('50 WATERFRONT',12,65);
        const t = new THREE.CanvasTexture(cv); t.colorSpace=THREE.SRGBColorSpace;
        const board = new THREE.Mesh(new THREE.PlaneGeometry(2.12,0.34),new THREE.MeshBasicMaterial({map:t}));
        board.position.set(0,3.12,-5.48); root.add(board);
      }
    }
    return root;
  }

  function create(THREE, world) {
    const root = new THREE.Group(); root.name='Station East street life';
    const lot = world && world.parkingLot;
    if (lot) {
      const lantern = new THREE.Group(); lantern.name='Station East lantern';
      const iron = new THREE.MeshStandardMaterial({color:0x2f3c43, roughness:0.6});
      const stone = new THREE.MeshStandardMaterial({color:0x9a9f9f,roughness:0.95});
      const glow = new THREE.MeshStandardMaterial({color:0xffe9b7,emissive:0xffc77e,emissiveIntensity:0.42});
      const base = new THREE.Mesh(new THREE.BoxGeometry(0.62,0.5,0.62),stone);
      base.position.y=0.25; lantern.add(base);
      const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.10,0.13,4.3,8),iron);
      pole.position.y=2.6; lantern.add(pole);
      const light = new THREE.Mesh(new THREE.BoxGeometry(0.54,0.55,0.54),glow);
      light.position.y=4.9; lantern.add(light);
      const cap = new THREE.Mesh(new THREE.ConeGeometry(0.47,0.25,4),iron);
      cap.rotation.y=Math.PI/4;cap.position.y=5.3;lantern.add(cap);
      lantern.position.set(lot.center.x+9,0,lot.center.z-13);root.add(lantern);
    }

    const centerline = world && world.route && world.route.centerline;
    let bus = null; const segments=[]; let total=0;
    if (Array.isArray(centerline) && centerline.length >= 3) {
      const first = centerline.slice(0,8);
      const points = first.map((pt,i) => {
        const before=first[Math.max(i-1,0)], after=first[Math.min(i+1,first.length-1)];
        const dx=after.x-before.x, dz=after.z-before.z, norm=Math.hypot(dx,dz)||1;
        return {x:pt.x+dz/norm*3.2,z:pt.z-dx/norm*3.2};
      });
      for (let i=1;i<points.length;i++) {
        const a=points[i-1],b=points[i],length=Math.hypot(b.x-a.x,b.z-a.z);
        segments.push({a,b,length,from:total}); total+=length;
      }
      bus=makeBus(THREE);root.add(bus);
    }
    function update(seconds) {
      if (!bus || !total) return;
      const d=((seconds*4.1+35)%(total+25))-12;
      bus.visible=d>=0&&d<=total; if (!bus.visible) return;
      const s=segments.find(item=>d<=item.from+item.length)||segments[segments.length-1];
      const t=Math.max(0,Math.min(1,(d-s.from)/(s.length||1)));
      const dx=s.b.x-s.a.x,dz=s.b.z-s.a.z;
      bus.position.set(s.a.x+dx*t,0.12,s.a.z+dz*t);
      bus.rotation.y=Math.atan2(-dx,-dz);
    }
    update(0);
    return {root,update};
  }
  return Object.freeze({peopleFor,bubble,create});
});

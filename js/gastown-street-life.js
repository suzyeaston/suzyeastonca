(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.GastownStreetLife = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  // Creative local-world scene, not a live GIS or vehicle tracking feed.
  function peopleFor(world, station) {
    if (!world || !world.parkingLot || !station || !station.sideEntranceFor) return [];
    const lot = world.parkingLot;
    if (!Array.isArray(lot.polygon) || lot.polygon.length < 7) return [];
    // The real parcel has a clear drive aisle between the Cordova entrance
    // and the harbour fence. Keep our walkers in that aisle instead of
    // accidentally putting them behind Waterfront Station's masonry walls.
    const entrance = { x: (lot.polygon[0].x + lot.polygon[1].x) / 2,
      z: (lot.polygon[0].z + lot.polygon[1].z) / 2 };
    const rear = { x: (lot.polygon[4].x + lot.polygon[5].x) / 2,
      z: (lot.polygon[4].z + lot.polygon[5].z) / 2 };
    const dx = rear.x - entrance.x, dz = rear.z - entrance.z;
    const length = Math.hypot(dx,dz) || 1;
    const lateral = { x: -dz/length, z: dx/length };
    function point(t, side) { return {
      x: entrance.x + dx*t + lateral.x*side,
      z: entrance.z + dz*t + lateral.z*side
    }; }
    const characters = [
      {id:'lot-chat-1',cue:'parking-smalltalk',t:0.26,side:-1.4},
      {id:'lot-chat-2',cue:'parking-smalltalk',t:0.34,side:1.4},
      {id:'lot-highfive-1',cue:'parking-highfive',t:0.49,side:-1.3},
      {id:'lot-highfive-2',cue:'parking-highfive',t:0.54,side:1.5},
      {id:'lot-walker-1',cue:'',t:0.69,side:-1.5},
      {id:'lot-walker-2',cue:'',t:0.75,side:1.2}
    ];
    return characters.map((item,i) => {
      const patrol = [point(item.t,item.side),point(Math.min(0.83,item.t+0.045),item.side)];
      return {
        id:item.id, role:'commuter',
        name:item.cue==='parking-highfive'?'Friendly local':'Station East local',
        voiceCue:item.cue,
        dialogId:item.cue==='parking-highfive'?'parking_high_five':'parking_small_talk',
        behavior:'route_walk', interactRadius:2.9, silhouetteScale:0.93+(i%3)*0.045,
        patrol, idleSpot:patrol[0],
        companionGroup:i<2?'station-chat':i<4?'station-high-five':undefined
      };
    });
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
    const root = new THREE.Group(); root.name = 'Illustrated Route 3 Waterfront electric trolley (non-live)';
    const blue = new THREE.MeshStandardMaterial({color:0x0866ae, roughness:0.6});
    const grey = new THREE.MeshStandardMaterial({color:0xc7d1d4, roughness:0.64});
    const steel = new THREE.MeshStandardMaterial({color:0x454e55, roughness:0.58});
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
    // Characteristic twin trolley collectors, rooftop electrical housing and
    // slim window mullions. Original geometry, deliberately light on draw calls.
    box(1.38,0.33,3.4,0,3.48,1.2,grey);
    function pole(start,end,radius,material) {
      const dx=end[0]-start[0], dy=end[1]-start[1], dz=end[2]-start[2];
      const length=Math.hypot(dx,dy,dz);
      const mesh=new THREE.Mesh(new THREE.CylinderGeometry(radius,radius,length,6),material);
      mesh.position.set((start[0]+end[0])/2,(start[1]+end[1])/2,(start[2]+end[2])/2);
      // Both collector poles lean rearward along local Z. No heavyweight
      // vector/quaternion dependencies in the lightweight scene test mock.
      mesh.rotation.x=Math.atan2(dz,dy);
      root.add(mesh);
    }
    for (const x of [-0.53,0.53]) {
      box(0.45,0.14,0.52,x,3.69,2.9,steel);
      pole([x,3.76,2.9],[x,5.42,5.8],0.055,steel);
      box(0.2,0.15,0.37,x,5.43,5.8,steel);
    }
    // Doors, mirrors, rear markers and low-profile bike rack distinguish the
    // vehicle from the old rectangular Route 50 placeholder.
    for (const z of [-2.7,2.65]) {
      box(0.065,1.62,1.14,-1.33,1.82,z,glass);
      box(0.082,1.64,0.055,-1.38,1.82,z,grey);
    }
    for (const x of [-1.44,1.44]) {
      box(0.32,0.42,0.12,x,2.6,-5.25,steel);
    }
    box(1.9,0.14,0.28,0,0.72,-5.53,steel);
    box(1.82,0.09,0.12,0,1.04,-5.56,steel);
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
        cx.fillStyle = '#ffd158'; cx.font='bold 46px monospace'; cx.fillText('3 WATERFRONT',12,65);
        const t = new THREE.CanvasTexture(cv); t.colorSpace=THREE.SRGBColorSpace;
        const board = new THREE.Mesh(new THREE.PlaneGeometry(2.12,0.34),new THREE.MeshBasicMaterial({map:t}));
        board.position.set(0,3.12,-5.48); root.add(board);
      }
    }
    // Original website house-ad, locally generated, with no third-party
    // transit branding or remote textures. Keep TransLink bus livery intact.
    // One panel faces each side of the illustrated electric trolley.
    if (typeof document !== 'undefined' && document.createElement) {
      const adCanvas = document.createElement('canvas');
      adCanvas.width = 1024;
      adCanvas.height = 256;
      const ctx = adCanvas.getContext('2d');
      if (ctx) {
        ctx.fillStyle = '#11151d';
        ctx.fillRect(0, 0, 1024, 256);
        ctx.fillStyle = '#75fa73';
        ctx.font = 'bold 112px monospace';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('suzyeaston.ca', 512, 128, 965);
        const adTexture = new THREE.CanvasTexture(adCanvas);
        adTexture.colorSpace = THREE.SRGBColorSpace;
        const adMaterial = new THREE.MeshBasicMaterial({
          map: adTexture, side: THREE.DoubleSide
        });
        for (const side of [-1, 1]) {
          const panel = new THREE.Mesh(new THREE.PlaneGeometry(4.75, 1.15), adMaterial);
          panel.name = 'suzyeaston.ca trolley side advertisement';
          // Local bus runs along Z; plane local X is aligned with Z after yaw.
          panel.position.set(side * 1.354, 1.96, 0.22);
          panel.rotation.y = side * Math.PI / 2;
          root.add(panel);
        }
        root.userData.advertiser = 'suzyeaston.ca';
      }
    }
    return root;
  }


  // Original lightweight meshes, not third-party models or live traffic.
  // Traffic follows the existing illustrated Cordova route; no road-network API.
  function makeStreetCar(THREE, index) {
    const car = new THREE.Group();
    car.name = 'Illustrated moving street car ' + (index + 1);
    const colors = [0x71858b, 0x9b4840, 0xc2ad8e, 0x334e69, 0x687b62, 0x9c9b94];
    const paint = new THREE.MeshStandardMaterial({color: colors[index % colors.length], roughness: 0.58});
    const glass = new THREE.MeshStandardMaterial({color:0x253b48, roughness:0.27});
    const rubber = new THREE.MeshStandardMaterial({color:0x1a2024,roughness:0.9});
    const headlight = new THREE.MeshBasicMaterial({color:0xffedc5});
    const taillight = new THREE.MeshBasicMaterial({color:0xa93c3c});
    function box(w,h,d,x,y,z,mat) {
      const mesh = new THREE.Mesh(new THREE.BoxGeometry(w,h,d),mat);
      mesh.position.set(x,y,z);car.add(mesh);
    }
    box(1.9,0.65,4.15,0,0.68,0,paint);
    box(1.62,0.61,2.25,0,1.18,-0.18,glass);
    for(const side of [-1,1]) {
      for(const z of [-1.35,1.27]) {
        const wheel = new THREE.Mesh(new THREE.CylinderGeometry(0.35,0.35,0.17,10),rubber);
        wheel.position.set(side*0.97,0.38,z);
        wheel.rotation.z = Math.PI/2;car.add(wheel);
      }
      box(0.3,0.16,0.06,side*0.67,0.65,-2.11,headlight);
      box(0.3,0.16,0.06,side*0.67,0.65,2.11,taillight);
    }
    return car;
  }

  // Vancouver-flavoured, ORIGINAL procedural soundscape. This does not
  // contain recorded conversations, unknown-language commuter samples,
  // copyrighted transit announcements, or externally hosted media.
  function makeLocalAmbience() {
    let ctx=null, master=null, active=false, nextGull=3.5, nextHorn=23, lastRoadWhoosh=-30;
    let currentWeather='clear', roadNoiseGain=null, rainNoiseGain=null;
    const AudioCtor = typeof window !== 'undefined' && (window.AudioContext || window.webkitAudioContext);
    function makeTone(start,peak,end,length,volume) {
      if(!ctx || !active) return;
      const now=ctx.currentTime, osc=ctx.createOscillator(), amp=ctx.createGain();
      osc.type='sine';
      osc.frequency.setValueAtTime(start,now);
      osc.frequency.linearRampToValueAtTime(peak,now+length*0.5);
      osc.frequency.linearRampToValueAtTime(end,now+length);
      amp.gain.setValueAtTime(0.0001,now);
      amp.gain.linearRampToValueAtTime(volume,now+length*0.22);
      amp.gain.exponentialRampToValueAtTime(0.0001,now+length);
      osc.connect(amp);amp.connect(master);osc.start(now);osc.stop(now+length+0.02);
    }
    function initialize() {
      ctx=new AudioCtor();
      master=ctx.createGain();master.gain.value=0;master.connect(ctx.destination);
      const sampleCount=Math.floor(ctx.sampleRate*2);
      const buffer=ctx.createBuffer(1,sampleCount,ctx.sampleRate);
      const samples=buffer.getChannelData(0);
      let seed=14071986;
      for(let i=0;i<samples.length;i++) {
        seed=(Math.imul(1664525,seed)+1013904223)>>>0;
        samples[i]=(seed/2147483648-1)*0.55;
      }
      // Clear weather: light, filtered city air. Rain: a separately gated,
      // brighter wash. Never leave a rain-like hiss playing when clear.
      for(const spec of [
        {type:'lowpass',hz:220,level:0.017,kind:'road'},
        {type:'highpass',hz:1200,level:0.10,kind:'rain'}
      ]) {
        const noise=ctx.createBufferSource();noise.buffer=buffer;noise.loop=true;
        const filter=ctx.createBiquadFilter();filter.type=spec.type;filter.frequency.value=spec.hz;
        const volume=ctx.createGain();volume.gain.value=spec.kind==='rain'?0:spec.level;
        noise.connect(filter);filter.connect(volume);volume.connect(master);noise.start();
        if(spec.kind==='rain')rainNoiseGain=volume;else roadNoiseGain=volume;
      }
      setWeather(currentWeather);
    }
    function setWeather(weather) {
      currentWeather=['clear','fog','drizzle','rain','thunderstorm'].includes(weather)?weather:'clear';
      if(!ctx)return;
      const rainLevel=currentWeather==='thunderstorm'?0.16:currentWeather==='rain'?0.10:currentWeather==='drizzle'?0.035:0;
      if(rainNoiseGain)rainNoiseGain.gain.setTargetAtTime(rainLevel,ctx.currentTime,0.4);
      if(roadNoiseGain)roadNoiseGain.gain.setTargetAtTime(rainLevel?0.008:0.017,ctx.currentTime,0.4);
    }
    function start() {
      if(!AudioCtor) return false;
      try {
        if(!ctx) initialize();
        active=true;
        master.gain.setTargetAtTime(0.75,ctx.currentTime,0.35);
        if(ctx.state==='suspended') ctx.resume().catch(()=>{});
        return true;
      } catch(error) {
        active=false;
        return false; // Ambient failure must never block the walk.
      }
    }
    function stop() {
      active=false;
      if(master && ctx) master.gain.setTargetAtTime(0,ctx.currentTime,0.16);
    }
    function update(seconds) {
      if(!active || !ctx || (typeof document!=='undefined' && document.hidden)) return;
      if(seconds>=nextGull) {
        makeTone(890,1190,740,0.48,0.015);
        nextGull=seconds+9.5+(Math.sin(seconds*0.3)+1)*3.7;
      }
      if(seconds>=nextHorn) {
        makeTone(115,128,110,1.45,0.008);
        nextHorn=seconds+44;
      }
      // Soft intermittent street-crossing tone, vehicle hiss and a
      // waterfront ferry horn. These are procedural effects, not recordings.
      if(Math.floor(seconds)%17===3 && seconds-lastRoadWhoosh>16){
        makeTone(175,280,110,1.2,0.014);lastRoadWhoosh=seconds;
      }
    }
    return {start,stop,update,setWeather};
  }

  function create(THREE, world) {
    const root = new THREE.Group(); root.name='Station East street life';
    const ambience=makeLocalAmbience();
    // Keep the lot central aisle open: do not generate a marker pillar or lantern.
    const centerline = world && world.route && world.route.centerline;
    let buses=[], segments=[], total=0, cars=[], lotCar=null;
    if(Array.isArray(centerline) && centerline.length>=3) {
      const first=centerline.slice(0,9);
      const points=first.map((pt,i)=>{
        const before=first[Math.max(i-1,0)],after=first[Math.min(i+1,first.length-1)];
        const dx=after.x-before.x,dz=after.z-before.z,norm=Math.hypot(dx,dz)||1;
        return {x:pt.x+dz/norm*3.2,z:pt.z-dx/norm*3.2};
      });
      for(let i=1;i<points.length;i++) {
        const a=points[i-1],b=points[i],length=Math.hypot(b.x-a.x,b.z-a.z);
        segments.push({a,b,length,from:total});total+=length;
      }
      buses=[makeBus(THREE),makeBus(THREE)];
      buses.forEach((bus,i)=>{bus.name+=' / vehicle '+(i+1);root.add(bus);});
      cars=Array.from({length:4},(_,i)=>makeStreetCar(THREE,i));
      cars.forEach(car=>root.add(car));
    }
    if(world && world.parkingLot && world.parkingLot.center && world.parkingLot.polygon && world.parkingLot.polygon.length>=2) {
      const lot=world.parkingLot;
      const entrance={x:(lot.polygon[0].x+lot.polygon[1].x)/2,z:(lot.polygon[0].z+lot.polygon[1].z)/2};
      const end={x:entrance.x+(lot.center.x-entrance.x)*0.78,z:entrance.z+(lot.center.z-entrance.z)*0.78};
      lotCar={mesh:makeStreetCar(THREE,4),entrance,end};
      lotCar.mesh.name='Slow moving illustrated Station East arrival';
      root.add(lotCar.mesh);
    }
    function along(distance,lateral,reverse,mesh) {
      if(!total || !segments.length) return;
      const d=((distance%total)+total)%total;
      const segment=segments.find(s=>d<=s.from+s.length)||segments[segments.length-1];
      const t=Math.max(0,Math.min(1,(d-segment.from)/(segment.length||1)));
      const dx=segment.b.x-segment.a.x,dz=segment.b.z-segment.a.z;
      const norm=Math.hypot(dx,dz)||1;
      mesh.position.set(segment.a.x+dx*t+dz/norm*lateral,0.12,segment.a.z+dz*t-dx/norm*lateral);
      mesh.rotation.y=Math.atan2(-dx,-dz)+(reverse?Math.PI:0);
    }
    function update(seconds) {
      // Route #3 trolleys run on the illustrated Cordova-side circuit.
      buses.forEach((bus,index)=>along(seconds*4.1+20+index*total/2,0,false,bus));
      cars.forEach((car,index)=>{
        // Water Street is westbound through-traffic, not a bidirectional
        // arcade. Centerline runs from Waterfront east toward Steam Clock.
        const distance=seconds*(3.0+index*0.5)+index*total/4;
        along(total-distance,-6.4,true,car);
      });
      if(lotCar) {
        const cycle=(seconds*0.115)%2, inbound=cycle<1;
        const t=inbound?cycle:2-cycle;
        const {entrance,end,mesh}=lotCar;
        const dx=end.x-entrance.x,dz=end.z-entrance.z;
        mesh.position.set(entrance.x+dx*t,0.12,entrance.z+dz*t);
        mesh.rotation.y=Math.atan2(-dx,-dz)+(inbound?0:Math.PI);
      }
      ambience.update(seconds);
    }
    update(0);
    return {root,update,startAudio:ambience.start,stopAudio:ambience.stop,setWeather:ambience.setWeather};
  }

  // Water Street heritage art-direction layer. GIS route points are authoritative;
  // facades and street furniture are illustrative, never surveyed placements.
  // Draw-call budget: shared materials and a small number of sampled frontage bays.
  function createWaterStreetDetails(THREE, world) {
    const root = new THREE.Group();
    root.name = 'Water Street / heritage streetscape and Steam Clock walk';
    const route = world && world.route && world.route.centerline;
    if (!Array.isArray(route) || route.length < 13) return root;
    const iron = new THREE.MeshStandardMaterial({color:0x252b2c,roughness:0.62,metalness:0.46});
    const stone = new THREE.MeshStandardMaterial({color:0xb8aa94,roughness:0.88});
    const lightStone = new THREE.MeshStandardMaterial({color:0xd4c7b0,roughness:0.86});
    const brick = [
      new THREE.MeshStandardMaterial({color:0x8a463b,roughness:0.9}),
      new THREE.MeshStandardMaterial({color:0x9e5844,roughness:0.9}),
      new THREE.MeshStandardMaterial({color:0x786356,roughness:0.9}),
      new THREE.MeshStandardMaterial({color:0x6e3a32,roughness:0.9})
    ];
    const shop = new THREE.MeshStandardMaterial({color:0x253940,roughness:0.65,metalness:0.16});
    const glass = new THREE.MeshStandardMaterial({color:0x375467,roughness:0.24,metalness:0.24});
    const warmGlass = new THREE.MeshStandardMaterial({color:0x74919a,emissive:0x71512e,emissiveIntensity:0.18,roughness:0.3});
    const foliage = new THREE.MeshStandardMaterial({color:0x3f5037,roughness:0.9});
    const wood = new THREE.MeshStandardMaterial({color:0x6c4935,roughness:0.85});
    const globe = new THREE.MeshStandardMaterial({color:0xffe6bb,emissive:0xffc883,emissiveIntensity:0.24,roughness:0.46});
    const darkRoad = new THREE.MeshStandardMaterial({color:0x624039,roughness:0.84,metalness:0.03});
    function box(group,name,x,y,z,w,h,d,mat) {
      const m = new THREE.Mesh(new THREE.BoxGeometry(w,h,d),mat);
      m.position.set(x,y,z);m.name=name;m.castShadow=false;m.receiveShadow=true;
      group.add(m);return m;
    }
    function cylinder(group,name,x,y,z,r,h,mat,edges) {
      const m = new THREE.Mesh(new THREE.CylinderGeometry(r,r,h,edges||8),mat);
      m.position.set(x,y,z);m.name=name;m.castShadow=false;group.add(m);return m;
    }
    function join(group,name,a,b,r,mat) {
      const dx=b.x-a.x,dy=b.y-a.y,dz=b.z-a.z;
      const len=Math.hypot(dx,dy,dz);
      if(len<0.01)return;
      const mesh=new THREE.Mesh(new THREE.CylinderGeometry(r,r,len,6),mat);
      mesh.name=name;
      mesh.position.set((a.x+b.x)/2,(a.y+b.y)/2,(a.z+b.z)/2);
      if(mesh.quaternion && typeof mesh.quaternion.setFromUnitVectors==='function' && THREE.Vector3) {
        mesh.quaternion.setFromUnitVectors(new THREE.Vector3(0,1,0),new THREE.Vector3(dx,dy,dz).normalize());
      } else {
        mesh.rotation.x=Math.atan2(dz,dy);
        mesh.rotation.z=-Math.atan2(dx,Math.hypot(dy,dz));
      }
      group.add(mesh);
    }
    function frame(i) {
      const a=route[i],b=route[i+1],dx=b.x-a.x,dz=b.z-a.z;
      const length=Math.hypot(dx,dz)||1;
      return {x:(a.x+b.x)/2,z:(a.z+b.z)/2,length,
        yaw:Math.atan2(-dx,-dz)};
    }
    function slab(i,width,material,y) {
      const f=frame(i);
      const pane=new THREE.Mesh(new THREE.PlaneGeometry(width,f.length+0.16),material);
      pane.rotation.set(-Math.PI/2,f.yaw,0,'YXZ');
      pane.position.set(f.x,y||0.04,f.z);
      pane.name='Water Street brick roadway / historic paver lane';
      pane.receiveShadow=true;pane.renderOrder=2;root.add(pane);
    }
    // Self-authored repeating brick pattern. No third-party Street View
    // imagery or geolocation textures are reproduced in the simulator.
    let paving=darkRoad;
    if(typeof document!=='undefined' && document.createElement && THREE.CanvasTexture) {
      const canvas=document.createElement('canvas');canvas.width=256;canvas.height=256;
      const cx=canvas.getContext('2d');
      if(cx) {
        cx.fillStyle='#634037';cx.fillRect(0,0,256,256);
        for(let row=0;row<16;row++){
          for(let col=-1;col<9;col++){
            const x=col*34+(row%2)*17,y=row*16,shade=(row*11+col*7+53)%5;
            cx.fillStyle=['#895244','#78443a','#995c4e','#70463d','#815248'][Math.abs(shade)];
            cx.fillRect(x+1,y+1,32,14);
            cx.fillStyle='rgba(228,182,143,.07)';cx.fillRect(x+2,y+2,30,1);
          }
        }
        const texture=new THREE.CanvasTexture(canvas);
        texture.wrapS=THREE.RepeatWrapping;texture.wrapT=THREE.RepeatWrapping;
        texture.repeat.set(1.4,4);
        texture.colorSpace=THREE.SRGBColorSpace;
        texture.anisotropy=4;
        paving=new THREE.MeshStandardMaterial({map:texture,color:0xb6a49a,roughness:0.79,metalness:0.09});
      }
    }
    // Indices 9-15 belong to Water Street, not the West Cordova trolley zone.
    for(let i=9;i<route.length-1;i++)slab(i,10.4,paving,0.055);

    function sign(group,label,x,y,z,w,mat) {
      // Simple original shop signs; no historic or current tenant claims.
      const plate=box(group,label+' signboard',x,y,z,w,0.43,0.16,mat);
      plate.userData.displayLabel=label;return plate;
    }
    // The GIS building meshes already supply real wall footprints. Previously
    // this pass added freestanding 12.7m opaque facade boxes into the roadway,
    // blocking the entire route. Keep *only* street furniture here; dedicated
    // footprint-aligned facade modules need a separately surveyed frontage.
    for(let i=9;i<route.length-1;i++){
      const f=frame(i);
      for(const side of [-1,1]) {
        const street=new THREE.Group();street.name='Water Street heritage sidewalk furniture';
        street.position.set(f.x,0,f.z);street.rotation.y=f.yaw;root.add(street);
        for(let n=-1;n<=1;n++){
          const x=n*5.1,z=side*7.35;
          const post=cylinder(street,'Black cast iron chain bollard',x,0.48,z,0.085,0.95,iron);
          cylinder(street,'Bollard spherical cap',x,1.02,z,0.13,0.18,iron);
          if(n<1) {
            const next=x+5.1;
            join(street,'Sagging heritage curb chain',{x,y:0.87,z},{x:(x+next)/2,y:0.65,z},0.023,iron);
            join(street,'Sagging heritage curb chain',{x:(x+next)/2,y:0.65,z},{x:next,y:0.87,z},0.023,iron);
          }
        }
        // Five globe lamps, warm and ornate, at alternating sidewalk bays.
        if(i%2===1){
          const z=side*8.6;
          cylinder(street,'Gastown five-globe iron lamppost',0,2.65,z,0.10,5.2,iron);
          for(const dx of [-0.52,0,0.52]){
            const y=dx===0?5.55:4.92;
            if(dx!==0)join(street,'Lamp ornate bracket',{x:0,y:4.8,z},{x:dx,y:y-0.1,z},0.045,iron);
            const orb=new THREE.Mesh(new THREE.SphereGeometry(0.23,9,7),globe);
            orb.name='Five globe heritage street light';orb.position.set(dx,y,z);street.add(orb);
          }
        }
        if(i%3===1) {
          // Leafless branching shape is recognizable in grey Vancouver weather.
          const z=side*8.85,x=3.3;
          cylinder(street,'Water Street mature tree trunk',x,2,z,0.21,3.8,wood);
          for(const arm of [-1,1])join(street,'Gastown street tree winter branches',
            {x,y:3.4,z},{x:x+arm*1.3,y:5.9,z:z+0.65},0.055,wood);
          box(street,'Iron street-tree grate',x,0.15,z,1.6,0.04,1.6,iron);
        }
        if(i===11 || i===14){
          box(street,'Gastown sidewalk bench seat',-2,0.56,side*8.85,1.6,0.12,0.5,wood);
          for(const x of [-2.6,-1.4])box(street,'Iron bench leg',x,0.3,side*8.85,0.09,0.54,0.14,iron);
          box(street,'Gastown bicycle rack',3.0,0.56,side*8.4,0.13,1,1.2,iron);
        }
      }
    }
    // A compact serviced delivery van at the curb, not a free-floating
    // fast traffic car; place it after the Steam Clock plaza approach.
    const vf=frame(12),delivery=new THREE.Group();
    delivery.name='Water Street parked local delivery van';
    delivery.position.set(vf.x,0.12,vf.z);
    delivery.rotation.y=vf.yaw;
    box(delivery,'White courier van body',3.8,1.45,-3.4,2.1,2.25,4.6,lightStone);
    box(delivery,'Van windscreen',3.8,1.75,-5.74,1.85,1.12,0.09,glass);
    for(const x of [2.65,4.95])for(const z of [-4.8,-2.3]){
      const wheel=new THREE.Mesh(new THREE.CylinderGeometry(0.46,0.46,0.15,10),iron);
      wheel.rotation.z=Math.PI/2;wheel.position.set(x,0.52,z);delivery.add(wheel);
    }
    root.add(delivery);
    root.userData.referenceApproximation=true;
    root.userData.routeStart=9;
    root.userData.routeEnd=route.length-1;
    return root;
  }

  return Object.freeze({peopleFor,bubble,create,createWaterStreetDetails});
});

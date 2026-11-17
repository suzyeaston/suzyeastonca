/*
 * Waterfront West Coast Express: original low-poly station / animated train.
 * Representative scenery only: not a surveyed track layout, station wayfinding
 * map or live vehicle-position feed. +X east, +Z south, +Y up.
 * References:
 * https://www.translink.ca/schedules-and-maps/west-coast-express
 * https://vancouver.ca/files/cov/2022-519-release.pdf
 */
(function(window){
  'use strict';
  const SOURCES={
    station:'https://www.translink.ca/schedules-and-maps/west-coast-express',
    yard:'https://vancouver.ca/files/cov/2022-519-release.pdf',
    fleet:'https://www.trains.com/pro/passenger/commuter-regional/vancouvers-west-coast-express-expands-service/'
  };
  function material(THREE,color,roughness,metalness){
    return new THREE.MeshStandardMaterial({color,roughness:roughness===undefined?0.75:roughness,metalness:metalness||0,fog:false});
  }
  function box(THREE,parent,name,x,y,z,w,h,d,mat){
    const mesh=new THREE.Mesh(new THREE.BoxGeometry(w,h,d),mat);
    mesh.name=name;mesh.position.set(x,y,z);
    mesh.castShadow=false;mesh.receiveShadow=true;
    parent.add(mesh);return mesh;
  }
  function sign(THREE,parent,name,x,y,z,w,h,backFacing){
    if(!window.document||!window.document.createElement)return;
    const canvas=window.document.createElement('canvas');
    canvas.width=1024;canvas.height=128;
    const ctx=canvas.getContext('2d');if(!ctx)return;
    ctx.fillStyle='#173954';ctx.fillRect(0,0,1024,128);
    ctx.fillStyle='#ffedab';ctx.font='bold 63px Arial, sans-serif';
    ctx.textAlign='center';ctx.textBaseline='middle';
    ctx.fillText(name,512,65,990);
    const texture=new THREE.CanvasTexture(canvas);texture.colorSpace=THREE.SRGBColorSpace;
    const mesh=new THREE.Mesh(new THREE.PlaneGeometry(w,h),
      new THREE.MeshBasicMaterial({map:texture,side:THREE.DoubleSide,fog:false}));
    mesh.name=name;mesh.position.set(x,y,z);
    // PlaneGeometry has a front and a back. Rotate the -Z-side label so its
    // text reads forward instead of mirrored from the platform camera.
    if(backFacing)mesh.rotation.y=Math.PI;
    parent.add(mesh);
  }
  function create(THREE,world){
    const station=world&&world.station;
    if(!station||!Number.isFinite(station.x)||!Number.isFinite(station.z))return null;
    const root=new THREE.Group();
    root.name='Waterfront West Coast Express platform and animated commuter train';
    // Station yaw describes its heritage street facade, NOT the railway.
    // The previous WCE root rotated the commuter line by -45 degrees,
    // sending trains across the N-Yard and toward Station East.
    // Coordinates below are in the same +X east / +Z south frame as the yard.
    root.position.set(0,-1.35,0);
    root.rotation.y=0;
    const gravel=material(THREE,0x555653,0.96),
      steel=material(THREE,0xd2cec1,0.34,0.69),
      steelWeb=material(THREE,0x79716c,0.76,0.35),
      sleeper=material(THREE,0x6c5646,0.89),
      concrete=material(THREE,0xadaaa1,0.94),
      cream=material(THREE,0xe1dbce,0.69),
      maroon=material(THREE,0x79424a,0.72),
      yellow=material(THREE,0xf5c12d,0.72),
      blue=material(THREE,0x165080,0.62),
      navy=material(THREE,0x1b2b56,0.53),
      white=material(THREE,0xebede9,0.69),
      glass=material(THREE,0x1b354a,0.22,0.14),
      wheels=material(THREE,0x24252b,0.88);
    // The three passenger tracks leave Waterfront Station eastward. They
    // run parallel through the platform and curve gently northeast into the
    // rail corridor. None doubles back across the eastbound freight yard.
    const routes=[0,1,2].map(row=>{
      const offset=row*5.8;
      return [{x:-96,z:-18-offset},{x:22,z:-18-offset},
        {x:75,z:-38-offset},{x:166,z:-60-offset}];
    });
    const ties=[],fallback=[];
    routes.forEach(route=>{
      for(let index=0;index<route.length-1;index++){
        const a=route[index],b=route[index+1],dx=b.x-a.x,dz=b.z-a.z;
        const len=Math.hypot(dx,dz),yaw=Math.atan2(-dz,dx);
        const cross={x:-dz/len,z:dx/len};
        const mid={x:(a.x+b.x)/2,z:(a.z+b.z)/2};
        box(THREE,root,'WCE gravel rail bed',mid.x,0.145,mid.z,len+0.35,0.18,3.58,gravel).rotation.y=yaw;
        for(const side of [-0.755,0.755]){
          const x=mid.x+cross.x*side,z=mid.z+cross.z*side;
          box(THREE,root,'WCE raised steel rail',x,0.375,z,len+0.12,0.13,0.10,steel).rotation.y=yaw;
          box(THREE,root,'WCE rail web',x,0.285,z,len+0.12,0.065,0.052,steelWeb).rotation.y=yaw;
        }
        for(let t=0.48;t<len-0.5;t+=0.88){
          const x=a.x+dx*t/len,z=a.z+dz*t/len;
          ties.push({x,z,yaw});
          fallback.push(x-cross.x*1.36,0.25,z-cross.z*1.36,
            x+cross.x*1.36,0.25,z+cross.z*1.36);
        }
      }
    });
    // Instancing avoids hundreds of individual Three.js draw calls on 8GB Macs.
    if(typeof THREE.InstancedMesh==='function'&&typeof THREE.Object3D==='function'){
      const mesh=new THREE.InstancedMesh(new THREE.BoxGeometry(0.26,0.11,2.72),sleeper,ties.length);
      mesh.name='WCE instanced timber sleepers';
      const dummy=new THREE.Object3D();
      ties.forEach((p,i)=>{
        dummy.position.set(p.x,0.25,p.z);
        dummy.rotation.set(0,p.yaw,0);dummy.updateMatrix();
        mesh.setMatrixAt(i,dummy.matrix);
      });
      mesh.instanceMatrix.needsUpdate=true;
      if(typeof mesh.computeBoundingSphere==='function')mesh.computeBoundingSphere();
      root.add(mesh);
    }else if(typeof THREE.LineSegments==='function'){
      const geo=new THREE.BufferGeometry();
      geo.setAttribute('position',new THREE.Float32BufferAttribute(fallback,3));
      const mesh=new THREE.LineSegments(geo,new THREE.LineBasicMaterial({color:0x947b68}));
      mesh.name='WCE batched timber sleeper fallback';root.add(mesh);
    }
    // Scene geometry has no surveyed platform footprint. The old -77..+37
    // west-to-east canopy cut directly THROUGH the rotated heritage hall:
    // a giant blue WCE sign appeared suspended inside the main concourse.
    // Stage the visible boarding apron only east of x=3, clear of the
    // station's rear wall. Tracks and train route remain independent.
    const apronWest=3,apronEast=78,apronWidth=apronEast-apronWest;
    const apronMid=(apronWest+apronEast)/2;
    box(THREE,root,'West Coast Express Waterfront boarding platform',
      apronMid,0.19,-10.35,apronWidth,0.31,9.15,concrete);
    box(THREE,root,'West Coast Express yellow tactile safety strip',
      apronMid,0.371,-14.65,apronWidth-1.5,0.045,0.62,yellow);
    box(THREE,root,'West Coast Express platform roof canopy',
      apronMid,5.91,-9.6,apronWidth,0.33,9.1,cream);
    for(const x of [7,23,40,57,74]){
      for(const z of [-6.7,-13.1]){
        box(THREE,root,'Waterfront platform maroon canopy post',
          x,3.02,z,0.27,5.75,0.27,maroon);
      }
    }
    box(THREE,root,'Waterfront platform south fascia',apronMid,5.57,-5.8,apronWidth,0.4,0.25,maroon);
    box(THREE,root,'Waterfront platform rail-facing fascia',apronMid,5.57,-14.9,apronWidth,0.4,0.25,maroon);
    sign(THREE,root,'WEST COAST EXPRESS · WATERFRONT',26,5.05,-5.64,17.5,0.85);
    // Visible passenger route from the heritage rear arch lands at the
    // eastern boarding apron near x=6, z=-9 (world coordinates). These are
    // light, authored platform cues, never a shortcut across the tracks.
    for(const x of [2,8,14,20,26,32]){
      box(THREE,root,'WCE east platform canopy skylight edge',
        x,5.73,-8.9,0.14,0.10,7.7,steelWeb);
    }
    box(THREE,root,'WCE transfer arrival navy information board',
      8,3.9,-5.75,12.2,0.94,0.12,navy);
    sign(THREE,root,'WATERFRONT / WEST COAST EXPRESS',
      8,3.9,-5.64,11.7,0.6);
    box(THREE,root,'WCE transfer accessibility information kiosk',
      20,1.25,-6.75,1.25,2.5,0.65,blue);
    box(THREE,root,'WCE transfer platform information display',
      20,1.85,-6.40,0.96,0.63,0.08,glass);
    for(const x of [16,57]){
      box(THREE,root,'WCE platform bench seat',x,0.92,-7.8,3.25,0.18,0.65,navy);
      for(const leg of [-1.1,1.1]){
        box(THREE,root,'WCE platform bench leg',x+leg,0.56,-7.8,0.14,0.67,0.12,steelWeb);
      }
    }
    // Animated blue/white bi-level commuter consist. Shortened to three
    // coaches and a recognisable blue/yellow F59PH cab for scene performance.
    for(const [index,center] of [-50,-28,-6].entries()){
      const coach=new THREE.Group();coach.name='West Coast Express bi-level coach '+(index+1);
      coach.position.set(center,0,-18);root.add(coach);
      box(THREE,coach,'Bilevel white passenger body',0,2.64,0,20.8,3.75,3.2,white);
      box(THREE,coach,'Bilevel navy roof',0,4.66,0,20.9,0.3,3.28,navy);
      box(THREE,coach,'Bilevel blue lower skirt',0,0.91,0,20.8,0.38,3.25,blue);
      for(const side of [-1,1]){
        const face=side*1.62;
        box(THREE,coach,'Bilevel yellow waist stripe',0,1.38,face,20.3,0.12,0.045,yellow);
        for(const deckY of [2.2,3.68]){
          for(const offset of [-7.3,-2.45,2.45,7.3]){
            box(THREE,coach,'West Coast Express double-deck window',
              offset,deckY,face,3.45,0.67,0.048,glass);
          }
        }
      }
      // Distinctive side nameplates make the consist read as WCE, not as
      // generic bi-level railcars when the camera pans from The Landing.
      sign(THREE,coach,'WEST COAST EXPRESS',0,1.13,1.685,8.6,0.48);
      sign(THREE,coach,'WEST COAST EXPRESS',0,1.13,-1.685,8.6,0.48,true);
      box(THREE,coach,'WCE blue entry door',-9.2,2.12,1.65,1.18,2.15,0.07,blue);
      for(const bogieX of [-6.7,6.7]){
        box(THREE,coach,'WCE wheel bogie',bogieX,0.54,0,3.5,0.76,2.66,wheels);
      }
    }
    const locomotive=new THREE.Group();
    locomotive.name='West Coast Express blue and yellow F59PH locomotive';
    locomotive.position.set(14,0,-18);root.add(locomotive);
    box(THREE,locomotive,'WCE navy-blue locomotive body',0,2.44,0,18.5,3.6,3.45,blue);
    box(THREE,locomotive,'WCE white cab',5.35,3.65,0,7.7,2,3.35,white);
    box(THREE,locomotive,'WCE blue roof',0,4.4,0,18.6,0.36,3.45,navy);
    box(THREE,locomotive,'WCE yellow nose',9.36,2.1,0,0.28,2.68,3.25,yellow);
    for(const side of [-1,1]){
      box(THREE,locomotive,'WCE yellow lightning side stripe',
        -0.45,2.25,side*1.765,17.2,0.66,0.04,yellow);
    }
    // Blue and yellow locomotive should be identifiable from either side.
    sign(THREE,locomotive,'WEST COAST EXPRESS',-2.2,3.0,1.79,9.2,0.8);
    sign(THREE,locomotive,'WEST COAST EXPRESS',-2.2,3.0,-1.79,9.2,0.8,true);
    box(THREE,locomotive,'WCE dark windshield',9.52,3.62,0,0.13,0.88,2.68,glass);
    box(THREE,locomotive,'WCE headlight',9.61,2.78,0,0.14,0.31,0.39,cream);
    for(const x of [-5.5,5.4]){
      box(THREE,locomotive,'WCE powered bogie',x,0.58,0,3.7,0.78,2.82,wheels);
    }
    // Bus-style deterministic route animation. All vehicles share ONE motion
    // controller and sample the same centerline, with fixed consist spacing.
    // Track geometry/platform remain static. This is an illustrative shuttle,
    // not a live train schedule or a claim of actual WCE reverse operations.
    const path=[];
    let pathLength=0;
    routes[0].forEach((point,i)=>{
      if(i>0)pathLength+=Math.hypot(point.x-routes[0][i-1].x,point.z-routes[0][i-1].z);
      path.push({x:point.x,z:point.z,at:pathLength});
    });
    const vehicleOffsets=[-64,-42,-20,0]; // cars 1..3, then locomotive
    const trainVehicles=[
      ...root.children.filter(node=>node.name.startsWith('West Coast Express bi-level coach ')),
      locomotive
    ];
    root.userData.trainMotion={
      path,vehicles:trainVehicles,offsets:vehicleOffsets,
      platformHeadDistance:110,excursion:120,
      dwellSeconds:8,runSeconds:28,endPauseSeconds:2,
      cycleSeconds:66
    };
    root.userData.westCoastExpress=true;
    root.userData.stagedTrainNotLive=true;
    root.userData.platformSurveyed=false;
    root.userData.railElevation=-1.35;
    root.userData.railCoordinateFrame='world-eastbound';
    root.userData.stationAnchor={x:station.x,z:station.z};
    root.userData.hasIndependentBuildingYaw=true;
    root.userData.platformBelowPublicLot=true;
    root.userData.tracksSurveyed=false;
    root.userData.vehicleCount=4;
    root.userData.routeTracks=routes;
    root.userData.simulatedMotion=true;
    root.userData.motionIsNotLiveTracking=true;
    root.userData.referenceSources=SOURCES;
    root.userData.noPublicRailAccess=true; // tracks and N-Yard remain restricted
    root.userData.publicPlatformConnection='rear heritage arcade via sloped pedestrian passage';
    root.userData.platformWalkableEasternApron=true;
    root.userData.visiblePlatformExtent={west:apronWest,east:apronEast};
    root.userData.canopyDoesNotIntersectHeritageHall=true;
    animate(root,0);
    return root;
  }
  function sampleTrack(path,distance){
    if(!Array.isArray(path)||path.length<2)return null;
    const max=path[path.length-1].at;
    const d=Math.max(0,Math.min(max,distance));
    let i=1;
    while(i<path.length-1&&d>path[i].at)i++;
    const a=path[i-1],b=path[i];
    const span=Math.max(0.001,b.at-a.at);
    const t=(d-a.at)/span;
    return {x:a.x+(b.x-a.x)*t,z:a.z+(b.z-a.z)*t,
      yaw:Math.atan2(-(b.z-a.z),b.x-a.x)};
  }
  function motionHeadDistance(motion,seconds){
    const time=((Math.max(0,Number(seconds)||0)%motion.cycleSeconds)
      +motion.cycleSeconds)%motion.cycleSeconds;
    const start=motion.platformHeadDistance;
    const end=start+motion.excursion;
    const dwell=motion.dwellSeconds;
    const run=motion.runSeconds;
    const pause=motion.endPauseSeconds;
    const ease=t=>t*t*(3-2*t);
    if(time<=dwell)return start;
    if(time<dwell+run)return start+(end-start)*ease((time-dwell)/run);
    if(time<dwell+run+pause)return end;
    return end-(end-start)*ease(Math.min(1,(time-dwell-run-pause)/run));
  }
  function animate(root,seconds){
    if(!root||!root.userData||!root.userData.trainMotion)return;
    const motion=root.userData.trainMotion;
    const head=motionHeadDistance(motion,seconds);
    motion.vehicles.forEach((vehicle,i)=>{
      const position=sampleTrack(motion.path,head+motion.offsets[i]);
      if(!position)return;
      vehicle.position.set(position.x,0,position.z);
      vehicle.rotation.y=position.yaw;
    });
    root.userData.trainMotionCurrentHead=head;
  }
  window.GastownWestCoastExpress=Object.freeze({create,animate,reference:SOURCES});
})(window);

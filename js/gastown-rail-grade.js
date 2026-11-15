/*
 * Station East / The Landing rail-edge section.
 *
 * Scene coordinates use +X east, +Z south. The lower service lane, chain-link
 * rail boundary, and tracks are non-walkable scenic geometry.
 * They express the documented STATION -> LOT -> RAIL CORRIDOR relationship.
 * Lane geometry/grades are illustrative (not an access or railway survey).
 *
 * See TransLink's Waterfront/WCE station maps, Heritage Vancouver 555 Cordova,
 * and the City of Vancouver 2022 N-Yard/rail hazards study.
 */
(function(window){
  'use strict';
  const RAIL_LEVEL=-1.35, LANE_LEVEL=-0.55, LOT_LEVEL=0.19;
  const REFS={
    railway:'https://vancouver.ca/files/cov/2022-519-release.pdf',
    parking:'https://heritagevancouver.org/top10-watch-list/2021/8-555-west-cordova/',
    station:'https://www.translink.ca/schedules-and-maps/west-coast-express'
  };
  function standard(THREE,color,roughness,metalness){
    return new THREE.MeshStandardMaterial({
      color,roughness:roughness===undefined?0.89:roughness,
      metalness:metalness||0,fog:false
    });
  }
  function slab(THREE,group,name,points,y,material){
    const shape=new THREE.Shape();
    points.forEach((p,i)=>i?shape.lineTo(p.x,-p.z):shape.moveTo(p.x,-p.z));
    shape.closePath();
    const mesh=new THREE.Mesh(new THREE.ShapeGeometry(shape),material);
    mesh.rotation.x=-Math.PI/2;
    mesh.position.y=y;
    mesh.name=name;mesh.receiveShadow=true;
    group.add(mesh);
    return mesh;
  }
  function segment(THREE,parent,name,a,b,y,width,height,material){
    const dx=b.x-a.x,dz=b.z-a.z,len=Math.hypot(dx,dz);
    const mesh=new THREE.Mesh(new THREE.BoxGeometry(len,height,width),material);
    mesh.position.set((a.x+b.x)/2,y,(a.z+b.z)/2);
    mesh.rotation.y=Math.atan2(-dz,dx);
    mesh.name=name;mesh.receiveShadow=true;
    parent.add(mesh);
    return mesh;
  }
  function outward(a,b,centre){
    const dx=b.x-a.x,dz=b.z-a.z,length=Math.hypot(dx,dz)||1;
    const normal={x:-dz/length,z:dx/length};
    const midpoint={x:(a.x+b.x)/2,z:(a.z+b.z)/2};
    const inward=(centre.x-midpoint.x)*normal.x+(centre.z-midpoint.z)*normal.z;
    return inward>0?{x:-normal.x,z:-normal.z}:normal;
  }
  function move(point,normal,dist){
    return {x:point.x+normal.x*dist,z:point.z+normal.z*dist};
  }
  function addWireFence(THREE,root,waypoints){
    const post=[],wires=[];
    const low=LANE_LEVEL+0.04,high=LANE_LEVEL+2.46;
    const push=(arr,a,b)=>arr.push(a.x,a.y,a.z,b.x,b.y,b.z);
    let posts=0;
    for(let i=0;i<waypoints.length-1;i++){
      const a=waypoints[i],b=waypoints[i+1];
      const dx=b.x-a.x,dz=b.z-a.z,length=Math.hypot(dx,dz);
      if(length<0.3)continue;
      const step=3.1;
      for(let d=0;d<length-0.1;d+=step){
        const d2=Math.min(length,d+step);
        const pt=t=>({x:a.x+dx*t/length,z:a.z+dz*t/length});
        const l=pt(d),r=pt(d2);
        const first=i===0&&d===0;
        if(first){push(post,{...l,y:low},{...l,y:high});posts++;}
        push(post,{...r,y:low},{...r,y:high});posts++;
        push(wires,{...l,y:high-0.17},{...r,y:high-0.17});
        push(wires,{...l,y:low+0.16},{...r,y:low+0.16});
        // Batched diamond mesh. Never use hundreds of individual Meshes.
        const count=Math.max(2,Math.ceil((d2-d)/0.48));
        for(let k=0;k<count;k++){
          const p=pt(d+(d2-d)*k/count);
          const q=pt(d+(d2-d)*(k+1)/count);
          for(let y=low+0.3;y<high-0.42;y+=0.43){
            push(wires,{...p,y},{...q,y:y+0.38});
            push(wires,{...p,y:y+0.38},{...q,y});
          }
        }
      }
    }
    function lines(name,verts,color,opacity){
      const geo=new THREE.BufferGeometry();
      geo.setAttribute('position',new THREE.Float32BufferAttribute(verts,3));
      const mesh=new THREE.LineSegments(geo,
        new THREE.LineBasicMaterial({color,opacity,transparent:opacity<1,depthWrite:false,fog:false}));
      mesh.name=name;root.add(mesh);
    }
    lines('Landing rail boundary chain-link steel posts',post,0x6f7675,1);
    lines('Landing railway fence diagonal wire mesh',wires,0xb0bbb5,0.7);
    return posts;
  }
  function create(THREE,world){
    const lot=world&&world.parkingLot;
    if(!lot||!Array.isArray(lot.polygon)||lot.polygon.length<7||!lot.center)return null;
    const root=new THREE.Group();
    root.name='Station East lower service alley and chain-link rail boundary';
    const pavement=standard(THREE,0x54544e,0.97);
    const verge=standard(THREE,0x75736b,0.96);
    const concrete=standard(THREE,0x908f85,0.92);
    const ballast=standard(THREE,0x484741,0.97);
    const steel=standard(THREE,0xc5bfb2,0.37,0.61);
    const edgeMat=standard(THREE,0x746f63,0.86);
    const exposedEdges=[[lot.polygon[5],lot.polygon[4]],
                        [lot.polygon[4],lot.polygon[3]]];
    const boundary=[];
    for(const [a,b] of exposedEdges){
      const n=outward(a,b,lot.center);
      const insideA=move(a,n,0.24),insideB=move(b,n,0.24);
      const outerA=move(a,n,7.6),outerB=move(b,n,7.6);
      slab(THREE,root,'Lower service alley outside Station East lot',
        [insideA,insideB,outerB,outerA],LANE_LEVEL,pavement);
      segment(THREE,root,'Station East grade-step retaining wall',
        a,b,-0.22,0.48,0.9,concrete);
      segment(THREE,root,'Service road rough gravel shoulder',
        outerA,outerB,LANE_LEVEL-0.028,0.8,0.055,verge);
      // Preserve both outboard corner offsets at the bend. Skipping the
      // second segment's start draws an accidental diagonal fence shortcut.
      boundary.push(outerA,outerB);
    }
    // Beyond the lot, a narrow back-of-building service corridor reads as
    // an alley BEFORE the further rail fence. It is outside the playable lot.
    const landingLane=[
      {x:82,z:43},{x:132,z:48},{x:196,z:27}
    ];
    const landingRailFence=[
      {x:86,z:32},{x:135,z:38},{x:198,z:17}
    ];
    for(let i=0;i<landingLane.length-1;i++){
      slab(THREE,root,'The Landing back service alley',
        [landingLane[i],landingLane[i+1],landingRailFence[i+1],
          landingRailFence[i]],LANE_LEVEL,pavement);
      segment(THREE,root,'Landing service lane edge',
        landingLane[i],landingLane[i+1],LANE_LEVEL+0.02,0.17,0.11,concrete);
    }
    // The boundary is separate from the existing black parking fence.
    const poles=addWireFence(THREE,root,boundary.concat(landingRailFence));
    // Repeating sleepers, ballast, and steel directly beyond the wire,
    // at the same grade as the lowered CPR N-Yard and WCE platform.
    const railRoutes=[
      [{x:52,z:-4},{x:83,z:7},{x:134,z:16},{x:205,z:0}],
      [{x:51,z:-10},{x:84,z:1},{x:136,z:9},{x:205,z:-7}]
    ];
    const sleeperVertices=[];
    for(const route of railRoutes){
      for(let i=0;i<route.length-1;i++){
        const a=route[i],b=route[i+1],dx=b.x-a.x,dz=b.z-a.z;
        const len=Math.hypot(dx,dz),nx=-dz/len,nz=dx/len;
        segment(THREE,root,'Lower railway ballast beside Landing',a,b,
          RAIL_LEVEL+0.07,3.5,0.16,ballast);
        for(const side of [-0.76,0.76]){
          const railA=move(a,{x:nx,z:nz},side);
          const railB=move(b,{x:nx,z:nz},side);
          segment(THREE,root,'Landing-side rail head',railA,railB,
            RAIL_LEVEL+0.25,0.1,0.12,steel);
          segment(THREE,root,'Landing-side rail web',railA,railB,
            RAIL_LEVEL+0.15,0.055,0.065,edgeMat);
        }
        for(let along=0.6;along<len-0.3;along+=0.82){
          const centre={x:a.x+dx*along/len,z:a.z+dz*along/len};
          const q=move(centre,{x:nx,z:nz},-1.35),r=move(centre,{x:nx,z:nz},1.35);
          sleeperVertices.push(q.x,RAIL_LEVEL+0.15,q.z,r.x,RAIL_LEVEL+0.15,r.z);
        }
      }
    }
    const geom=new THREE.BufferGeometry();
    geom.setAttribute('position',new THREE.Float32BufferAttribute(sleeperVertices,3));
    const sleepers=new THREE.LineSegments(geom,
      new THREE.LineBasicMaterial({color:0x9d8774,fog:false}));
    sleepers.name='Landing-side batched railway sleepers';
    root.add(sleepers);
    root.userData={
      lotElevation:LOT_LEVEL,
      laneElevation:LANE_LEVEL,
      railElevation:RAIL_LEVEL,
      wireFencePostCount:poles,
      railwayTrackCount:railRoutes.length,
      railRoutes,
      fencedBoundary:boundary.concat(landingRailFence),
      illustrativeNotSurveyed:true,
      serviceLaneNotPublic:true,
      sourceReferences:REFS
    };
    return root;
  }
  window.GastownRailGrade=Object.freeze({create,outward,reference:REFS});
})(window);

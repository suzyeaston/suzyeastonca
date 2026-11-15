/*
 * Station East / The Landing rail-edge section.
 *
 * Scene coordinates use +X east, +Z south. The lower service lane, chain-link
 * rail boundary and utility yard are non-walkable scenic geometry.
 * They represent the back-of-Landing alley, not the CPR rail alignment.
 * Lane geometry/grades are illustrative (not an access or railway survey).
 *
 * See TransLink's Waterfront/WCE station maps, Heritage Vancouver 555 Cordova,
 * and the City of Vancouver 2022 N-Yard/rail hazards study.
 */
(function(window){
  'use strict';
  const RAIL_LEVEL=-1.35, LANE_LEVEL=0.11, LOT_LEVEL=0.19;
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
  function box(THREE,parent,name,x,y,z,w,h,d,material){
    const mesh=new THREE.Mesh(new THREE.BoxGeometry(w,h,d),material);
    mesh.name=name;mesh.position.set(x,y,z);
    mesh.castShadow=false;mesh.receiveShadow=true;
    parent.add(mesh);return mesh;
  }
  // User-supplied October 2020 / February 2022 Street View coordinates of
  // the service alley along The Landing. Project them using the SAME
  // tangent-plane origin as the civic/buildings scene.
  const SERVICE_REFERENCE=Object.freeze([
    {lat:49.2851413,lon:-123.1107414},
    {lat:49.2851168,lon:-123.1105671},
    {lat:49.2850634,lon:-123.1102798}
  ]);
  function streetViewInScene(origin,point){
    const metresPerDegree=111320;
    return {
      x:(point.lon-origin.lon)*metresPerDegree*Math.cos(origin.lat*Math.PI/180),
      z:(origin.lat-point.lat)*metresPerDegree
    };
  }
  function create(THREE,world){
    const lot=world&&world.parkingLot,landing=world&&world.landing;
    const origin=world&&world.meta&&world.meta.gis&&world.meta.gis.origin;
    if(!lot||!landing||!origin||!Array.isArray(lot.polygon))return null;
    const root=new THREE.Group();
    root.name='The Landing alley and fenced railway utility compound';
    const pavement=standard(THREE,0x535754,0.93);
    const concrete=standard(THREE,0x8b8b82,0.85);
    const gravel=standard(THREE,0x68665e,0.95);
    const brick=standard(THREE,0x874f41,0.88);
    const brickDark=standard(THREE,0x5d352e,0.91);
    const metal=standard(THREE,0x34474d,0.57,0.32);
    const yellow=standard(THREE,0xd3b43c,0.9);
    const mapped=SERVICE_REFERENCE.map(point=>streetViewInScene(origin,point));
    // Road runs beside The Landing's northern wall, approximately
    // (67,77) -> (80,80) -> (101,86) in scene metres.
    // The previous invented (82,43) -> (196,27) lane was on the
    // WRONG side of the lot and put chain link across live tracks.
    const first=mapped[0],last=mapped[mapped.length-1];
    const route=[
      {x:first.x-4.7,z:first.z-1.3},
      ...mapped,
      {x:last.x+18.2,z:last.z+4.5}
    ];
    const north=[],south=[];
    let roadSegments=0;
    for(let i=0;i<route.length-1;i++){
      const a=route[i],b=route[i+1];
      const dx=b.x-a.x,dz=b.z-a.z,length=Math.hypot(dx,dz);
      if(length<0.2)continue;
      // Along an eastbound lane, the left (-Z) edge bounds the rail
      // utility compound and the right (+Z) edge faces The Landing.
      const n={x:-dz/length,z:dx/length};
      const nearA=move(a,n,-2.6),nearB=move(b,n,-2.6);
      const farA=move(a,n,2.6),farB=move(b,n,2.6);
      slab(THREE,root,'The Landing surveyed-view service alley',
        [nearA,nearB,farB,farA],LANE_LEVEL,pavement);
      segment(THREE,root,'The Landing back-of-building service curb',
        farA,farB,LANE_LEVEL+0.045,0.15,0.09,concrete);
      if(!north.length)north.push(nearA);
      north.push(nearB);
      if(!south.length)south.push(farA);
      south.push(farB);
      roadSegments++;
    }
    // Compact fenced utility yard, not a second rail-yard surface. It lies
    // immediately NORTH of the real alley and far SOUTH of the CPR rails.
    const yard=[
      {x:75,z:69},{x:120,z:83},{x:138,z:49},{x:93,z:38}
    ];
    slab(THREE,root,'Fenced rail-serving utility yard gravel',yard,LANE_LEVEL-0.025,gravel);
    // One straight wire fence along the lane, then a short return on the
    // yard's east boundary. Neither crosses a passenger/freight rail.
    const fencePosts=addWireFence(THREE,root,north);
    const returnPosts=addWireFence(THREE,root,[north[north.length-1],yard[2]]);
    // The low red-brick utility building and fenced storage area are visible
    // in the supplied Feb 2022 photography. Scale/details illustrative.
    const shed=new THREE.Group();
    shed.name='Low brick railway utility and service building';
    shed.position.set(111,LANE_LEVEL,57);
    root.add(shed);
    box(THREE,shed,'Service utility shed red brick',0,2.45,0,23,4.9,13.7,brick);
    box(THREE,shed,'Service utility shed flat roof',0,4.97,0,23.4,0.22,14.1,brickDark);
    box(THREE,shed,'Shed service access green door',0,1.72,6.94,4.0,3.25,0.12,metal);
    for(const x of [-7.5,7.5]){
      box(THREE,shed,'Shed mechanical wall louver',x,3.45,6.95,3.2,1.0,0.13,concrete);
    }
    box(THREE,root,'Utility yard storage pallet',88,LANE_LEVEL+0.43,57,3.3,0.8,2.6,concrete);
    for(const x of [84,91]){
      box(THREE,root,'Fenced compound yellow bollard',x,LANE_LEVEL+0.6,72,0.20,1.2,0.2,yellow);
    }
    root.userData={
      lotElevation:LOT_LEVEL,
      laneElevation:LANE_LEVEL,
      railElevation:RAIL_LEVEL,
      wireFencePostCount:fencePosts+returnPosts,
      railwayTrackCount:0,
      railRoutes:[],
      mappedStreetViewPoints:mapped,
      alleyCenterline:route,
      fenceBoundary:north,
      serviceYardFootprint:yard,
      roadSegments,
      duplicateRailGeometryRemoved:true,
      crossesExistingRailway:false,
      illustrativeNotSurveyed:true,
      serviceLaneNotPublic:true,
      sourceReferences:REFS
    };
    return root;
  }
  window.GastownRailGrade=Object.freeze({create,outward,streetViewInScene,reference:REFS});
})(window);

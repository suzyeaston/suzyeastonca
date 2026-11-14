/*
 * Vancouver's actual downtown waterfront skyline around Waterfront Station.
 * Original, low-poly silhouettes informed by named, existing architecture,
 * not licensed photogrammetry or a fictional wall of skyscrapers.
 *
 * Latitude/longitude are published address/parcel point anchors; outlines,
 * heights (unless explicitly cited) and ornament are ARTISTIC APPROXIMATIONS.
 * This is *non-collidable scenery* beyond the small walkable Gastown corridor.
 * Ground north is -Z, east is +X. No extra axis flip.
 */
(function (window) {
  'use strict';
  const R = 6371008.8, DEG = Math.PI / 180;
  const LANDMARKS = Object.freeze([
    Object.freeze({
      id:'granville-square-200', name:'200 Granville Square',
      address:'200 Granville Street', lat:49.285886, lon:-123.112977,
      style:'1973 concrete-and-bronze gridded office slab with roof control room',
      source:'https://localhistory.vpl.ca/link/buildings1036',
      width:25, depth:23, height:114, scenicOnly:true
    }),
    Object.freeze({
      id:'royal-bank-675', name:'Royal Bank Tower / Six Seven Five',
      address:'675 West Hastings Street', lat:49.285301, lon:-123.113428,
      style:'1931 setback Art Deco limestone tower',
      source:'https://675westhastings.com/the-building/',
      width:23, depth:23, height:64, scenicOnly:true
    }),
    Object.freeze({
      id:'sinclair-centre', name:'Sinclair Centre former Post Office',
      address:'757 West Hastings Street', lat:49.285968, lon:-123.113868,
      style:'1910 Beaux-Arts stone post office and 43m clock tower',
      source:'https://www.sinclaircentre.com/?page_id=14',
      width:35, depth:23, height:43, scenicOnly:true
    }),
    Object.freeze({
      id:'hastings-601', name:'601 West Hastings',
      address:'601 West Hastings Street', lat:49.28484963, lon:-123.1128793,
      style:'2021 25-storey glazed office tower with lifted corner podium',
      source:'https://pci-group.com/projects/601-west-hastings/',
      width:18, depth:20, height:107.8, scenicOnly:true
    })
  ]);

  function project(world, point) {
    const o = world && world.meta && world.meta.gis && world.meta.gis.origin;
    if (!o || !Number.isFinite(o.lat) || !Number.isFinite(o.lon)
      || !point || !Number.isFinite(point.lat) || !Number.isFinite(point.lon)) return null;
    return {
      x:(point.lon-o.lon)*R*DEG*Math.cos(o.lat*DEG),
      z:-(point.lat-o.lat)*R*DEG
    };
  }

  function create(THREE,world) {
    if (!THREE || !THREE.Group || !THREE.Mesh || !THREE.MeshStandardMaterial) return null;
    if (!project(world,LANDMARKS[0])) return null;
    const root=new THREE.Group();
    root.name='Verified Vancouver waterfront skyline / off-corridor scenery';
    root.userData.scenicOnly=true;
    root.userData.landmarkCount=LANDMARKS.length;
    root.userData.coordinateSystem='+X east / -Z north';
    const mat=(color,roughness=0.8,metalness=0.05)=>new THREE.MeshStandardMaterial({color,roughness,metalness});
    const granite=mat(0xa7a39a),sandstone=mat(0xc5b9a3);
    const limestone=mat(0xb9b6aa),bronze=mat(0x52575a,0.65,0.32);
    const bronzeGlass=mat(0x364c52,0.32,0.18);
    const shadow=mat(0x334955,0.3,0.12),blueGlass=mat(0x52768a,0.22,0.15);
    const paleSilver=mat(0xaabac1,0.58,0.27),clockFace=mat(0xf1e6c7);
    const dark=mat(0x303638),copper=mat(0x6e7772,0.64,0.3);

    function block(parent,name,x,y,z,w,h,d,material) {
      const obj=new THREE.Mesh(new THREE.BoxGeometry(w,h,d),material);
      obj.name=name;obj.position.set(x,y,z);
      obj.castShadow=false;obj.receiveShadow=false;
      parent.add(obj);return obj;
    }
    function cylinder(parent,name,x,y,z,top,bottom,h,material,segments=12) {
      const obj=new THREE.Mesh(new THREE.CylinderGeometry(top,bottom,h,segments),material);
      obj.name=name;obj.position.set(x,y,z);
      obj.castShadow=false;obj.receiveShadow=false;
      parent.add(obj);return obj;
    }
    function newLandmark(ref) {
      const center=project(world,ref);
      const group=new THREE.Group();
      group.name=ref.name;
      group.position.set(center.x,0,center.z);
      group.userData={
        landmarkId:ref.id,reference:ref.source,anchor:{lat:ref.lat,lon:ref.lon},
        style:ref.style,address:ref.address,illustrativeMassing:true,
        footprintWidth:ref.width,footprintDepth:ref.depth,heightApprox:ref.height,
        scenicOnly:true,collidable:false
      };
      root.add(group);
      return group;
    }
    // Shared low-cost façade grids. Do not turn every window into a draw call.
    function officeGrid(g,tag,w,h,d,stone,glazing,floors) {
      const top=Math.max(7,h-3),y=top/2+2;
      for(const side of [-1,1]){
        for(let i=-3;i<=3;i++){
          block(g,tag+' front vertical pier',i*w/8,y,side*(d/2+0.07),
            0.20,top,0.13,stone);
        }
        for(let i=-3;i<=3;i++){
          block(g,tag+' side vertical pier',side*(w/2+0.07),y,
            i*d/8,0.13,top,0.20,stone);
        }
        for(let row=2;row<floors;row+=3) {
          const bandY=2+row*(top/floors);
          block(g,tag+' front floor ribbon',0,bandY,side*(d/2+0.11),
            w+0.15,0.13,0.10,stone);
          block(g,tag+' side floor ribbon',side*(w/2+0.11),bandY,0,
            0.10,0.13,d+0.15,stone);
        }
        block(g,tag+' glazed front elevation',0,y,side*(d/2+0.015),
          w-1.3,top-1.2,0.045,glazing);
        block(g,tag+' glazed side elevation',side*(w/2+0.015),y,0,
          0.045,top-1.2,d-1.3,glazing);
      }
    }

    // Vancouver's 1973 30-storey Granville Square: heavy rectilinear grid
    // and the harbour traffic control/penthouse at the roof, not a glass spike.
    {
      const g=newLandmark(LANDMARKS[0]),w=25,d=23,h=112;
      block(g,'200 Granville Square dark bronze office shaft',0,h/2+7,0,w,h,d,bronze);
      block(g,'200 Granville Square broad plaza podium',0,6,0,29,12,27,granite);
      officeGrid(g,'Granville Square',w,112,d,granite,bronzeGlass,30);
      block(g,'Granville Square mechanical rooftop',0,122,0,17,6.4,14,bronze);
      block(g,'Granville Square harbour air-control room',0,126.6,0,9,3.2,10,paleSilver);
      block(g,'Granville Square roof line',0,128.3,0,11,0.5,12,granite);
    }

    // The former Royal Bank's receding stone tiers, rather than a monolithic
    // blue skyscraper. 16-storey scheme above an articulated classical base.
    {
      const g=newLandmark(LANDMARKS[1]);
      block(g,'Royal Bank limestone lower commercial block',0,13,0,25,26,23,sandstone);
      block(g,'Royal Bank Art Deco middle setback',0,36,0,21,21,19,limestone);
      block(g,'Royal Bank tower upper setback',0,54,0,16,15,16,limestone);
      block(g,'Royal Bank flat stepped crown',0,62,0,17.5,1,17.5,bronze);
      for(const side of [-1,1]){
        for(let i=-3;i<=3;i++){
          block(g,'Royal Bank narrow vertical window bay',i*2.7,33,
            side*11.6,1.55,53,0.08,shadow);
        }
        for(let y=12;y<=61;y+=9){
          block(g,'Royal Bank limestone belt course',0,y,side*11.75,25,0.25,0.16,sandstone);
        }
      }
      block(g,'Royal Bank heavy stone entry canopy',0,4.5,11.9,11,0.6,1.8,granite);
    }

    // Sinclair Centre's historic corner post office is four interconnected
    // low-rise heritage structures, with the characteristic 43m clock tower.
    {
      const g=newLandmark(LANDMARKS[2]);
      block(g,'Sinclair Centre Beaux-Arts stone post office block',0,11,0,35,22,23,limestone);
      block(g,'Sinclair Centre projecting stone cornice',0,22.2,0,37,0.6,24.5,sandstone);
      block(g,'Sinclair Centre recessed mansard roof',0,25.6,0,33,6,21,copper);
      block(g,'Sinclair Centre interior glass atrium roof',0,29,0,15,1.0,11,blueGlass);
      for(const side of [-1,1])for(let i=-6;i<=6;i++){
        block(g,'Sinclair Centre tall heritage window',i*2.45,13,side*11.55,1.35,9,0.06,shadow);
      }
      const tx=12.5,tz=7.4;
      block(g,'Sinclair Centre post office clock tower shaft',tx,29,tz,8,23,8,sandstone);
      block(g,'Sinclair Centre belfry cornice',tx,40.4,tz,9.3,0.7,9.3,granite);
      cylinder(g,'Sinclair Centre historic roof dome',tx,42,tz,3.7,4.7,3.4,copper,12);
      for(const side of [-1,1]){
        const front=cylinder(g,'Sinclair Centre clock face / Cordova',tx,36,tz+side*4.2,2.4,2.4,0.13,clockFace,24);
        front.rotation.x=Math.PI/2;
        const flank=cylinder(g,'Sinclair Centre clock face / Granville',tx+side*4.2,36,tz,2.4,2.4,0.13,clockFace,24);
        flank.rotation.z=Math.PI/2;
      }
    }

    // The 2021 25-storey 601 West Hastings / Waterfront Hub tower rises
    // behind the older streetwall; its corner was designed with a folded,
    // lifted podium rather than a featureless giant vertical slab.
    {
      const g=newLandmark(LANDMARKS[3]);
      block(g,'601 West Hastings lifted glazed podium',0,6.2,0,20,12.4,21,paleSilver);
      block(g,'601 West Hastings sculpted glass office shaft',0,60,0,18,95,20,blueGlass);
      officeGrid(g,'601 West Hastings',18,99,20,paleSilver,shadow,25);
      block(g,'601 West Hastings rooftop setback',0,109,0,15,2.8,17,paleSilver);
      block(g,'601 West Hastings angled plaza overhang',2,13.4,9.3,17,0.55,3.1,paleSilver);
    }
    return root;
  }
  window.GastownWaterfrontSkyline=Object.freeze({LANDMARKS,project,create});
})(window);

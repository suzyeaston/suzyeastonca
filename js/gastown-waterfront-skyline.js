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
    }),
    Object.freeze({
      id:'woodwards-w43', name:"Woodward's W43 / restored W sign",
      address:'128 West Cordova Street', lat:49.28297, lon:-123.10808,
      style:'2009 red-framed glass W43 tower, historic masonry base and elevated W',
      source:'https://www.seabc.ca/wordpress/files/notable_structures/Vancouver_Notable_Structures_IABSE_SEABC_2017.pdf',
      width:24, depth:22, height:122.3, scenicOnly:true
    }),
    Object.freeze({
      id:'dominion-207', name:'Dominion Building / 207 West Hastings',
      address:'207 West Hastings Street', lat:49.282902, lon:-123.109644,
      style:'1909-10 red-and-cream heritage building with green mansard crown',
      source:'https://localhistory.vpl.ca/link/buildings1114',
      width:22, depth:20, height:45, scenicOnly:true
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

    // Woodward's is southeast of the playable Water Street corridor. The
    // actual 128 W Cordova location, not the parking lot, sets the skyline.
    {
      const g=newLandmark(LANDMARKS[4]);
      const rust=mat(0x873c36),red=mat(0xc85a45),darkGlass=mat(0x344b58,0.27,0.17);
      const frame=mat(0x737c7b,0.61,0.24),heritage=mat(0x945346),wRed=mat(0xf04b32,0.58,0.08);
      const balcony=mat(0xc5c6be,0.74,0.11),warmGlass=mat(0x486775,0.35,0.16);
      // W43's real footprint is a triangular flatiron between converging
      // old/new city grids. The previous 23x21m BLOCK created the notorious
      // featureless blade in the screenshot, even after facade decorations.
      // This thin, tapered six-vertex outline has a gently rounded Cordova
      // nose, and is extruded vertically. In simple test environments without
      // ExtrudeGeometry, retain a box fallback but flag the geometry mode.
      let flatironOutline=null;
      if(THREE.Shape&&THREE.ExtrudeGeometry){
        flatironOutline=new THREE.Shape();
        flatironOutline.moveTo(-10.5,4.5);
        flatironOutline.quadraticCurveTo(-13.1,6.4,-12.6,8.0);
        flatironOutline.quadraticCurveTo(-12.1,11.0,-8.6,11.5);
        flatironOutline.lineTo(10.4,11.2);
        flatironOutline.lineTo(12.1,-9.9);
        flatironOutline.quadraticCurveTo(11.6,-12.3,7.2,-12.2);
        flatironOutline.closePath();
        const shape=new THREE.ExtrudeGeometry(flatironOutline,{
          depth:122.3,steps:1,bevelEnabled:false,curveSegments:6
        });
        // Shape XY -> world XZ; extrusion local +Z -> world +Y.
        shape.rotateX(-Math.PI/2);
        const shaft=new THREE.Mesh(shape,darkGlass);
        shaft.name="Woodward's W43 glazed residential shaft";
        shaft.castShadow=false;shaft.receiveShadow=false;
        g.add(shaft);
      } else {
        block(g,"Woodward's W43 glazed residential shaft",0,61.15,0,23,122.3,21,darkGlass);
      }
      g.userData.woodwardsPlan='triangular flatiron with rounded Cordova corner';
      g.userData.woodwardsUsesExtrudedMassing=!!flatironOutline;
      const glassSkin=mat(0x567482,0.30,0.21);
      function facadeStrip(name,x1,z1,x2,z2,y,h,thickness,material){
        const dx=x2-x1,dz=z2-z1;
        const mesh=block(g,name,(x1+x2)/2,y,(z1+z2)/2,
          Math.hypot(dx,dz),h,thickness,material);
        mesh.rotation.y=-Math.atan2(dz,dx);
        return mesh;
      }
      // Glass is on three visible elevations rather than only the "front"
      // of an opaque rectangle. Orange metal fins evoke the real screen.
      facadeStrip("Woodward's W43 north glazed curtainwall",-8.8,-11.59,
        10.2,-11.32,63,112,0.17,glassSkin);
      facadeStrip("Woodward's W43 diagonal south glazed curtainwall",-10.25,-4.55,
        7.10,12.25,63,112,0.17,glassSkin);
      facadeStrip("Woodward's W43 east return glazing",12.20,-9.3,
        12.20,9.4,63,112,0.17,glassSkin);
      for(const [x,z] of [[-12.2,-8.0],[-8.7,-11.55],[10.4,-11.20],
        [12.16,-9.9],[12.16,9.9],[7.2,12.25]]){
        block(g,"Woodward's rust red vertical outside corner frame",x,61,z,
          0.31,120,0.31,rust);
      }
      for(let i=0;i<8;i++){
        const t=(i+0.5)/8;
        block(g,"Woodward's orange north steel screen mullion",
          -8.6+19.0*t,62,-11.67,0.22,116,0.22,red);
        block(g,"Woodward's orange diagonal steel screen mullion",
          -10.25+(17.35*t),62,-4.55+(16.8*t),
          0.22,116,0.22,red);
      }
      // Nine beveled-looking glass bays at the rounded corner instead of
      // a rectangular slab with stripes stuck to its face.
      for(let bay=-4;bay<=4;bay++){
        const x=-10.55+bay*0.52;
        const z=-7.8-1.55*Math.sqrt(Math.max(0,1-(bay/5)**2));
        const panel=block(g,"Woodward's bowed north facade glass bay",
          x,63,z,0.43,111.5,0.14,warmGlass);
        panel.rotation.y=-bay*0.13;
        block(g,"Woodward's curved brick mullion",
          x+0.24,62.9,z-0.06,0.12,112,0.20,rust);
      }
      // Floor-by-floor crescent balconies wrapped around the rounded nose.
      // Instancing is essential: 35 balconies cost ONE draw call on a small
      // MacBook, instead of 35 expensive standalone meshes.
      const balconySlab=new THREE.CylinderGeometry(2.25,2.25,0.18,12,1,
        false,-Math.PI*0.12,Math.PI*1.20);
      if(THREE.InstancedMesh&&THREE.Object3D){
        const floors=35;
        const stack=new THREE.InstancedMesh(balconySlab,balcony,floors);
        stack.name="Woodward's rounded corner balcony stack";
        stack.castShadow=false;stack.receiveShadow=false;
        const marker=new THREE.Object3D();
        for(let i=0;i<floors;i++){
          marker.position.set(-11.0,8.4+i*3.17,-7.55);
          marker.rotation.y=0.28;
          marker.updateMatrix();
          stack.setMatrixAt(i,marker.matrix);
        }
        stack.instanceMatrix.needsUpdate=true;
        g.add(stack);
      } else {
        for(let y=8.4;y<120;y+=12){
          const slab=new THREE.Mesh(balconySlab,balcony);
          slab.name="Woodward's rounded corner balcony stack fallback";
          slab.position.set(-11.0,y,-7.55);
          slab.rotation.y=0.28;slab.castShadow=false;slab.receiveShadow=false;
          g.add(slab);
        }
      }
      // Thin white balconies emphasize the real W43's rhythm; avoid thick
      // 11-metre-spaced full-red facades, which made it read as a red slab.
      for(let y=9;y<120;y+=10.3){
        facadeStrip("Woodward's curved pale balcony fascia",-8.5,-11.82,
          10.5,-11.52,y,0.28,0.38,balcony);
        facadeStrip("Woodward's east elevation pale balcony return",12.35,-9.4,
          12.35,9.1,y,0.28,0.37,balcony);
      }
      // The main horizontal balcony ribbons have 3.17m storey spacing.
      // Do not allocate one mesh per storey when Three can instance them.
      if(THREE.InstancedMesh&&THREE.Object3D){
        for(const edge of [
          {name:"Woodward's north horizontal balcony lattice",
            a:[-8.6,-11.76],b:[10.35,-11.49]},
          {name:"Woodward's angled horizontal balcony lattice",
            a:[-10.3,-4.75],b:[7.25,12.42]}
        ]){
          const dx=edge.b[0]-edge.a[0],dz=edge.b[1]-edge.a[1];
          const rail=new THREE.InstancedMesh(
            new THREE.BoxGeometry(Math.hypot(dx,dz),0.15,0.23),red,35);
          rail.name=edge.name;
          rail.castShadow=false;rail.receiveShadow=false;
          const dummy=new THREE.Object3D();
          for(let floor=0;floor<35;floor++){
            dummy.position.set((edge.a[0]+edge.b[0])/2,8.5+floor*3.17,
              (edge.a[1]+edge.b[1])/2);
            dummy.rotation.y=-Math.atan2(dz,dx);
            dummy.updateMatrix();rail.setMatrixAt(floor,dummy.matrix);
          }
          rail.instanceMatrix.needsUpdate=true;g.add(rail);
        }
      }
      if(flatironOutline){
        const roofGeometry=new THREE.ExtrudeGeometry(flatironOutline,{
          depth:0.60,steps:1,bevelEnabled:false,curveSegments:6
        });
        roofGeometry.rotateX(-Math.PI/2);
        const roof=new THREE.Mesh(roofGeometry,heritage);
        roof.name="Woodward's planted tower roof edge";
        roof.position.y=122.3;
        roof.castShadow=false;roof.receiveShadow=false;g.add(roof);
      } else {
        block(g,"Woodward's planted tower roof edge",0,122.4,0,25.2,0.55,23.5,heritage);
      }
      // Rebuilt brick frontage and supported W are a separate low-rise
      // element, not an out-of-scale sign painted across the glass shaft.
      block(g,"Woodward's heritage brick base",-24,11.6,0,28,23.2,24,heritage);
      block(g,"Woodward's heritage stone cornice",-24,23.4,0,29,0.65,25,sandstone);
      for(let floor=0;floor<4;floor++){
        block(g,"Woodward's heritage upper-storey window band",-24,6+floor*4.0,-12.1,24,2.0,0.12,shadow);
      }
      // Articulated restored 1903/08 storefront rather than a generic slab.
      for(const x of [-34,-27,-20,-14]){
        block(g,"Woodward's restored heritage brick pier",x,12.3,-12.17,
          0.50,20.2,0.34,heritage);
        block(g,"Woodward's lower retail entrance glazing",x+2.2,2.5,-12.20,
          3.7,4.2,0.13,warmGlass);
      }
      block(g,"Woodward's heritage retail canopy",-24,4.96,-13.3,
        28.2,0.42,2.3,balcony);
      block(g,"Woodward's heritage podium roof pavilion",-24,25.7,0,
        22,3.2,19,heritage);
      const signX=-25,signZ=-13;
      for(const x of [-5,5])block(g,"Woodward's W steel lattice upright",
        signX+x,34.4,signZ,0.30,21.0,0.35,frame);
      function stroke(name,x1,y1,x2,y2,thickness,material){
        const dx=x2-x1,dy=y2-y1,len=Math.hypot(dx,dy);
        const node=block(g,name,signX+(x1+x2)/2,(y1+y2)/2,signZ,
          len,thickness,0.55,material);
        node.rotation.z=Math.atan2(dy,dx);
      }
      for(let y=26;y<45;y+=6){
        stroke("Woodward's W crisscross steel truss",-5,y,5,y+6,0.22,frame);
        stroke("Woodward's W crisscross steel truss",5,y,-5,y+6,0.22,frame);
      }
      const wp=[[-5,51],[-2.5,45],[0,49],[2.5,45],[5,51]];
      for(let i=0;i<wp.length-1;i++)stroke("Woodward's replica red W sign",
        wp[i][0],wp[i][1],wp[i+1][0],wp[i+1][1],1.1,wRed);
      // The red W must read in silhouette from BOTH directions, including
      // the Steam Clock/Water Street approach. Offset a second face slightly.
      const frontSign=4;
      for(let i=0;i<frontSign;i++){
        const before=wp[i],after=wp[i+1];
        const dx=after[0]-before[0],dy=after[1]-before[1];
        const length=Math.hypot(dx,dy);
        const back=block(g,"Woodward's W backlit reverse face",
          signX+(before[0]+after[0])/2,(before[1]+after[1])/2,
          signZ+0.70,length,1.1,0.12,wRed);
        back.rotation.z=Math.atan2(dy,dx);
      }
      g.userData.landmarkDetails='W43 curved residential glass tower, pale balcony ribbons, heritage podium and two-sided rooftop W';
      g.userData.woodwardsCurvedFacade=true;
      g.userData.woodwardsWVisibleBothSides=true;
    }
    // Dominion Trust Building, 207 West Hastings: 1909-10 brick and
    // cream-stone vertical rhythm, and a dark green mansard crown.
    {
      const g=newLandmark(LANDMARKS[5]);
      const terracotta=mat(0x8e4b40),slate=mat(0x40544d),heritageWindow=mat(0x34434b,0.35,0.08);
      block(g,'Dominion Building red brick heritage tower',0,19,0,22,38,20,terracotta);
      for(const z of [-10.15,10.15]){
        for(let y=6;y<=35;y+=5.1)
          block(g,'Dominion Building tall sash band',0,y,z,18,2.7,0.12,heritageWindow);
        for(const x of [-8,-3,3,8])
          block(g,'Dominion Building stone window pilaster',x,21,z,0.48,33,0.2,sandstone);
      }
      block(g,'Dominion Building pale stone cornice',0,38.2,0,24,0.8,22,sandstone);
      block(g,'Dominion Building green mansard roof',0,42.1,0,20,7,18,slate);
      block(g,'Dominion Building copper crest',0,45.7,0,13,0.7,12,copper);
    }
    return root;
  }
  window.GastownWaterfrontSkyline=Object.freeze({LANDMARKS,project,create});
})(window);

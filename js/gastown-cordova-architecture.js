/*
 * Gastown / W Cordova architectural study, 2026.
 *
 * Three problematic, highly concave near-station GIS extrusion blocks are
 * replaced with lightweight, orthogonal, footprint-bounded silhouettes.
 * References: user's 2024 Street View / 3D map captures of Waterfront
 * Station, Seymour and 333 W Cordova. This is an illustrative stage, NOT
 * an architectural survey or a Google imagery-derived texture asset.
 */
(function(root){
  'use strict';
  const REPLACED_IDS=Object.freeze(['feature-92','feature-15','feature-16']);
  function replacementIds(){return REPLACED_IDS.slice();}
  function boundedBuilding(source){
    const pts=Array.isArray(source&&source.footprint_local)?source.footprint_local:[];
    if(pts.length<3)return null;
    const xs=pts.map(p=>p.x),zs=pts.map(p=>p.z);
    if(xs.some(x=>!Number.isFinite(x))||zs.some(z=>!Number.isFinite(z)))return null;
    return {
      x:source.x,z:source.z,yaw:source.yaw||0,
      minX:Math.min(...xs),maxX:Math.max(...xs),
      minZ:Math.min(...zs),maxZ:Math.max(...zs)
    };
  }
  function create(THREE,world){
    if(!THREE||!THREE.Group||!world||!Array.isArray(world.buildings))return null;
    const rootGroup=new THREE.Group();
    rootGroup.name='W Cordova reference architecture and clean skyline';
    const cyan=new THREE.MeshBasicMaterial({color:0x718b99,side:THREE.DoubleSide});
    const blue=new THREE.MeshBasicMaterial({color:0x334b5e,side:THREE.DoubleSide});
    const bright=new THREE.MeshBasicMaterial({color:0x8aa7b0,side:THREE.DoubleSide});
    const mullion=new THREE.MeshStandardMaterial({color:0xb5c3c8,roughness:0.5,metalness:0.4});
    const limestone=new THREE.MeshStandardMaterial({color:0xaeb1b1,roughness:0.89,metalness:0.04,emissive:0x27292b,emissiveIntensity:0.22});
    const paleStone=new THREE.MeshStandardMaterial({color:0xd1c8b8,roughness:0.85,emissive:0x35312b,emissiveIntensity:0.11});
    const glassBase=new THREE.MeshStandardMaterial({color:0x365366,roughness:0.2,metalness:0.28,emissive:0x264355,emissiveIntensity:0.15});
    const charcoal=new THREE.MeshStandardMaterial({color:0x3b444d,roughness:0.77,metalness:0.05});

    function addBox(group,name,cx,cy,cz,width,height,depth,mat){
      const mesh=new THREE.Mesh(new THREE.BoxGeometry(width,height,depth),mat);
      mesh.name=name;
      mesh.position.set(cx,cy,cz);
      // These blockouts cannot cast mysterious black triangles onto Cordova.
      mesh.castShadow=false;
      mesh.receiveShadow=false;
      group.add(mesh);
      return mesh;
    }
    function shell(group,tag,cx,cz,w,d,h,baseMat,windowMat,storeys){
      addBox(group,tag+' clean orthogonal body',cx,h/2,cz,w,h,d,baseMat);
      const floors=Math.max(2,Math.min(20,storeys||Math.floor(h/3.8)));
      const floorHeight=h/floors;
      // Earlier full-width cyan ribbons looked like missing building geometry.
      // Discrete dark sash bays and pale floor bands read as actual facades
      // from a moving camera, without introducing 3D GIS tile dependencies.
      for(let i=1;i<floors;i++){
        const y=i*floorHeight+floorHeight*0.48;
        for(const side of [-1,1]){
          const faceZ=cz+side*(d/2+0.056);
          for(let bay=-2;bay<=2;bay++){
            addBox(group,tag+' individual front sash',cx+bay*(w*0.176),y,
              faceZ,w*0.138,Math.max(0.62,floorHeight*0.49),0.08,windowMat);
          }
          addBox(group,tag+' continuous floor string course',cx,y-floorHeight*0.38,
            faceZ,w*0.97,0.11,0.09,mullion);
          const sideX=cx+side*(w/2+0.056);
          for(let bay=-1.5;bay<=1.5;bay++){
            addBox(group,tag+' individual side sash',sideX,y,
              cz+bay*(d*0.19),0.08,Math.max(0.62,floorHeight*0.49),d*0.145,windowMat);
          }
        }
      }
      addBox(group,tag+' roof coping',cx,h+0.18,cz,w+0.65,0.36,d+0.65,paleStone);
      addBox(group,tag+' recessed ground-floor glazing',cx,1.65,cz+d/2+0.14,w*0.72,2.65,0.1,glassBase);
      addBox(group,tag+' ground-floor canopy',cx,3.3,cz+d/2+0.92,w*0.52,0.26,1.60,charcoal);
    }
    function blockFor(id,title){
      const source=world.buildings.find(b=>b&&b.id===id);
      const bounds=source&&boundedBuilding(source);
      if(!bounds)return null;
      const group=new THREE.Group();
      group.name=title;
      group.position.set(bounds.x,0,bounds.z);
      group.rotation.y=-bounds.yaw;
      group.userData.sourceFootprintId=id;
      group.userData.referenceApproximation=true;
      group.userData.boundingSize={width:bounds.maxX-bounds.minX,depth:bounds.maxZ-bounds.minZ};
      rootGroup.add(group);
      return{group,b:bounds};
    }
    // 333 Seymour / Grant Thornton Place. This civic polygon (feature-92)
    // corresponds to 49.28533, -123.11246 on the southeast corner of
    // Seymour and Cordova. The former observation tower was wrongly here.
    {
      const item=blockFor('feature-92','333 Seymour / waterfront office frontage');
      if(item){
        const {group,b}=item;
        const cx=(b.minX+b.maxX)*0.5,cz=(b.minZ+b.maxZ)*0.5;
        shell(group,'333 Seymour stone and glass office podium',cx,cz,25,25,13,
          limestone,blue,3);
        shell(group,'333 Seymour 17 storey gridded office tower',cx,cz,
          22,22,56,glassBase,cyan,17);
        addBox(group,'333 Seymour stepped roof',cx,56.7,cz,20,1.2,20,paleStone);
        group.userData.landmark='333 Seymour';
        group.userData.architectureSource='https://pci-group.com/projects/333-seymour/';
      }
    }
    // 320 Granville (feature-15) is the curving 30-storey KPF design.
    // Its 2023 built height is about 117m. Previously this was a 47m
    // rectangular "Seymour" block: accurate coordinate, wrong architecture.
    {
      const item=blockFor('feature-15','320 Granville / curved waterfront glass tower');
      if(item){
        const {group,b}=item;
        const cx=(b.minX+b.maxX)*0.5,cz=(b.minZ+b.maxZ)*0.5;
        shell(group,'320 Granville glazed public lobby',cx,cz,31,29,12,
          limestone,blue,3);
        addBox(group,'320 Granville 30 storey tower glass core',cx,62.5,cz,
          22,105,22,glassBase);
        for(let level=0;level<9;level++){
          // The set-back facade shifts gently at each belt, implying the
          // real undulating stacked ribbon design without using web textures.
          const y=18+level*11.3,step=Math.sin(level*0.75)*0.72;
          addBox(group,'320 Granville offset silver floor ribbon',cx+step,y,cz,
            23.5,0.32,23.5,mullion);
        }
        for(const side of [-1,1])for(let bay=-3;bay<=3;bay++){
          const offset=bay*3.1;
          addBox(group,'320 Granville curved front glass fin',cx+offset,66,
            cz+side*11.3,0.30,97,0.31,(bay%2===0)?bright:cyan);
          addBox(group,'320 Granville curved side glass fin',
            cx+side*11.3,66,cz+offset,0.31,97,0.30,(bay%2===0)?bright:cyan);
        }
        addBox(group,'320 Granville rooftop step',cx,116,cz,17,1.8,18,paleStone);
        group.userData.landmark='320 Granville';
        group.userData.architectureSource='https://www.kpf.com/project/320-granville';
        group.userData.heightApproximateMeters=117.3;
      }
    }
    // Harbour Centre is actually at 555 W HASTINGS, southeast of the station:
    // 49.284697, -123.111935 (SEABC engineering guide and PeeringDB).
    // The correct civic parcel is feature-16, not feature-92. Convert the
    // published position into that polygon's local coordinates once, and
    // leave the station and public surface lot untouched.
    {
      const item=blockFor('feature-16','Harbour Centre / Spencer Building / Vancouver Lookout');
      if(item){
        const {group,b}=item;
        const origin=world.meta&&world.meta.gis&&world.meta.gis.origin;
        const rad=Math.PI/180,R=6371008.8;
        const wx=origin&&(Number.isFinite(origin.lat)&&Number.isFinite(origin.lon))
          ?(-123.111935-origin.lon)*R*rad*Math.cos(origin.lat*rad):b.x;
        const wz=origin&&Number.isFinite(origin.lat)
          ?-(49.284697-origin.lat)*R*rad:b.z;
        const dx=wx-b.x,dz=wz-b.z,c=Math.cos(b.yaw),s=Math.sin(b.yaw);
        const cx=c*dx+s*dz,cz=-s*dx+c*dz;
        shell(group,'Harbour Centre heritage Spencer podium',cx,cz,32,30,20,
          paleStone,blue,5);
        const towerBase=20,towerTop=123,levels=28,w=22,d=22;
        addBox(group,'Harbour Centre concrete gridded 28 storey shaft',
          cx,(towerBase+towerTop)*0.5,cz,w,towerTop-towerBase,d,limestone);
        for(let floor=0;floor<levels;floor++){
          const y=towerBase+(floor+0.5)*(towerTop-towerBase)/levels;
          for(const side of [-1,1]){
            addBox(group,'Harbour Centre front window ribbon',cx,y,
              cz+side*(d/2+0.06),w-1.4,1.6,0.07,blue);
            addBox(group,'Harbour Centre side window ribbon',
              cx+side*(w/2+0.06),y,cz,0.07,1.6,d-1.4,blue);
          }
        }
        for(let bay=-3;bay<=3;bay++)for(const side of [-1,1]){
          const offset=bay*2.72;
          addBox(group,'Harbour Centre front concrete mullion',cx+offset,
            (towerBase+towerTop)/2,cz+side*(d/2+0.1),0.24,towerTop-towerBase,0.1,limestone);
          addBox(group,'Harbour Centre side concrete mullion',
            cx+side*(w/2+0.1),(towerBase+towerTop)/2,cz+offset,
            0.1,towerTop-towerBase,0.24,limestone);
        }
        const lookoutMetal=new THREE.MeshStandardMaterial({
          color:0xb2bdc5,metalness:0.55,roughness:0.42});
        const lookoutGlass=new THREE.MeshStandardMaterial({
          color:0x568094,metalness:0.28,roughness:0.18});
        function disc(name,radius,height,y,material){
          const mesh=new THREE.Mesh(new THREE.CylinderGeometry(radius,radius,height,32),material);
          mesh.name=name;mesh.position.set(cx,y,cz);
          mesh.castShadow=false;mesh.receiveShadow=false;group.add(mesh);
          return mesh;
        }
        disc('Harbour Centre lookout pedestal',5.8,5,125.5,limestone);
        disc('Vancouver Lookout panoramic glass ring',13.1,4.6,130.3,lookoutGlass);
        disc('Vancouver Lookout lower silver rim',13.7,0.72,127.8,lookoutMetal);
        disc('Vancouver Lookout overhanging circular roof',14.7,1.05,132.7,lookoutMetal);
        disc('Vancouver Lookout roof cap',10.6,0.65,133.6,paleStone);
        addBox(group,'Harbour Centre slim antenna mast',cx,142.3,cz,
          0.22,16.6,0.22,mullion);
        // 542 W Cordova / Harbour Centre: in the Station East parking QA
        // screenshots, the 2026-11-15 frontage showed TWO BLANK GREY WALLS.
        // The old positive local-Z glazing was on the building's back face:
        // both civic edges actually face the parking lot on NEGATIVE local Z.
        // Compute the public-facing sign against the lot centre instead of
        // relying on a fragile edge-winding assumption.
        const civic=world.buildings.find(building=>building.id==='feature-16');
        const coords=civic.footprint;
        const parking=world.parkingLot&&world.parkingLot.center;
        const toLocal=p=>{
          const dx=p.x-b.x,dz=p.z-b.z;
          return {x:dx*Math.cos(b.yaw)+dz*Math.sin(b.yaw),
            z:-dx*Math.sin(b.yaw)+dz*Math.cos(b.yaw)};
        };
        const masonry=new THREE.MeshStandardMaterial({color:0xbab6ab,roughness:0.9});
        const trim=new THREE.MeshStandardMaterial({color:0xd1c8b7,roughness:0.87});
        const darkGlazing=new THREE.MeshStandardMaterial({
          color:0x3d606c,roughness:0.22,metalness:0.18});
        const doorFrame=new THREE.MeshStandardMaterial({color:0x343e43,roughness:0.55,metalness:0.2});
        const yellow=new THREE.MeshBasicMaterial({color:0xe9d44f});
        const shopGreen=new THREE.MeshBasicMaterial({color:0x23613b});

        function shopSign(parent,name,text,x,y,face,width,height,bg,fg) {
          const z=face*0.52;
          const backing=addBox(parent,name+' fascia panel',x,y,z,width,height,0.12,
            bg==='#e9d44f'?yellow:shopGreen);
          backing.userData={signText:text,facesParkingLot:true};
          // Procedural lettering keeps this a standalone, locally rendered
          // original asset. Node's Three.js test stub has no document/canvas.
          if(typeof document!=='undefined'&&THREE.CanvasTexture&&THREE.PlaneGeometry){
            const canvas=document.createElement('canvas');
            canvas.width=1024;canvas.height=128;
            const ctx=canvas.getContext('2d');
            if(ctx){
              ctx.fillStyle=bg;ctx.fillRect(0,0,1024,128);
              let fontSize=90;
              ctx.font='bold '+fontSize+'px Arial, sans-serif';
              while(ctx.measureText(text).width>935 && fontSize>34){
                fontSize-=4;ctx.font='bold '+fontSize+'px Arial, sans-serif';
              }
              ctx.fillStyle=fg;ctx.textAlign='center';ctx.textBaseline='middle';
              ctx.fillText(text,512,65);
              const tex=new THREE.CanvasTexture(canvas);
              if(THREE.SRGBColorSpace)tex.colorSpace=THREE.SRGBColorSpace;
              const lettering=new THREE.Mesh(new THREE.PlaneGeometry(width-0.08,height-0.05),
                new THREE.MeshBasicMaterial({map:tex,side:THREE.DoubleSide}));
              lettering.name=name+' legible canvas lettering';
              lettering.position.set(x,y,face*0.6);
              lettering.rotation.y=face<0?Math.PI:0;
              lettering.castShadow=false;lettering.receiveShadow=false;
              parent.add(lettering);
            }
          }
        }
        function lotFacingEdge(start,end,label) {
          const a=coords[start],z=coords[end],p=toLocal(a),q=toLocal(z);
          const dx=q.x-p.x,dz=q.z-p.z,len=Math.hypot(dx,dz);
          if(len<5)return null;
          const frontage=new THREE.Group();
          frontage.name='Harbour Centre Cordova civic parcel frontage '+start+'-'+end;
          frontage.position.set((p.x+q.x)/2,0,(p.z+q.z)/2);
          frontage.rotation.y=Math.atan2(-dz,dx);
          const sceneYaw=-b.yaw+frontage.rotation.y;
          const mx=(a.x+z.x)/2,mz=(a.z+z.z)/2;
          const toLot=(parking.x-mx)*Math.sin(sceneYaw)
            +(parking.z-mz)*Math.cos(sceneYaw);
          const face=toLot>=0?1:-1;
          frontage.userData.civicEdge=[start,end];
          frontage.userData.parkingFace=face;
          frontage.userData.isScenicFacade=true;
          frontage.userData.streetLevelRetail=label;
          group.add(frontage);

          // Four comparatively low storeys, not a 17-metre opaque slab.
          // Keep geometry within 0.55m of the existing mapped parcel edge.
          addBox(frontage,'Harbour Centre articulated stone podium body',
            0,6.7,0,len,13.4,0.34,masonry);
          const bayCount=Math.max(3,Math.round(len/5.4)),bayW=len/bayCount;
          for(const direction of [-1,1]){
            // Low-cost rhythm on both sides: flights around the scene will
            // never expose the same blank back of the old single-face mesh.
            for(const y of [5.85,8.95,11.85]){
              for(let i=0;i<bayCount;i++){
                const x=(i+0.5)*bayW-len/2;
                addBox(frontage,'Harbour Centre individual upper sash window',
                  x,y,direction*0.225,bayW-0.78,1.68,0.09,darkGlazing);
              }
            }
            for(let i=1;i<bayCount;i++){
              const x=i*bayW-len/2;
              addBox(frontage,'Harbour Centre concrete window pier',x,8.9,
                direction*0.29,0.36,8.9,0.15,trim);
            }
          }
          // Public ground-floor shop bays, glazed below a shallow fascia.
          for(let i=0;i<bayCount;i++){
            const x=(i+0.5)*bayW-len/2;
            addBox(frontage,'Harbour Centre Cordova retail display glass',
              x,1.72,face*0.26,bayW-0.56,2.95,0.12,glassBase);
            addBox(frontage,'Harbour Centre Cordova shop mullion',
              x,1.7,face*0.35,0.11,3.1,0.1,doorFrame);
          }
          addBox(frontage,'Harbour Centre narrow ground-floor lintel',
            0,3.58,face*0.32,len,0.32,0.28,trim);
          addBox(frontage,'Harbour Centre upper retail fascia',
            0,4.35,face*0.31,len,0.64,0.20,masonry);
          addBox(frontage,'Harbour Centre stepped podium cornice',
            0,13.68,0,len+0.6,0.42,0.8,trim);
          if(label==='Dollarama'){
            const entryX=-len*0.16;
            addBox(frontage,'Dollarama recessed glass door pair',
              entryX,1.55,face*0.37,2.9,2.9,0.1,darkGlazing);
            for(const side of [-1,1])
              addBox(frontage,'Dollarama metal door jamb',
                entryX+side*1.46,1.6,face*0.43,0.1,3,0.16,doorFrame);
            addBox(frontage,'Dollarama door meeting stile',
              entryX,1.6,face*0.45,0.09,2.95,0.1,doorFrame);
            shopSign(frontage,'Dollarama Cordova storefront','DOLLARAMA',
              entryX,4.42,face,11.6,0.86,'#e9d44f','#1c6a39');
          } else {
            shopSign(frontage,'Harbour Centre Cordova wayfinding','HARBOUR CENTRE',
              len*0.04,4.42,face,13.4,0.82,'#23613b','#f1f1d9');
          }
          return frontage;
        }

        // The 5–14 edges are one collinear Cordova-facing 79m streetwall,
        // interrupted only by the small 8m shop/food-court lobby.
        const westRetail=lotFacingEdge(5,6,'Dollarama');
        const eastRetail=lotFacingEdge(13,14,'Harbour Centre');
        const a=coords[6],z=coords[13],pa=toLocal(a),pb=toLocal(z);
        const deltaX=pb.x-pa.x,deltaZ=pb.z-pa.z;
        const entryLen=Math.hypot(deltaX,deltaZ);
        if(westRetail&&eastRetail&&entryLen>5&&entryLen<12){
          const entry=new THREE.Group();
          entry.name='Harbour Centre Cordova recessed food court and shops lobby';
          entry.position.set((pa.x+pb.x)/2,0,(pa.z+pb.z)/2);
          entry.rotation.y=Math.atan2(-deltaZ,deltaX);
          const face=westRetail.userData.parkingFace;
          entry.userData.recessedBetweenCivicEdges=[6,13];
          entry.userData.parkingFace=face;
          group.add(entry);
          addBox(entry,'Harbour Centre shaded lobby interior',
            0,2.0,-face*0.6,entryLen-0.35,4.0,0.25,doorFrame);
          addBox(entry,'Harbour Centre food court full-height doors',
            0,2,face*0.12,entryLen-0.55,3.8,0.1,glassBase);
          for(const x of [-entryLen*0.31,0,entryLen*0.31])
            addBox(entry,'Harbour Centre lobby metal door mullion',
              x,1.9,face*0.19,0.11,3.8,0.11,mullion);
          shopSign(entry,'Harbour Centre food court and shops','FOOD COURT  |  SHOPS',
            0,4.33,face,entryLen-0.4,0.77,'#23613b','#f1f1d9');
          addBox(entry,'Harbour Centre glass lobby upper band',
            0,9.1,0,entryLen-0.3,8.6,0.32,darkGlazing);
          addBox(entry,'Harbour Centre recessed lobby roof coping',
            0,13.65,0,entryLen-0.15,0.38,0.55,trim);
        }
        group.userData.cordovaStreetWallSource='City of Vancouver feature-16 footprint edges';
        group.userData.cordovaFrontFacesComputedFromLot=true;
        group.userData.cordovaRetailLandmark='Dollarama / 542 W Cordova (illustrative storefront)';
        group.userData.landmark='Harbour Centre / Spencer Building';
        group.userData.architectureSource='https://harbourcentre.com/';
        group.userData.civicAnchor={lat:49.284697,lon:-123.111935};
        group.userData.landmarkHeightApproximate=true;
      }
    }
    rootGroup.userData.replacedIds=REPLACED_IDS.slice();
    rootGroup.userData.artifactsSuppressed='complex GIS extrusion near station';
    return rootGroup;
  }
  root.GastownCordovaArchitecture=Object.freeze({create,replacementIds,boundedBuilding});
})(window);

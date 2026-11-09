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
    // The masonry office mass opposite the CPR brickwork. Its clean stepped
    // streetwall preserves the 1914 station as the foreground civic landmark.
    {
      const item=blockFor('feature-92','W Cordova / Seymour pale stone office frontage');
      if(item){
        const {group,b}=item;
        const cx=(b.minX+b.maxX)*0.5,cz=(b.minZ+b.maxZ)*0.5;
        shell(group,'Stone square-bay Cordova office block',cx,cz,24,25,30,limestone,blue,8);
        addBox(group,'Office stepped parapet',cx,30.45,cz,20,0.6,20,paleStone);
        // Harbour Centre is the 28-storey office tower rising above the
        // heritage Spencer podium, with a circular lookout / restaurant crown.
        // Anchor the interpretation to this existing civic parcel, never to a
        // free-floating skyline coordinate. Dimensions remain illustrative.
        const towerX=cx-1.0, towerZ=cz-1.2;
        const towerWidth=19.0, towerDepth=18.5;
        const towerBase=21.0, towerTop=105.0, levels=28;
        addBox(group,'Harbour Centre concrete gridded 28 storey shaft',
          towerX,(towerBase+towerTop)/2,towerZ,towerWidth,towerTop-towerBase,towerDepth,limestone);
        // Reinforce the distinctive repetitive square window rhythm without
        // creating hundreds of independent deep glass cubes.
        for(let floor=0;floor<levels;floor++){
          const y=towerBase+(floor+0.52)*(towerTop-towerBase)/levels;
          for(let bay=-3;bay<=3;bay++){
            const offset=bay*2.42;
            for(const side of [-1,1]){
              addBox(group,'Harbour Centre square front glazing',
                towerX+offset,y,towerZ+side*(towerDepth/2+0.052),
                1.72,1.78,0.07,blue);
              addBox(group,'Harbour Centre square side glazing',
                towerX+side*(towerWidth/2+0.052),y,towerZ+offset,
                0.07,1.78,1.72,blue);
            }
          }
        }
        // Disc-shaped observation gallery with a slim pedestal and broad
        // silver roof is its unmistakable silhouette from the station lot.
        const lookoutMetal=new THREE.MeshStandardMaterial({
          color:0xb2bdc5,metalness:0.55,roughness:0.42
        });
        const lookoutGlass=new THREE.MeshStandardMaterial({
          color:0x568094,metalness:0.28,roughness:0.18
        });
        function disc(name,radius,height,y,material){
          const mesh=new THREE.Mesh(new THREE.CylinderGeometry(radius,radius,height,32),material);
          mesh.name=name;mesh.position.set(towerX,y,towerZ);
          mesh.castShadow=false;mesh.receiveShadow=false;
          group.add(mesh);return mesh;
        }
        disc('Harbour Centre lookout pedestal',5.8,5,107.5,limestone);
        disc('Vancouver Lookout panoramic glass ring',13.1,4.6,111.9,lookoutGlass);
        disc('Vancouver Lookout lower silver rim',13.7,0.72,109.35,lookoutMetal);
        disc('Vancouver Lookout overhanging circular roof',14.7,1.05,114.5,lookoutMetal);
        disc('Vancouver Lookout roof cap',10.6,0.65,115.35,paleStone);
        group.userData.landmark='Harbour Centre / Spencer Building';
        group.userData.landmarkHeightApproximate=true;
      }
    }
    // A second rectilinear glass-and-stone mass brings the actual downtown
    // vertical skyline into the Seymour sightline, rather than blank rows.
    {
      const item=blockFor('feature-15','Seymour reflective blue office tower');
      if(item){
        const {group,b}=item;
        const cx=(b.minX+b.maxX)*0.5,cz=(b.minZ+b.maxZ)*0.5;
        shell(group,'Seymour gridded glass tower',cx,cz,24,25,47,glassBase,cyan,13);
        addBox(group,'Seymour set-back glass penthouse',cx,49.5,cz,15,5,17,blue);
      }
    }
    // Seymour-to-Gastown transition: the supplied 333 W Cordova images
    // show highly reflective modern glass with rounded vertical elements.
    // Build a slim tower within the civic outline plus a lower street wall.
    {
      const item=blockFor('feature-16','Cordova modern curved glass and low-rise frontage');
      if(item){
        const {group,b}=item;
        shell(group,'Cordova low-rise sandstone streetwall',-18,-24,37,25,
          17,paleStone,blue,4);
        shell(group,'Cordova glass upper office shaft',1,1,19,19,
          58,glassBase,cyan,16);
        const cX=1,cZ=1;
        // Curved corner rhythm without massive triangulated mesh/solid
        // specular roof wedges, and without claiming a surveyed tower.
        for(let i=-3;i<=3;i++){
          const offset=i*2.45;
          const z=cZ+9.5+0.52*Math.cos(i*0.58);
          addBox(group,'Cordova curved glass strip '+i,cX+offset,31,z,2.18,53,
            0.22,(i%2===0)?bright:cyan);
        }
        addBox(group,'Cordova glazed rooftop crown',1,60.2,1,16,1.0,16,mullion);
      }
    }
    rootGroup.userData.replacedIds=REPLACED_IDS.slice();
    rootGroup.userData.artifactsSuppressed='complex GIS extrusion near station';
    return rootGroup;
  }
  root.GastownCordovaArchitecture=Object.freeze({create,replacementIds,boundedBuilding});
})(window);

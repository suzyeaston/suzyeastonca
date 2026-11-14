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

(function(){
'use strict';
let map=null,channels=[],markers=[],popup=null,visible=new Set(),selected=null,onTune=()=>{},onScope=()=>{};
const status=()=>document.getElementById('yr-map-status');
const scopes={vancouver:[[-123.32,49.12],[-122.82,49.36]],salish:[[-123.5,47.2],[-122.15,49.4]]};
function scope(name){
 if(!scopes[name]) return;
 document.querySelectorAll('[data-radio-region]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.radioRegion===name)));
 if(map) map.fitBounds(scopes[name],{padding:42,duration:0});
 onScope(name);
}
function draw(){
 if(!map)return;
 markers.forEach(m=>m.remove());markers=[];
 const sites=new Map();
 channels.filter(c=>visible.has(c.key)&&Number.isFinite(c.map_lat)&&Number.isFinite(c.map_lon)).forEach(c=>{
  // Nearby sources share a chooser instead of overlapping clickable pins.
  const key=c.map_lat.toFixed(2)+','+c.map_lon.toFixed(2);
  if(!sites.has(key))sites.set(key,[]);sites.get(key).push(c);
 });
 sites.forEach(group=>{
  const first=group[0],button=document.createElement('button');button.type='button';button.className='yr-map-pin';
  button.dataset.kind=first.group==='Vancouver recordings'?'field':first.group==='Scanners'?'scanner':first.group==='Hydrophones'?'hydro':'radio';
  button.textContent=group.length>1?String(group.length):first.group==='Vancouver recordings'?'★':'●';
  button.setAttribute('aria-label',group.map(c=>c.label).join(', ')+' — choose a listening source');
  button.setAttribute('aria-pressed',String(group.some(c=>c.key===selected)));
  button.addEventListener('click',(event)=>{
   event.stopPropagation();
   if(popup)popup.remove();
   const box=document.createElement('div');box.className='yr-map-popup';
   const heading=document.createElement('h3');heading.textContent=first.place||first.label;box.append(heading);
   group.forEach(c=>{
    const label=document.createElement('p');label.textContent=c.label;box.append(label);
    const tune=document.createElement(c.mode==='link_out'?'a':'button');tune.textContent=c.mode==='link_out'?'Open official player ↗':'Tune in';
    if(c.mode==='link_out'){tune.href=c.link_url||c.source_url;tune.target='_blank';tune.rel='noopener noreferrer';}
    tune.addEventListener('click',()=>{onTune(c.key);if(popup)popup.remove();document.getElementById('yr-title')?.scrollIntoView({block:'nearest'});});box.append(tune);
   });
   popup=new window.maplibregl.Popup({offset:20,maxWidth:'300px'}).setLngLat([first.map_lon,first.map_lat]).setDOMContent(box).addTo(map);
  });
  markers.push(new window.maplibregl.Marker({element:button}).setLngLat([first.map_lon,first.map_lat]).addTo(map));
  // MapLibre sets a generic aria-label; restore the useful station names.
  button.setAttribute('aria-label',group.map(c=>c.label).join(', ')+' — choose a listening source');
 });
}
window.YvrListeningMap={
 init(list,tune,change){
  channels=list;visible=new Set(list.filter(c=>c.region!=='salish').map(c=>c.key));onTune=tune;onScope=change;
  document.querySelectorAll('[data-radio-region]').forEach(b=>b.addEventListener('click',()=>scope(b.dataset.radioRegion)));
  const box=document.getElementById('yr-map');if(!box)return;
  if(!window.maplibregl){status().textContent='Map could not load. All listening sources remain available in the station list.';return;}
  try{
   map=new window.maplibregl.Map({container:box,style:'https://tiles.openfreemap.org/styles/dark',center:[-123.10,49.25],zoom:10,attributionControl:true});
   map.addControl(new window.maplibregl.NavigationControl(),'top-right');
   map.on('load',()=>{status().textContent='Map ready. Tap a pinpoint to choose a source.';draw();});
   map.on('error',()=>{status().textContent='Some map tiles could not load. Use the station list if the map is incomplete.';});
   map.fitBounds(scopes.vancouver,{padding:42,duration:0});
   draw();
  }catch(_){status().textContent='This browser could not display the map. The station list still works.';}
 },
 filter(keys){visible=new Set(keys);draw();},
 select(key){selected=key;draw();},scope
};
})();

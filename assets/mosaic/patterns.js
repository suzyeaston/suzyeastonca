/* MOSAIC · original, editable browser-native instrument and rhythm loops.
 * Inspired by SUZY//AI Film Club's beat score and TOASTER loop rack.
 * Deliberately no samples, pretrained model or external audio.
 */
(function(root,factory){
 const api=factory();
 if(typeof module==='object'&&module.exports)module.exports=api;
 if(root)root.MosaicPatterns=api;
})(typeof globalThis!=='undefined'?globalThis:this,function(){
 'use strict';
 const STEPS=16;
 const DEFAULT_RHYTHM=Object.freeze({
   kick:Object.freeze([0,8]),snare:Object.freeze([4,12]),
   hat:Object.freeze([0,2,4,6,8,10,12,14])
 });
 const PRESETS=Object.freeze([
   {id:'heartbeat',name:'Rhythm · Harbour Pulse',kind:'drums',
     steps:{kick:[0,4,8,12],snare:[4,12],hat:[0,2,4,6,8,10,12,14]}},
   {id:'broken',name:'Rhythm · Broken Machine',kind:'drums',
     steps:{kick:[0,7,10],snare:[4,12,15],hat:[0,3,6,8,10,13,15]}},
   {id:'half-time',name:'Rhythm · Half-time / Air',kind:'drums',
     steps:{kick:[0,9],snare:[8],hat:[0,4,6,8,12,14]}},
   {id:'sub-walk',name:'Bass · Sub Walk',kind:'bass',
     notes:[{step:0,note:40,durSteps:3},{step:4,note:40,durSteps:2},{step:7,note:43,durSteps:2},{step:10,note:47,durSteps:3},{step:14,note:43,durSteps:2}]},
   {id:'glass-circuit',name:'Keys · Glass Circuit',kind:'keys',
     notes:[{step:0,note:64,durSteps:2},{step:3,note:67,durSteps:2},{step:6,note:71,durSteps:2},{step:10,note:74,durSteps:3},{step:14,note:67,durSteps:2}]},
   {id:'soft-orbit',name:'Pad · Soft Orbit',kind:'pad',
     notes:[{step:0,note:52,durSteps:8},{step:0,note:59,durSteps:8},{step:8,note:55,durSteps:8},{step:8,note:62,durSteps:8}]}
 ]);
 function safeTempo(input){
   const n=Number(input);
   return Number.isFinite(n)?Math.max(60,Math.min(200,Math.round(n))):120;
 }
 function safeBars(input){return [1,2,4].includes(Number(input))?Number(input):4;}
 function validateSteps(input){
   const output={};
   for(const type of ['kick','snare','hat']){
     const raw=Array.isArray(input?.[type])?input[type]:[];
     output[type]=[...new Set(raw.filter(n=>Number.isInteger(n)&&n>=0&&n<STEPS))].sort((a,b)=>a-b);
   }
   return output;
 }
 function eventsFromSteps(steps,bpm,bars){
   const clean=validateSteps(steps),perStep=60/safeTempo(bpm)/4;
   const count=safeBars(bars),events=[];
   const drums={kick:{note:36,dur:.18},snare:{note:38,dur:.14},hat:{note:42,dur:.085}};
   for(let bar=0;bar<count;bar++)for(const [type,info] of Object.entries(drums))
     for(const step of clean[type])
       events.push({t:Number(((bar*STEPS+step)*perStep).toFixed(6)),note:info.note,dur:info.dur});
   return events.sort((a,b)=>a.t-b.t||a.note-b.note);
 }
 function buildPreset(id,bpm=120,bars=4,steps=null){
   const preset=PRESETS.find(p=>p.id===id);
   if(!preset&&id!=='custom-rhythm')return null;
   const tempo=safeTempo(bpm),barCount=safeBars(bars),perStep=60/tempo/4;
   const kind=id==='custom-rhythm'?'drums':preset.kind;
   const name=id==='custom-rhythm'?'Rhythm · My Beat':preset.name;
   let events=[];
   if(kind==='drums')events=eventsFromSteps(id==='custom-rhythm'?steps:preset.steps,tempo,barCount);
   else for(let bar=0;bar<barCount;bar++)for(const ev of preset.notes)
     events.push({t:Number(((bar*STEPS+ev.step)*perStep).toFixed(6)),
       note:ev.note,dur:Number(Math.min(3,ev.durSteps*perStep*.9).toFixed(6))});
   return events.length?{
     id:'loop-'+Date.now()+'-'+Math.random().toString(36).slice(2,7),
     kind,name,source:id==='custom-rhythm'?'custom-rhythm':'preset',presetId:id,
     bpm:tempo,bars:barCount,seconds:barCount*4*60/tempo,
     events,muted:false,volume:kind==='drums'?.65:.75,
   }:null;
 }
 return Object.freeze({STEPS,DEFAULT_RHYTHM,PRESETS,safeTempo,safeBars,validateSteps,eventsFromSteps,buildPreset});
});

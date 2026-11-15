/* MOSAIC audio-only overdub lanes.
 * Mic capture never occupies or replaces a video tile. Decoded audio is
 * scheduled per musical cycle against the same AudioContext epoch as synths.
 */
(function(root,factory){
 const api=factory();
 if(typeof module==='object'&&module.exports)module.exports=api;
 if(root)root.MosaicAudioLoops=api;
})(typeof globalThis!=='undefined'?globalThis:this,function(){
 'use strict';
 const MAX=12;
 function validate(raw){
   if(!raw||!(raw.blob instanceof Blob)||typeof raw.id!=='string')return null;
   const id=raw.id.slice(0,64);
   if(!/^[a-zA-Z0-9_-]+$/.test(id))return null;
   const seconds=Number(raw.seconds);
   if(!(seconds>.2&&seconds<=1800))return null;
   const bpm=Math.min(200,Math.max(60,Number(raw.bpm)||120));
   return {id,blob:raw.blob,seconds,bpm,muted:!!raw.muted,
     volume:Math.min(1.5,Math.max(0,Number(raw.volume??.85)||0)),
     name:String(raw.name||'Mic overdub').slice(0,44),
     startAt:Number.isFinite(raw.startAt)?Math.max(0,raw.startAt):0};
 }
 function cycleTimes(seconds,from,to,startAt=0){
   const step=Number(seconds),offset=Number(startAt)||0;
   if(!(step>0&&to>from))return [];
   const result=[];
   let n=Math.max(0,Math.ceil((from-offset-.00001)/step));
   while(n<=Math.ceil(to/step)+1&&result.length<120){
     const t=offset+n*step;
     if(t>=to)break;
     if(t>=from-.00001)result.push(t);
     n++;
   }
   return result;
 }
 class Rack{
   constructor({host,ensureAudioContext,onStatus,onChange,getPosition,onPlay,getBpm,onMicStart,onFinish}){
     this.host=host;this.ensureAudioContext=ensureAudioContext;this.onStatus=onStatus;
     this.onChange=onChange;this.getPosition=getPosition;this.onPlay=onPlay;
     this.getBpm=getBpm;this.onMicStart=onMicStart;this.onFinish=onFinish;
     const el=id=>host.document.getElementById(id);
     this.recordButton=el('audio-loop-record');
     this.bars=el('audio-loop-bars');this.status=el('audio-loop-status');
     this.lanes=el('audio-loop-lanes');
     this.layers=[];this.ctx=null;this.bus=null;this.playing=false;this.nextWindow=0;
     this.voices=new Set();this.channelBuses=new Map();this.exportBus=null;
     this.pending=null;this.captureTimer=null;this.micStream=null;this.recorder=null;
     this.recordButton.addEventListener('click',()=>this.pending?this.cancel():this.record());
     this.render();
   }
   get isRecording(){return !!this.pending;}
   get hasLayers(){return this.layers.length>0;}
   get duration(){return Math.max(0,...this.layers.map(l=>l.seconds+l.startAt));}
   ensureBus(ctx){
     if(this.ctx===ctx&&this.bus)return;
     this.ctx=ctx;this.bus=ctx.createGain();this.bus.gain.value=.95;
     this.bus.connect(ctx.destination);this.channelBuses.clear();
   }
   channel(layer){
     this.ensureBus(this.ctx);
     let g=this.channelBuses.get(layer.id);
     if(!g){g=this.ctx.createGain();g.connect(this.bus);this.channelBuses.set(layer.id,g);}
     g.gain.value=layer.muted?0:layer.volume;
     return g;
   }
   applyGain(id){
     const layer=this.layers.find(l=>l.id===id),node=this.channelBuses.get(id);
     if(layer&&node){
       const target=layer.muted?0:layer.volume;
       node.gain.setTargetAtTime(target,this.ctx.currentTime,.025);
     }
   }
   connectExport(destination){if(this.bus&&destination){this.bus.connect(destination);this.exportBus=destination;}}
   disconnectExport(){if(this.bus&&this.exportBus)try{this.bus.disconnect(this.exportBus);}catch{}
     this.exportBus=null;}
   async decode(raw){
     const data=await raw.blob.arrayBuffer();
     const buffer=await this.ctx.decodeAudioData(data);
     return {...raw,buffer};
   }
   async restore(saved){
     const ctx=await this.ensureAudioContext(false);this.ensureBus(ctx);
     for(const raw of (Array.isArray(saved)?saved:[]).slice(0,MAX)){
       const valid=validate(raw);if(!valid)continue;
       try{this.layers.push(await this.decode(valid));}
       catch{this.onStatus('A saved mic loop could not be decoded on this device.',true);}
     }
     this.render();this.onChange('restored');
   }
   async add(record){
     const clip=validate(record);
     if(!clip)throw Error('Invalid microphone loop.');
     if(this.layers.length>=MAX)throw Error('12 audio-only lanes reached.');
     const ctx=await this.ensureAudioContext();this.ensureBus(ctx);
     const decoded=await this.decode(clip);
     this.layers.push(decoded);this.render();this.onChange('added',decoded);
     return decoded;
   }
   startPlayback(){this.stopPlayback();this.playing=true;this.nextWindow=0;this.tick(0);}
   tick(elapsed){
     if(!this.playing||!this.ctx)return;
     const to=Math.max(0,elapsed)+.22;
     if(to<=this.nextWindow)return;
     const base=this.ctx.currentTime-Math.max(0,elapsed);
     for(const layer of this.layers){
       if(!layer.buffer)continue;
       const channel=this.channel(layer);
       for(const t of cycleTimes(layer.seconds,this.nextWindow,to,layer.startAt)){
         const voice=this.ctx.createBufferSource();
         voice.buffer=layer.buffer;
         voice.connect(channel);
         voice.start(Math.max(this.ctx.currentTime+.006,base+t));
         // Do not trust the media container to align exactly to a musical bar.
         // Each audio cycle starts at a new transport-aligned boundary.
         voice.stop(Math.max(this.ctx.currentTime+.006,base+t)+Math.min(layer.seconds,layer.buffer.duration)+.005);
         this.voices.add(voice);voice.onended=()=>this.voices.delete(voice);
       }
     }
     this.nextWindow=to;
   }
   stopPlayback(){
     this.playing=false;this.nextWindow=0;
     for(const voice of this.voices)try{voice.stop();}catch{}
     this.voices.clear();
   }
   applyMixSnapshot(snapshot){
     for(const layer of this.layers){
       const level=snapshot['audio:'+layer.id];
       if(level===undefined)continue;
       layer.muted=level<=.001;
       if(level>.001)layer.volume=Math.min(1.5,Math.max(0,level));
       this.applyGain(layer.id);
     }
     this.render();
   }
   change(id,type,value){
     const layer=this.layers.find(l=>l.id===id);if(!layer)return;
     if(type==='remove'){
       this.layers=this.layers.filter(l=>l.id!==id);
       try{this.channelBuses.get(id)?.disconnect();}catch{}
       this.channelBuses.delete(id);
     }
     else if(type==='mute')layer.muted=!layer.muted;
     else if(type==='volume')layer.volume=Math.max(0,Math.min(1.5,Number(value)/100));
     this.applyGain(id);this.render();this.onChange(type,layer);
   }
   async record(){
     if(this.pending||this.layers.length>=MAX){this.onStatus('Audio lane limit reached.',true);return;}
     if(!this.host.navigator.mediaDevices?.getUserMedia||typeof this.host.MediaRecorder==='undefined'){
       this.onStatus('Microphone overdubbing needs a modern browser and HTTPS.',true);return;
     }
     const bpm=this.getBpm(),bars=Number(this.bars.value)||4;
     const length=bars*240/bpm;
     const job={canceled:false,started:false,clipSeconds:length,bpm};
     this.pending=job;this.render();
     try{
       const stream=await this.host.navigator.mediaDevices.getUserMedia({audio:{
         echoCancellation:false,noiseSuppression:false,autoGainControl:false}});
       if(job.canceled){stream.getTracks().forEach(t=>t.stop());return;}
       this.micStream=stream;
       this.onMicStart?.();
       const ctx=await this.ensureAudioContext();this.ensureBus(ctx);
       const canType=type=>this.host.MediaRecorder.isTypeSupported?.(type);
       const mime=['audio/webm;codecs=opus','audio/mp4','audio/webm'].find(canType);
       const recorder=mime?new this.host.MediaRecorder(stream,{mimeType:mime}):new this.host.MediaRecorder(stream);
       this.recorder=recorder;const chunks=[];
       recorder.ondataavailable=e=>{if(e.data?.size)chunks.push(e.data);};
       const stopped=new Promise((resolve,reject)=>{
         recorder.onstop=resolve;recorder.onerror=e=>reject(e.error||Error('Microphone recording failed'));
       });
       const startClock=()=>this.getPosition();
       if(!(await this.onPlay()))throw Error('Could not start the backing transport.');
       const active=this.getPosition();
       const bar=240/bpm;
       // Start at a future downbeat with time to prepare the recorder.
       const startAt=Math.ceil((active+.35)/bar)*bar;
       job.startAt=startAt;
       this.status.textContent='ARMED / STARTS NEXT BAR';
       await new Promise((resolve,reject)=>{
         const id=this.host.setInterval(()=>{
           if(job.canceled){this.host.clearInterval(id);reject(Error('Canceled'));return;}
           if(startClock()>=startAt-.025){this.host.clearInterval(id);resolve();}
         },15);
         job.armTimer=id;
       });
       if(job.canceled)return;
       recorder.start(250);job.started=true;
       this.status.textContent='REC / '+bars+' BAR'+(bars===1?'':'S');
       this.render();
       await new Promise((resolve,reject)=>{
         const id=this.host.setInterval(()=>{
           if(job.canceled){this.host.clearInterval(id);resolve();return;}
           if(startClock()>=startAt+length-.02){this.host.clearInterval(id);resolve();}
         },15);
         job.stopTimer=id;
       });
       if(recorder.state==='recording')recorder.stop();
       await stopped;
       if(!job.canceled&&chunks.some(c=>c.size)){
         const blob=new Blob(chunks,{type:recorder.mimeType||mime||'audio/webm'});
         const clip=await this.add({id:'mic-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,6),
           blob,name:'MIC '+(this.layers.length+1),seconds:length,bpm,muted:false,volume:.85,startAt:0});
         job.saved=clip;
         this.status.textContent='LOOP SAVED · '+bars+' BARS';
         this.onStatus('Audio overdub saved as a new, independent musical lane. You can build another part. ♡');
       }
     }catch(error){
       if(!job.canceled)this.onStatus('Microphone loop failed: '+(error.message||error),true);
     }finally{
       if(job.armTimer)this.host.clearInterval(job.armTimer);
       if(job.stopTimer)this.host.clearInterval(job.stopTimer);
       if(this.recorder?.state==='recording')this.recorder.stop();
       this.recorder=null;
       this.micStream?.getTracks().forEach(t=>t.stop());this.micStream=null;
       if(this.pending===job)this.pending=null;
       this.render();this.onFinish?.();
     }
   }
   cancel(){
     const job=this.pending;if(!job)return;
     job.canceled=true;
     if(job.armTimer)this.host.clearInterval(job.armTimer);
     if(job.stopTimer)this.host.clearInterval(job.stopTimer);
     if(this.recorder?.state==='recording')this.recorder.stop();
     this.micStream?.getTracks().forEach(t=>t.stop());
     this.status.textContent='CANCELLED';
     this.onStatus('Microphone take discarded. Existing loops kept.');
     this.render();
   }
   render(){
     this.recordButton.textContent=this.pending?'■ CANCEL AUDIO TAKE':'● RECORD AUDIO LOOP';
     this.recordButton.classList.toggle('is-recording',!!this.pending);
     this.recordButton.disabled=!this.pending&&this.layers.length>=MAX;
     this.bars.disabled=!!this.pending;
     this.lanes.replaceChildren();
     for(const layer of this.layers){
       const row=this.host.document.createElement('div');row.className='audio-loop-row';
       const label=this.host.document.createElement('strong');
       label.textContent=layer.name+' · '+layer.seconds.toFixed(1)+'s';
       const mute=this.host.document.createElement('button');mute.type='button';
       mute.textContent=layer.muted?'UNMUTE':'MUTE';
       mute.addEventListener('click',()=>this.change(layer.id,'mute'));
       const vol=this.host.document.createElement('input');vol.type='range';vol.min=0;vol.max=150;vol.step=5;
       vol.value=String(Math.round(layer.volume*100));
       vol.setAttribute('aria-label',layer.name+' volume');
       vol.addEventListener('input',()=>this.change(layer.id,'volume',vol.value));
       const remove=this.host.document.createElement('button');remove.type='button';
       remove.textContent='×';remove.setAttribute('aria-label','Remove '+layer.name);
       remove.addEventListener('click',()=>this.change(layer.id,'remove'));
       row.append(label,mute,vol,remove);this.lanes.append(row);
     }
   }
   clear(){this.cancel();this.stopPlayback();this.layers=[];this.channelBuses.clear();
     this.render();this.onChange('clear');}
 }
 return {Rack,validate,cycleTimes,MAX};
});

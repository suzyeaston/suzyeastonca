/* MOSAIC / SUZY//AI crossover: deterministic Web Audio note layers.
 * Adapted from suzyeaston/suzy-ai Film Club browser synth and TOASTER
 * independent looping concepts. No Magenta model or network service.
 */
(function(root,factory){
  const bank=root.MosaicSoundbank||(typeof module==='object'&&module.exports?require('./soundbank.js'):null);
  const api=factory(bank);
  if(typeof module==='object'&&module.exports)module.exports=api;
  if(root)root.MosaicInstruments=api;
})(typeof globalThis!=='undefined'?globalThis:this,function(bank){
  'use strict';
  if(!bank)throw new Error('MOSAIC soundbank module must load before instruments');
  const {SOUNDS,defaultVoice,soundFor,frequency,playNote}=bank;
  const KINDS=['keys','bass','pad'];
  const OFFSETS=[0,2,4,5,7,9,11,12];
  const KEYBOARD='asdfghjk';
  const MAX_LAYERS=12, MAX_EVENTS=512, STORAGE='mosaic-instrument-layers-v1';
  const pitch=(kind,pad)=>(kind==='bass'?48:60)+OFFSETS[pad];
  function normalizeLayer(raw){
    if(!raw||!(KINDS.includes(raw.kind)||raw.kind==='drums'))return null;
    const seconds=Number(raw.seconds);
    if(!Number.isFinite(seconds)||seconds<.2||seconds>1800)return null;
    const events=(Array.isArray(raw.events)?raw.events:[]).slice(0,MAX_EVENTS).map(e=>{
      const t=Number(e.t),note=Number(e.note),dur=Number(e.dur);
      if(!Number.isFinite(t)||t<0||t>=seconds||!Number.isInteger(note)||note<24||note>108||!Number.isFinite(dur)||dur<=0||dur>4)return null;
      return {t,note,dur};
    }).filter(Boolean);
    if(!events.length)return null;
    const result={id:String(raw.id||'layer').slice(0,64),kind:raw.kind,
      voice:soundFor(raw.kind,raw.voice)?.id||defaultVoice(raw.kind),
      seconds,events,startAt:Number.isFinite(raw.startAt)?Math.max(0,Math.min(1800,raw.startAt)):0,muted:!!raw.muted,volume:Math.max(0,Math.min(1.5,Number(raw.volume??.8)||0))};
    if(raw.source==='preset'||raw.source==='custom-rhythm'){
      result.source=raw.source;
      result.name=String(raw.name||raw.kind).slice(0,55);
      result.bpm=Number.isFinite(raw.bpm)?Math.max(60,Math.min(200,Math.round(raw.bpm))):120;
      result.bars=[1,2,4].includes(raw.bars)?raw.bars:4;
      result.presetId=String(raw.presetId||'').slice(0,40);
    }
    return result;
  }
  function eventsInWindow(layer,from,to){
    if(!layer||layer.muted||!(to>from)||!layer.seconds)return [];
    const out=[];
    const startAt=Number.isFinite(layer.startAt)?Math.max(0,layer.startAt):0;
    for(const e of layer.events){
      let n=Math.max(0,Math.ceil((from-e.t-startAt-.000001)/layer.seconds));
      for(;n<=Math.ceil(to/layer.seconds);n++){
        const t=startAt+e.t+n*layer.seconds;
        if(t>=to)break;
        if(t>=from-.000001)out.push({...e,t,kind:layer.kind,volume:layer.volume,voice:layer.voice});
      }
    }
    return out.sort((a,b)=>a.t-b.t).slice(0,256);
  }
  class Rack {
    constructor({host,ensureAudioContext,onRecord,onChange,onStatus,onPreviewStart,getTransportPosition}){
      this.host=host;this.ensureAudioContext=ensureAudioContext;
      this.onPreviewStart=onPreviewStart;
      this.getTransportPosition=getTransportPosition;
      this.onRecord=onRecord;this.onChange=onChange;this.onStatus=onStatus;
      const el=id=>host.document.getElementById(id);
      this.kind=el('instrument-kind');this.recordBtn=el('instrument-record');
      this.pads=el('instrument-pads');this.lanes=el('instrument-layers');
      this.counter=el('instrument-count');
      this.voiceSelect=el('instrument-voice');
      this.loopVoiceSelect=el('instrument-loop-voice');
      this.previewLoopButton=el('instrument-preview-loop');
      this.previewRhythmButton=el('instrument-preview-rhythm');
      this.previewing=null;this.previewNodes=new Set();this.previewOutput=null;
      this.previewTimer=null;this.previewEpoch=0;
      this.patterns=host.MosaicPatterns;
      this.presetSelect=el('instrument-loop-preset');
      this.barsSelect=el('instrument-loop-bars');
      this.addLoopButton=el('instrument-add-loop');
      this.addRhythmButton=el('instrument-add-rhythm');
      this.stepHost=el('instrument-step-grid');
      this.stepData={};
      for(const drum of ['kick','snare','hat'])this.stepData[drum]=[...this.patterns.DEFAULT_RHYTHM[drum]];
      this.steps=[];
      this.createStepGrid();
      this.addLoopButton.addEventListener('click',()=>this.addPreset());
      this.addRhythmButton.addEventListener('click',()=>this.addCustomRhythm());
      this.previewLoopButton.addEventListener('click',()=>this.preview('preset'));
      this.previewRhythmButton.addEventListener('click',()=>this.preview('rhythm'));
      this.kind.addEventListener('change',()=>this.setSoundOptions(this.voiceSelect,this.kind.value));
      this.presetSelect.addEventListener('change',()=>{
        this.stopPreview();this.setSoundOptions(this.loopVoiceSelect,this.selectedLoopKind());
      });
      this.setSoundOptions(this.voiceSelect,this.kind.value);
      this.setSoundOptions(this.loopVoiceSelect,this.selectedLoopKind());
      this.layers=[];this.recording=null;this.ctx=null;this.bus=null;this.exportBus=null;
      this.playing=false;this.nextWindow=0;this.voices=new Set();
      try{const items=JSON.parse(host.localStorage.getItem(STORAGE)||'[]');
        if(Array.isArray(items))this.layers=items.slice(0,MAX_LAYERS).map(normalizeLayer).filter(Boolean);}
      catch{/* local storage disabled */}
      const labels=['C','D','E','F','G','A','B','C↑'];
      for(let i=0;i<8;i++){
        const button=host.document.createElement('button');button.type='button';
        button.className='instrument-pad';
        const note=host.document.createElement('strong');note.textContent=labels[i];
        const key=host.document.createElement('small');key.textContent=KEYBOARD[i].toUpperCase();
        button.append(note,key);button.setAttribute('aria-label','Play '+labels[i]);
        button.addEventListener('click',()=>this.hit(i));this.pads.append(button);
      }
      this.recordBtn.addEventListener('click',()=>this.onRecord());
      host.addEventListener('keydown',e=>{
        if(e.repeat||e.altKey||e.ctrlKey||e.metaKey||e.isComposing)return;
        const tag=e.target?.tagName?.toLowerCase();
        if(['input','textarea','select'].includes(tag)||e.target?.isContentEditable || (tag==='button'&&!e.target.classList.contains('instrument-pad')))return;
        const index=KEYBOARD.indexOf(e.key.toLowerCase());
        if(index>=0){e.preventDefault();this.hit(index);}
      });
      this.render();
    }
    selectedLoopKind(){
      return this.patterns.PRESETS.find(p=>p.id===this.presetSelect.value)?.kind||'drums';
    }
    setSoundOptions(select,kind){
      const previous=select.value;select.replaceChildren();
      for(const voice of SOUNDS[kind]||[]){
        const option=this.host.document.createElement('option');
        option.value=voice.id;option.textContent=voice.label;
        option.title=voice.description;select.append(option);
      }
      if(SOUNDS[kind]?.some(x=>x.id===previous))select.value=previous;
    }
    stopPreview(){
      this.previewEpoch++;
      if(this.previewTimer!==null){this.host.clearInterval(this.previewTimer);this.previewTimer=null;}
      for(const source of this.previewNodes)try{source.stop();}catch{}
      this.previewNodes.clear();
      try{this.previewOutput?.disconnect();}catch{}
      this.previewOutput=null;this.previewing=null;
      this.previewLoopButton.textContent='▶ HEAR LOOP';
      this.previewRhythmButton.textContent='▶ HEAR BEAT';
    }
    async preview(mode){
      if(this.previewing===mode){this.stopPreview();return;}
      this.onPreviewStart?.();
      this.stopPreview();
      if(this.recording)return;
      const token=this.previewEpoch;
      this.previewing=mode;
      this.previewLoopButton.textContent=mode==='preset'?'■ STOP PREVIEW':'▶ HEAR LOOP';
      this.previewRhythmButton.textContent=mode==='rhythm'?'■ STOP PREVIEW':'▶ HEAR BEAT';
      try{
        const bpm=this.host.document.getElementById('bpm-input').value;
        const pattern=mode==='preset'
          ?this.patterns.buildPreset(this.presetSelect.value,bpm,1)
          :this.patterns.buildPreset('custom-rhythm',bpm,1,this.stepData);
        if(!pattern){this.stopPreview();this.onStatus('Add drum steps to audition this rhythm.',true);return;}
        const ctx=await this.ensureAudioContext();
        if(token!==this.previewEpoch)return;
        const output=ctx.createGain();output.gain.value=.75;
        output.connect(ctx.destination);this.previewOutput=output;
        const voice=mode==='preset'?this.loopVoiceSelect.value:defaultVoice('drums');
        let nextBarAt=ctx.currentTime+.055;
        // An audio-clock lookahead repeats the audition until Stop is pressed.
        // Preview never writes to a layer or the video export bus.
        const schedule=()=>{
          if(token!==this.previewEpoch)return;
          while(nextBarAt<ctx.currentTime+.20){
            for(const event of pattern.events){
              for(const node of playNote(ctx,output,pattern.kind,event.note,
                  nextBarAt+event.t,event.dur,pattern.volume,voice)){
                this.previewNodes.add(node);node.onended=()=>this.previewNodes.delete(node);
              }
            }
            nextBarAt+=pattern.seconds;
          }
        };
        schedule();
        this.previewTimer=this.host.setInterval(schedule,30);
        this.onStatus('Auditioning '+pattern.name+' on repeat. Press Stop Preview or Add when it feels right.');
      }catch(error){this.stopPreview();this.onStatus('Preview failed: '+(error.message||error),true);}
    }
    createStepGrid(){
      const doc=this.host.document;
      for(const [instrument,label] of [['kick','KICK'],['snare','SNARE'],['hat','HAT']]){
        const row=doc.createElement('div');row.className='instrument-step-row';
        const title=doc.createElement('span');title.className='instrument-step-label';title.textContent=label;
        row.append(title);
        for(let i=0;i<16;i++){
          const b=doc.createElement('button');b.type='button';
          b.className='instrument-step'+(i%4===0?' is-quarter':'');
          b.setAttribute('aria-label',label+' step '+(i+1));
          b.textContent=i%4===0?String(i/4+1):'·';
          b.setAttribute('aria-pressed',String(this.stepData[instrument].includes(i)));
          b.addEventListener('click',()=>{
            if(this.previewing)this.stopPreview();
            const data=this.stepData[instrument],idx=data.indexOf(i);
            if(idx>=0)data.splice(idx,1);else data.push(i);
            data.sort((a,b)=>a-b);
            b.setAttribute('aria-pressed',String(data.includes(i)));
          });
          row.append(b);this.steps.push(b);
        }
        this.stepHost.append(row);
      }
    }
    addLayer(raw){
      if(this.layers.length>=MAX_LAYERS)return this.onStatus('The rack has 12 layers. Mute or remove one to make room.',true);
      const layer=normalizeLayer(raw);
      if(!layer)return this.onStatus('That pattern is empty or cannot be played. Add some notes first.',true);
      this.stopPreview();
      if(this.playing&&this.getTransportPosition){
        // A new musical lane joins on the next bar boundary, rather than
        // randomly in the middle of a rhythm or restarting everything.
        const elapsed=this.getTransportPosition();
        const tempo=Number(this.layers.find(item=>item.bpm)?.bpm||layer.bpm||120);
        const bar=240/tempo;
        layer.startAt=Math.ceil((elapsed+.25)/bar)*bar;
      }
      this.layers.push(layer);this.save();this.onChange('added');this.render();
      this.onStatus(layer.name+' is looping at '+layer.bpm+' BPM. Add another part or press Play All. ♡');
      return layer;
    }
    addPreset(){
      const current=this.patterns.buildPreset(this.presetSelect.value,
        this.host.document.getElementById('bpm-input').value,this.barsSelect.value);
      if(current)current.voice=this.loopVoiceSelect.value;
      return this.addLayer(current);
    }
    addCustomRhythm(){
      const current=this.patterns.buildPreset('custom-rhythm',
        this.host.document.getElementById('bpm-input').value,this.barsSelect.value,this.stepData);
      if(current)current.voice=defaultVoice('drums');
      return this.addLayer(current);
    }
    get hasLayers(){return this.layers.length>0;}
    get duration(){return Math.max(0,...this.layers.map(l=>l.seconds+(l.startAt||0)));}
    get isRecording(){return !!this.recording;}
    get elapsed(){return this.recording?(this.recording.clock?this.recording.clock():(performance.now()-this.recording.startedAt)/1000):0;}
    save(){try{this.host.localStorage.setItem(STORAGE,JSON.stringify(this.layers));}
      catch{this.onStatus('Cannot save instrument layers in this browser. Export your movie before leaving.',true);}}
    ensureBus(ctx){
      if(this.ctx===ctx&&this.bus)return this.bus;
      this.ctx=ctx;this.bus=ctx.createGain();this.bus.gain.value=.85;
      this.bus.connect(ctx.destination);return this.bus;
    }
    connectExport(destination){
      if(this.ctx&&this.bus&&destination){this.bus.connect(destination);this.exportBus=destination;}
    }
    disconnectExport(){
      if(this.bus&&this.exportBus)try{this.bus.disconnect(this.exportBus);}catch{}
      this.exportBus=null;
    }
    begin({clock=null,bpm=120}={}){if(this.recording||this.layers.length>=MAX_LAYERS)return false;
      this.recording={kind:this.kind.value,voice:this.voiceSelect.value,
        clock:typeof clock==='function'?clock:null,bpm,startedAt:performance.now(),events:[]};
      this.render(true);return true;}
    finish(){
      const r=this.recording;if(!r)return null;
      this.recording=null;
      const tail=Math.max(0,...r.events.map(e=>e.t+e.dur));
      const elapsed=r.clock?r.clock():(performance.now()-r.startedAt)/1000;
      const baseLength=Math.max(.25,elapsed,tail);
      const beatBar=240/Math.max(60,Math.min(200,Number(r.bpm)||120));
      const seconds=r.clock?Math.ceil((baseLength+.005)/beatBar)*beatBar:baseLength;
      const layer=normalizeLayer({id:String(Date.now())+'-'+Math.random().toString(36).slice(2,8),
        kind:r.kind,voice:r.voice,seconds,
        events:r.events,muted:false,volume:.8});
      if(layer){this.layers.push(layer);this.save();this.onChange('added',layer);}
      this.render();return layer;
    }
    async hit(pad){
      try{
        const ctx=await this.ensureAudioContext(),kind=this.recording?.kind||this.kind.value;
        const note=pitch(kind,pad),dur=kind==='pad'?1.25:kind==='bass'?.47:.6;
        playNote(ctx,this.ensureBus(ctx),kind,note,ctx.currentTime+.01,dur,.9,this.recording?.voice||this.voiceSelect.value);
        if(this.recording){
          if(this.recording.events.length<MAX_EVENTS){
            this.recording.events.push({t:Math.max(0,this.recording.clock?
              this.recording.clock():(performance.now()-this.recording.startedAt)/1000),note,dur});
          }else this.onStatus('512-note limit reached. Stop and keep this layer.',true);
        }
      }catch(e){this.onStatus('Instrument unavailable: '+(e.message||e),true);}
    }
    startPlayback(){this.stopPreview();this.stopPlayback();this.playing=true;this.nextWindow=0;this.tick(0);}
    tick(elapsed){
      if(!this.playing||!this.ctx)return;
      const to=Math.max(0,elapsed)+.20;
      if(to<=this.nextWindow)return;
      const base=this.ctx.currentTime-Math.max(0,elapsed);
      for(const layer of this.layers)for(const ev of eventsInWindow(layer,this.nextWindow,to)){
        const voices=playNote(this.ctx,this.ensureBus(this.ctx),ev.kind,ev.note,base+ev.t,ev.dur,ev.volume,ev.voice);
        for(const voice of voices){this.voices.add(voice);voice.onended=()=>this.voices.delete(voice);}
      }
      this.nextWindow=to;
    }
    stopPlayback(){this.stopPreview();this.playing=false;this.nextWindow=0;
      for(const voice of this.voices)try{voice.stop();}catch{}this.voices.clear();}
    applyMixSnapshot(snapshot){
      for(const layer of this.layers){
        const value=snapshot['instrument:'+layer.id];
        if(value===undefined)continue;
        layer.muted=value<=.001;
        if(value>.001)layer.volume=Math.min(1.5,Math.max(0,value));
      }
      this.render();
    }
    change(id,field,value){
      const layer=this.layers.find(l=>l.id===id);if(!layer)return;
      if(field==='remove')this.layers=this.layers.filter(l=>l.id!==id);
      if(field==='mute')layer.muted=!layer.muted;
      if(field==='volume')layer.volume=Math.max(0,Math.min(1.5,Number(value)/100));
      if(field==='voice')layer.voice=soundFor(layer.kind,value)?.id||defaultVoice(layer.kind);
      this.save();this.onChange();this.render();
    }
    clear(){this.stopPlayback();this.layers=[];this.recording=null;
      try{this.host.localStorage.removeItem(STORAGE);}catch{}
      this.render();this.onChange();}
    render(busy=false){
      this.recordBtn.textContent=this.recording?'■ STOP & KEEP LAYER':'● RECORD INSTRUMENT LAYER';
      this.recordBtn.classList.toggle('is-stop',!!this.recording);
      this.recordBtn.disabled=(busy&&!this.recording)||(!this.recording&&this.layers.length>=MAX_LAYERS);
      this.kind.disabled=busy||!!this.recording;
      this.voiceSelect.disabled=busy||!!this.recording;
      this.loopVoiceSelect.disabled=busy;
      this.previewLoopButton.disabled=busy;
      this.previewRhythmButton.disabled=busy;
      this.presetSelect.disabled=busy;
      this.barsSelect.disabled=busy;
      this.addLoopButton.disabled=busy||this.layers.length>=MAX_LAYERS;
      this.addRhythmButton.disabled=busy||this.layers.length>=MAX_LAYERS;
      for(const step of this.steps)step.disabled=busy;
      this.counter.textContent=this.layers.length+' / '+MAX_LAYERS+' layers';
      this.lanes.replaceChildren();
      if(!this.layers.length){
        const p=this.host.document.createElement('p');p.className='instrument-empty';
        p.textContent='No layers yet. Pick a sound, record a phrase, play the keys.';
        this.lanes.append(p);
      }
      for(const layer of this.layers){
        const row=this.host.document.createElement('div');row.className='instrument-layer';
        const title=this.host.document.createElement('span');title.className='instrument-layer-title';
        title.textContent=(layer.name||layer.kind.toUpperCase())+' · '+layer.events.length+
          ' hits / '+layer.seconds.toFixed(1)+'s'+(layer.bpm?' · '+layer.bpm+' BPM':'');
        const mute=this.host.document.createElement('button');mute.type='button';
        mute.textContent=layer.muted?'Unmute':'Mute';mute.disabled=busy;
        mute.setAttribute('aria-label',(layer.muted?'Unmute ':'Mute ')+layer.kind+' layer');
        mute.addEventListener('click',()=>this.change(layer.id,'mute'));
        const level=this.host.document.createElement('input');level.type='range';
        level.min='0';level.max='150';level.value=String(Math.round(layer.volume*100));
        level.setAttribute('aria-label',layer.kind+' layer volume');level.disabled=busy;
        level.addEventListener('change',()=>this.change(layer.id,'volume',level.value));
        const remove=this.host.document.createElement('button');remove.type='button';
        remove.textContent='×';remove.className='instrument-remove';remove.disabled=busy;
        remove.setAttribute('aria-label','Remove '+layer.kind+' layer');
        remove.addEventListener('click',()=>this.change(layer.id,'remove'));
        const voice=this.host.document.createElement('select');
        voice.className='instrument-layer-voice';
        voice.setAttribute('aria-label','Sound for '+(layer.name||layer.kind)+' layer');
        voice.disabled=busy;
        for(const option of SOUNDS[layer.kind]||[]){
          const node=this.host.document.createElement('option');
          node.value=option.id;node.textContent=option.label;voice.append(node);
        }
        voice.value=layer.voice;
        voice.addEventListener('change',()=>this.change(layer.id,'voice',voice.value));
        const controls=this.host.document.createElement('div');
        controls.className='instrument-layer-controls';
        controls.append(voice,mute,level,remove);
        row.append(title,controls);this.lanes.append(row);
      }
    }
  }
  return {Rack,KINDS,OFFSETS,KEYBOARD,SOUNDS,defaultVoice,soundFor,frequency,pitch,normalizeLayer,eventsInWindow,playNote};
});

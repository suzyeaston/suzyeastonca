/* MOSAIC / SUZY//AI crossover: deterministic Web Audio note layers.
 * Adapted from suzyeaston/suzy-ai Film Club browser synth and TOASTER
 * independent looping concepts. No Magenta model or network service.
 */
(function(root,factory){
  const api=factory();
  if(typeof module==='object'&&module.exports)module.exports=api;
  if(root)root.MosaicInstruments=api;
})(typeof globalThis!=='undefined'?globalThis:this,function(){
  'use strict';
  const KINDS=['keys','bass','pad'];
  const OFFSETS=[0,2,4,5,7,9,11,12];
  const KEYBOARD='asdfghjk';
  const MAX_LAYERS=12, MAX_EVENTS=512, STORAGE='mosaic-instrument-layers-v1';
  const frequency=midi=>440*Math.pow(2,(midi-69)/12);
  const pitch=(kind,pad)=>(kind==='bass'?48:60)+OFFSETS[pad];
  function normalizeLayer(raw){
    if(!raw||!KINDS.includes(raw.kind))return null;
    const seconds=Number(raw.seconds);
    if(!Number.isFinite(seconds)||seconds<.2||seconds>1800)return null;
    const events=(Array.isArray(raw.events)?raw.events:[]).slice(0,MAX_EVENTS).map(e=>{
      const t=Number(e.t),note=Number(e.note),dur=Number(e.dur);
      if(!Number.isFinite(t)||t<0||t>=seconds||!Number.isInteger(note)||note<24||note>108||!Number.isFinite(dur)||dur<=0||dur>4)return null;
      return {t,note,dur};
    }).filter(Boolean);
    return events.length?{id:String(raw.id||'layer').slice(0,64),kind:raw.kind,
      seconds,events,muted:!!raw.muted,volume:Math.max(0,Math.min(1.5,Number(raw.volume??.8)||0))}:null;
  }
  function eventsInWindow(layer,from,to){
    if(!layer||layer.muted||!(to>from)||!layer.seconds)return [];
    const out=[];
    for(const e of layer.events){
      let n=Math.max(0,Math.ceil((from-e.t-.000001)/layer.seconds));
      for(;n<=Math.ceil(to/layer.seconds);n++){
        const t=e.t+n*layer.seconds;
        if(t>=to)break;
        if(t>=from-.000001)out.push({...e,t,kind:layer.kind,volume:layer.volume});
      }
    }
    return out.sort((a,b)=>a.t-b.t).slice(0,256);
  }
  function playNote(ctx,bus,kind,note,at,length,volume){
    const start=Math.max(ctx.currentTime+.006,at);
    const dur=Math.max(.08,Math.min(3,length));
    const gain=ctx.createGain();
    const amp=Math.min(.23,Math.max(0,volume)*.18);
    const attack=kind==='pad'?.12:.012;
    gain.gain.setValueAtTime(0,start);
    gain.gain.linearRampToValueAtTime(amp,start+Math.min(attack,dur*.35));
    gain.gain.setValueAtTime(amp,Math.max(start+Math.min(attack,dur*.35),start+dur-.08));
    gain.gain.linearRampToValueAtTime(0,start+dur+.08);
    gain.connect(bus);
    const f=frequency(note),voices=[];
    const oscillator=(type,ratio,detune=0)=>{
      const o=ctx.createOscillator();o.type=type;
      o.frequency.setValueAtTime(f*ratio,start);o.detune.value=detune;
      o.connect(gain);o.start(start);o.stop(start+dur+.1);voices.push(o);
    };
    if(kind==='bass'){oscillator('sine',1);oscillator('triangle',.5);}
    else if(kind==='pad'){oscillator('sawtooth',1,-5);oscillator('triangle',1,5);}
    else {oscillator('triangle',1);oscillator('sine',2);}
    return voices;
  }
  class Rack {
    constructor({host,ensureAudioContext,onRecord,onChange,onStatus}){
      this.host=host;this.ensureAudioContext=ensureAudioContext;
      this.onRecord=onRecord;this.onChange=onChange;this.onStatus=onStatus;
      const el=id=>host.document.getElementById(id);
      this.kind=el('instrument-kind');this.recordBtn=el('instrument-record');
      this.pads=el('instrument-pads');this.lanes=el('instrument-layers');
      this.counter=el('instrument-count');
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
        if(['input','textarea','select'].includes(tag)||e.target?.isContentEditable)return;
        const index=KEYBOARD.indexOf(e.key.toLowerCase());
        if(index>=0){e.preventDefault();this.hit(index);}
      });
      this.render();
    }
    get hasLayers(){return this.layers.length>0;}
    get duration(){return Math.max(0,...this.layers.map(l=>l.seconds));}
    get isRecording(){return !!this.recording;}
    get elapsed(){return this.recording?(performance.now()-this.recording.startedAt)/1000:0;}
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
    begin(){if(this.recording||this.layers.length>=MAX_LAYERS)return false;
      this.recording={kind:this.kind.value,startedAt:performance.now(),events:[]};
      this.render(true);return true;}
    finish(){
      const r=this.recording;if(!r)return null;
      this.recording=null;
      const tail=Math.max(0,...r.events.map(e=>e.t+e.dur));
      const layer=normalizeLayer({id:String(Date.now())+'-'+Math.random().toString(36).slice(2,8),
        kind:r.kind,seconds:Math.max(.25,(performance.now()-r.startedAt)/1000,tail),
        events:r.events,muted:false,volume:.8});
      if(layer){this.layers.push(layer);this.save();this.onChange();}
      this.render();return layer;
    }
    async hit(pad){
      try{
        const ctx=await this.ensureAudioContext(),kind=this.recording?.kind||this.kind.value;
        const note=pitch(kind,pad),dur=kind==='pad'?1.25:kind==='bass'?.47:.6;
        playNote(ctx,this.ensureBus(ctx),kind,note,ctx.currentTime+.01,dur,.9);
        if(this.recording){
          if(this.recording.events.length<MAX_EVENTS){
            this.recording.events.push({t:Math.max(0,(performance.now()-this.recording.startedAt)/1000),note,dur});
          }else this.onStatus('512-note limit reached. Stop and keep this layer.',true);
        }
      }catch(e){this.onStatus('Instrument unavailable: '+(e.message||e),true);}
    }
    startPlayback(){this.stopPlayback();this.playing=true;this.nextWindow=0;this.tick(0);}
    tick(elapsed){
      if(!this.playing||!this.ctx)return;
      const to=Math.max(0,elapsed)+.20;
      if(to<=this.nextWindow)return;
      const base=this.ctx.currentTime-Math.max(0,elapsed);
      for(const layer of this.layers)for(const ev of eventsInWindow(layer,this.nextWindow,to)){
        const voices=playNote(this.ctx,this.ensureBus(this.ctx),ev.kind,ev.note,base+ev.t,ev.dur,ev.volume);
        for(const voice of voices){this.voices.add(voice);voice.onended=()=>this.voices.delete(voice);}
      }
      this.nextWindow=to;
    }
    stopPlayback(){this.playing=false;this.nextWindow=0;
      for(const voice of this.voices)try{voice.stop();}catch{}this.voices.clear();}
    change(id,field,value){
      const layer=this.layers.find(l=>l.id===id);if(!layer)return;
      if(field==='remove')this.layers=this.layers.filter(l=>l.id!==id);
      if(field==='mute')layer.muted=!layer.muted;
      if(field==='volume')layer.volume=Math.max(0,Math.min(1.5,Number(value)/100));
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
        title.textContent=layer.kind.toUpperCase()+' · '+layer.events.length+' notes / '+layer.seconds.toFixed(1)+'s';
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
        row.append(title,mute,level,remove);this.lanes.append(row);
      }
    }
  }
  return {Rack,KINDS,OFFSETS,KEYBOARD,frequency,pitch,normalizeLayer,eventsInWindow,playNote};
});

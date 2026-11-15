/* MOSAIC performance scenes: the same recorded loops, different saved mixes.
 * Inspired by SUZY//TOAST's user-controlled arrangement cues. No AI.
 */
(function(root,factory){
  const api=factory();
  if(typeof module==='object'&&module.exports)module.exports=api;
  if(root)root.MosaicScenes=api;
})(typeof globalThis!=='undefined'?globalThis:this,function(){
  'use strict';
  const STORAGE='mosaic-performance-scenes-v1';
  const IDS=Object.freeze(['verse','chorus','bridge']);
  const LABELS=Object.freeze({verse:'VERSE',chorus:'CHORUS',bridge:'BRIDGE'});
  function gain(value){const n=Number(value);return Number.isFinite(n)?Math.min(1.5,Math.max(0,n)):0;}
  function normalizeMix(raw){
    const mix={};
    if(raw&&typeof raw==='object'&&!Array.isArray(raw)){
      for(const [key,value] of Object.entries(raw).slice(0,80)){
        if(/^(video:[0-8]|audio:[a-zA-Z0-9_-]{1,64}|instrument:[a-zA-Z0-9_-]{1,64})$/.test(key))
          mix[key]=gain(value);
      }
    }
    return mix;
  }
  function nextLaunch(elapsed,bpm,lead=.25){
    const tempo=Number(bpm),bar=240/(Number.isFinite(tempo)&&tempo>=60&&tempo<=200?tempo:120);
    return Math.max(bar,Math.ceil((Math.max(0,elapsed)+Math.max(0,lead))/bar)*bar);
  }
  class Deck{
    constructor(host,onChange){
      this.host=host;this.onChange=onChange||(()=>{});
      this.active='verse';this.pending=null;
      this.scenes={verse:{...{}},chorus:{...{}},bridge:{...{}}};
      try{
        const saved=JSON.parse(host.localStorage.getItem(STORAGE)||'{}');
        if(IDS.includes(saved.active))this.active=saved.active;
        for(const id of IDS)this.scenes[id]=normalizeMix(saved.scenes?.[id]);
      }catch{/* Private browsing may prevent localStorage */}
    }
    save(){try{this.host.localStorage.setItem(STORAGE,JSON.stringify({
      active:this.active,scenes:this.scenes
    }));}catch{/* Recording remains in memory for this session */}}
    seed(current){
      const base=normalizeMix(current);
      for(const id of IDS)if(!Object.keys(this.scenes[id]).length)this.scenes[id]={...base};
      this.save();this.onChange();
    }
    rememberAdded(key,volume){
      // New layers are deliberately NOT copied into every scene.
      this.scenes[this.active][key]=gain(volume);
      for(const id of IDS)if(id!==this.active&&!(key in this.scenes[id]))this.scenes[id][key]=0;
      this.save();this.onChange();
    }
    capture(current){
      this.scenes[this.active]=normalizeMix(current);
      this.save();this.onChange();return this.active;
    }
    queue(id,elapsed,bpm){
      if(!IDS.includes(id))return null;
      if(id===this.active){this.pending=null;this.onChange();return null;}
      this.pending={id,at:nextLaunch(elapsed,bpm)};
      this.onChange();return this.pending;
    }
    tick(elapsed,apply){
      if(!this.pending||elapsed<this.pending.at-.26)return false;
      const id=this.pending.id;this.pending=null;this.active=id;
      this.save();
      apply(id,this.scenes[id]);
      this.onChange();return true;
    }
    selectNow(id,apply){
      if(!IDS.includes(id))return false;
      this.pending=null;this.active=id;this.save();
      apply(id,this.scenes[id]);this.onChange();return true;
    }
    clear(){
      this.pending=null;this.active='verse';
      this.scenes={verse:{},chorus:{},bridge:{}};
      try{this.host.localStorage.removeItem(STORAGE);}catch{}
      this.onChange();
    }
  }
  return {Deck,IDS,LABELS,gain,normalizeMix,nextLaunch,STORAGE};
});

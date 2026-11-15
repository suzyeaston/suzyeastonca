/* MOSAIC sound bank: original Web Audio patches with no external samples.
 * Deterministic percussion ideas from SUZY//AI Film Club browser audio.
 */
(function(root,factory){
  const api=factory();
  if(typeof module==='object'&&module.exports)module.exports=api;
  if(root)root.MosaicSoundbank=api;
})(typeof globalThis!=='undefined'?globalThis:this,function(){
  'use strict';
  const SOUNDS=Object.freeze({
    keys:Object.freeze([
      Object.freeze({id:'keys-electric',label:'Electric keys · mellow',description:'Rounded, piano-like decay'}),
      Object.freeze({id:'keys-glass',label:'Glass keys · bright',description:'Bell-like upper harmonics'})
    ]),
    bass:Object.freeze([
      Object.freeze({id:'bass-round',label:'Round bass · warm',description:'Soft, plucked bass'}),
      Object.freeze({id:'bass-sub',label:'Sub bass · deep',description:'Gentle low-frequency pulse'})
    ]),
    pad:Object.freeze([
      Object.freeze({id:'pad-silk',label:'Silk pad · warm',description:'Soft, filtered chord cloud'}),
      Object.freeze({id:'pad-air',label:'Air pad · light',description:'Light organ-like harmony'})
    ]),
    drums:Object.freeze([
      Object.freeze({id:'drums-soft',label:'Soft kit · warm',description:'Rounded kick and restrained hats'}),
      Object.freeze({id:'drums-crisp',label:'Crisp kit · punchy',description:'Tighter kick and brighter snare'})
    ])
  });
  const defaultVoice=kind=>SOUNDS[kind]?.[0]?.id||'keys-electric';
  const soundFor=(kind,id)=>SOUNDS[kind]?.find(s=>s.id===id)||SOUNDS[kind]?.[0]||null;
  const frequency=midi=>440*Math.pow(2,(midi-69)/12);
  const noiseCache=new WeakMap();
  function noise(ctx){
    let b=noiseCache.get(ctx);
    if(b)return b;
    b=ctx.createBuffer(1,Math.ceil(ctx.sampleRate*.35),ctx.sampleRate);
    const ch=b.getChannelData(0);let state=0xace1;
    for(let i=0;i<ch.length;i++){
      state=(Math.imul(state,1664525)+1013904223)>>>0;
      ch[i]=(state/4294967296)*2-1;
    }
    noiseCache.set(ctx,b);return b;
  }
  function drum(ctx,bus,note,at,volume,voice){
    const crisp=voice==='drums-crisp';
    const start=Math.max(ctx.currentTime+.005,at);
    const gain=ctx.createGain();gain.connect(bus);
    const amp=Math.max(.0001,Math.min(.29,volume*(crisp?.23:.16)));
    const env=gain.gain;
    env.setValueAtTime(.0001,start);
    const source=[];
    if(note===36){
      const osc=ctx.createOscillator();osc.type='sine';
      osc.frequency.setValueAtTime(crisp?148:113,start);
      osc.frequency.exponentialRampToValueAtTime(crisp?49:43,start+.16);
      env.exponentialRampToValueAtTime(amp,start+.007);
      env.exponentialRampToValueAtTime(.0001,start+.23);
      osc.connect(gain);osc.start(start);osc.stop(start+.25);source.push(osc);
    } else {
      const src=ctx.createBufferSource();src.buffer=noise(ctx);
      const high=ctx.createBiquadFilter();high.type='highpass';
      high.frequency.value=note===42?(crisp?7100:4600):(crisp?1750:950);
      env.exponentialRampToValueAtTime(amp*(note===42?.43:1),start+.003);
      env.exponentialRampToValueAtTime(.0001,start+(note===42?.07:.16));
      src.connect(high);high.connect(gain);
      src.start(start);src.stop(start+(note===42?.08:.18));source.push(src);
    }
    return source;
  }
  function playNote(ctx,bus,kind,note,at,length,volume,voice){
    const patch=soundFor(kind,voice)?.id||defaultVoice(kind);
    if(kind==='drums')return drum(ctx,bus,note,at,volume,patch);
    if(!SOUNDS[kind])return [];
    const start=Math.max(ctx.currentTime+.005,at);
    const duration=Math.max(.09,Math.min(3,length));
    const isPad=kind==='pad',isBass=kind==='bass';
    const filter=ctx.createBiquadFilter();filter.type='lowpass';filter.Q.value=.45;
    const cutoff=patch==='pad-silk'?780:patch==='pad-air'?1550:
      patch==='bass-sub'?350:patch==='bass-round'?650:
      patch==='keys-glass'?5300:2600;
    filter.frequency.setValueAtTime(cutoff,start);
    const envelope=ctx.createGain();
    const loudness=Math.max(.0001,Math.min(.15,Math.max(0,volume)*(isPad?.078:isBass?.13:.11)));
    const attack=isPad?(patch==='pad-silk'?.25:.16):.009;
    const release=isPad?(patch==='pad-silk'?.82:.56):isBass?.24:.3;
    const peak=start+Math.min(attack,duration*.5);
    envelope.gain.setValueAtTime(.0001,start);
    envelope.gain.linearRampToValueAtTime(loudness,peak);
    if(isPad){
      envelope.gain.setValueAtTime(loudness,start+duration);
    } else {
      envelope.gain.exponentialRampToValueAtTime(
        Math.max(.0002,loudness*(isBass?.4:.19)),
        start+duration
      );
    }
    envelope.gain.exponentialRampToValueAtTime(.0001,start+duration+release);
    envelope.connect(filter);filter.connect(bus);
    const f=frequency(note),voices=[];
    const osc=(type,multiplier,level=1,detune=0)=>{
      const o=ctx.createOscillator(),g=ctx.createGain();
      o.type=type;o.frequency.setValueAtTime(f*multiplier,start);
      o.detune.value=detune;g.gain.value=level;
      o.connect(g);g.connect(envelope);o.start(start);
      o.stop(start+duration+release+.05);voices.push(o);
    };
    if(patch==='bass-round'){osc('sine',1,.9);osc('triangle',.5,.25);}
    else if(patch==='bass-sub'){osc('sine',.5,.88);osc('sine',1,.22);}
    else if(patch==='pad-silk'){osc('sine',1,.7,-2);osc('triangle',1,.22,2);osc('sine',2,.08);}
    else if(patch==='pad-air'){osc('triangle',1,.4,-2);osc('sine',1,.53,2);osc('sine',2,.12);}
    else if(patch==='keys-glass'){osc('sine',1,.78);osc('sine',2.01,.25);osc('triangle',3,.12);}
    else {osc('sine',1,.85);osc('triangle',1,.25);osc('sine',2,.14);}
    return voices;
  }
  return Object.freeze({SOUNDS,defaultVoice,soundFor,frequency,playNote});
});

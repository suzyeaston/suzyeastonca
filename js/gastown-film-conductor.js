/*
 * SOFT RESET / SUZY//AI
 * In-simulator 60s film conductor. The musical model runs in the artist's
 * local Magenta RealTime host, NOT in this browser. A printed music stem can
 * be imported for local playback and Web Audio FFT-driven scene modulation.
 * No audio files or likeness assets are uploaded to the website.
 */
(function (window) {
  'use strict';
  const PRESETS=Object.freeze({
    'harbour-pulse':Object.freeze({
      title:'Harbour pulse',prompt:'Sparse tidal bass pulse, softened machine rhythm, glassy plucked harmonics, space between notes, evolving slowly, original ambient electronic score.'
    }),
    'rail-memory':Object.freeze({
      title:'Rail memory',prompt:'Interlocking metallic ostinati with distant double bass resonance, granular friction and asymmetrical percussion, accelerating but never using a recognizable existing melody.'
    }),
    'container-choir':Object.freeze({
      title:'Container choir',prompt:'Industrial percussive echoes transforming into layered vowel-like harmonies, tense chromatic motion, long synthetic reverberant tails, cinematic scale.'
    }),
    'human-signal':Object.freeze({
      title:'Human signal',prompt:'Leave a quiet pocket around a human female voice or clean guitar note. Respond gently with restrained bowed tones and a single luminous harmonic overtone.'
    }),
    'terminal-bloom':Object.freeze({
      title:'Terminal bloom',prompt:'Evolving bioluminescent synth choir with warm suspended harmonies, low strings, delicate motion and a sense of intelligent systems cooperating rather than competing.'
    }),
    'seabus-lullaby':Object.freeze({
      title:'SeaBus lullaby',prompt:'Soft marine mechanical ambience, bell-like overtones, quiet analog synth waves, slow 6/8 pulse, tenderness without sentimentality, unresolved spacious closing harmony.'
    })
  });
  const clamp=(n,min,max)=>Math.max(min,Math.min(max,Number.isFinite(n)?n:min));
  function formatTime(seconds) {
    const n=Math.floor(clamp(seconds,0,60));
    return '00:'+String(n).padStart(2,'0');
  }
  function mount(app,THREE,worldGroup,world) {
    const panel=app&&app.querySelector('[data-film-panel]');
    const toggle=app&&app.querySelector('[data-action="film-panel-toggle"]');
    if(!panel||!toggle||!THREE||!worldGroup||!window.GastownFilmCues)return null;
    const opts=panel.querySelector('[data-film-preset]');
    const prompt=panel.querySelector('[data-film-prompt]');
    const promptCopy=panel.querySelector('[data-film-copy]');
    const clip=panel.querySelector('[data-film-audio]');
    const audio=panel.querySelector('[data-film-player]');
    const ruler=panel.querySelector('[data-film-timeline]');
    const timeLabel=panel.querySelector('[data-film-time]');
    const phase=panel.querySelector('[data-film-phase]');
    const morph=panel.querySelector('[data-film-morph]');
    const status=panel.querySelector('[data-film-status]');
    const close=panel.querySelector('[data-film-close]');
    const visuals=new THREE.Group();
    visuals.name='SOFT RESET original procedural sound-reactive world';
    visuals.visible=false;
    worldGroup.add(visuals);
    const spires=[];
    const lot=world&&world.parkingLot&&world.parkingLot.center||{x:44,z:54};
    for(let i=0;i<11;i++){
      const angle=i*Math.PI*2/11;
      const radius=18+(i%3)*3.6;
      const mat=new THREE.MeshBasicMaterial({
        color:0x8ec5dc,transparent:true,opacity:0.84,depthWrite:false,fog:false
      });
      const mesh=new THREE.Mesh(new THREE.BoxGeometry(0.42+(i%3)*0.1,1,0.42),mat);
      mesh.name='AI score-responsive harbour light structure '+(i+1);
      mesh.position.set(lot.x+Math.cos(angle)*radius,0.6,lot.z+Math.sin(angle)*radius);
      mesh.userData.baseX=mesh.position.x;
      mesh.userData.baseZ=mesh.position.z;
      mesh.userData.phase=i*0.9;
      mesh.userData.size=i%3;
      visuals.add(mesh);
      spires.push(mesh);
    }
    let objectUrl=null;
    let audioContext=null,analyser=null,frequency=null;
    let previousState=null;
    let pausedTime=0;
    const setStatus=s=>{if(status)status.textContent=s;};
    function updatePreset() {
      const selected=PRESETS[opts.value]||PRESETS['harbour-pulse'];
      prompt.textContent=selected.prompt;
      return selected.prompt;
    }
    function setOpen(open){
      panel.hidden=!open;
      toggle.setAttribute('aria-expanded',open?'true':'false');
      if(open&&window.document.pointerLockElement){
        window.document.exitPointerLock&&window.document.exitPointerLock();
      }
    }
    toggle.addEventListener('click',()=>setOpen(panel.hidden));
    if(close)close.addEventListener('click',()=>setOpen(false));
    if(opts)opts.addEventListener('change',updatePreset);
    updatePreset();
    if(promptCopy)promptCopy.addEventListener('click',()=>{
      const text=updatePreset();
      if(window.navigator&&window.navigator.clipboard&&window.navigator.clipboard.writeText){
        window.navigator.clipboard.writeText(text)
          .then(()=>setStatus('Original Magenta prompt copied.'))
          .catch(()=>setStatus('Clipboard unavailable. Select and copy the prompt above.'));
      } else {
        setStatus('Select and copy the prompt above.');
      }
    });
    function attachAnalyser() {
      if(analyser)return;
      const Ctx=window.AudioContext||window.webkitAudioContext;
      if(!Ctx)return;
      try{
        audioContext=new Ctx();
        const source=audioContext.createMediaElementSource(audio);
        analyser=audioContext.createAnalyser();
        analyser.fftSize=512;
        analyser.smoothingTimeConstant=0.79;
        frequency=new Uint8Array(analyser.frequencyBinCount);
        source.connect(analyser);
        analyser.connect(audioContext.destination);
      }catch(err){
        setStatus('Audio playback available; spectrum capture unavailable.');
      }
    }
    if(clip)clip.addEventListener('change',()=>{
      const file=clip.files&&clip.files[0];
      if(!file)return;
      audio.pause();
      if(objectUrl)window.URL.revokeObjectURL(objectUrl);
      objectUrl=window.URL.createObjectURL(file);
      audio.src=objectUrl;
      audio.load();
      pausedTime=0;
      ruler.value='0';
      attachAnalyser();
      setStatus('Local audio loaded: '+file.name+'. It never leaves this browser.');
    });
    audio.addEventListener('play',()=>{
      attachAnalyser();
      if(audioContext&&audioContext.state==='suspended'){
        audioContext.resume().catch(()=>setStatus('Browser audio needs a user gesture.'));
      }
      if(!morph.checked){morph.checked=true;visuals.visible=true;}
    });
    audio.addEventListener('pause',()=>{if(audio.src)pausedTime=clamp(audio.currentTime,0,60);});
    audio.addEventListener('ended',()=>{
      pausedTime=Math.min(60,audio.duration||60);
      audio.pause();
      setStatus('Clip finished. Scrub for still-frame capture.');
    });
    ruler.addEventListener('input',()=>{
      pausedTime=clamp(Number(ruler.value),0,60);
      if(audio.src&&audio.readyState>=1){
        try{audio.currentTime=Math.min(pausedTime,Math.max(0,(audio.duration||60)-0.05));}catch(e){}
      }
      timeLabel.textContent=formatTime(pausedTime);
    });
    morph.addEventListener('change',()=>{
      visuals.visible=morph.checked;
      setStatus(morph.checked?'World score enabled. Scrub or play your Magenta recording.':'Original world restored.');
    });
    function levels(){
      if(!analyser||!frequency||audio.paused)return{bass:0,mids:0,highs:0};
      analyser.getByteFrequencyData(frequency);
      function mean(a,b){
        let sum=0;
        for(let i=a;i<b&&i<frequency.length;i++)sum+=frequency[i];
        return sum/Math.max(1,Math.min(b,frequency.length)-a)/255;
      }
      return{bass:mean(1,10),mids:mean(10,65),highs:mean(65,160)};
    }
    function renderState(state) {
      previousState=state;
      visuals.visible=!!morph.checked;
      if(!visuals.visible)return;
      const energy=state.visualPulse;
      const war=state.warningGlow;
      const bloom=state.bioluminescence;
      spires.forEach((part,index)=>{
        const p=part.userData.phase;
        const h=0.22+(state.mechanicalGrowth*7.5)+(bloom*14)
          +(energy*3.4*(0.4+0.6*Math.abs(Math.sin(state.seconds*0.9+p))));
        part.scale.y=h;
        part.position.y=h*0.5+0.2;
        part.rotation.y=state.seconds*0.01*(1+index%3)+bloom*0.36;
        part.material.color.setHex(war>0.45?0xe86a65:bloom>0.18?0x80e3d1:0xb4a2df);
        part.material.opacity=0.36+0.46*clamp(state.distortion+bloom,0,1);
      });
    }
    function tick() {
      const useAudio=!!audio.src&&!audio.paused;
      const seconds=useAudio?clamp(audio.currentTime,0,60):pausedTime;
      const state=window.GastownFilmCues.sample(seconds,levels());
      renderState(state);
      if(visuals.visible||!panel.hidden){
        timeLabel.textContent=formatTime(seconds);
        phase.textContent=window.GastownFilmCues.cueAt(seconds).title;
        ruler.value=String(seconds);
      }
      return state;
    }
    const dispose=()=>{
      audio.pause();
      if(objectUrl)window.URL.revokeObjectURL(objectUrl);
      if(audioContext)audioContext.close().catch(()=>{});
      worldGroup.remove(visuals);
    };
    window.addEventListener('pagehide',dispose,{once:true});
    return Object.freeze({tick,dispose,PRESETS,visuals,getState:()=>previousState,formatTime});
  }
  window.GastownFilmConductor=Object.freeze({PRESETS,mount,formatTime});
})(window);

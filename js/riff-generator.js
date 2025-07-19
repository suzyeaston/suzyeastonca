// Riff Generator Vue App using Tone.js

const MODE_INTERVALS = {
  major:     [0,2,4,5,7,9,11],
  minor:     [0,2,3,5,7,8,10],
  dorian:    [0,2,3,5,7,9,10],
  mixolydian:[0,2,4,5,7,9,10]
};
const NOTE_NAMES = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];
const PROGRESSIONS = [
  [1,4,5,1],
  [1,6,4,5],
  [2,5,1,1],
  [1,5,6,4]
];
const TIPS = [
  'Leave space for the groove.',
  'Try muting the high strings for punch.',
  'Let the melody breathe.',
  'Focus on emotion over perfection.',
  'Layer subtle textures for depth.'
];

function noteName(semi, oct=4){
  const name = NOTE_NAMES[((semi%12)+12)%12];
  return name+oct;
}

function chordNotes(rootName, mode, degree){
  const base = NOTE_NAMES.indexOf(rootName);
  const scale = MODE_INTERVALS[mode].map(s=> (s+base)%12);
  const idx = (degree-1)%7;
  const semis = [scale[idx], scale[(idx+2)%7], scale[(idx+4)%7]];
  return [noteName(semis[0],3), noteName(semis[1],4), noteName(semis[2],4)];
}

function generateMelody(scale, chord, steps){
  const notes = [];
  for(let i=0;i<steps;i++){
    const source = Math.random()<0.6? chord : scale;
    const semi = source[Math.floor(Math.random()*source.length)];
    notes.push(noteName(semi,5));
  }
  return notes;
}

function scaleSemis(rootName, mode){
  const base = NOTE_NAMES.indexOf(rootName);
  return MODE_INTERVALS[mode].map(s=> (s+base)%12);
}

const SYNTH_SETTINGS = {
  electric_guitar_clean: { oscillator: { type: 'square' } },
  acoustic_grand_piano:  { oscillator: { type: 'triangle' } },
  electric_bass_finger:  { oscillator: { type: 'sawtooth' } },
  distortion_guitar:     { oscillator: { type: 'sawtooth' } }
};

const app = Vue.createApp({
  template: `
    <div>
      <div class="controls">
        <label>Mode
          <select v-model="mode">
            <option value="major">Major</option>
            <option value="minor">Minor</option>
            <option value="dorian">Dorian</option>
            <option value="mixolydian">Mixolydian</option>
          </select>
        </label>
        <label>Tempo
          <input type="number" v-model.number="tempo" min="60" max="200" />
        </label>
        <label>Instrument
          <select v-model="instrument">
            <option value="electric_guitar_clean">Electric Guitar</option>
            <option value="acoustic_grand_piano">Piano</option>
            <option value="electric_bass_finger">Bass</option>
            <option value="distortion_guitar">Distortion Guitar</option>
          </select>
        </label>
        <button @click="generate" :disabled="isPlaying">Generate Riff</button>
      </div>
      <div class="producer-tip" v-if="tip">
        <strong>Producer's Tip:</strong> {{ tip }}
      </div>
      <textarea v-model="progressionText" rows="2"></textarea>
    </div>
  `,
  data(){
    return {
      tempo: 120,
      mode: 'major',
      instrument: 'electric_guitar_clean',
      progressionText: '',
      tip: TIPS[Math.floor(Math.random()*TIPS.length)],
      isPlaying: false
    };
  },
  methods:{
    async generate(){
      const root = NOTE_NAMES[Math.floor(Math.random()*NOTE_NAMES.length)];
      const pattern = PROGRESSIONS[Math.floor(Math.random()*PROGRESSIONS.length)];
      const chords = pattern.map(d=> chordNotes(root,this.mode,d));
      const scale = scaleSemis(root,this.mode);
      const melody = chords.flatMap(ch => generateMelody(scale,ch.map(n=>NOTE_NAMES.indexOf(n.slice(0,-1))),2));
      this.progressionText = chords.map(c=>c.map(n=>n.slice(0,-1)).join('-')).join(' | ');
      await this.play(chords, melody);
      this.tip = TIPS[Math.floor(Math.random()*TIPS.length)];
    },
    async play(chords, melody){
      this.isPlaying = true;
      await Tone.start();
      const synth = new Tone.PolySynth(Tone.Synth, SYNTH_SETTINGS[this.instrument]);
      const reverb = new Tone.Reverb({decay:2,wet:0.3}).toDestination();
      const delay  = new Tone.FeedbackDelay({delayTime:'8n',feedback:0.2,wet:0.2});
      synth.chain(delay,reverb);
      const recorder = new Tone.Recorder();
      synth.connect(recorder);
      recorder.start();
      const noteDur = 60/this.tempo;
      let t = Tone.now()+0.3;
      chords.forEach(ch => {
        ch.forEach(n=> synth.triggerAttackRelease(n,noteDur,t));
        t += noteDur;
      });
      melody.forEach((n,i)=>{
        synth.triggerAttackRelease(n,noteDur/2, Tone.now()+0.3+i*(noteDur/2));
      });
      setTimeout(async()=>{
        const rec = await recorder.stop();
        const url = URL.createObjectURL(rec);
        const audio = document.getElementById('riff-audio');
        audio.src = url;
        audio.style.display='block';
        this.isPlaying=false;
      }, (chords.length+1)*noteDur*1000);
    }
  }
});

app.mount('#riff-app');

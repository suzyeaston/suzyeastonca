// Riff Generator Vue App using Tone.js

const MODE_INTERVALS = {
  major:      [0, 2, 4, 5, 7, 9, 11],
  minor:      [0, 2, 3, 5, 7, 8, 10],
  dorian:     [0, 2, 3, 5, 7, 9, 10],
  mixolydian: [0, 2, 4, 5, 7, 9, 10]
};
const NOTE_NAMES = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];
const MODE_PROGRESSIONS = {
  major:      [1, 5, 6, 4],
  mixolydian: [1, 4, 5, 1],
  minor:      [1, 6, 7, 5],
  dorian:     [1, 4, 5, 1]
};
const PATTERN_LIBRARY = [
  [0, 1, 2, 3, 1, 2, 0, 1],
  [0, 2, 1, 2, 3, 2, 1, 0],
  [0, 1, 0, 2, 3, 2, 1, 1],
  [0, 1, 2, 1, 3, 2, 1, 0]
];
const TIPS = [
  'Leave space for the groove.',
  'Try muting the high strings for punch.',
  'Let the melody breathe.',
  'Focus on emotion over perfection.',
  'Layer subtle textures for depth.'
];

const INSTRUMENT_PROFILES = {
  piano: {
    library: 'piano',
    chordOctaves: [4, 4, 5],
    reverb: { decay: 2.8, wet: 0.35 },
    filter: null,
    distortion: null,
    chorus: null,
    volume: -3
  },
  bass: {
    library: 'bass-electric',
    chordOctaves: [2, 3, 3],
    reverb: { decay: 1.2, wet: 0.12 },
    filter: { type: 'lowpass', frequency: 600, rolloff: -24 },
    distortion: null,
    chorus: null,
    volume: -2
  },
  guitar: {
    library: 'guitar-electric',
    chordOctaves: [3, 4, 4],
    reverb: { decay: 1.6, wet: 0.25 },
    filter: { type: 'highpass', frequency: 90 },
    distortion: null,
    chorus: { frequency: 3, delayTime: 2.5, depth: 0.2 },
    volume: -4
  },
  distortion_guitar: {
    library: 'guitar-electric',
    chordOctaves: [3, 4, 4],
    reverb: { decay: 1.8, wet: 0.28 },
    filter: { type: 'highpass', frequency: 120 },
    distortion: 0.4,
    chorus: null,
    volume: -6
  }
};

const instrumentChains = {};
const masterOutput = new Tone.Gain(1).toDestination();
const recordingDestination = Tone.getContext().createMediaStreamDestination();
masterOutput.connect(recordingDestination);

function noteName(semi, oct = 4) {
  const name = NOTE_NAMES[((semi % 12) + 12) % 12];
  return name + oct;
}

function adjustOctave(note, shift) {
  const match = note.match(/([A-G]#?)(\d+)/);
  if (!match) return note;
  const base = match[1];
  const octave = parseInt(match[2], 10) + shift;
  return base + octave;
}

function chordSemitones(rootName, mode, degree) {
  const base = NOTE_NAMES.indexOf(rootName);
  const scale = MODE_INTERVALS[mode].map((s) => (s + base) % 12);
  const idx = (degree - 1) % 7;
  return [scale[idx], scale[(idx + 2) % 7], scale[(idx + 4) % 7]];
}

function chordNotes(rootName, mode, degree, octaves) {
  const semis = chordSemitones(rootName, mode, degree);
  return semis.map((semi, i) => noteName(semi, octaves[i] || octaves[octaves.length - 1]));
}

function chordDisplay(semis) {
  return semis.map((s) => NOTE_NAMES[((s % 12) + 12) % 12]).join('–');
}

async function ensureInstrumentChain(key) {
  if (instrumentChains[key]) return instrumentChains[key];
  const profile = INSTRUMENT_PROFILES[key] || INSTRUMENT_PROFILES.piano;
  if (typeof SampleLibrary === 'undefined') {
    throw new Error('SampleLibrary not loaded');
  }
  const sampler = SampleLibrary.load({
    instruments: profile.library,
    baseUrl: 'https://nbrosowsky.github.io/tonejs-instruments/samples/',
    minify: true,
    release: 1
  });
  await Tone.loaded();

  const reverb = new Tone.Reverb(profile.reverb);
  reverb.connect(masterOutput);
  let chainEnd = reverb;

  if (profile.distortion) {
    const dist = new Tone.Distortion(profile.distortion);
    dist.connect(chainEnd);
    chainEnd = dist;
  }

  if (profile.chorus) {
    const chorus = new Tone.Chorus(profile.chorus.frequency, profile.chorus.delayTime, profile.chorus.depth).start();
    chorus.connect(chainEnd);
    chainEnd = chorus;
  }

  if (profile.filter) {
    const filter = new Tone.Filter(profile.filter);
    filter.connect(chainEnd);
    chainEnd = filter;
  }

  sampler.connect(chainEnd);
  sampler.volume.value = profile.volume || 0;

  instrumentChains[key] = { sampler, output: reverb };
  return instrumentChains[key];
}

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
            <option value="guitar">Electric Guitar</option>
            <option value="piano">Piano</option>
            <option value="bass">Bass</option>
            <option value="distortion_guitar">Distortion Guitar</option>
          </select>
        </label>
          <label>Bars
            <select v-model.number="bars">
              <option value="4">4</option>
              <option value="8">8</option>
              <option value="12">12</option>
              <option value="16">16</option>
            </select>
          </label>
          <button @click="generate" :disabled="isPlaying || isRecording">Generate Riff</button>
          <button @click="togglePlay" :disabled="!hasRiff" class="secondary">
            {{ isPlaying ? 'Stop' : 'Play Riff' }}
          </button>
          <button @click="downloadRiff" :disabled="!hasRiff || isRecording" class="secondary">
            Download Riff (WebM audio)
          </button>
        </div>
        <div class="producer-tip" v-if="tip">
          <strong>Producer's Tip:</strong> {{ tip }}
        </div>
        <textarea v-model="progressionText" rows="2" readonly></textarea>
      </div>
    `,
  data() {
    return {
      tempo: 120,
      mode: 'major',
      instrument: 'guitar',
      bars: 8,
      progressionText: '',
      tip: TIPS[Math.floor(Math.random() * TIPS.length)],
      isPlaying: false,
      isRecording: false,
      hasRiff: false,
      activePart: null,
      currentChords: [],
      currentPatterns: []
    };
  },
  computed: {
    instrumentProfile() {
      return INSTRUMENT_PROFILES[this.instrument] || INSTRUMENT_PROFILES.piano;
    }
  },
  methods: {
    randomTip() {
      return TIPS[Math.floor(Math.random() * TIPS.length)];
    },
    getProgression() {
      return MODE_PROGRESSIONS[this.mode] || MODE_PROGRESSIONS.major;
    },
    buildTonePool(chord) {
      const pool = [chord.notes[0], chord.notes[1], chord.notes[2]];
      pool.push(adjustOctave(chord.notes[0], 1));
      return pool;
    },
    generateBarPattern(chord, barIndex) {
      const pool = this.buildTonePool(chord);
      const pattern = PATTERN_LIBRARY[(barIndex + Math.floor(Math.random() * PATTERN_LIBRARY.length)) % PATTERN_LIBRARY.length];
      return pattern.map((step) => pool[step % pool.length]);
    },
    buildChords(root) {
      const pattern = this.getProgression();
      const chords = [];
      for (let i = 0; i < this.bars; i++) {
        const degree = pattern[i % pattern.length];
        const semis = chordSemitones(root, this.mode, degree);
        const notes = chordNotes(root, this.mode, degree, this.instrumentProfile.chordOctaves);
        chords.push({
          display: chordDisplay(semis),
          notes
        });
      }
      return chords;
    },
    async fetchProducerTip(summary) {
      const statusEl = document.getElementById('riff-status');
      if (statusEl) statusEl.textContent = "Producer's Tip: thinking...";
      const config = window.seRiffConfig || {};
      if (!config.tipEndpoint) {
        this.tip = this.randomTip();
        return;
      }
      try {
        const res = await fetch(config.tipEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.nonce || ''
          },
          body: JSON.stringify({
            mode: this.mode,
            tempo: this.tempo,
            instrument: this.instrument,
            summary: summary
          })
        });
        if (!res.ok) throw new Error('Tip request failed');
        const data = await res.json();
        if (data && data.tip) {
          this.tip = data.tip;
          return;
        }
      } catch (err) {
        console.error('Tip fetch failed', err);
      }
      this.tip = this.randomTip();
    },
      async generate() {
        const statusEl = document.getElementById('riff-status');
        if (statusEl) {
        statusEl.textContent = 'Generating...';
        statusEl.style.display = 'block';
      }

      const root = NOTE_NAMES[Math.floor(Math.random() * NOTE_NAMES.length)];
      const chords = this.buildChords(root);
      const patterns = chords.map((ch, idx) => this.generateBarPattern(ch, idx));

      this.progressionText = chords.map((c) => c.display).join(' | ');
      this.tip = "Producer's Tip: thinking...";
      this.currentChords = chords;
      this.currentPatterns = patterns;
      this.hasRiff = true;

      try {
        await this.play(chords, patterns);
        if (statusEl) statusEl.textContent = 'Riff ready!';
      } catch (e) {
        if (statusEl) statusEl.textContent = 'Error generating riff';
        console.error(e);
      }

      this.fetchProducerTip(this.progressionText + ` @ ${this.tempo} BPM (${this.mode})`);
    },
    buildEvents(chords, patterns) {
      const beat = 60 / this.tempo;
      const barDuration = beat * 4;
      const steps = patterns[0] ? patterns[0].length : 8;
      const stepDuration = barDuration / steps;
      const events = [];

      patterns.forEach((pattern, barIndex) => {
        const barStart = barIndex * barDuration;
        const chord = chords[barIndex];
        events.push({ time: barStart, note: chord.notes, duration: barDuration * 0.9 });
        pattern.forEach((note, step) => {
          events.push({ time: barStart + step * stepDuration, note, duration: stepDuration * 0.9 });
        });
      });

      return { events, totalDuration: barDuration * patterns.length };
    },
    async play(chords, patterns) {
      await Tone.start();
      const chain = await ensureInstrumentChain(this.instrument);
      const sampler = chain.sampler;
      this.stopPlayback();

      Tone.Transport.bpm.value = this.tempo;
      const { events, totalDuration } = this.buildEvents(chords, patterns);
      const part = new Tone.Part(function(time, event) {
        sampler.triggerAttackRelease(event.note, event.duration, time);
      }, events).start(0);

      this.activePart = part;
      this.isPlaying = true;
      Tone.Transport.start('+0.1');

      Tone.Transport.scheduleOnce(() => {
        this.stopPlayback();
      }, totalDuration + 0.5);

      const statusEl = document.getElementById('riff-status');
      if (statusEl) statusEl.textContent = 'Playing...';
    },
    stopPlayback() {
      Tone.Transport.stop();
      Tone.Transport.cancel();
      if (this.activePart) {
        this.activePart.dispose();
        this.activePart = null;
      }
      this.isPlaying = false;
    },
    async togglePlay() {
      if (!this.hasRiff) return;
      if (this.isPlaying) {
        this.stopPlayback();
        return;
      }
      if (!this.currentChords.length || !this.currentPatterns.length) return;
      await this.play(this.currentChords, this.currentPatterns);
    },
    async downloadRiff() {
      if (!this.hasRiff || !this.currentChords.length || !this.currentPatterns.length) return;
      if (typeof MediaRecorder === 'undefined') {
        const statusEl = document.getElementById('riff-status');
        if (statusEl) statusEl.textContent = 'Recording not supported in this browser.';
        return;
      }
      this.isRecording = true;
      this.stopPlayback();
      await Tone.start();
      const chain = await ensureInstrumentChain(this.instrument);
      const sampler = chain.sampler;

      Tone.Transport.bpm.value = this.tempo;
      const { events, totalDuration } = this.buildEvents(this.currentChords, this.currentPatterns);
      const part = new Tone.Part(function(time, event) {
        sampler.triggerAttackRelease(event.note, event.duration, time);
      }, events).start(0);
      this.activePart = part;
      this.isPlaying = true;

      const statusEl = document.getElementById('riff-status');
      if (statusEl) statusEl.textContent = 'Rendering audio...';

      const chunks = [];
      let mimeType = 'audio/webm';
      if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/webm')) {
        mimeType = 'audio/webm';
      } else if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported('audio/wav')) {
        mimeType = 'audio/wav';
      }

      const mediaRecorder = new MediaRecorder(recordingDestination.stream, { mimeType: mimeType });
      const extension = mimeType.indexOf('wav') !== -1 ? 'wav' : 'webm';
      mediaRecorder.ondataavailable = function(e) {
        if (e.data && e.data.size > 0) {
          chunks.push(e.data);
        }
      };

      mediaRecorder.onstop = function() {
        const blob = new Blob(chunks, { type: mediaRecorder.mimeType || mimeType });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'suzy-riff.' + extension;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        const audio = document.getElementById('riff-audio');
        if (audio) {
          audio.src = url;
          audio.style.display = 'block';
        }

        setTimeout(function() {
          URL.revokeObjectURL(url);
        }, 2000);
      };

      mediaRecorder.start();
      Tone.Transport.start('+0.1');

      setTimeout(() => {
        mediaRecorder.stop();
        this.stopPlayback();
        this.isRecording = false;
        const statusEl = document.getElementById('riff-status');
        if (statusEl) statusEl.textContent = 'Riff rendered!';
      }, (totalDuration + 0.6) * 1000);
    }
  }
});

app.mount('#riff-app');

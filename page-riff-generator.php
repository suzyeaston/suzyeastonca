<?php
/*
Template Name: Riff Generator
*/
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <div class="riff-generator-container">
            <!--<h1 class="pixel-font">Riff Generator 8000</h1>-->
            
            <div class="generator-panel">
                <div class="controls">
                    <select id="genre-select">
                        <option value="rock">Rock</option>
                        <option value="punk">Punk</option>
                        <option value="metal">Metal</option>
                        <option value="jazz">Jazz</option>
                        <option value="classical">Classical</option>
                    </select>
                    
                    <button id="generate-riff" class="action-button">Generate Riff</button>
                    
                    <div class="riff-output">
                        <h3 class="pixel-font">Your Riff:</h3>
                        <p id="riff-text"></p>
                        <audio id="riff-audio" controls></audio>
                    </div>
                </div>

                <div class="commission-section">
                    <h3 class="pixel-font">Get a Custom Song!</h3>
                    <p>Wanna turn this riff into a full song in your favorite genre… or something hilariously weird?</p>
                    <p>Support my music on <a href="https://suzyeaston.bandcamp.com" target="_blank">Bandcamp</a> and hit me up!</p>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://unpkg.com/tone@14.7.77/build/Tone.js"></script>
<script src="https://unpkg.com/soundfont-player@0.15.0/dist/soundfont-player.js"></script>
<script>
// ==============================================
//    Genre‑specific riff generation
// ==============================================
const genres = {
    rock:  {
        notes: ['E4','G4','A4','B4','C5'],
        type: 'square',
        tempo: 150,
        distortion: 20
    },
    punk:  {
        notes: ['A4','D5','E5'],
        type: 'square',
        tempo: 210,
        distortion: 40
    },
    metal: {
        notes: ['E2','G2','A2','D3','F3'],
        type: 'sawtooth',
        tempo: 130,
        distortion: 80
    },
    jazz:  {
        notes: ['F2','G2','Bb2','C3','Eb3'],
        type: 'triangle',
        tempo: 90,
        distortion: 10
    },
    classical: {
        notes: ['C4','E4','G4','A4','B4','C5'],
        type: 'sine',
        tempo: 120,
        distortion: 0
    }
};

const instrumentMap = {
    rock: 'electric_guitar_clean',
    punk: 'electric_guitar_muted',
    metal: 'distortion_guitar',
    jazz: 'electric_bass_finger',
    classical: 'violin'
};

const bassMap = {
    rock: 'electric_bass_finger',
    punk: 'electric_bass_pick',
    metal: 'electric_bass_pick',
    jazz: 'acoustic_bass',
    classical: null
};

const drumTags = {
    rock: 'rock drum hit',
    punk: 'punk drum hit',
    metal: 'metal drum hit',
    jazz: 'brush drum hit',
    classical: ''
};

// Freesound token is optional; when absent we simply skip drum samples
const FS_TOKEN = '';
const sampleCache = {};

const HUMANIZE = {
    time: 0.04,
    gain: 0.2,
    freq: 0.02
};

async function loadDrumSample(genre){
    if(!FS_TOKEN){
        sampleCache[genre] = null;
        return null; // skip fetching when token isn't provided
    }
    if(sampleCache[genre]) return sampleCache[genre];
    const query = encodeURIComponent(drumTags[genre] || 'drum');
    const url = `https://freesound.org/apiv2/search/text/?query=${query}&page_size=5&token=${FS_TOKEN}`;
    try{
        const resp = await fetch(url);
        const data = await resp.json();
        const files = data.results.map(r => r.previews['preview-hq-mp3']);
        sampleCache[genre] = files;
        return files;
    }catch(e){
        console.warn('Freesound request failed', e);
        sampleCache[genre] = null;
        return null;
    }
}

async function playDrums(genre){
    const samples = await loadDrumSample(genre);
    if(samples && samples.length){
        const url = samples[Math.floor(Math.random()*samples.length)];
        const player = new Tone.Player(url).toDestination();
        player.autostart = true;
    }
    // silently skip drums when no sample is available
}

function noteToFreq(note){
    const match = note.match(/([A-G])(b|#)?(\d)/);
    if(!match) return 440;
    const noteMap = {C:0,D:2,E:4,F:5,G:7,A:9,B:11};
    let [,n,acc,oct] = match;
    let semitone = noteMap[n] + (acc === '#' ? 1 : (acc === 'b' ? -1 : 0));
    const midi = semitone + (parseInt(oct,10)+1)*12;
    return 440 * Math.pow(2, (midi-69)/12);
}

function makeDistortionCurve(amount){
    const k = typeof amount === 'number' ? amount : 50;
    const n = 44100;
    const curve = new Float32Array(n);
    const deg = Math.PI/180;
    for(let i=0;i<n;i++){
        const x = i*2/n - 1;
        curve[i] = (3+k)*x*20*deg/(Math.PI+k*Math.abs(x));
    }
    return curve;
}

function bufferToWave(abuffer, len) {
    const numOfChan = abuffer.numberOfChannels;
    const length = len * numOfChan * 2 + 44;
    const buffer = new ArrayBuffer(length);
    const view = new DataView(buffer);
    let channels = [];
    let i, sample, offset = 0, pos = 0;

    function setUint16(data){ view.setUint16(pos, data, true); pos += 2; }
    function setUint32(data){ view.setUint32(pos, data, true); pos += 4; }

    setUint32(0x46464952); // "RIFF"
    setUint32(length - 8); // file length - 8
    setUint32(0x45564157); // "WAVE"

    setUint32(0x20746d66); // "fmt " chunk
    setUint32(16); // length = 16
    setUint16(1); // PCM
    setUint16(numOfChan);
    setUint32(abuffer.sampleRate);
    setUint32(abuffer.sampleRate * 2 * numOfChan);
    setUint16(numOfChan * 2);
    setUint16(16);

    setUint32(0x61746164); // "data" chunk
    setUint32(length - pos - 4);

    for(i = 0; i < numOfChan; i++)
        channels.push(abuffer.getChannelData(i));

    while(pos < length){
        for(i = 0; i < numOfChan; i++){
            sample = Math.max(-1, Math.min(1, channels[i][offset]));
            sample = sample < 0 ? sample * 0x8000 : sample * 0x7FFF;
            view.setInt16(pos, sample, true);
            pos += 2;
        }
        offset++;
    }
    return buffer;
}

async function playWithSamples(genre, riff, noteDur){
    const ac = Tone.context;
    const inst  = await Soundfont.instrument(ac, instrumentMap[genre]);
    const bass  = await Soundfont.instrument(ac, bassMap[genre]);
    const start = ac.currentTime + 0.1;
    riff.forEach((note, i) => {
        const tOffset = (Math.random() - 0.5) * HUMANIZE.time;
        const vol    = 0.8 + (Math.random()-0.5) * HUMANIZE.gain;
        inst.play(note, start + i*noteDur + tOffset, { gain: vol });
        const bassNote = Tone.Frequency(note).transpose(-12).toNote();
        const bassVol  = 0.7 + (Math.random()-0.5) * HUMANIZE.gain;
        bass.play(bassNote, start + i*noteDur + tOffset, { gain: bassVol });
    });
    playDrums(genre);
    const audioElement = document.getElementById('riff-audio');
    audioElement.src = '';
    audioElement.style.display = 'none';
}

function playWithOscillator(data, riff, noteDur){
    const {type, distortion} = data;
    const totalDur = noteDur*riff.length + 0.1;
    const offline = new OfflineAudioContext(1, Math.ceil(44100*totalDur), 44100);
    let currentTime = 0;

    riff.forEach(note => {
        const osc = offline.createOscillator();
        osc.type = type;
        const baseFreq = noteToFreq(note);
        osc.frequency.value = baseFreq * (1 + (Math.random()-0.5)*HUMANIZE.freq);
        const gain = offline.createGain();
        osc.connect(gain);

        let dest = gain;
        if(distortion){
            const dist = offline.createWaveShaper();
            dist.curve = makeDistortionCurve(distortion);
            dist.oversample = '4x';
            gain.connect(dist);
            dest = dist;
        }

        dest.connect(offline.destination);
        const vol = 0.7 + (Math.random()-0.5)*HUMANIZE.gain;
        const startTime = currentTime + (Math.random()-0.5)*HUMANIZE.time;
        gain.gain.setValueAtTime(0, startTime);
        gain.gain.linearRampToValueAtTime(vol, startTime + 0.02);
        gain.gain.linearRampToValueAtTime(vol*0.7, startTime + noteDur*0.8);
        gain.gain.linearRampToValueAtTime(0, startTime + noteDur);
        osc.start(startTime);
        osc.stop(startTime + noteDur);
        currentTime += noteDur;
    });

    offline.startRendering().then(renderedBuffer => {
        const wav = bufferToWave(renderedBuffer, renderedBuffer.length);
        const blob = new Blob([wav], {type: 'audio/wav'});
        const url = URL.createObjectURL(blob);
        const audioElement = document.getElementById('riff-audio');
        audioElement.src = url;
        audioElement.controls = true;
        audioElement.style.display = 'block';
    });
}

async function generateRiff(){
    const genreKey = document.getElementById('genre-select').value;
    const data = genres[genreKey];
    const notes = data.notes;
    const tempo = data.tempo;
    const type = data.type;
    const distortionAmount = data.distortion;

    const riffLength = Math.floor(Math.random()*4)+4;
    const riff = [];
    for(let i=0;i<riffLength;i++){
        riff.push(notes[Math.floor(Math.random()*notes.length)]);
    }
    document.getElementById('riff-text').textContent = riff.join(' - ');

    const noteDur = 60/tempo;

    try {
        await playWithSamples(genreKey, riff, noteDur);
    } catch(e) {
        console.warn('Falling back to oscillator', e);
        playWithOscillator({type, distortion: distortionAmount}, riff, noteDur);
    }
}

window.addEventListener('load', () => {
    document.getElementById('generate-riff').addEventListener('click', generateRiff);
});
</script>

<?php get_footer(); ?>

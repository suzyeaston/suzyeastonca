<?php
/*
Template Name: Riff Generator
*/
get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <div class="riff-generator-container">
            <h1 class="pixel-font">Riff Generator 8000</h1>
            
            <div class="generator-panel">
                <div class="controls">
                    <select id="genre-select">
                        <option value="rock">Rock</option>
                        <option value="punk">Punk</option>
                        <option value="metal">Metal</option>
                        <option value="jazz">Jazz</option>
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
                    <p>Love this riff? Want a full song based on it?</p>
                    <p class="price">$50</p>
                    <button class="commission-button" onclick="window.location.href='https://ko-fi.com/suzyeaston?amount=50'">Commission Song</button>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// ==============================================
//    Genre‑specific riff generation
// ==============================================
const genres = {
    rock:  {
        notes: ['E4','A4','B4','E5'],
        type: 'square',
        tempo: 180,
        distortion: 40
    },
    punk:  {
        notes: ['C4','F4','G4','C5'],
        type: 'square',
        tempo: 200,
        distortion: 30
    },
    metal: {
        notes: ['D4','E4','F4','A4','Bb4','C5'],
        type: 'sawtooth',
        tempo: 160,
        distortion: 70
    },
    jazz:  {
        notes: ['F4','G4','A4','C5','Eb5'],
        type: 'triangle',
        tempo: 100,
        distortion: 0
    }
};

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

function generateRiff(){
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
    const totalDur = noteDur*riff.length + 0.1;
    const offline = new OfflineAudioContext(1, Math.ceil(44100*totalDur), 44100);
    let currentTime = 0;

    riff.forEach(note => {
        const osc = offline.createOscillator();
        osc.type = type;
        osc.frequency.value = noteToFreq(note);
        const gain = offline.createGain();
        osc.connect(gain);

        let dest = gain;
        if(distortionAmount){
            const dist = offline.createWaveShaper();
            dist.curve = makeDistortionCurve(distortionAmount);
            dist.oversample = '4x';
            gain.connect(dist);
            dest = dist;
        }

        dest.connect(offline.destination);
        gain.gain.setValueAtTime(0, currentTime);
        gain.gain.linearRampToValueAtTime(1, currentTime + 0.02);
        gain.gain.linearRampToValueAtTime(0.7, currentTime + noteDur*0.8);
        gain.gain.linearRampToValueAtTime(0, currentTime + noteDur);
        osc.start(currentTime);
        osc.stop(currentTime + noteDur);
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

window.addEventListener('load', () => {
    document.getElementById('generate-riff').addEventListener('click', generateRiff);
});
</script>

<?php get_footer(); ?>

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
// Riff Generator
const genres = {
    rock: ['E5', 'G5', 'D5', 'A5'],
    punk: ['C5', 'F5', 'G5', 'C5'],
    metal: ['D5', 'A5', 'E5', 'B5'],
    jazz: ['F5', 'A5', 'C5', 'E5']
};

function generateRiff() {
    const genre = document.getElementById('genre-select').value;
    const notes = genres[genre];
    const riffLength = Math.floor(Math.random() * 4) + 4;
    const riff = [];
    
    // Generate a sequence of notes
    for (let i = 0; i < riffLength; i++) {
        const note = notes[Math.floor(Math.random() * notes.length)];
        riff.push(note);
    }
    
    // Display riff
    document.getElementById('riff-text').textContent = riff.join(' - ');
    
    // Create audio context
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    
    // Play each note in sequence
    let currentTime = audioCtx.currentTime;
    
    riff.forEach((note, index) => {
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        
        // Map MIDI notes to frequencies
        const frequency = 440 * Math.pow(2, (note.charCodeAt(0) - 69) / 12);
        
        oscillator.type = 'sawtooth';
        oscillator.frequency.value = frequency;
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        
        // Add envelope
        gainNode.gain.setValueAtTime(0, currentTime);
        gainNode.gain.linearRampToValueAtTime(1, currentTime + 0.01);
        gainNode.gain.linearRampToValueAtTime(0.5, currentTime + 0.3);
        gainNode.gain.linearRampToValueAtTime(0, currentTime + 0.5);
        
        oscillator.start(currentTime);
        oscillator.stop(currentTime + 0.5);
        
        currentTime += 0.6; // Add delay between notes
    });
    
    // Update audio element
    const audioElement = document.getElementById('riff-audio');
    audioElement.src = '';
    audioElement.controls = false;
    audioElement.style.display = 'none';
}

// Initialize
window.addEventListener('load', () => {
    document.getElementById('generate-riff').addEventListener('click', generateRiff);
});
</script>

<?php get_footer(); ?>

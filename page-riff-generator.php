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
    
    for (let i = 0; i < riffLength; i++) {
        riff.push(notes[Math.floor(Math.random() * notes.length)]);
    }
    
    // Display riff
    document.getElementById('riff-text').textContent = riff.join(' - ');
    
    // Generate audio
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioCtx.createOscillator();
    oscillator.type = 'sawtooth';
    oscillator.frequency.setValueAtTime(notes[0].replace(/[A-G]/, ''), audioCtx.currentTime);
    oscillator.connect(audioCtx.destination);
    oscillator.start();
    oscillator.stop(audioCtx.currentTime + 2);
}

// Initialize
window.addEventListener('load', () => {
    document.getElementById('generate-riff').addEventListener('click', generateRiff);
});
</script>

<?php get_footer(); ?>

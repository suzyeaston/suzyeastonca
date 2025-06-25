document.addEventListener('DOMContentLoaded', function() {
    createSparklyName();
});

function createSparklyName() {
    var sparklyNameContainer = document.createElement('div');
    sparklyNameContainer.setAttribute('id', 'sparkly-name');
    sparklyNameContainer.className = 'sparkle';
    sparklyNameContainer.textContent = 'Suzy Easton';
    var header = document.getElementById('retro-game-header');
    if (header) {
        header.appendChild(sparklyNameContainer);
    }
}

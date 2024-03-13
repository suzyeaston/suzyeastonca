document.addEventListener('DOMContentLoaded', function() {
    var overlay = document.createElement('div');
    overlay.setAttribute('id', 'start-overlay');
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:black;color:white;display:flex;justify-content:center;align-items:center;font-size:20px;z-index:10000;';
    overlay.innerHTML = 'Press anywhere to begin';
    document.body.appendChild(overlay);

    overlay.addEventListener('click', function() {
        overlay.style.display = 'none'; // Hide overlay
        createSparklyName();
    });

    function createSparklyName() {
        var sparklyNameContainer = document.createElement('div');
        sparklyNameContainer.setAttribute('id', 'sparkly-name');
        sparklyNameContainer.className = 'sparkle';
        sparklyNameContainer.innerHTML = 'Suzy Easton';
        document.getElementById('retro-game-header').appendChild(sparklyNameContainer);
    }
});

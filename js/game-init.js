document.addEventListener('DOMContentLoaded', function() {
    var overlay = document.createElement('div');
    overlay.setAttribute('id', 'start-overlay');
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:black;color:white;display:flex;justify-content:center;align-items:center;font-size:20px;z-index:10000;';
    overlay.innerHTML = 'Press anywhere to begin';
    document.body.appendChild(overlay);

    // Add click event to overlay
    overlay.addEventListener('click', function() {
        overlay.style.display = 'none'; // Hide overlay
        displayPixelatedName();
    });

    function displayPixelatedName() {
        var nameContainer = document.createElement('div');
        nameContainer.setAttribute('id', 'pixelated-name');
        nameContainer.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);font-family:pixel-font;color:white;font-size:40px;z-index:10001;';
        nameContainer.innerHTML = 'Suzy Easton';
        document.body.appendChild(nameContainer);
    }
});

document.addEventListener('DOMContentLoaded', function() {
    createSparklyName();
    staggerMenus();
});

function createSparklyName() {
    var sparklyNameContainer = document.createElement('div');
    sparklyNameContainer.setAttribute('id', 'sparkly-name');
    sparklyNameContainer.className = 'sparkle';
    sparklyNameContainer.textContent = 'Suzy Easton';
    var header = document.getElementById('retro-game-header');
    if (header) {
        header.appendChild(sparklyNameContainer);
        handleSparkleScale(sparklyNameContainer);
        window.addEventListener('resize', function() {
            handleSparkleScale(sparklyNameContainer);
        });
    }
}

function handleSparkleScale(el) {
    if (!el) return;
    var compact = window.matchMedia('(max-width: 720px)').matches;
    el.dataset.compact = compact ? 'true' : 'false';
}

function staggerMenus() {
    var items = document.querySelectorAll('#menu-container .menu-item');
    items.forEach(function(item, index) {
        item.style.setProperty('--stagger-order', index);
        item.classList.add('menu-item--staggered');
    });
}

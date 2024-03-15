document.addEventListener('DOMContentLoaded', function() {
    const bioContainer = document.getElementById('bio-container');
    if (bioContainer) {
        bioContainer.style.position = 'absolute';
        bioContainer.style.bottom = '-100%';
        window.scrollTo({ top: 0 });
        animateBio();
    }

    function animateBio() {
        let startPosition = window.innerHeight;
        const endPosition = -bioContainer.offsetHeight;

        const interval = setInterval(() => {
            startPosition -= 2;
            if (startPosition <= endPosition) clearInterval(interval);

            bioContainer.style.bottom = `${startPosition}px`;
        }, 20);
    }
});

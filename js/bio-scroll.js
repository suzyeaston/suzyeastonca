document.addEventListener('DOMContentLoaded', function() {
    const bioContainer = document.getElementById('bio-container');
    if (bioContainer) {
        
        bioContainer.style.position = 'absolute';
        bioContainer.style.whiteSpace = 'nowrap';
        bioContainer.style.left = '-100%';
        bioContainer.style.top = '0';

        animateBio();
    }

    function animateBio() {
        let startLeft = -bioContainer.offsetWidth; // Start from the left
        const endPosition = window.innerWidth;

        const interval = setInterval(() => {
            startLeft += 2;
            if (startLeft >= endPosition) clearInterval(interval);

            bioContainer.style.left = `${startLeft}px`;
        }, 20); // Adjust for smoother or faster animation
    }
});

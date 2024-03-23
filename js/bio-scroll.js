document.addEventListener('DOMContentLoaded', function() {
    const bioContainer = document.getElementById('bio-container');
    if (bioContainer) {
        // Initialize the container for the crawl effect
        bioContainer.style.position = 'absolute';
        bioContainer.style.top = '100%'; // Start off-screen at the bottom
        bioContainer.style.left = '0';
        bioContainer.style.width = '100%';
        bioContainer.style.textAlign = 'center';
    }
});

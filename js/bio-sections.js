document.addEventListener('DOMContentLoaded', function() {
    const bioSections = document.querySelectorAll('.bio-section-item');
    
    bioSections.forEach(section => {
        const title = section.querySelector('.section-title');
        const content = section.querySelector('.section-content');
        
        title.addEventListener('click', function() {
            section.classList.toggle('active');
            
            // Toggle aria-expanded attribute
            title.setAttribute('aria-expanded', section.classList.contains('active'));
        });
    });
});

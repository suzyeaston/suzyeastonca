<?php get_header(); ?>

<main id="main-content">
    <header id="retro-game-header">
        <div id="stacked-nerd-title" class="glowing-text">Stacked Nerd</div>
        <img src="https://suzyeaston.ca/wp-content/uploads/2024/03/suzy2.jpeg" alt="Suzy Easton" class="animated-image">
    </header>
<section id="menu-container">
    <div class="menu-item" onclick="location.href='/home'">Home</div>
    <div class="menu-item" onclick="location.href='/bio'">About</div>
    <div class="menu-item" onclick="location.href='/easy-living-with-suzy-easton'">All New Podcast - Easy Living with Suzy Easton</div>
    <div class="menu-item" onclick="location.href='/the-midnight-mix'">Live Music with Suzy Easton</div>
    <div class="menu-item" onclick="location.href='/canucks-app'">Canucks App</div>
    <div class="menu-item" onclick="location.href='/music-releases'">Bandcamp Music Releases</div>
    <div class="menu-item" onclick="location.href='/social-media'">Social Media</div>
    <div class="menu-item" onclick="location.href='/contact'">Contact</div>
    
</section>

</main>

<style>
.glowing-text {
    text-shadow: none;
}

.animated-image {
    width: 200px; 
    height: auto;
    display: block;
    margin: 20px auto;
    animation: float 2s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}
</style>

<?php get_footer(); ?>

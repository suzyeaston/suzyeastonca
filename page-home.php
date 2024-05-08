<?php
/*
Template Name: Home Page
*/

get_header();
?>

<main id="main-content">
    <header id="retro-game-header">
        <div id="stacked-nerd-title" class="glowing-text">Stacked Nerd</div>
        <img src="https://suzyeaston.ca/wp-content/uploads/2024/03/suzy2.jpeg" alt="Suzy Easton" class="animated-image">
    </header>
    <section id="menu-container">
        <div class="menu-item" onclick="location.href='https://twitter.com/officialsuzye'">Twitter</div>
        <div class="menu-item" onclick="location.href='https://www.linkedin.com/in/suzyeaston/'">Linkedin</div>
        <div class="menu-item" onclick="location.href='https://suzyeaston.bandcamp.com/'">Music</div>
        <div class="menu-item" onclick="location.href='https://www.youtube.com/user/anabsolutepitch'">YouTube</div>
        <div class="menu-item" onclick="location.href='bio'">Bio</div>
        <div class="menu-item" onclick="location.href='music-lessons'">Music Lessons</div>
        <div class="menu-item" onclick="location.href='coming-soon'">Suzy's Musical Adventure - Video Game (Coming Soon)</div>
        <div class="menu-item" onclick="location.href='contact'">Contact</div>
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

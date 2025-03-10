<?php
/*
Template Name: Music Releases Page
*/

get_header();
?>
<main id="main-content">
    <header id="retro-game-header">
        <div id="stacked-nerd-title">Music Releases</div>
    </header>
    <section class="page-content">
        <?php
        while ( have_posts() ) : the_post();
            the_content();
        endwhile;
        ?>
        <div class="music-embeds">
            <iframe style="border: 0; width: 100%; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/album=1616992883/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true/" seamless><a href="https://suzyeaston.bandcamp.com/album/guiding-currents">Guiding Currents by Suzy Easton</a></iframe>
            <iframe style="border: 0; width: 100%; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/album=901825062/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true/" seamless><a href="https://suzyeaston.bandcamp.com/album/a-little-louder">A Little Louder by Suzy Easton</a></iframe>
            <iframe style="border: 0; width: 100%; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/album=3277261011/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true/" seamless><a href="https://suzyeaston.bandcamp.com/album/victoria-day">Victoria Day by Suzy Easton</a></iframe>
            <iframe style="border: 0; width: 100%; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/album=2300901547/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true/" seamless><a href="https://suzyeaston.bandcamp.com/album/on-cambie-street">On Cambie Street by Suzy Easton</a></iframe>
            <iframe style="border: 0; width: 100%; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/album=3683160898/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true/" seamless><a href="https://suzyeaston.bandcamp.com/album/echoes-of-dissonance">Echoes Of Dissonance by Suzy Easton</a></iframe>
            <iframe style="border: 0; width: 100%; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/track=721366290/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true/" seamless><a href="https://suzyeaston.bandcamp.com/track/heartbeats-in-harmony">Heartbeats in Harmony by Suzy Easton</a></iframe>
            <iframe style="border: 0; width: 100%; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/track=2989534823/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true/" seamless><a href="https://suzyeaston.bandcamp.com/track/across-the-oceans">Across The Oceans by Suzy Easton</a></iframe>
            <iframe style="border: 0; width: 100%; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/album=1455101985/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/transparent=true/" seamless><a href="https://suzyeaston.bandcamp.com/album/ride-the-expo-line">Ride The Expo Line by Suzy Easton</a></iframe>
        </div>
    </section>
</main>
<?php
get_footer();
?>

<?php
/**
 * Homepage doorway to the Vancouver Music & Culture calendar.
 * Uses already-cached listings; never makes remote requests from the homepage.
 */
$home_culture_next = null;
if ( function_exists( 'suzy_vmc_events' ) ) {
    $home_culture_upcoming = suzy_vmc_events();
    $home_culture_next = $home_culture_upcoming[0] ?? null;
}
?>
<section class="home-culture-spotlight crt-block" aria-labelledby="home-culture-title">
    <div class="home-culture-spotlight__art">
        <span class="home-culture-spotlight__staff" aria-hidden="true"></span>
        <pre class="home-culture-spotlight__clef" role="img" aria-label="Treble clef in hand-built ASCII pixel art over a five-line music staff">              +@@@@         
             @@@@@@@+       
            @@@@@@@@@       
           @@@@+    @@      
           @@@      @@      
          +@@       @@      
          +@+      @@@      
           @+    +@@@+      
           @@   @@@@@       
            @+@@@@@@        
          +@@@@@@@+         
        +@@@@@@@@           
      +@@@@@@@@             
    +@@@@@@@++@             
   @@@@@@+    @@            
 +@@@@@+      +@+           
 @@@@@      +@@@@@@@@@@+    
@@@@+     +@@@@@@@@@@@@@@@  
@@@@     @@@@++ @@   +@@@@@ 
@@@     +@@@    +@      @@@@
+@@     +@@      +@      @@@
 +@@     +@@     +@      @@@
   @@      +++    @+    +@@ 
    ++++          +@   +@+  
       +++++     ++@@+++    
             +++++ +@       
                    @@      
                    +@      
       +@@@@@+       @+     
      +@@@@@@@@      @@     
      @@@@@@@@@     +@+     
       @@@@@++     +@+      
         +@@++++@@++        </pre>
        <p class="home-culture-spotlight__art-label">YVR // SIDE B</p>
    </div>
    <div class="home-culture-spotlight__content">
        <p class="home-section-kicker pixel-font">sound // words // ideas</p>
        <h2 id="home-culture-title" class="pixel-font">VANCOUVER MUSIC + CULTURE</h2>
        <p class="home-culture-spotlight__intro">Small rooms, strange sounds, big ideas. Discover local music, experimental sound, music tech, poetry, philosophy and independent arts.</p>
        <?php if ( is_array( $home_culture_next ) && ! empty( $home_culture_next['start'] ) ) :
            $home_culture_start = (int) $home_culture_next['start'];
            $home_culture_timezone = suzy_vmc_tz( $home_culture_start );
            ?>
            <p class="home-culture-spotlight__next">
                <span class="home-culture-spotlight__next-label">NEXT ON THE FREQUENCY</span>
                <time datetime="<?php echo esc_attr( wp_date( 'c', $home_culture_start, $home_culture_timezone ) ); ?>"><?php echo esc_html( wp_date( 'D, M j · g:i a', $home_culture_start, $home_culture_timezone ) ); ?></time>
                <span class="home-culture-spotlight__next-title"><?php echo esc_html( $home_culture_next['title'] ?? 'Upcoming cultural event' ); ?></span>
            </p>
        <?php endif; ?>
        <a class="pixel-button home-culture-spotlight__cta" href="<?php echo esc_url( home_url( '/vancouver-music-culture/' ) ); ?>">Explore music &amp; culture</a>
    </div>
</section>

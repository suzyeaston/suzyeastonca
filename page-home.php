<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
    <div class="hero-section">
        <h1 id="home-title" class="pixel-font color-cycle">Hi, I&rsquo;m Suzy Easton.</h1>
       <section class="pixel-intro" style="max-width: 720px; margin: 0 auto; line-height: 1.8; font-size: 1.05rem;">
    <p>I&rsquo;m a musician, technologist, and creative builder based in Vancouver.</p>

    <p>Toured nationally as a bassist, recorded with Steve Albini in Chicago, and appeared on MuchMusic. Today, I keep creating, playing around with programming/coding and releasing new music at night while shipping infrastructure code by day hah!</p>

    <p>After navigating tenancy issues in my own building and helping neighbours do the same, I’ve started to get involved in civic life, and exploring a possible run for Vancouver City Council in 2026.</p>
</section>

        <p class="arcade-subtext">Insert coin to explore</p>
        <div class="puck-icon">🏒</div>
        <?php
        $visitor_data = include get_template_directory() . '/visitor-tracker.php';
        $phrases = [
            '⚡ %d poor souls from %s woke the Albini bot today.',
            '🎸 %d noise-makers from %s just asked why their band sucks.',
            '🤖 Albini\'s circuit board has processed %d fragile egos from %s.',
            '👁️ %d lurkers from %s looking for validation. None found.',
            '🔥 %d punks from %s have faced the wrath of Albini\'s sarcasm.',
            '💀 %d existential crises triggered in %s.',
            '🚀 %s launched %d useless questions into the void.'
        ];
        ?>
        <div class="visitor-counter">
          <?php foreach ($visitor_data['locations'] as $c => $cnt): ?>
            <p><?php printf(esc_html($phrases[array_rand($phrases)]), $cnt, $c); ?></p>
          <?php endforeach; ?>
          <?php if (!empty($visitor_data['locations'])): ?>
            <p>
              <?php
              arsort($visitor_data['locations']);
              $leaders = array_slice($visitor_data['locations'], 0, 3, true);
              $leaderboard = [];
              foreach ($leaders as $cc => $ct) {
                  $leaderboard[] = "$cc ($ct)";
              }
              echo 'Leaderboard: ' . esc_html(implode(', ', $leaderboard));
              ?>
            </p>
          <?php endif; ?>
        </div>

        <section class="track-analyzer-feature">
            <h2 class="pixel-font">Suzy's Track Analyzer</h2>
            <p class="pixel-font">Drop an MP3, channel the chaos, get an instant vibe check.</p>
            <a href="https://www.suzyeaston.ca/suzys-track-analyzer/" class="pixel-button analyzer-cta">Analyze a Track</a>
        </section>

        <div class="button-cluster">
            <div class="button-group">
                <h3 class="group-title">🎵 Music &amp; Podcasts</h3>
                <div class="group-buttons">
                    <a href="https://suzyeaston.bandcamp.com" class="pixel-button" target="_blank">Bandcamp</a>
                    <a href="/podcast" class="pixel-button">Podcast: Easy Living</a>
                </div>
            </div>

            <div class="button-group">
                <h3 class="group-title">🎮 Games &amp; Tools</h3>
                <div class="group-buttons">
                    <a href="/riff-generator" class="pixel-button">Riff Generator</a>
                    <a href="/arcade" class="pixel-button">Canucks Game</a>
                    <a href="/albini-qa" class="pixel-button">Albini Q&amp;A</a>
                </div>
            </div>

            <div class="button-group">
                <h3 class="group-title">📺 Livestream &amp; Events</h3>
                <div class="group-buttons">
                    <a href="/social-media" class="pixel-button">Livestream</a>
                    <a href="/music-releases" class="pixel-button">Upcoming Events</a>
                </div>
            </div>

            <div class="button-group">
                <h3 class="group-title">📚 About &amp; Info</h3>
                <div class="group-buttons">
                    <a href="/bio" class="pixel-button">About Suzy</a>
                </div>
            </div>
        </div>

    </div>

    <section class="now-listening">
        <h2 class="pixel-font">Now Listening</h2>
        <iframe width="100%" height="360" src="https://www.youtube.com/embed/GwetNnBkgQM?autoplay=0" title="Metric – Full Performance (Live on KEXP)" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        <p class="pixel-font now-listening-caption">🎶 Metric — Live on KEXP. Raw energy, shimmering synths, no AI weapons funding here.</p>
        <div class="info-callout pixel-font">
            <p>Spotify CEO Daniel Ek used his investment firm Prima Materia to lead a <strong>€600M (~US $694M)</strong> Series D funding round in June 2025 for Helsing, a German defense‑AI startup now valued at $12&nbsp;billion.</p>
            <p>Helsing develops AI‑enabled drones, aircraft and submarines for defense, and Ek also sits on its board as chairman.</p>
            <p>At least one indie band – Deerhoof – pulled their music from Spotify in protest, citing ethical concerns over music &ldquo;killing people&rdquo; due to this connection.</p>
        </div>
    </section>

    <section class="advocacy-section">
        <h2 class="pixel-font">City &amp; Housing</h2>
        <p>I&rsquo;m learning alongside neighbours in the Downtown Eastside to keep residents housed and heard. I&rsquo;m also considering a 2026 City Council run focused on housing justice.</p>
        <div class="group-buttons">
            <a href="/advocacy" class="pixel-button">Learn More</a>
            <a href="/contact" class="pixel-button">Get Involved</a>
            <a href="https://www.carnegiehousingproject.ca/events" class="pixel-button" target="_blank">See Local Housing Events</a>
        </div>
    </section>

    <section class="featured-content">
        <div class="featured-item">
            <h2 class="pixel-font">What's New</h2>
            <div class="news-grid">
                <div class="news-item">
                    <h3>Live Stream Schedule</h3>
                    <p>Join me every Friday at 8PM PST</p>
                    <a href="/social-media" class="more-button">Watch Live</a>
                </div>
            </div>
        </div>

        <div class="featured-item">
            <h2 class="pixel-font">Latest Podcast</h2>
            <p class="pixel-font">"Easy Living with Suzy Easton" - Stories, insights, and laughter about life in Vancouver</p>
            <?php
            $latest_podcast = new WP_Query(array(
                'posts_per_page' => 1,
                'post_type' => 'podcast'
            ));
            
            if ($latest_podcast->have_posts()) :
                while ($latest_podcast->have_posts()) :
                    $latest_podcast->the_post();
                    ?>
                    <div class="podcast-preview">
                        <h3><?php the_title(); ?></h3>
                        <?php the_excerpt(); ?>
                        <a href="<?php the_permalink(); ?>" class="more-button">Listen Now</a>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
            endif;
            ?>
        </div>

    </section>



    <section class="support-section">
        <h2 class="pixel-font">Support My Creative Journey</h2>
        <p class="pixel-font">For collaborations or just to chat, reach out at <a href="mailto:suzyeaston@icloud.com" class="support-link">suzyeaston@icloud.com</a></p>
        <p style="text-align:center;">🎶 <a href="https://suzyeaston.bandcamp.com" target="_blank">Support my music on Bandcamp</a></p>
        <p style="text-align:center;">New demo drops this weekend. Stay noisy.</p>
    </section>
</main>

<?php get_footer(); ?>

<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
    <div class="hero-section">
        <h1 class="retro-title glow-lite">Suzy Easton &mdash; Vancouver</h1>
        <h2 class="retro-title glow-lite">Musician &amp; Creative Technologist</h2>
        <section class="pixel-intro" style="max-width: 720px; margin: 0 auto; line-height: 1.8; font-size: 1.05rem;">
    <p>I&rsquo;m a musician, technologist, and creative builder based in Vancouver.</p>

    <p>Toured nationally as a bassist, recorded with Steve Albini in Chicago, and appeared on MuchMusic. Today, I keep creating, playing around with programming/coding and releasing new music at night while shipping infrastructure code by day hah!</p>

    <p>After navigating tenancy issues in my own building and helping neighbours do the same, I’ve started to get involved in civic life, and exploring a possible run for Vancouver City Council in 2026.</p>
</section>

        <p class="arcade-subtext">Insert coin to explore</p>
        <div class="puck-icon" role="img" aria-label="retro hockey puck icon">🏒</div>
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

    <?php get_template_part('parts/lousy-outages-teaser'); ?>

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

    <section class="track-analyzer-feature">
        <h2 class="pixel-font">Track Analyzer</h2>
        <p class="pixel-font">Upload an MP3 and get instant feedback on your mix.</p>
        <a href="/suzys-track-analyzer" class="pixel-button analyzer-cta">Analyze Your Track</a>
    </section>

    <section class="party-announcement crt-block">
        <h2>Grunge &amp; Rock Video Party (Instagram)</h2>
        <p>We’re cueing up a feed of loud guitars, grainy VHS vibes, and deep cuts.
           Follow <a href="https://instagram.com/officialsuzyeaston" target="_blank">@officialsuzyeaston</a>
           for the kickoff date &amp; set list. Requests welcome. Bring flannel.</p>
    </section>


    <section class="support-section">
        <h2 class="pixel-font">Support My Creative Journey</h2>
        <p class="pixel-font">For collaborations or just to chat, reach out at <a href="mailto:suzyeaston@icloud.com" class="support-link">suzyeaston@icloud.com</a></p>
        <p style="text-align:center;">🎶 <a href="https://suzyeaston.bandcamp.com" target="_blank">Support my music on Bandcamp</a></p>
        <p style="text-align:center;">New demo drops this weekend. Stay noisy.</p>
    </section>
</main>

<?php get_footer(); ?>

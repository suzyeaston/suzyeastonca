<?php
/* Template Name: Homepage */
get_header();
?>

<main id="homepage-content">
    <?php
    $hero_eyebrow = apply_filters('se_home_hero_eyebrow', 'Vancouver, BC • QA automation • IT operations • Creative AI');
    $hero_headline = apply_filters('se_home_hero_headline', 'I build strange, useful things.');
    $hero_logo_label = apply_filters('se_home_hero_title', 'Suzy Easton');
    $hero_logo_top = apply_filters('se_home_hero_logo_top', 'Suzy');
    $hero_logo_mid = apply_filters('se_home_hero_logo_mid', '');
    $hero_logo_bottom = apply_filters('se_home_hero_logo_bottom', 'Easton');
    $hero_logo_top_text = function_exists('mb_strtoupper') ? mb_strtoupper($hero_logo_top, 'UTF-8') : strtoupper($hero_logo_top);
    $hero_logo_mid_text = function_exists('mb_strtoupper') ? mb_strtoupper($hero_logo_mid, 'UTF-8') : strtoupper($hero_logo_mid);
    $hero_logo_bottom_text = function_exists('mb_strtoupper') ? mb_strtoupper($hero_logo_bottom, 'UTF-8') : strtoupper($hero_logo_bottom);
    ?>

    <div class="hero hero-section">
        <div class="hero-grid">
            <div class="hero-main">
                <?php if (!empty($hero_eyebrow)) : ?>
                    <p class="hero-eyebrow pixel-font"><?php echo esc_html($hero_eyebrow); ?></p>
                <?php endif; ?>

                <h1 class="hero-core-headline"><?php echo esc_html($hero_headline); ?></h1>

                <p class="hero-copy">
                    <?php echo esc_html('I’m Suzy Easton — a senior technical generalist, musician, and creative technologist who turns messy workflows, flaky systems, and half-formed ideas into practical outcomes. My work spans QA automation, IT operations, AI prototypes, civic/open-data experiments, music tech, and public projects like Lousy Outages.'); ?>
                </p>

                <p class="hero-availability">
                    <?php echo esc_html('Available for senior technical roles, contract QA/automation work, practical AI prototypes, and useful weird builds.'); ?>
                </p>

                <div class="hero-status-chips" aria-label="Current status">
                    <span class="hero-status-chip"><strong><?php echo esc_html('BUILD MODE:'); ?></strong> <?php echo esc_html('SHIPPING'); ?></span>
                    <span class="hero-status-chip"><strong><?php echo esc_html('LOCATION:'); ?></strong> <?php echo esc_html('VANCOUVER'); ?></span>
                    <span class="hero-status-chip"><strong><?php echo esc_html('STATUS:'); ?></strong> <?php echo esc_html('OPEN TO WORK'); ?></span>
                </div>

                <div class="hero-cta-group">
                    <a href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>" class="pixel-button hero-primary-cta">Enter Gastown Simulator</a>
                    <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>" class="pixel-button hero-secondary-cta">Work with Suzy</a>
                    <a href="<?php echo esc_url('https://www.linkedin.com/in/suzyeaston/'); ?>" class="pixel-button hero-tertiary-cta" target="_blank" rel="noopener noreferrer">LinkedIn</a>
                </div>
                <p class="hero-collab-link"><a href="<?php echo esc_url(home_url('/bio/')); ?>"><?php echo esc_html('Read bio →'); ?></a></p>
            </div>

            <button class="hero-deco hero-ship hero-ship--autopilot" type="button" aria-label="Drag the spaceship" title="Drag me" tabindex="0"></button>

            <aside class="hero-side hero-photo-card" aria-label="Hero wordmark and portrait">
                <div class="hero-wordmark-wrap">
                    <div class="hero-badge">
                        <p class="hero-wordmark" aria-label="<?php echo esc_attr($hero_logo_label); ?>">
                            <span class="line1">
                                <?php echo esc_html($hero_logo_top_text); ?>
                                <?php if (!empty($hero_logo_mid_text)) : ?>
                                    <small><?php echo esc_html($hero_logo_mid_text); ?></small>
                                <?php endif; ?>
                            </span>
                            <span class="line2"><?php echo esc_html($hero_logo_bottom_text); ?></span>
                        </p>
                    </div>
                </div>
                <div class="hero-photo-frame">
                    <a class="hero-photo-link hero-photo-link-block" href="<?php echo esc_url(home_url('/bio/')); ?>">
                        <img class="hero-photo" src="<?php echo esc_url(home_url('/wp-content/uploads/2026/01/IMG_9003.jpg')); ?>" alt="<?php echo esc_attr('Suzy Easton smiling with a guitar'); ?>" loading="lazy" decoding="async">
                    </a>
                </div>
                <p class="hero-photo-caption pixel-font"><a class="hero-photo-link" href="<?php echo esc_url(home_url('/bio/')); ?>"><?php echo esc_html('Vancouver, BC / Read bio →'); ?></a></p>
            </aside>
        </div>

        <div class="hero-game-stage" tabindex="0" aria-label="Pacific Static mini arcade game">
            <p class="hero-game-stage__header pixel-font"><?php echo esc_html('PACIFIC STATIC'); ?></p>
            <div class="hero-game-stage__screen" role="region" aria-label="Pacific Static game screen">
                <p class="hero-game-stage__idle pixel-font"><?php echo esc_html('PACIFIC STATIC'); ?><br><?php echo esc_html('Defend the Vancouver signal.'); ?><br><?php echo esc_html('Press G to play.'); ?><br><?php echo esc_html('WASD move // Space fire // Esc quit'); ?></p>
            </div>
            <p class="hero-game-stage__mobile-note"><?php echo esc_html('Mini arcade available on keyboard screens.'); ?></p>
        </div>

        <p class="arcade-subtext">Retro-futurist lab mode: online</p>
    </div>

    <?php
    $ai_film_club_watch_url = 'https://www.youtube.com/watch?v=f2MdY3qcxt8';
    $ai_film_club_embed_url = 'https://www.youtube-nocookie.com/embed/f2MdY3qcxt8';
    $ai_film_club_repo_url = 'https://github.com/suzyeaston/suzyeastonca';
    ?>
    <section class="ai-film-feature crt-block" aria-labelledby="ai-film-feature-title">
        <div class="ai-film-feature__media">
            <p class="ai-film-feature__badge pixel-font"><?php echo esc_html('FEATURED CONVERSATION // ON AIR'); ?></p>
            <div class="ai-film-feature__embed-wrap">
                <iframe
                    src="<?php echo esc_url($ai_film_club_embed_url); ?>"
                    title="<?php echo esc_attr('AI Film Club fireside chat with Mayumi Rollings'); ?>"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen>
                </iframe>
            </div>
            <div class="ai-film-feature__meta" aria-label="AI Film Club metadata">
                <p class="ai-film-feature__meta-label pixel-font"><?php echo esc_html('SESSION INFO'); ?></p>
                <ul class="ai-film-feature__meta-list">
                    <li><?php echo esc_html('AI Film Club'); ?></li>
                    <li><?php echo esc_html('With Mayumi Rollings'); ?></li>
                    <li><?php echo esc_html('Creative Tech'); ?></li>
                    <li><?php echo esc_html('Vancouver / ASMR Lab'); ?></li>
                </ul>
            </div>
        </div>
        <div class="ai-film-feature__copy">
            <p class="ai-film-feature__kicker pixel-font"><?php echo esc_html('LATEST SIGNAL // AI FILM CLUB'); ?></p>
            <h2 id="ai-film-feature-title" class="pixel-font"><?php echo esc_html('AI Film Club fireside chat: building tools, not just prompts'); ?></h2>
            <p><?php echo esc_html('I joined Mayumi Rollings for an AI Film Club fireside chat about the middle ground between prompting and building: ASMR Lab, Gastown/Vancouver scene experiments, procedural audio, creator control, and why I started making my own tools instead of waiting for a platform to fit.'); ?></p>
            <p class="ai-film-feature__note"><?php echo esc_html('Part film experiment, part product prototype, part “fine, I’ll build it myself.”'); ?></p>
            <div class="ai-film-feature__actions" aria-label="AI Film Club and lab links">
                <a class="pixel-button" href="<?php echo esc_url($ai_film_club_watch_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('Watch the fireside chat'); ?></a>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/asmr-lab/')); ?>"><?php echo esc_html('Explore ASMR Lab'); ?></a>
                <a class="pixel-button" href="<?php echo esc_url($ai_film_club_repo_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html('View source on GitHub'); ?></a>
            </div>
        </div>
    </section>

    <section class="selected-work crt-block" aria-labelledby="selected-work-title">
        <h2 id="selected-work-title" class="pixel-font">Featured builds</h2>
        <p class="selected-work__intro">Projects with a practical point of view: experiments that ship, teach, and stay useful.</p>
        <div class="selected-work__grid">
            <article class="selected-work__card">
                <h3 class="pixel-font">Gastown Simulator</h3>
                <p>First-person Vancouver prototype using browser rendering, civic/open-data world files, route anchors, weather/time-of-day controls, and iterative product design.</p>
                <p class="selected-work__tags" aria-label="Gastown Simulator technology tags"><span>Three.js</span><span>Civic data</span><span>Worldbuilding</span></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/page-gastown-sim/')); ?>">Enter Gastown</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">Track Analyzer</h3>
                <p>AI feedback tool for musicians: upload a track, get practical mix notes, and move faster from “something&rsquo;s off” to “that&rsquo;s the problem.”</p>
                <p class="selected-work__tags" aria-label="Track Analyzer technology tags"><span>AI</span><span>Audio</span><span>Musician tools</span></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/suzys-track-analyzer/')); ?>">Analyze a Track</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">Lousy Outages</h3>
                <p>Retro terminal outage tracker for modern service chaos: status clarity, provider feeds, alert hooks, and public utility.</p>
                <p class="selected-work__tags" aria-label="Lousy Outages technology tags"><span>APIs</span><span>Monitoring</span><span>WordPress</span></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/lousy-outages/')); ?>">View Outages</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">ASMR Lab / Rain City Experiments</h3>
                <p>Procedural audio-visual experiments where storyboards, synths, browser visuals, and AI prompts meet in the weird part of the lab.</p>
                <p class="selected-work__tags" aria-label="ASMR Lab technology tags"><span>AI film</span><span>Procedural audio</span><span>Creative tools</span></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/asmr-lab/')); ?>">Explore the Lab</a>
            </article>
            <article class="selected-work__card">
                <h3 class="pixel-font">Albini Q&amp;A</h3>
                <p>An experimental voice-and-attitude-driven creative app inspired by Steve Albini: part tribute, part interactive web experiment, part chaos-tested music-tech artifact.</p>
                <p class="selected-work__tags" aria-label="Albini Q and A technology tags"><span>AI</span><span>Music</span><span>Web experiment</span></p>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/albini-qa/')); ?>">Open Albini Q&amp;A</a>
            </article>
        </div>
    </section>

    <section class="skills-home crt-block" aria-labelledby="skills-home-title">
        <h2 id="skills-home-title" class="pixel-font">Where I'm useful</h2>
        <ul class="skills-home__list">
            <li>QA automation and release confidence</li>
            <li>IT operations, identity, endpoint, and SaaS troubleshooting</li>
            <li>Python, PowerShell, and JavaScript automation</li>
            <li>Practical AI tools and internal workflows</li>
            <li>Production debugging with logs, calm, and receipts</li>
            <li>Music, audio, and creative web experiments</li>
        </ul>
    </section>

    <section class="music-world crt-block" aria-labelledby="music-world-title">
        <h2 id="music-world-title" class="pixel-font">The other signal chain</h2>
        <p>The same brain behind the systems also writes songs, makes noisy little films, talks shop, and occasionally turns Vancouver rain into a production aesthetic.</p>
        <p class="home-section-legend-links" aria-label="Music and media links">
            <a href="https://suzyeaston.bandcamp.com" target="_blank" rel="noopener noreferrer">Bandcamp</a>
            <span aria-hidden="true">//</span>
            <a href="https://soundcloud.com/suzyeaston" target="_blank" rel="noopener noreferrer">SoundCloud</a>
            <span aria-hidden="true">//</span>
            <a href="https://www.youtube.com/@suzyeaston" target="_blank" rel="noopener noreferrer">YouTube</a>
            <span aria-hidden="true">//</span>
            <a href="https://instagram.com/officialsuzyeaston" target="_blank" rel="noopener noreferrer">Instagram</a>
            <span aria-hidden="true">//</span>
            <a href="<?php echo esc_url(home_url('/podcast/')); ?>">Podcast</a>
        </p>
    </section>

    <section class="collab-invite-home crt-block" aria-labelledby="collab-invite-title">
        <h2 id="collab-invite-title" class="pixel-font">Available for senior roles, contract work, and strange useful builds</h2>
        <p>I’m open to senior technical roles, contract QA/automation work, practical AI prototypes, and debugging projects where the system is messy and someone needs to make the thing make sense.</p>
        <div class="collab-invite-home__actions">
            <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>" class="pixel-button">Work with Suzy</a>
            <a href="<?php echo esc_url('https://www.linkedin.com/in/suzyeaston/'); ?>" target="_blank" rel="noopener noreferrer" class="pixel-button">LinkedIn</a>
            <a href="mailto:suzyeaston@icloud.com?subject=Work%20Inquiry" class="pixel-button">Email Suzy</a>
        </div>
    </section>

    <?php
    $visitor_data = include get_template_directory() . '/visitor-tracker.php';
    $total = isset($visitor_data['count']) ? (int) $visitor_data['count'] : 0;
    $total = max(0, $total);
    ?>
    <section class="utility-nav-home crt-block" aria-labelledby="utility-nav-title">
        <h2 id="utility-nav-title" class="pixel-font">Lab activity</h2>
        <p class="utility-nav-home__counter"><?php echo esc_html(sprintf('👁️ %d lab visits logged.', $total)); ?></p>
        <p class="utility-nav-home__mini-links"><a href="<?php echo esc_url(home_url('/projects/')); ?>">Projects</a> // <a href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>">Work with Suzy</a> // <a href="<?php echo esc_url(home_url('/bio/')); ?>">Bio</a></p>
    </section>
</main>

<?php get_footer(); ?>

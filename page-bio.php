<?php
/*
Template Name: Bio Page
*/
get_header();

$hero_actions = array(
    array('label' => 'Work With Suzy', 'url' => '/work-with-suzy/'),
    array('label' => 'Projects', 'url' => '/projects/'),
    array('label' => 'Email Suzy', 'url' => 'mailto:suzyeaston@icloud.com?subject=Bio%20page%20inquiry'),
    array('label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/suzyeaston/'),
    array('label' => 'Bandcamp', 'url' => 'https://suzyeaston.bandcamp.com'),
);

$main_cards = array(
    array('label' => 'SIDE A', 'title' => 'Noise, nerve, and timing', 'body' => 'Before tech became the main stage, I played bass in Vancouver bands including Minto/The Smokes and Seafoam, toured across Canada, appeared on MuchMusic, and recorded in Chicago with Steve Albini. Music taught me how to troubleshoot under pressure, collaborate with intense people, trust my ear, and keep the whole thing moving when the signal gets messy.'),
    array('label' => 'SIDE B', 'title' => 'Systems under pressure', 'body' => 'I\'ve spent 12+ years working across QA automation, IT operations, support engineering, SaaS platforms, identity, endpoint/security, cloud-connected infrastructure, APIs, and production troubleshooting. I build tests, scripts, alerts, docs, dashboards, and workflows that make fragile systems easier to understand and harder to break.'),
    array('label' => 'SIDE C', 'title' => 'The public lab', 'body' => 'My site is part portfolio, part arcade cabinet, part notebook, part product lab. I use it to build in public with WordPress/PHP, JavaScript, AI-assisted workflows, audio experiments, civic/open-data ideas, and Vancouver-flavoured tools that are strange enough to be memorable and useful enough to keep shipping.'),
    array('label' => 'SIDE D', 'title' => 'Vancouver signal', 'body' => 'A lot of my work starts with the city around me: transit, venues, small businesses, housing pressure, weird outages, civic data, rain, noise, and the feeling that useful tools should have personality. I\'m interested in technology that helps real people navigate messy systems — not just shinier dashboards for people who already have too many dashboards.'),
);
?>
<main id="main-content">
    <section class="bio-page" aria-label="Bio page">
        <div class="bio-shell">
            <header class="bio-hero">
                <p class="bio-kicker"><?php echo esc_html( 'VANCOUVER SIGNAL CHAIN // MUSIC • SYSTEMS • AI' ); ?></p>
                <h1 class="bio-title"><?php echo esc_html( 'SUZY EASTON' ); ?></h1>
                <p class="bio-subtitle"><?php echo esc_html( 'Musician. Creative technologist. Senior technical generalist. Builder of strange, useful systems.' ); ?></p>
                <p class="bio-intro"><?php echo esc_html( 'I’m a Vancouver-born musician and technical systems fixer. I came up through piano lessons, open mics, recording arts, touring vans, bass amps, and studio floors — places where you learn fast, listen harder, and make the thing work even when the room is loud.' ); ?></p>
                <p class="bio-intro"><?php echo esc_html( 'That same instinct now runs through my technical work: QA automation, IT operations, cloud-connected systems, identity/security, SaaS troubleshooting, APIs, production debugging, and practical AI tools. The through-line is simple: turn chaos into signal, make the useful thing work, and get a little louder when the moment calls for it.' ); ?></p>
                <div class="bio-status-strip" role="status" aria-label="Current availability"><?php echo esc_html( 'AVAILABLE FOR: senior technical roles • contract builds • QA automation • WordPress/PHP • practical AI prototypes • weird bug triage' ); ?></div>
                <div class="bio-terminal-strip" aria-label="Operator metadata">
                    <span><?php echo esc_html( 'LOCATION: VANCOUVER' ); ?></span>
                    <span><?php echo esc_html( 'MODE: BUILDING' ); ?></span>
                    <span><?php echo esc_html( 'SIGNAL: ONLINE' ); ?></span>
                </div>
                <div class="bio-actions" aria-label="Primary actions">
                    <?php foreach ( $hero_actions as $action ) : ?>
                        <a class="bio-button" href="<?php echo esc_url( $action['url'] ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
                    <?php endforeach; ?>
                </div>
            </header>

            <section class="bio-grid" aria-label="Background and technical profile">
                <?php foreach ( $main_cards as $card ) : ?>
                    <article class="bio-card">
                        <p class="bio-card__label"><?php echo esc_html( $card['label'] ); ?></p>
                        <h2 class="bio-card__title"><?php echo esc_html( $card['title'] ); ?></h2>
                        <p class="bio-card__body"><?php echo esc_html( $card['body'] ); ?></p>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="bio-proof-panel" aria-labelledby="bio-proof-title">
                <div class="bio-proof-panel__header">
                    <h2 id="bio-proof-title"><?php echo esc_html( 'PROOF OF SIGNAL' ); ?></h2>
                    <p><?php echo esc_html( 'A few receipts from the archive — music, press, radio, and public builds.' ); ?></p>
                </div>
                <div class="bio-proof-panel__grid">
                    <article class="bio-proof-card"><p class="bio-proof-card__label">PRESS CLIPPING</p><h3 class="bio-proof-card__title">Minto finally gets serious</h3><p class="bio-proof-card__body">The Georgia Straight covered Minto’s shift from The Smokes into a harder-working Vancouver rock band, the Steve Albini recording trip in Chicago, and the live-sounding foundation behind Lay It on Me.</p><a class="bio-proof-card__link" href="<?php echo esc_url( 'https://www.straight.com/article-244417/minto-finally-gets-serious' ); ?>" target="_blank" rel="noopener">Read the Georgia Straight article</a></article>
                    <article class="bio-proof-card"><p class="bio-proof-card__label">LOCAL RADIO</p><h3 class="bio-proof-card__title">CiTR airplay</h3><p class="bio-proof-card__body">“Obsolete” and “A Little Louder” both received airplay on UBC’s CiTR — a local signal boost from the campus/community radio world that helped shape so much Vancouver music culture.</p></article>
                    <article class="bio-proof-card"><p class="bio-proof-card__label">SELF-RELEASED TRACK</p><h3 class="bio-proof-card__title">A Little Louder</h3><p class="bio-proof-card__body">A short, loud rock signal flare — part confidence anthem, part reminder to take up space.</p><a class="bio-proof-card__link" href="<?php echo esc_url( 'https://suzyeaston.bandcamp.com' ); ?>" target="_blank" rel="noopener">Listen on Bandcamp</a></article>
                    <article class="bio-proof-card"><p class="bio-proof-card__label">SELF-PRODUCED TRACK</p><h3 class="bio-proof-card__title">Obsolete</h3><p class="bio-proof-card__body">Industrial-tinged glitch pop about feeling obsolete in a digital world — written, performed, recorded, layered, and produced by Suzy.</p><a class="bio-proof-card__link" href="<?php echo esc_url( 'https://suzyeaston.bandcamp.com/album/obsolete' ); ?>" target="_blank" rel="noopener">Listen on Bandcamp</a></article>
                </div>
            </section>

            <section class="bio-builds" aria-labelledby="bio-builds-title"><div class="bio-builds__header"><h2 id="bio-builds-title">SIGNALS IN ROTATION</h2><p>A few active signals from the lab — music, code, prototypes, and civic-tech weirdness all feeding the same machine.</p></div><div class="bio-builds__grid">
                <article class="bio-build"><h3 class="bio-build__title">suzyeaston.ca</h3><p class="bio-build__meta">custom WordPress/PHP portfolio + creative-tech sandbox</p><p class="bio-build__body">Retro UI, REST/API workflows, custom templates, AI-assisted prototyping, audio experiments, and interactive web projects.</p><a class="bio-build__link" href="<?php echo esc_url( '/projects/' ); ?>">Open projects hub</a></article>
                <article class="bio-build"><h3 class="bio-build__title">Gastown Simulator</h3><p class="bio-build__meta">Vancouver browser-world prototype</p><p class="bio-build__body">A first-person Vancouver corridor experiment using civic/open-data flavour, route anchors, weather/time controls, and iterative product design.</p><a class="bio-build__link" href="<?php echo esc_url( '/page-gastown-sim/' ); ?>">Explore prototype</a></article>
                <article class="bio-build"><h3 class="bio-build__title">VanOps Radar / Lousy Outages</h3><p class="bio-build__meta">local ops + status monitoring concepts</p><p class="bio-build__body">Vancouver-first operational signal tools for outages, transit/road/weather context, provider status weirdness, and business continuity.</p><p><a class="bio-build__link" href="<?php echo esc_url( '/vanops-radar/' ); ?>">VanOps Radar</a> <a class="bio-build__link" href="<?php echo esc_url( '/lousy-outages/' ); ?>">Lousy Outages</a></p></article>
                <article class="bio-build"><h3 class="bio-build__title">Track Analyzer / ASMR Lab / AI Film Club</h3><p class="bio-build__meta">AI/audio/video experiments</p><p class="bio-build__body">Creative tooling for music feedback, procedural sound, synchronized visuals, storytelling, and the “fine, I’ll build it myself” side of AI.</p><p><a class="bio-build__link" href="<?php echo esc_url( '/suzys-track-analyzer/' ); ?>">Track Analyzer</a> <a class="bio-build__link" href="<?php echo esc_url( '/asmr-lab/' ); ?>">ASMR Lab</a></p></article>
            </div></section>

            <section class="bio-useful" aria-labelledby="bio-useful-title"><h2 id="bio-useful-title">WHERE I’M USEFUL</h2><div class="bio-useful__grid"><p class="bio-useful__item">Weird bug triage</p><p class="bio-useful__item">QA automation and release confidence</p><p class="bio-useful__item">WordPress/PHP and JavaScript builds</p><p class="bio-useful__item">SaaS, identity, endpoint, and cloud troubleshooting</p><p class="bio-useful__item">Practical AI prototypes with reviewable outputs</p><p class="bio-useful__item">Dashboards, alerts, workflows, and operational cleanup</p><p class="bio-useful__item">Translating between technical and non-technical humans</p><p class="bio-useful__item">Making cursed systems less cursed</p></div></section>

            <section class="bio-cta-panel" aria-labelledby="bio-cta-title"><h2 class="bio-cta-panel__title" id="bio-cta-title">WORK WITH THE OPERATOR</h2><p class="bio-cta-panel__body">If you need someone who can debug the weird issue, automate the boring thing, test the fragile workflow, clean up the dashboard, explain the system, or make the project feel less cursed, I’m probably your person.</p><div class="bio-cta-panel__actions"><a class="bio-button" href="<?php echo esc_url( 'mailto:suzyeaston@icloud.com?subject=Work%20With%20Suzy' ); ?>">Email Suzy</a><a class="bio-button" href="<?php echo esc_url( '/work-with-suzy/' ); ?>">Work With Suzy</a><a class="bio-button" href="<?php echo esc_url( '/projects/' ); ?>">Projects</a><a class="bio-button" href="<?php echo esc_url( 'https://github.com/suzyeaston' ); ?>" target="_blank" rel="noopener">GitHub</a></div></section>
        </div>
    </section>
</main>

<?php get_footer(); ?>

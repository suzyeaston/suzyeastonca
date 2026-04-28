<?php
/**
 * Template Name: Projects
 */

get_header();

$project_url = static function (string $slug, string $fallback_path = ''): string {
    $page = get_page_by_path($slug);

    if ($page instanceof WP_Post) {
        return get_permalink($page);
    }

    if ($fallback_path === '') {
        $fallback_path = '/' . trim($slug, '/') . '/';
    }

    return home_url($fallback_path);
};
?>
<main id="main-content" class="projects-page">
    <div class="projects-shell">
        <section class="projects-hero projects-section" aria-labelledby="projects-title">
            <p class="projects-eyebrow pixel-font">Build log // useful weird systems</p>
            <h1 id="projects-title" class="retro-title glow-lite">Projects</h1>
            <p class="projects-lead">A field guide to the tools, prototypes, dashboards, music-tech experiments, civic builds, and strange useful systems I&rsquo;m building in public.</p>
            <p>Some are polished enough to use. Some are live prototypes. Some are weird little labs that teach me what to build next. The common thread: practical systems, creative taste, and enough technical grit to make the thing actually work.</p>
            <div class="projects-actions" role="group" aria-label="Projects page jumps and primary links">
                <a class="pixel-button" href="#featured-projects">Featured builds</a>
                <a class="pixel-button" href="#creative-labs">Creative labs</a>
                <a class="pixel-button" href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>">Work with Suzy</a>
            </div>
        </section>

        <section id="featured-projects" class="projects-section" aria-labelledby="featured-projects-title">
            <div class="projects-section-header">
                <h2 id="featured-projects-title" class="pixel-font">Featured builds</h2>
                <p>The main projects I&rsquo;d point a recruiter, client, collaborator, or curious Vancouver human toward first.</p>
            </div>
            <div class="projects-grid projects-grid--featured">
                <article class="projects-card projects-card--featured">
                    <h3 class="pixel-font">VanOps Radar</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Current product experiment</p>
                    <p>A Vancouver-first operations dashboard concept for small and medium businesses that need clearer signals around road access, transit, weather, utilities, events, and business continuity.</p>
                    <p class="projects-card__proof"><strong>What it proves:</strong> Civic product thinking, dashboards, local operations, SMB positioning, and practical web development.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('vanops-radar')); ?>">Open VanOps Radar</a>
                    </div>
                </article>

                <article class="projects-card projects-card--featured">
                    <h3 class="pixel-font">Gastown Simulator</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Live prototype / desktop-first</p>
                    <p>A first-person Vancouver corridor prototype from Waterfront Station toward Water Street and the Steam Clock, using browser rendering, civic/open-data world files, route anchors, weather/time-of-day controls, and iterative product design.</p>
                    <p class="projects-card__proof"><strong>What it proves:</strong> Three.js-style worldbuilding, civic data pipelines, browser interaction, debugging, and build-in-public persistence.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('page-gastown-sim', '/page-gastown-sim/')); ?>">Enter Gastown</a>
                    </div>
                </article>

                <article class="projects-card projects-card--featured">
                    <h3 class="pixel-font">Lousy Outages</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Active monitoring lab</p>
                    <p>A retro internet/provider outage tracker and status-page experiment with provider feeds, alert ideas, public utility energy, and chaos-monitoring personality.</p>
                    <p class="projects-card__proof"><strong>What it proves:</strong> Status dashboards, monitoring UX, REST/plugin architecture, provider signal handling, and operational thinking.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('lousy-outages')); ?>">View Lousy Outages</a>
                    </div>
                </article>

                <article class="projects-card projects-card--featured">
                    <h3 class="pixel-font">Track Analyzer</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Live music-tech tool</p>
                    <p>An AI-assisted feedback tool for musicians: upload a track, get practical mix notes, and move faster from &ldquo;something feels off&rdquo; to &ldquo;that is probably the problem.&rdquo;</p>
                    <p class="projects-card__proof"><strong>What it proves:</strong> AI-assisted workflows, audio/product thinking, upload UX, OpenAI integration, and musician-first design.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('suzys-track-analyzer', '/suzys-track-analyzer/')); ?>">Analyze a Track</a>
                    </div>
                </article>
            </div>
        </section>

        <section id="creative-labs" class="projects-section" aria-labelledby="creative-labs-title">
            <div class="projects-section-header">
                <h2 id="creative-labs-title" class="pixel-font">Creative labs</h2>
                <p>Experiments where music, AI, browser visuals, retro interfaces, and Vancouver rain all start yelling into the same effects pedal.</p>
            </div>
            <div class="projects-grid">
                <article class="projects-card">
                    <h3 class="pixel-font">ASMR Lab / Rain City Experiments</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Rebuild in progress</p>
                    <p>Procedural audio-visual experiments for storyboarded micro-scenes, browser foley, synchronized timelines, Vancouver route presets, and AI-assisted creative control.</p>
                    <p class="projects-card__proof"><strong>What it proves:</strong> Audio engines, visual timelines, prompt-to-structure workflows, creative tooling, and experimental UX.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('asmr-lab')); ?>">Explore ASMR Lab</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">Albini Q&amp;A</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Music-tech experiment</p>
                    <p>An interactive creative app inspired by Steve Albini&rsquo;s public voice and engineering ethos: part tribute, part music-technology playground, part quote-grounded interface experiment.</p>
                    <p class="projects-card__proof"><strong>What it proves:</strong> Voice, archives, UI personality, quote handling, and creative AI boundaries.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('albini-qa')); ?>">Open Albini Q&amp;A</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">Riff Generator</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Small creative utility</p>
                    <p>A quick creative prompt machine for music ideas, riffs, and songwriting sparks.</p>
                    <p class="projects-card__proof"><strong>What it proves:</strong> Small-tool thinking, playful UX, and musician-focused utility.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('riff-generator')); ?>">Generate a Riff</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">Arcade / Canucks Puck Bash</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Playable web toy</p>
                    <p>Retro arcade energy, hockey chaos, and browser-game experimentation living inside the same custom WordPress universe.</p>
                    <p class="projects-card__proof"><strong>What it proves:</strong> Canvas/game UI thinking, interaction loops, fun as product glue, and Vancouver sports nonsense in the best way.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('arcade')); ?>">Open Arcade</a>
                    </div>
                </article>
            </div>
        </section>

        <section id="vancouver-builds" class="projects-section" aria-labelledby="vancouver-builds-title">
            <div class="projects-section-header">
                <h2 id="vancouver-builds-title" class="pixel-font">Vancouver, civic, and community builds</h2>
                <p>Local-first experiments and pages connected to Vancouver, civic life, community, advocacy, events, and useful public information.</p>
            </div>
            <div class="projects-grid">
                <article class="projects-card">
                    <h3 class="pixel-font">VanOps Radar</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Local disruption intelligence</p>
                    <p>A Vancouver SMB operations dashboard concept for local disruption intelligence and business continuity.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('vanops-radar')); ?>">Open VanOps Radar</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">Gastown Simulator</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Browser world prototype</p>
                    <p>A browser-based Vancouver worldbuilding prototype using local route logic and civic/open-data thinking.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('page-gastown-sim', '/page-gastown-sim/')); ?>">Enter Gastown</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">Advocacy updates</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Civic notebook</p>
                    <p>A place for tenant-rights, DTES, city council, and civic advocacy notes as Suzy keeps building a stronger public voice in Vancouver.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('advocacy')); ?>">Read advocacy updates</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">Coffee for Builders</h3>
                    <p class="projects-card__status"><strong>Status:</strong> Community experiment</p>
                    <p>A lightweight invitation for local builders, makers, founders, musicians, technologists, and practical weirdos to connect without networking-event cosplay.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('coffee-for-builders')); ?>">Coffee for Builders</a>
                    </div>
                </article>
            </div>
        </section>

        <section class="projects-section" aria-labelledby="more-signals-title">
            <div class="projects-section-header">
                <h2 id="more-signals-title" class="pixel-font">More signals</h2>
            </div>
            <div class="projects-mini-grid">
                <a class="projects-card" href="<?php echo esc_url($project_url('music-releases')); ?>"><strong>Music Releases</strong></a>
                <a class="projects-card" href="<?php echo esc_url($project_url('podcast')); ?>"><strong>Podcast</strong></a>
                <a class="projects-card" href="<?php echo esc_url(home_url('/bio/')); ?>"><strong>Bio</strong></a>
                <a class="projects-card" href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>"><strong>Work With Suzy</strong></a>
                <a class="projects-card" href="https://github.com/suzyeaston/suzyeastonca" target="_blank" rel="noopener noreferrer"><strong>GitHub source</strong></a>
            </div>
        </section>

        <section class="projects-section" aria-labelledby="how-i-build-title">
            <div class="projects-section-header">
                <h2 id="how-i-build-title" class="pixel-font">How I build</h2>
                <p>I build in public, use the site as a working lab, and treat prototypes as proof. A project does not need to be perfect to be useful &mdash; it needs to reveal the next problem clearly.</p>
            </div>
            <div class="projects-method-grid">
                <article class="projects-card"><h3 class="pixel-font">Practical first</h3><p>Tools should solve a real workflow or teach something concrete.</p></article>
                <article class="projects-card"><h3 class="pixel-font">Weird is allowed</h3><p>Personality is not a bug. It helps people remember the thing.</p></article>
                <article class="projects-card"><h3 class="pixel-font">Systems matter</h3><p>The fun layer still needs sane data, caching, APIs, fallbacks, and debugging.</p></article>
                <article class="projects-card"><h3 class="pixel-font">Ship, learn, revise</h3><p>The point is momentum: build the smallest useful version, test it, then make it sharper.</p></article>
            </div>
        </section>

        <section class="projects-final-cta projects-section" aria-labelledby="projects-cta-title">
            <h2 id="projects-cta-title" class="pixel-font">Want to build something useful and slightly dangerous?</h2>
            <p>I&rsquo;m open to senior technical roles, contract web development, QA/automation work, custom WordPress builds, dashboards, practical AI prototypes, and weird bug triage.</p>
            <div class="projects-actions" role="group" aria-label="Work with Suzy and contact links">
                <a class="pixel-button" href="<?php echo esc_url(home_url('/work-with-suzy/')); ?>">Work with Suzy</a>
                <a class="pixel-button" href="mailto:suzyeaston@icloud.com?subject=Project%20Inquiry">Email Suzy</a>
                <a class="pixel-button" href="https://github.com/suzyeaston/suzyeastonca" target="_blank" rel="noopener noreferrer">View GitHub</a>
            </div>
        </section>
    </div>
</main>
<?php
get_footer();

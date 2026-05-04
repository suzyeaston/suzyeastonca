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
            <p class="projects-eyebrow projects-hero__eyebrow pixel-font">Build log</p>
            <h1 id="projects-title" class="projects-hero__title">Projects</h1>
            <p class="projects-lead projects-hero__lead">Things I&rsquo;m building, testing, breaking, fixing, and slowly turning into something useful. Some are polished enough to show. Some are still experiments. That is kind of the point.</p>
            <div class="projects-actions" role="group" aria-label="Projects page jumps and primary links">
                <a class="pixel-button" href="#featured-projects">Featured</a>
                <a class="pixel-button" href="#interactive-projects">Interactive</a>
                <a class="pixel-button" href="#music-ai-projects">Music / AI</a>
            </div>
        </section>

        <section id="featured-projects" class="projects-section" aria-labelledby="featured-projects-title">
            <div class="projects-section-header">
                <h2 id="featured-projects-title" class="pixel-font">Featured</h2>
                <p>The lead build I want people to see first.</p>
            </div>
            <div class="projects-grid projects-grid--featured">
                <article class="projects-card projects-card--featured">
                    <h3 class="pixel-font">Lousy Outages</h3>
                    <p>A WordPress plugin I&rsquo;m building for outage monitoring, alert preferences, community reports, and early-warning signals.</p>
                    <ul class="projects-badges" aria-label="Lousy Outages details">
                        <li>WordPress plugin</li><li>IT ops</li><li>REST API</li><li>Early warning</li><li>Subscriber alerts</li>
                    </ul>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('lousy-outages')); ?>">Open project</a>
                        <a class="pixel-button" href="https://github.com/suzyeaston/suzyeastonca" target="_blank" rel="noopener noreferrer">View GitHub</a>
                    </div>
                </article>
            </div>
        </section>

        <section id="interactive-projects" class="projects-section" aria-labelledby="interactive-projects-title">
            <div class="projects-section-header">
                <h2 id="interactive-projects-title" class="pixel-font">Interactive</h2>
            </div>
            <div class="projects-grid">
                <article class="projects-card">
                    <h3 class="pixel-font">Gastown Simulator</h3>
                    <p>A browser-based Vancouver prototype with maps, routes, civic data, and game-style navigation.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('page-gastown-sim', '/page-gastown-sim/')); ?>">Open project</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">Track Analyzer</h3>
                    <p>An AI-assisted music feedback tool for rough mixes and songwriting notes.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('suzys-track-analyzer', '/suzys-track-analyzer/')); ?>">Open project</a>
                    </div>
                </article>
            </div>
        </section>

        <section id="music-ai-projects" class="projects-section" aria-labelledby="music-ai-projects-title">
            <div class="projects-section-header">
                <h2 id="music-ai-projects-title" class="pixel-font">Music / AI</h2>
            </div>
            <div class="projects-grid">
                <article class="projects-card">
                    <h3 class="pixel-font">Riff Generator</h3>
                    <p>A small music idea generator for quick prompts, riffs, and creative nudges.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('riff-generator')); ?>">Open project</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">ASMR Lab</h3>
                    <p>A playful audio experiment for generated sound textures and web-based ambience.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('asmr-lab')); ?>">Open project</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">AI/Audio Experiments</h3>
                    <p>Small experiments with generated sound, web visuals, and AI-assisted storytelling.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('albini-qa')); ?>">Open project</a>
                    </div>
                </article>
            </div>
        </section>
    </div>
</main>
<?php
get_footer();

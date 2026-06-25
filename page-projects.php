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
            <p class="projects-eyebrow projects-hero__eyebrow pixel-font">build log</p>
            <h1 id="projects-title" class="projects-hero__title">Projects</h1>
            <p class="projects-lead projects-hero__lead">Music tools, practical AI prototypes, outage dashboards, Vancouver web builds, and weird useful experiments. Some are polished. Some are still mutating in public.</p>
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
                    <p>A WordPress plugin for outage monitoring, alert preferences, community reports, and early warning signals. Built for the gap between ‘all systems operational’ and everyone yelling in Slack.</p>
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

        <section id="interactive-projects" class="projects-section projects-section--selected" aria-labelledby="interactive-projects-title">
            <h2 class="projects-selected-title pixel-font">Selected Projects</h2>
            <div class="projects-section-header">
                <h2 id="interactive-projects-title" class="pixel-font">Interactive</h2>
            </div>
            <div class="projects-grid">
                <article class="projects-card">
                    <h3 class="pixel-font">Gastown Simulator</h3>
                    <p>A playable Vancouver corridor using route logic, civic data, street mood, and arcade-map browser-world obsession.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('gastown-sim', '/gastown-sim/')); ?>">Open project</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">Track Analyzer</h3>
                    <p>MP3 feedback for rough mixes, lyrics, structure, feel, and the part of the song that is almost working.</p>
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
                    <p>A tiny prompt machine for riffs, song starts, and getting unstuck before the song gets precious.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('riff-generator')); ?>">Open project</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">ASMR Lab</h3>
                    <p>Procedural sound textures, web ambience, and the soft-machine experiments feeding the larger lab.</p>
                    <div class="projects-card__actions">
                        <a class="pixel-button" href="<?php echo esc_url($project_url('asmr-lab')); ?>">Open project</a>
                    </div>
                </article>

                <article class="projects-card">
                    <h3 class="pixel-font">Albini QA / Recording Oracle</h3>
                    <p>A quote-backed recording prompt machine for direct, unsentimental mix and arrangement questions.</p>
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

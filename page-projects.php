<?php
/**
 * Template Name: Projects
 *
 * A deliberately curated, source-backed portfolio. Status labels describe
 * what exists today, not features that might be built later.
 */
get_header();

$project_url = static function ( string $slug, string $fallback_path = '' ): string {
    $page = get_page_by_path( $slug );
    if ( $page instanceof WP_Post ) {
        return get_permalink( $page );
    }
    return home_url( '' !== $fallback_path ? $fallback_path : '/' . trim( $slug, '/' ) . '/' );
};

/**
 * One card renderer keeps badges, links, status and accessible headings
 * consistent for new builds and older experiments.
 */
$render_project = static function ( array $project, bool $featured = false ): void {
    ?>
    <article class="projects-card<?php echo $featured ? ' projects-card--featured' : ''; ?>">
        <p class="projects-card__status"><?php echo esc_html( $project['status'] ); ?></p>
        <h3 class="projects-card__title"><?php echo esc_html( $project['title'] ); ?></h3>
        <p><?php echo esc_html( $project['description'] ); ?></p>
        <?php if ( ! empty( $project['detail'] ) ) : ?>
            <p class="projects-card__proof"><?php echo esc_html( $project['detail'] ); ?></p>
        <?php endif; ?>
        <ul class="projects-badges" aria-label="<?php echo esc_attr( $project['title'] . ' technologies and focus' ); ?>">
            <?php foreach ( $project['tags'] as $tag ) : ?>
                <li><?php echo esc_html( $tag ); ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="projects-card__actions">
            <?php foreach ( $project['links'] as $link ) : ?>
                <a class="pixel-button" href="<?php echo esc_url( $link['url'] ); ?>"<?php echo ! empty( $link['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $link['label'] ); ?></a>
            <?php endforeach; ?>
        </div>
    </article>
    <?php
};

$featured_projects = [
    [
        'title' => 'SUZY//TOAST · The Appliance Latent Space',
        'status' => 'Performed October 29, 2026 · still evolving',
        'description' => 'A musician-led AI performance instrument, played at Futureproof Festival at Vancouver’s H.R. MacMillan Space Centre. Live vocals, generative textures and independently captured loops turned a small laptop into an orchestra.',
        'detail' => 'The set ran on an 8 GB MacBook Pro using Magenta RealTime 2 Small, a native Swift Audio Unit host, a Python bridge, JavaScript/Web Audio and hands-on PCM looping. The physical toaster remained a prototype, not the instrument on stage.',
        'tags' => [ 'Live performance', 'Magenta RealTime', 'Swift', 'Python', 'Web Audio', 'Looping' ],
        'links' => [
            [ 'label' => 'Explore performance system', 'url' => 'https://github.com/suzyeaston/suzy-ai', 'external' => true ],
            [ 'label' => 'Open browser instrument', 'url' => $project_url( 'appliance-latent-space' ) ],
        ],
    ],
    [
        'title' => 'Gastown Simulator',
        'status' => 'Playable desktop prototype · evolving',
        'description' => 'A walkable, filmable version of Vancouver’s Waterfront Station, Water Street and Steam Clock corridor. Explore by foot or camera, meet fictional characters, experiment with weather, and use the city as a creative scene.',
        'detail' => 'Built with Three.js/WebGL, Vancouver open-data references, custom geometry, procedural ambience and extensive regression testing. Stylized geography, not a surveyed digital twin. Began before the November AI Film Club film.',
        'tags' => [ 'Three.js', 'WebGL', 'Civic data', 'World-building', 'Interactive filmmaking' ],
        'links' => [
            [ 'label' => 'Enter Gastown', 'url' => $project_url( 'gastown-sim' ) ],
            [ 'label' => 'Read build notes', 'url' => 'https://github.com/suzyeaston/suzyeastonca/blob/main/docs/gastown-authoring.md', 'external' => true ],
        ],
    ],
    [
        'title' => 'Lousy Outages',
        'status' => 'Live monitoring dashboard',
        'description' => 'A WordPress-native incident console tracking official cloud, AI and developer-service status feeds. It separates provider-confirmed incidents, degradation, advisories and silence instead of presenting everything as “all operational.”',
        'detail' => 'Provider ingestion, incident history and lifecycle logic, JSON/RSS views, diagnostics and alerting foundations. Experimental subscription and entitlement features are not represented as universally enabled.',
        'tags' => [ 'PHP / WordPress', 'REST API', 'RSS', 'Incident lifecycle', 'IT operations' ],
        'links' => [
            [ 'label' => 'Open status console', 'url' => $project_url( 'lousy-outages' ) ],
            [ 'label' => 'View plugin source', 'url' => 'https://github.com/suzyeaston/suzyeastonca/tree/main/lousy-outages', 'external' => true ],
        ],
    ],
];

$project_sections = [
    [
        'id' => 'vancouver-projects',
        'title' => 'Vancouver / Civic Signals',
        'intro' => 'Maps, local discovery and cultural infrastructure. Source-backed observations, not invented completeness.',
        'items' => [
            [
                'title' => 'AFTERIMAGE / Vancouver',
                'status' => 'Experimental city + creative-work atlas',
                'description' => 'A Vancouver field guide to creative employers, sourced job leads, civic developments and community networks. A zoomable street map helps connect film, VFX, games, music tech and engineering without pretending every listing is current.',
                'tags' => [ 'MapLibre', 'OpenFreeMap', 'Python', 'Civic research', 'Source provenance' ],
                'links' => [
                    [ 'label' => 'Explore atlas', 'url' => $project_url( 'afterimage' ) ],
                    [ 'label' => 'View source', 'url' => 'https://github.com/suzyeaston/afterimage-vancouver', 'external' => true ],
                ],
            ],
            [
                'title' => 'Vancouver Tech Events',
                'status' => 'Public calendar + accumulating archive',
                'description' => 'A multi-source local tech-events calendar with historical listings, year and topic filters, and monthly counts. The archive distinguishes captured listings from proof that events occurred.',
                'tags' => [ 'PHP', 'Event aggregation', 'WP-Cron', 'Historical data', 'Source transparency' ],
                'links' => [
                    [ 'label' => 'Open tech calendar', 'url' => $project_url( 'vancouver-tech-events' ) ],
                    [ 'label' => 'Coverage notes', 'url' => 'https://github.com/suzyeaston/suzyeastonca/blob/main/docs/vancouver-tech-events-coverage.md', 'external' => true ],
                ],
            ],
            [
                'title' => 'Vancouver Music & Culture',
                'status' => 'Public cultural directory + calendar',
                'description' => 'A home for independent gigs, experimental sound, music technology, poetry, philosophy, artist talks and smaller Vancouver rooms. Twenty-six curated venue/organizer links plus a growing historical event archive.',
                'detail' => 'Four configured automated source adapters are separate from the larger browse-only venue directory. A linked venue is not a promise of complete ingestion.',
                'tags' => [ 'Local music', 'Independent venues', 'Experimental arts', 'Multi-source research' ],
                'links' => [
                    [ 'label' => 'Explore culture', 'url' => $project_url( 'vancouver-music-culture' ) ],
                    [ 'label' => 'Source audit', 'url' => 'https://github.com/suzyeaston/suzyeastonca/blob/main/docs/vancouver-music-culture-coverage.md', 'external' => true ],
                ],
            ],
        ],
    ],
    [
        'id' => 'music-ai-projects',
        'title' => 'Sound / Moving Images',
        'intro' => 'Music as a system you can play, record, remix or move through. The human musician stays in charge.',
        'items' => [
            [
                'title' => 'Loop Lab',
                'status' => 'Playable browser recording prototype',
                'description' => 'A small tape-machine experiment for recording a first loop, overdubbing locally, arranging layers and exporting a WAV mix. No upload and no AI required to make noise.',
                'tags' => [ 'Web Audio', 'MediaRecorder', 'Looping', 'WAV export' ],
                'links' => [ [ 'label' => 'Open Loop Lab', 'url' => $project_url( 'loop-lab' ) ] ],
            ],
            [
                'title' => 'The Appliance · Browser Instrument',
                'status' => 'Public audiovisual prototype',
                'description' => 'An earlier programmable instrument with notes, chords, rhythm patterns, interactive synthesis, synchronized visuals, local vocal takes and saved scenes. Related to the live TOAST work, but not the same live AI Audio Unit setup.',
                'detail' => 'The browser instrument’s latent/neural controls are disabled; seeded variations are musical transformations, not a running latent model.',
                'tags' => [ 'TypeScript', 'Web Audio', 'Sequencing', 'Canvas', 'Local storage' ],
                'links' => [
                    [ 'label' => 'Play browser version', 'url' => $project_url( 'appliance-latent-space' ) ],
                    [ 'label' => 'View source', 'url' => 'https://github.com/suzyeaston/appliance-latent-space-live', 'external' => true ],
                ],
            ],
            [
                'title' => 'SUZY//PIRATE RADIO',
                'status' => 'Receiver + relay prototype · not on air yet',
                'description' => 'An independent internet-radio experiment for original music, improvised sets, field recordings and conversations. A receiver, source transmitter and relay architecture exist; the public stream is not connected yet.',
                'tags' => [ 'Node.js', 'Python / FFmpeg', 'Streaming', 'Broadcast architecture' ],
                'links' => [
                    [ 'label' => 'Visit receiver', 'url' => $project_url( 'radio' ) ],
                    [ 'label' => 'Read code', 'url' => 'https://github.com/suzyeaston/suzyeastonca/tree/main/radio-station', 'external' => true ],
                ],
            ],
            [
                'title' => 'Skywhale Airways',
                'status' => 'Published collaborative film + WebGL microsite',
                'description' => 'A collaboratively created AI Film Club project and psychedelic airport for time travelers. A scroll-driven Three.js experience uses shader transitions, music-reactive effects, parallax and a downloadable visual artifact lab.',
                'tags' => [ 'Creative collaboration', 'Three.js', 'GLSL', 'Web Audio', 'Vite' ],
                'links' => [
                    [ 'label' => 'Board Skywhale', 'url' => 'https://skywhaleairways.com/', 'external' => true ],
                    [ 'label' => 'Project credits & code', 'url' => 'https://github.com/suzyeaston/skywhale-airways', 'external' => true ],
                ],
            ],
            [
                'title' => 'MACHINE VISIONS',
                'status' => 'Exhibition + publishing framework',
                'description' => 'A gallery system for selected AI-assisted films, stills and process notes, with structured metadata and deliberate publication. A framework in progress, not a claim that every work in the catalog is exhibited.',
                'tags' => [ 'Moving image', 'Publishing', 'Metadata', 'Creative tooling' ],
                'links' => [
                    [ 'label' => 'See framework', 'url' => 'https://github.com/suzyeaston/suzyeastonca/blob/main/docs/ai-art.md', 'external' => true ],
                ],
            ],
        ],
    ],
    [
        'id' => 'systems-projects',
        'title' => 'Local Intelligence / Open Systems',
        'intro' => 'Publishing boundaries, reproducible inputs and real infrastructure underneath the creative work.',
        'items' => [
            [
                'title' => 'SUZY//AI',
                'status' => 'Open-source local-first research',
                'description' => 'A performer-led musical AI environment exploring local models, memory, prompt control, real-time audio and live looping. Human approval shapes what the instrument learns and what enters the mix.',
                'tags' => [ 'Local models', 'Python', 'Swift', 'MIDI', 'Human-in-the-loop' ],
                'links' => [ [ 'label' => 'Explore architecture', 'url' => 'https://github.com/suzyeaston/suzy-ai', 'external' => true ] ],
            ],
            [
                'title' => 'POP//CONTEXT',
                'status' => 'Local evidence pipeline · v0.1',
                'description' => 'Turns a selected media window into inspectable transcript, representative frames and timestamped evidence. The pipeline stops before interpretation; integration into SUZY//AI memory is not automatic.',
                'tags' => [ 'Python', 'Media analysis', 'Local-first', 'Reproducibility' ],
                'links' => [ [ 'label' => 'View pipeline', 'url' => 'https://github.com/suzyeaston/pop-context', 'external' => true ] ],
            ],
            [
                'title' => 'SUZY//WORLD',
                'status' => 'Open-source WordPress knowledge plugin',
                'description' => 'A deliberately published public music knowledge layer with structured artists, albums, review versions, tags and REST endpoints. Private AI context is not dumped into the public database.',
                'tags' => [ 'WordPress plugin', 'MariaDB', 'REST API', 'Publication controls' ],
                'links' => [ [ 'label' => 'Explore repository', 'url' => 'https://github.com/suzyeaston/suzy-world', 'external' => true ] ],
            ],
            [
                'title' => 'suzyeaston.ca / The Workshop',
                'status' => 'Production WordPress platform',
                'description' => 'The custom PHP/JavaScript site running these experiments: music interfaces, civic-data visualizations, event aggregation, status intelligence, GitHub Actions deployments and Git-to-WordPress writing.',
                'tags' => [ 'PHP', 'JavaScript', 'WordPress', 'GitHub Actions', 'CI / QA' ],
                'links' => [
                    [ 'label' => 'Browse source', 'url' => 'https://github.com/suzyeaston/suzyeastonca', 'external' => true ],
                    [ 'label' => 'Read field notes', 'url' => $project_url( 'blog' ) ],
                ],
            ],
        ],
    ],
    [
        'id' => 'experiments-projects',
        'title' => 'Small Instruments / Earlier Experiments',
        'intro' => 'Useful sketches and smaller tools. They still belong in the lab, just not ahead of the newer work.',
        'items' => [
            [
                'title' => 'Track Analyzer',
                'status' => 'Music feedback experiment',
                'description' => 'Upload an MP3 to explore rough-mix feedback around lyrics, arrangement, structure and feel.',
                'tags' => [ 'Music', 'Audio feedback', 'Prototype' ],
                'links' => [ [ 'label' => 'Open analyzer', 'url' => $project_url( 'suzys-track-analyzer' ) ] ],
            ],
            [
                'title' => 'ASMR Lab',
                'status' => 'Procedural audio experiment',
                'description' => 'Browser sound textures and ambient layers for the softer, stranger corners of the music lab.',
                'tags' => [ 'Sound design', 'Web ambience' ],
                'links' => [ [ 'label' => 'Open ASMR Lab', 'url' => $project_url( 'asmr-lab' ) ] ],
            ],
            [
                'title' => 'Riff Generator',
                'status' => 'Songwriting sketch',
                'description' => 'Small prompt-driven riffs and song starts for breaking out of a blank page.',
                'tags' => [ 'Songwriting', 'Creative prompts' ],
                'links' => [ [ 'label' => 'Try generator', 'url' => $project_url( 'riff-generator' ) ] ],
            ],
            [
                'title' => 'Albini QA / Recording Oracle',
                'status' => 'Recording-culture experiment',
                'description' => 'An archival, quote-backed prompt experiment for tough arrangement and recording decisions.',
                'tags' => [ 'Recording', 'Production', 'Archive' ],
                'links' => [ [ 'label' => 'Open oracle', 'url' => $project_url( 'albini-qa' ) ] ],
            ],
        ],
    ],
];
?>
<main id="main-content" class="projects-page">
    <div class="projects-shell">
        <section class="projects-hero projects-section" aria-labelledby="projects-title">
            <p class="projects-eyebrow projects-hero__eyebrow pixel-font">music // systems // worlds</p>
            <h1 id="projects-title" class="projects-hero__title">Projects</h1>
            <p class="projects-lead projects-hero__lead">I used to build songs on stage. Now I also build instruments, simulations, infrastructure and strange useful software. The through-line is still improvisation, and the human stays at the controls.</p>
            <p class="projects-hero__support">A selected map of what’s playable, operating, developing or deliberately experimental. Every card links to an actual project, source or build note.</p>
            <nav class="projects-actions" aria-label="Jump to project collections">
                <a class="pixel-button" href="#featured-projects">Featured</a>
                <a class="pixel-button" href="#vancouver-projects">Vancouver</a>
                <a class="pixel-button" href="#music-ai-projects">Sound / Film</a>
                <a class="pixel-button" href="#systems-projects">Systems</a>
                <a class="pixel-button" href="#experiments-projects">Experiments</a>
            </nav>
        </section>

        <section id="featured-projects" class="projects-section" aria-labelledby="featured-projects-title">
            <div class="projects-section-header">
                <p class="projects-section__kicker">CURRENT SIGNAL / SELECTED WORK</p>
                <h2 id="featured-projects-title" class="pixel-font">Featured</h2>
                <p>Three sides of the same practice: performance, playable worlds and operational systems.</p>
            </div>
            <div class="projects-grid projects-grid--featured">
                <?php foreach ( $featured_projects as $project ) { $render_project( $project, true ); } ?>
            </div>
        </section>

        <?php foreach ( $project_sections as $section ) : ?>
            <section id="<?php echo esc_attr( $section['id'] ); ?>" class="projects-section projects-section--selected" aria-labelledby="<?php echo esc_attr( $section['id'] . '-title' ); ?>">
                <div class="projects-section-header">
                    <p class="projects-section__kicker">SELECTED PROJECTS</p>
                    <h2 id="<?php echo esc_attr( $section['id'] . '-title' ); ?>" class="pixel-font"><?php echo esc_html( $section['title'] ); ?></h2>
                    <p><?php echo esc_html( $section['intro'] ); ?></p>
                </div>
                <div class="projects-grid">
                    <?php foreach ( $section['items'] as $project ) { $render_project( $project ); } ?>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="projects-section projects-final-cta" aria-labelledby="projects-final-title">
            <h2 id="projects-final-title" class="pixel-font">The build log stays open.</h2>
            <p>Some work ships as code. Some work belongs on stage. Some becomes a field note when the experiment is over. The repositories show the systems; the journal shows what happened while making them.</p>
            <div class="projects-actions">
                <a class="pixel-button" href="https://github.com/suzyeaston" target="_blank" rel="noopener noreferrer">Explore GitHub</a>
                <a class="pixel-button" href="<?php echo esc_url( $project_url( 'blog' ) ); ?>">Read Meanwhile</a>
            </div>
        </section>
    </div>
</main>
<?php get_footer();
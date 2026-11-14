<?php
/**
 * Persistent, bounded historical index for Vancouver Tech Events.
 *
 * This stores public event *listings*, not evidence that an event took place.
 * The transient upcoming feed remains separate and unchanged.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Store only meaningful, dated, linked records; never persist scraper payloads. */
function suzy_vte_archive_normalize( array $event, ?int $captured_at = null ): ?array {
    $title = trim( (string) ( $event['title'] ?? '' ) );
    $url   = trim( (string) ( $event['url'] ?? '' ) );
    $start = (int) ( $event['start'] ?? 0 );
    $parts = wp_parse_url( $url );
    $now   = $captured_at ?? time();

    // Bound old/far-future entries and reject placeholders or unlinked discoveries.
    if ( '' === $title || 0 === $start || ! is_array( $parts )
        || ! in_array( strtolower( $parts['scheme'] ?? '' ), [ 'https', 'http' ], true )
        || empty( $parts['host'] )
        || $start < strtotime( '2020-01-01T00:00:00Z' )
        || $start > $now + 3 * 365 * DAY_IN_SECONDS ) {
        return null;
    }

    $identity = suzy_vte_event_identity_key( $event );
    if ( '' === $identity ) {
        return null;
    }

    $end = (int) ( $event['end'] ?? 0 );
    return [
        'id'          => sha1( $identity ),
        'title'       => substr( $title, 0, 280 ),
        'url'         => $url,
        'start'       => $start,
        'end'         => $end >= $start ? $end : 0,
        'location'    => substr( trim( (string) ( $event['location'] ?? '' ) ), 0, 220 ),
        'source'      => substr( trim( (string) ( $event['source'] ?? '' ) ), 0, 140 ),
        'origin'      => ! empty( $event['curated'] ) || ( $event['origin'] ?? '' ) === 'verified' ? 'verified' : 'feed',
        'captured_at' => (int) ( $event['captured_at'] ?? $now ),
    ];
}

/** Limit option growth and retain newest 2,500 listings. */
function suzy_vte_archive_bound( array $records ): array {
    usort( $records, static function ( $a, $b ) {
        return (int) ( $b['start'] ?? 0 ) <=> (int) ( $a['start'] ?? 0 );
    } );
    return array_slice( $records, 0, 2500 );
}

/**
 * Preserve records seen while fetching, BEFORE the upcoming-only filter.
 * WordPress options persist between transient cache expiry and deployments.
 */
function suzy_vte_archive_capture( array $events ): void {
    if ( empty( $events ) ) {
        return; // A blocked or empty source must never erase the archive.
    }

    $stored = get_option( 'suzy_vte_event_archive_v1', [] );
    $stored = is_array( $stored ) ? $stored : [];
    $by_id = [];
    foreach ( $stored as $event ) {
        if ( is_array( $event ) && ! empty( $event['id'] ) ) {
            $by_id[ (string) $event['id'] ] = $event;
        }
    }

    $changed = false;
    foreach ( $events as $event ) {
        if ( ! is_array( $event ) ) {
            continue;
        }
        $incoming = suzy_vte_archive_normalize( $event );
        if ( null === $incoming ) {
            continue;
        }
        $id = $incoming['id'];
        if ( ! isset( $by_id[ $id ] ) ) {
            $by_id[ $id ] = $incoming;
            $changed = true;
            continue;
        }

        $previous = $by_id[ $id ];
        $prefer_live = ( $previous['origin'] ?? '' ) === 'verified' && $incoming['origin'] === 'feed';
        $merged = $prefer_live
            ? suzy_vte_merge_event_records( $incoming, $previous )
            : suzy_vte_merge_event_records( $previous, $incoming );
        // Keep the original discovery timestamp and the strongest provenance.
        $merged['id'] = $id;
        $merged['captured_at'] = (int) ( $previous['captured_at'] ?? $incoming['captured_at'] );
        $merged['origin'] = ( $incoming['origin'] === 'feed' || ( $previous['origin'] ?? '' ) === 'feed' ) ? 'feed' : 'verified';
        unset( $merged['curated'], $merged['spotlight'] );
        if ( $merged !== $previous ) {
            $by_id[ $id ] = $merged;
            $changed = true;
        }
    }

    if ( ! $changed ) {
        return;
    }

    $bounded = suzy_vte_archive_bound( array_values( $by_id ) );
    // 'false' prevents a potentially large historical array from autoloading on every WP request.
    if ( false === get_option( 'suzy_vte_event_archive_v1', false ) ) {
        if ( add_option( 'suzy_vte_event_archive_v1', $bounded, '', false ) ) {
            return;
        }
    }
    update_option( 'suzy_vte_event_archive_v1', $bounded, false );
}

/**
 * Small, manually audited historical backfill for the first archive QA pass.
 *
 * These were published by their organizers for Nov 10–12, 2026. An entry
 * represents a listing, not confirmation that an event took place. Keep
 * backfill out of the live upcoming feed and use the original event URL
 * so the existing stable identity logic deduplicates future observations.
 */
function suzy_vte_archive_verified_backfill(): array {
    $timezone = suzy_vte_vancouver_timezone();
    $at = static function ( string $local ) use ( $timezone ): int {
        return ( new DateTimeImmutable( $local, $timezone ) )->getTimestamp();
    };

    return [
        [
            'title' => 'NKST Summit Vancouver 2026',
            'start' => $at( '2026-11-10 08:00:00' ),
            'end' => $at( '2026-11-10 17:00:00' ),
            'location' => 'Anvil Centre, New Westminster',
            'url' => 'https://thenkst.com/nkst-summit-vancouver-2026/',
            'source' => 'NKST (official)',
            'curated' => true,
        ],
        [
            'title' => 'Get Tech Vibes. Get Connected. Get Funded.',
            'start' => $at( '2026-11-10 17:00:00' ),
            'location' => 'The Basement, 136 W Cordova St, Vancouver',
            'url' => 'https://uni.unite.love/events/get-tech-vibes-get-connected-get-funded-a65b9c',
            'source' => 'UNITE (organizer)',
            'curated' => true,
        ],
        [
            // The Meetup publishes 17:00 UTC (10:00 Vancouver PCT), while
            // body copy still says "9 PST"; use the dated UTC calendar time.
            'title' => 'Nov 11 - AI, ML and Computer Vision Meetup',
            'start' => $at( '2026-11-11 10:00:00' ),
            'end' => $at( '2026-11-11 12:00:00' ),
            'location' => 'Online (Vancouver AI, ML and Computer Vision Meetup)',
            'url' => 'https://www.meetup.com/vancouver-ai-machine-learning-and-computer-vision-meetup/events/316572570/',
            'source' => 'Meetup: Vancouver AI, ML and Computer Vision',
            'curated' => true,
        ],
        [
            'title' => 'FV + AI November: Fraser Valley AI Community Meetup',
            'start' => $at( '2026-11-11 18:00:00' ),
            'end' => $at( '2026-11-11 20:00:00' ),
            'location' => 'Coleman Technologies, Langley Township (regional)',
            'url' => 'https://bc-ai.ca/events/2026-11-11-fv-ai-november-fraser-valley-ai-community-meetup',
            'source' => 'BC + AI (official)',
            'curated' => true,
        ],
        [
            'title' => 'Social Pair Coding',
            'start' => $at( '2026-11-12 12:00:00' ),
            'end' => $at( '2026-11-12 14:00:00' ),
            'location' => 'Grant Thornton Place, 320 Granville St, Vancouver',
            'url' => 'https://www.meetup.com/code-together-vancouver/events/317107736/',
            'source' => 'Meetup: Code Together Vancouver',
            'curated' => true,
        ],
        [
            'title' => 'The Hardware Behind the AI Buildout',
            'start' => $at( '2026-11-12 15:00:00' ),
            'end' => $at( '2026-11-12 17:00:00' ),
            'location' => 'And-Co Offices, 1575 W Georgia St, Vancouver',
            'url' => 'https://luma.com/vancouver-06wa',
            'source' => 'Vancouver Tech Journal / ReuseTechBC',
            'curated' => true,
        ],
        [
            'title' => 'VanCitySec',
            'start' => $at( '2026-11-12 17:30:00' ),
            'end' => $at( '2026-11-12 23:30:00' ),
            'location' => 'Taylight, 990 Smithe St, Vancouver',
            'url' => 'https://luma.com/8i062xgp',
            'source' => 'VanCitySec (official)',
            'curated' => true,
        ],
        [
            'title' => 'AI Film Club with Kevin Friel | Nov 12',
            'start' => $at( '2026-11-12 18:30:00' ),
            'end' => $at( '2026-11-12 22:00:00' ),
            'location' => 'Multimodal Media Lab, 375 Water St, Vancouver',
            'url' => 'https://luma.com/noir',
            'source' => 'BC + AI Events (official)',
            'curated' => true,
        ],
    ];
}

/** Display a local date span for multi-day events without counting each day as another event. */
function suzy_vte_archive_date_label( array $event ): string {
    $start = (int) ( $event['start'] ?? 0 );
    $start_timezone = suzy_vte_vancouver_timezone( $start );
    $label = wp_date( 'M j, Y · ' . get_option( 'time_format' ), $start, $start_timezone );
    $end = (int) ( $event['end'] ?? 0 );
    if ( $end <= $start ) {
        return $label;
    }

    // End timestamps are often exclusive; subtract a second for all-day feeds.
    $last_moment = max( $start, $end - 1 );
    $last_timezone = suzy_vte_vancouver_timezone( $last_moment );
    if ( wp_date( 'Y-m-d', $start, $start_timezone ) === wp_date( 'Y-m-d', $last_moment, $last_timezone ) ) {
        return $label;
    }
    return wp_date( 'M j', $start, $start_timezone ) . '–'
        . wp_date( wp_date( 'Y', $start, $start_timezone ) === wp_date( 'Y', $last_moment, $last_timezone ) ? 'j, Y' : 'M j, Y', $last_moment, $last_timezone )
        . ' · ' . wp_date( get_option( 'time_format' ), $start, $start_timezone );
}

/** Include known source-verified one-offs from before archiving was installed. */
function suzy_vte_archive_listings( ?int $now = null ): array {
    $now = $now ?? time();
    $saved = get_option( 'suzy_vte_event_archive_v1', [] );
    $saved = is_array( $saved ) ? $saved : [];
    $seeded = array_merge( suzy_get_vancouver_tech_spotlight_events(), suzy_get_vancouver_tech_verified_fallback_events(), suzy_vte_archive_verified_backfill() );
    $by_id = [];

    foreach ( $saved as $event ) {
        if ( is_array( $event ) && ! empty( $event['id'] ) ) {
            $by_id[ $event['id'] ] = $event;
        }
    }
    foreach ( $seeded as $event ) {
        $normalized = suzy_vte_archive_normalize( $event, $now );
        if ( null !== $normalized && ! isset( $by_id[ $normalized['id'] ] ) ) {
            $by_id[ $normalized['id'] ] = $normalized;
        }
    }

    $past = array_filter( $by_id, static function ( $event ) use ( $now ) {
        $end = ! empty( $event['end'] ) ? (int) $event['end'] : (int) ( $event['start'] ?? 0 );
        return $end > 0 && $end < $now;
    } );
    return suzy_vte_archive_bound( array_values( $past ) );
}

/**
 * Broad, deterministic title-based categories for exploratory trends.
 * These are inferred topics, not organizer-declared classifications.
 */
function suzy_vte_archive_topic( array $event ): string {
    $title = strtolower( (string) ( $event['title'] ?? '' ) );
    if ( preg_match( '/film|music|creative|art\\b|design|ux\\b|game|media|immersive/', $title ) ) {
        return 'creative';
    }
    if ( preg_match( '/\\bai\\b|machine learning|\\bml\\b|llm|prompt|agentic|generative|neural/', $title ) ) {
        return 'ai';
    }
    if ( preg_match( '/security|infosec|cyber|privacy|defcon|hackathon|ctf|threat/', $title ) ) {
        return 'security';
    }
    if ( preg_match( '/cloud|aws|azure|devops|platform|kubernetes|network|infrastructure|dns|data center/', $title ) ) {
        return 'infrastructure';
    }
    if ( preg_match( '/startup|founder|venture|invest|pitch|entrepreneur|business|fintech/', $title ) ) {
        return 'startup';
    }
    if ( preg_match( '/developer|coding|programming|javascript|python|software|web3|blockchain|workshop|build/', $title ) ) {
        return 'developer';
    }
    return 'other';
}

/** Page-local archive, filters and small monthly distribution without JS or API calls. */
function suzy_vte_render_archive_html( ?array $listings = null, ?int $now = null ): string {
    $now = $now ?? time();
    $listings = $listings ?? suzy_vte_archive_listings( $now );
    $topics = [
        'all' => 'All topics',
        'ai' => 'AI & machine learning',
        'developer' => 'Software & developer',
        'security' => 'Security & privacy',
        'infrastructure' => 'Cloud & infrastructure',
        'creative' => 'Creative technology',
        'startup' => 'Startups & business',
        'other' => 'Other technology',
    ];
    $years = [];
    foreach ( $listings as $event ) {
        $stamp = (int) ( $event['start'] ?? 0 );
        if ( $stamp > 0 ) {
            $years[ wp_date( 'Y', $stamp, suzy_vte_vancouver_timezone( $stamp ) ) ] = true;
        }
    }
    // PHP casts numeric-looking array keys to ints; normalize for strict GET checks.
    $years = array_map( 'strval', array_keys( $years ) );
    rsort( $years, SORT_NUMERIC );

    $requested_year = isset( $_GET['vte_year'] ) && is_scalar( $_GET['vte_year'] ) ? (int) $_GET['vte_year'] : 0;
    $year = in_array( (string) $requested_year, $years, true ) ? (string) $requested_year : ( $years[0] ?? '' );
    $requested_topic = isset( $_GET['vte_topic'] ) && is_scalar( $_GET['vte_topic'] ) ? (string) $_GET['vte_topic'] : 'all';
    $topic = isset( $topics[ $requested_topic ] ) ? $requested_topic : 'all';

    $filtered = array_values( array_filter( $listings, static function ( $event ) use ( $year, $topic ) {
        $stamp = (int) ( $event['start'] ?? 0 );
        return $stamp > 0
            && wp_date( 'Y', $stamp, suzy_vte_vancouver_timezone( $stamp ) ) === $year
            && ( 'all' === $topic || suzy_vte_archive_topic( $event ) === $topic );
    } ) );
    $months = array_fill( 1, 12, 0 );
    foreach ( $filtered as $event ) {
        $stamp = (int) $event['start'];
        $month = (int) wp_date( 'n', $stamp, suzy_vte_vancouver_timezone( $stamp ) );
        ++$months[ $month ];
    }

    $per_page = 30;
    $total = count( $filtered );
    $pages = max( 1, (int) ceil( $total / $per_page ) );
    $requested_page = isset( $_GET['vte_page'] ) && is_scalar( $_GET['vte_page'] ) ? (int) $_GET['vte_page'] : 1;
    $page = max( 1, min( $pages, $requested_page ) );
    $shown = array_slice( $filtered, ( $page - 1 ) * $per_page, $per_page );
    $base_url = home_url( '/vancouver-tech-events/' );

    ob_start();
    ?>
    <section id="vte-archive" class="vte-archive" aria-labelledby="vte-archive-title">
        <p class="vte-archive__kicker pixel-font">past signals</p>
        <h2 id="vte-archive-title">Event archive &amp; trends</h2>
        <p class="vte-archive__intro">Explore previously listed Vancouver tech events by year and topic. New listings are preserved when the calendar refreshes. Earlier records are limited to events we can source.</p>
        <?php if ( empty( $listings ) ) : ?>
            <p>The archive is starting from verified records and future calendar captures. No historical listings are available yet.</p>
        <?php else : ?>
            <form class="vte-archive__filters" action="<?php echo esc_url( $base_url ); ?>#vte-archive" method="get">
                <label for="vte-year">Year</label>
                <select name="vte_year" id="vte-year">
                    <?php foreach ( $years as $option_year ) : ?>
                        <option value="<?php echo esc_attr( $option_year ); ?>"<?php echo $option_year === $year ? ' selected' : ''; ?>><?php echo esc_html( $option_year ); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="vte-topic">Topic (inferred)</label>
                <select name="vte_topic" id="vte-topic">
                    <?php foreach ( $topics as $slug => $label ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>"<?php echo $slug === $topic ? ' selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Explore</button>
            </form>
            <div class="vte-archive__overview">
                <p><strong><?php echo (int) $total; ?></strong> archived listings in <?php echo esc_html( $year ); ?><?php echo 'all' !== $topic ? ' · ' . esc_html( $topics[ $topic ] ) : ''; ?></p>
                <p class="vte-archive__coverage">Partial historical coverage. A dash means no records captured, not no events.</p>
                <div class="vte-archive__months" role="img" aria-label="<?php echo esc_attr( 'Known archived listings (incomplete coverage) for ' . $year . ', ' . $topics[ $topic ] ); ?>">
                    <?php $peak = max( 1, max( $months ) ); ?>
                    <?php foreach ( $months as $month => $count ) : ?>
                        <div class="vte-archive__month">
                            <span class="vte-archive__bar" style="height:<?php echo (int) round( 100 * $count / $peak ); ?>%" title="<?php echo esc_attr( $count . ' listings' ); ?>"></span>
                            <span><?php echo esc_html( ( new DateTimeImmutable( '2026-' . sprintf( '%02d', $month ) . '-01' ) )->format( 'M' ) ); ?></span>
                            <small><?php echo $count > 0 ? (int) $count : '–'; ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ( empty( $shown ) ) : ?>
                <p>No archived listings match these filters.</p>
            <?php else : ?>
                <ul class="vte-event-list vte-event-list--archive">
                    <?php foreach ( $shown as $event ) : ?>
                        <li class="vte-event">
                            <span class="vte-archive__date"><?php
                                $stamp = (int) $event['start'];
                                echo esc_html( suzy_vte_archive_date_label( $event ) );
                            ?></span>
                            <a class="vte-title" href="<?php echo esc_url( $event['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $event['title'] ); ?></a>
                            <div class="vte-meta">
                                <?php if ( ! empty( $event['location'] ) ) : ?><span class="vte-location"><?php echo esc_html( $event['location'] ); ?></span><?php endif; ?>
                                <span class="vte-source"><?php echo esc_html( $event['source'] ?? '' ); ?></span>
                                <span class="vte-archive__topic"><?php echo esc_html( $topics[ suzy_vte_archive_topic( $event ) ] ); ?></span>
                                <span class="vte-archive__provenance"><?php echo ( $event['origin'] ?? '' ) === 'verified' ? 'Verified listing' : 'Captured from feed'; ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ( $pages > 1 ) : ?>
                    <nav class="vte-archive__pages" aria-label="Archived event pages">
                        <?php if ( $page > 1 ) : ?><a href="<?php echo esc_url( add_query_arg( [ 'vte_year' => $year, 'vte_topic' => $topic, 'vte_page' => $page - 1 ], $base_url ) . '#vte-archive' ); ?>">Newer</a><?php endif; ?>
                        <span>Page <?php echo (int) $page; ?> of <?php echo (int) $pages; ?></span>
                        <?php if ( $page < $pages ) : ?><a href="<?php echo esc_url( add_query_arg( [ 'vte_year' => $year, 'vte_topic' => $topic, 'vte_page' => $page + 1 ], $base_url ) . '#vte-archive' ); ?>">Older</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
        <p class="vte-archive__caveat"><strong>Data note:</strong> These are publicly listed events, not confirmed attendance or proof an event was held. The archive is incomplete before tracking began; monthly counts describe known records only and should not be used to infer zero activity. Topic labels are keyword-based.</p>
    </section>
    <?php
    return ob_get_clean();
}

/** WP-Cron keeps collecting when visitors aren't on the calendar page. */
function suzy_vte_archive_schedule(): void {
    if ( ! wp_next_scheduled( 'suzy_vte_archive_refresh' ) ) {
        wp_schedule_event( time() + 120, 'twicedaily', 'suzy_vte_archive_refresh' );
    }
}
function suzy_vte_archive_refresh(): void {
    suzy_get_vancouver_tech_events();
}
add_action( 'init', 'suzy_vte_archive_schedule' );
add_action( 'suzy_vte_archive_refresh', 'suzy_vte_archive_refresh' );

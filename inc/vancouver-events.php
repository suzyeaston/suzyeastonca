<?php
/**
 * Vancouver Events: one read-only view over the existing tech and culture
 * collections. Never scrapes a second time or changes either source archive.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function suzy_yvr_events_topics(): array {
    return [
        'all' => 'All topics',
        'tech:ai' => 'Tech · AI & machine learning',
        'tech:developer' => 'Tech · Software & developer',
        'tech:security' => 'Tech · Security & privacy',
        'tech:infrastructure' => 'Tech · Cloud & infrastructure',
        'tech:creative' => 'Tech · Creative technology',
        'tech:startup' => 'Tech · Startups & business',
        'tech:other' => 'Tech · Other',
        'culture:experimental' => 'Culture · Experimental & improv',
        'culture:music-tech' => 'Culture · Music technology & sound art',
        'culture:music' => 'Culture · Live local music',
        'culture:poetry' => 'Culture · Poetry & literature',
        'culture:ideas' => 'Culture · Philosophy & conversations',
        'culture:arts' => 'Culture · Art, film & performance',
    ];
}

/** Normalize only source records that have an actual date, title and link. */
function suzy_yvr_events_normalize( array $event, string $track ): ?array {
    if ( ! in_array( $track, [ 'tech', 'culture' ], true ) ) { return null; }
    $title = trim( (string) ( $event['title'] ?? '' ) );
    $url = trim( (string) ( $event['url'] ?? '' ) );
    $start = (int) ( $event['start'] ?? 0 );
    $parts = wp_parse_url( $url );
    if ( '' === $title || $start <= 0 || ! is_array( $parts )
        || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'https', 'http' ], true )
        || empty( $parts['host'] ) ) { return null; }

    $topic = 'tech' === $track
        ? 'tech:' . suzy_vte_archive_topic( $event )
        : 'culture:' . (string) ( $event['category'] ?? 'arts' );
    if ( ! isset( suzy_yvr_events_topics()[ $topic ] ) ) {
        $topic = $track . ':other';
        if ( 'culture' === $track ) { $topic = 'culture:arts'; }
    }
    $end = (int) ( $event['end'] ?? 0 );
    // Exact title + start is a conservative cross-feed dedupe key. The same
    // generic venue URL at the same time may represent different performances.
    $name_key = strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', $title ) );
    return [
        'id' => sha1( $name_key . '|' . $start ),
        'title' => $title,
        'start' => $start,
        'end' => $end >= $start ? $end : 0,
        'url' => $url,
        'location' => trim( (string) ( $event['location'] ?? '' ) ),
        'source' => trim( (string) ( $event['source'] ?? 'Organizer listing' ) ),
        'tracks' => [ $track ],
        'topics' => [ $topic ],
        'origin' => ( ! empty( $event['curated'] ) || ( $event['origin'] ?? '' ) === 'verified' ) ? 'verified' : 'feed',
        'time_note' => trim( (string) ( $event['time_note'] ?? '' ) ),
    ];
}

/** Combine identical occurrences across both feeds without losing provenance. */
function suzy_yvr_events_merge( array $tech, array $culture ): array {
    $combined = [];
    foreach ( [ 'tech' => $tech, 'culture' => $culture ] as $track => $events ) {
        foreach ( $events as $raw ) {
            $item = is_array( $raw ) ? suzy_yvr_events_normalize( $raw, $track ) : null;
            if ( null === $item ) { continue; }
            $id = $item['id'];
            if ( ! isset( $combined[ $id ] ) ) {
                $combined[ $id ] = $item;
                continue;
            }
            $prev = $combined[ $id ];
            // Prefer an organizer-verified detail URL, but retain both topic
            // tags and the sources that reported the same occurrence.
            $better = $item['origin'] === 'verified' && $prev['origin'] !== 'verified';
            if ( $better ) {
                foreach ( [ 'url', 'title', 'location' ] as $key ) {
                    if ( '' !== $item[ $key ] ) { $prev[ $key ] = $item[ $key ]; }
                }
                $prev['origin'] = 'verified';
            }
            $prev['tracks'] = array_values( array_unique( array_merge( $prev['tracks'], $item['tracks'] ) ) );
            $prev['topics'] = array_values( array_unique( array_merge( $prev['topics'], $item['topics'] ) ) );
            $sources = array_filter( array_unique( array_merge( explode( ' · ', $prev['source'] ), [ $item['source'] ] ) ) );
            $prev['source'] = implode( ' · ', $sources );
            if ( '' === $prev['location'] ) { $prev['location'] = $item['location']; }
            if ( '' === $prev['time_note'] ) { $prev['time_note'] = $item['time_note']; }
            if ( $item['end'] > $prev['end'] ) { $prev['end'] = $item['end']; }
            $combined[ $id ] = $prev;
        }
    }
    return array_values( $combined );
}

/**
 * Existing collections remain owners of fetching, caching, source health and
 * historical retention. Archive view performs NO external fetches.
 */
function suzy_yvr_events_records( string $view = 'upcoming', ?int $now = null ): array {
    $now = $now ?? time();
    if ( 'archive' === $view ) {
        $tech = suzy_vte_archive_listings( $now );
        $culture = suzy_vmc_history( $now );
    } else {
        $payload = suzy_get_vancouver_tech_events();
        $tech = is_array( $payload['events'] ?? null ) ? $payload['events'] : [];
        $culture = suzy_vmc_events( $now );
    }
    $events = suzy_yvr_events_merge( $tech, $culture );
    $events = array_values( array_filter( $events, static function ( $event ) use ( $view, $now ) {
        $finish = $event['end'] > 0 ? $event['end'] : $event['start'];
        return 'archive' === $view ? $finish < $now : $finish >= $now;
    } ) );
    usort( $events, static function ( $a, $b ) use ( $view ) {
        $cmp = $a['start'] <=> $b['start'];
        return 'archive' === $view ? -$cmp : $cmp;
    } );
    return $events;
}

/** Whitelist all public query values; unknown values safely become defaults. */
function suzy_yvr_events_filters( array $input ): array {
    $read = static function ( string $key ) use ( $input ): string {
        return isset( $input[ $key ] ) && is_scalar( $input[ $key ] )
            ? trim( (string) $input[ $key ] ) : '';
    };
    $view = in_array( $read( 'view' ), [ 'upcoming', 'archive' ], true ) ? $read( 'view' ) : 'upcoming';
    $track = in_array( $read( 'track' ), [ 'all', 'tech', 'culture' ], true ) ? $read( 'track' ) : 'all';
    $topic = isset( suzy_yvr_events_topics()[ $read( 'topic' ) ] ) ? $read( 'topic' ) : 'all';
    $period = in_array( $read( 'period' ), [ 'all', '7', '30', '90' ], true ) ? $read( 'period' ) : 'all';
    $year = preg_match( '/^20\d{2}$/', $read( 'year' ) ) ? $read( 'year' ) : '';
    $month = preg_match( '/^(?:[1-9]|1[0-2])$/', $read( 'month' ) ) ? $read( 'month' ) : 'all';
    $q = substr( trim( strip_tags( $read( 'q' ) ) ), 0, 80 );
    $page = max( 1, min( 500, (int) $read( 'page' ) ) );
    return compact( 'view', 'track', 'topic', 'period', 'year', 'month', 'q', 'page' );
}

/** Common filters work identically on upcoming and historical listings. */
function suzy_yvr_events_filter( array $events, array $filters, ?int $now = null ): array {
    $now = $now ?? time();
    return array_values( array_filter( $events, static function ( $event ) use ( $filters, $now ) {
        if ( 'all' !== $filters['track'] && ! in_array( $filters['track'], $event['tracks'], true ) ) {
            return false;
        }
        if ( 'all' !== $filters['topic'] && ! in_array( $filters['topic'], $event['topics'], true ) ) {
            return false;
        }
        if ( '' !== $filters['q'] ) {
            $haystack = implode( ' ', [ $event['title'], $event['location'], $event['source'] ] );
            if ( false === stripos( $haystack, $filters['q'] ) ) { return false; }
        }
        if ( 'archive' === $filters['view'] ) {
            $date = wp_date( 'Y-n', $event['start'], suzy_vte_vancouver_timezone( $event['start'] ) );
            [ $year, $month ] = explode( '-', $date );
            return ( '' === $filters['year'] || $year === $filters['year'] )
                && ( 'all' === $filters['month'] || $month === $filters['month'] );
        }
        return 'all' === $filters['period']
            || $event['start'] <= $now + (int) $filters['period'] * DAY_IN_SECONDS;
    } ) );
}

function suzy_yvr_events_archive_years( array $events ): array {
    $years = [];
    foreach ( $events as $event ) {
        $start = (int) ( $event['start'] ?? 0 );
        if ( $start > 0 ) {
            $years[ wp_date( 'Y', $start, suzy_vte_vancouver_timezone( $start ) ) ] = true;
        }
    }
    $years = array_map( 'strval', array_keys( $years ) );
    rsort( $years, SORT_NUMERIC );
    return $years;
}

/** Theme-only route works before an editable WordPress Page is created. */
function suzy_yvr_events_is_route(): bool {
    if ( is_admin() ) { return false; }
    $path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
    $base = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    if ( '' !== $base ) {
        if ( ! str_starts_with( $path, $base . '/' ) ) { return false; }
        $path = substr( $path, strlen( $base ) + 1 );
    }
    return $path === 'vancouver-events';
}
add_filter( 'pre_handle_404', static function ( $preempt ) {
    if ( suzy_yvr_events_is_route() ) { status_header( 200 ); return true; }
    return $preempt;
}, 10, 1 );
add_filter( 'redirect_canonical', static function ( $url ) {
    return suzy_yvr_events_is_route() ? false : $url;
} );
add_filter( 'template_include', static function ( $template ) {
    if ( ! suzy_yvr_events_is_route() ) { return $template; }
    $target = get_template_directory() . '/page-vancouver-events.php';
    if ( ! is_file( $target ) ) { return $template; }
    global $wp_query;
    if ( $wp_query instanceof WP_Query ) { $wp_query->is_404 = false; }
    status_header( 200 );
    return $target;
}, 99 );
add_action( 'wp_enqueue_scripts', static function (): void {
    if ( suzy_yvr_events_is_route() ) {
        wp_enqueue_style( 'suzy-vancouver-events', get_template_directory_uri() . '/assets/css/vancouver-events.css', [], '1.0.0' );
    }
}, 15 );

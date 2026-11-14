<?php
/**
 * Vancouver Music & Culture: smaller rooms, experimental sound, music tech,
 * poetry, creative practice, philosophy and local conversations.
 *
 * This is a source-backed event listing index, not a ticket seller.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function suzy_vmc_tz( ?int $timestamp = null ): DateTimeZone {
    // B.C. stays at UTC-7 after March 8, 2026; retain historic IANA dates.
    return null === $timestamp || $timestamp >= 1772964000
        ? new DateTimeZone( '-07:00' ) : new DateTimeZone( 'America/Vancouver' );
}

function suzy_vmc_at( string $local ): int {
    return ( new DateTimeImmutable( $local, suzy_vmc_tz() ) )->getTimestamp();
}

/** Direct organizer calendars, not general search-engine result pages. */
function suzy_vmc_sources(): array {
    return [
        [ 'id' => '8east', 'label' => '8EAST / NOW Society', 'url' => 'https://www.8east.ca/events/', 'endpoint' => 'https://www.8east.ca/wp-json/tribe/events/v1/events?per_page=50', 'kind' => 'tribe', 'venue' => '8EAST, 8 E Pender St, Vancouver', 'category' => 'experimental' ],
        [ 'id' => 'roundhouse', 'label' => 'Roundhouse Arts', 'url' => 'https://www.roundhouse.ca/events/', 'endpoint' => 'https://www.roundhouse.ca/wp-json/tribe/events/v1/events?per_page=50', 'kind' => 'tribe', 'venue' => 'Roundhouse, Vancouver', 'category' => 'arts' ],
        [ 'id' => 'lobe', 'label' => 'Lobe / spatial sound', 'url' => 'https://lobestudio.ca/', 'endpoint' => 'https://roadrunner-red-yde9.squarespace.com/new-events?format=json', 'kind' => 'squarespace', 'venue' => 'Lobe, 713 E Hastings St, Vancouver', 'category' => 'music-tech' ],
        [ 'id' => 'luma_discovery', 'label' => 'Luma Vancouver discovery', 'url' => 'https://luma.com/discover/vancouver', 'kind' => 'luma', 'category' => 'arts' ],
    ];
}

/** Additional official sources to check when their pages do not expose a dependable feed. */
function suzy_vmc_directory(): array {
    return [
        [ 'label' => 'Western Front · sound, performance & art', 'url' => 'https://www.westernfront.ca/' ],
        [ 'label' => 'Music on Main · contemporary music', 'url' => 'https://www.musiconmain.ca/events/' ],
        [ 'label' => 'Vancouver Public Library · author events', 'url' => 'https://www.vpl.ca/programs/author-events' ],
        [ 'label' => 'Pandora’s Collective · poetry', 'url' => 'https://www.pandorascollective.com/' ],
        [ 'label' => 'Vancouver Curiosity Club · ideas & philosophy', 'url' => 'https://vancouvercuriosityclub.com/events' ],
        [ 'label' => '8EAST · free improvisation and community jams', 'url' => 'https://www.8east.ca/events/' ],
        [ 'label' => 'Lobe · 4DSOUND and listening sessions', 'url' => 'https://lobestudio.ca/' ],
        [ 'label' => 'Roundhouse · community arts', 'url' => 'https://www.roundhouse.ca/events/' ],
        [ 'label' => 'Heart of the City · grassroots cultural festival', 'url' => 'https://www.heartofthecityfestival.com/' ],
        [ 'label' => 'Eastside Culture Crawl · artists & studios', 'url' => 'https://culturecrawl.ca/' ],
        [ 'label' => 'The Wise · local live music', 'url' => 'https://www.thewisehall.ca/' ],
        [ 'label' => 'New Forms Media Society · electronic & audiovisual arts', 'url' => 'https://newformsfestival.com/' ],
    ];
}

/**
 * Individually verified event-date safety net, not a synthetic recurrence
 * generator. Keep the original organizer event URLs for rechecking details.
 */
function suzy_vmc_verified_events(): array {
    $raw = [
        [ 'Constellation Festival: film, music, movement & art', '2026-11-13 20:00', '2026-11-13 21:30', 'Roundhouse, 181 Roundhouse Mews, Vancouver', 'https://www.roundhouse.ca/event/constellation-festival/2026-11-13/', 'Roundhouse / Chapel Sound', 'arts' ],
        [ 'Fall Improvisation Workshops: Matthew Ariaratnam', '2026-11-14 14:00', '2026-11-14 15:30', '8EAST, 8 E Pender St, Vancouver', 'https://www.8east.ca/event/fall-improvisation-workshops-2026-matthew-ariaratnam/', 'NOW Society / 8EAST', 'experimental' ],
        [ 'Constellation Festival: multidisciplinary night', '2026-11-14 19:30', '2026-11-14 23:00', 'Roundhouse, 181 Roundhouse Mews, Vancouver', 'https://www.roundhouse.ca/event/constellation-festival/2026-11-14/', 'Roundhouse / Chapel Sound', 'arts' ],
        [ 'How Much Land Does a Man Need? Tolstoy discussion', '2026-11-15 12:30', '2026-11-15 14:00', 'UBC Learning Exchange, 612 Main St, Vancouver', 'https://vancouvercuriosityclub.com/events/how-much-land-does-a-man-need-tolstoy-on-greed-secur-06350f242559-226f31b0', 'Vancouver Curiosity Club', 'ideas' ],
        [ 'Art & Poetry in the Gallery', '2026-11-17 18:30', '2026-11-17 20:00', 'Sidney & Gertrude Zack Gallery, 950 W 41st Ave, Vancouver', 'https://www.pandorascollective.com/art--poetry-in-the-gallery.html', 'Pandora’s Collective', 'poetry' ],
        [ 'Twisted Poets Literary Salon', '2026-11-18 18:00', '2026-11-18 19:45', 'Britannia Library, 1661 Napier St, Vancouver', 'https://vpl.bibliocommons.com/v2/events/69698d252c65973d00181b3f', 'Vancouver Public Library / Pandora’s Collective', 'poetry' ],
        [ 'Mycoglossia: Album Listening Session in 4D Sound', '2026-11-18 18:00', '2026-11-18 21:30', 'Lobe, 713 E Hastings St, Vancouver', 'https://roadrunner-red-yde9.squarespace.com/new-events/mycoglossia-album-listening-session-in-4d-sound', 'Lobe', 'music-tech' ],
        [ 'Concurrence Gathering #14', '2026-11-19 19:00', '2026-11-19 21:00', '8EAST, 8 E Pender St, Vancouver', 'https://www.8east.ca/event/concurrence-gathering-14-presented-by-now/', 'NOW Society / 8EAST', 'experimental' ],
        [ 'Six Turntables: collective listening & remixed archives', '2026-11-21 14:00', '', 'Grand Luxe Hall, Western Front, 303 E 8th Ave, Vancouver', 'https://www.westernfront.ca/events/six-turntables', 'Western Front', 'music-tech' ],
        [ 'Improv Karaoke #67', '2026-11-25 18:30', '2026-11-25 22:00', '8EAST, 8 E Pender St, Vancouver', 'https://www.8east.ca/event/improv-karaoke-67/', 'NOW Society / 8EAST', 'experimental' ],
        [ 'Music for the Winter Solstice 2026', '2026-12-09 19:30', '', 'Heritage Hall, 3102 Main St, Vancouver', 'https://www.musiconmain.ca/event/music-for-the-winter-solstice-2026/', 'Music on Main', 'music' ],
        // Historical provenance seeds support archive QA but don't imply full coverage.
        [ 'BlackGate: contemporary acoustic & electronic music', '2026-11-03 19:30', '', 'Roundhouse, Vancouver', 'https://www.musiconmain.ca/event/blackgate/', 'Music on Main', 'music' ],
        [ 'at the root: listening party, poetry & open mic', '2026-11-10 18:30', '2026-11-10 21:30', 'Lobe, 713 E Hastings St, Vancouver', 'https://roadrunner-red-yde9.squarespace.com/new-events', 'Lobe', 'poetry' ],
    ];
    return array_map( static function ( $item ) {
        return [
            'title' => $item[0], 'start' => suzy_vmc_at( $item[1] ),
            'end' => '' !== $item[2] ? suzy_vmc_at( $item[2] ) : 0,
            'location' => $item[3], 'url' => $item[4], 'source' => $item[5],
            'category' => $item[6], 'origin' => 'verified',
        ];
    }, $raw );
}

function suzy_vmc_categories(): array {
    return [
        'all' => 'Everything', 'experimental' => 'Experimental & improv',
        'music-tech' => 'Music technology & sound art', 'music' => 'Live local music',
        'poetry' => 'Poetry & literature', 'ideas' => 'Philosophy & conversations',
        'arts' => 'Art, film & performance',
    ];
}

/** Classification only uses actual event titles, never broad assumptions about venue guests. */
function suzy_vmc_infer_category( string $title, string $fallback = 'arts' ): string {
    $t = strtolower( $title );
    if ( preg_match( '/spatial|4d ?sound|sound art|synth|modular|listening session|turntable|music technolog|audiovisual|electroacoustic|audio lab|soundscape/', $t ) ) {
        return 'music-tech';
    }
    if ( preg_match( '/improv|free jazz|experimental|sound performance|karaoke|concurrence/', $t ) ) {
        return 'experimental';
    }
    if ( preg_match( '/poet|literary|author|book launch|reading|spoken word|zine|story slam/', $t ) ) {
        return 'poetry';
    }
    if ( preg_match( '/philosoph|discuss|conversation|lecture|talk|think|ethic|tolstoy|ideas|salon/', $t ) ) {
        return 'ideas';
    }
    if ( preg_match( '/concert|music|album|song|jazz|folk|gig|live band|dj|choral/', $t ) ) {
        return 'music';
    }
    if ( preg_match( '/art|film|performance|screening|theatre|dance|gallery|festival|creative|exhibition/', $t ) ) {
        return 'arts';
    }
    return $fallback;
}

/** Validate all records before they can reach the public HTML or persistent history. */
function suzy_vmc_normalize( array $event ): ?array {
    $title = trim( (string) ( $event['title'] ?? '' ) );
    $url = trim( (string) ( $event['url'] ?? '' ) );
    $start = (int) ( $event['start'] ?? 0 );
    $parts = wp_parse_url( $url );
    if ( '' === $title || ! $start || ! is_array( $parts )
        || strtolower( (string) ( $parts['scheme'] ?? '' ) ) !== 'https'
        || empty( $parts['host'] ) || $start < strtotime( '2020-01-01T00:00:00Z' )
        || $start > time() + 730 * DAY_IN_SECONDS ) {
        return null;
    }
    $categories = suzy_vmc_categories();
    $category = (string) ( $event['category'] ?? 'arts' );
    if ( ! isset( $categories[ $category ] ) || 'all' === $category ) {
        $category = suzy_vmc_infer_category( $title );
    }
    $path = strtolower( rtrim( (string) ( $parts['path'] ?? '' ), '/' ) );
    $host = strtolower( (string) $parts['host'] );
    $identity = $host . $path . '|' . $start;
    $end = (int) ( $event['end'] ?? 0 );
    return [
        'id' => sha1( $identity ),
        'title' => substr( $title, 0, 250 ),
        'start' => $start,
        'end' => $end >= $start ? $end : 0,
        'url' => $url, 'location' => substr( trim( (string) ( $event['location'] ?? '' ) ), 0, 200 ),
        'source' => substr( trim( (string) ( $event['source'] ?? '' ) ), 0, 100 ),
        'category' => $category,
        'origin' => ( $event['origin'] ?? '' ) === 'verified' ? 'verified' : 'feed',
        'captured_at' => (int) ( $event['captured_at'] ?? time() ),
    ];
}

/** Compatible with The Events Calendar public REST endpoint. */
function suzy_vmc_parse_tribe( array $json, array $source ): array {
    $events = [];
    foreach ( array_slice( is_array( $json['events'] ?? null ) ? $json['events'] : [], 0, 60 ) as $item ) {
        if ( ! is_array( $item ) ) { continue; }
        $local_start = (string) ( $item['start_date'] ?? '' );
        if ( ! preg_match( '/^20\d{2}-\d{2}-\d{2} \d{2}:\d{2}/', $local_start ) ) { continue; }
        $local_end = (string) ( $item['end_date'] ?? '' );
        try {
            $start = suzy_vmc_at( $local_start );
            $end = preg_match( '/^20\d{2}-\d{2}-\d{2} \d{2}:\d{2}/', $local_end ) ? suzy_vmc_at( $local_end ) : 0;
        } catch ( Exception $e ) { continue; }
        $venue = is_array( $item['venue'] ?? null ) ? $item['venue'] : [];
        $city = strtolower( trim( (string) ( $venue['city'] ?? '' ) ) );
        if ( '' !== $city && 'vancouver' !== $city ) { continue; }
        $events[] = [
            'title' => html_entity_decode( strip_tags( (string) ( $item['title'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ),
            'start' => $start, 'end' => $end,
            'url' => (string) ( $item['url'] ?? '' ),
            'location' => (string) ( $venue['venue'] ?? $source['venue'] ),
            'source' => $source['label'],
            'category' => suzy_vmc_infer_category( (string) ( $item['title'] ?? '' ), $source['category'] ),
        ];
    }
    return $events;
}

/** Squarespace collections return Unix milliseconds, not PHP seconds. */
function suzy_vmc_parse_squarespace( array $json, array $source ): array {
    $events = [];
    $items = is_array( $json['items'] ?? null ) ? $json['items'] : [];
    foreach ( array_slice( $items, 0, 80 ) as $item ) {
        if ( ! is_array( $item ) || empty( $item['startDate'] ) ) { continue; }
        $start = (int) floor( (float) $item['startDate'] / 1000 );
        $end = ! empty( $item['endDate'] ) ? (int) floor( (float) $item['endDate'] / 1000 ) : 0;
        $slug = ltrim( (string) ( $item['urlId'] ?? '' ), '/' );
        $url = ! empty( $item['fullUrl'] ) && str_starts_with( (string) $item['fullUrl'], '/new-events/' )
            ? 'https://roadrunner-red-yde9.squarespace.com' . (string) $item['fullUrl']
            : 'https://roadrunner-red-yde9.squarespace.com/new-events/' . rawurlencode( $slug );
        if ( '' === $slug && empty( $item['fullUrl'] ) ) { continue; }
        $title = html_entity_decode( strip_tags( (string) ( $item['title'] ?? '' ) ), ENT_QUOTES, 'UTF-8' );
        $events[] = [
            'title' => $title, 'start' => $start, 'end' => $end,
            'url' => $url, 'location' => $source['venue'],
            'source' => $source['label'],
            'category' => suzy_vmc_infer_category( $title, $source['category'] ),
        ];
    }
    return $events;
}

function suzy_vmc_fetch_source( array $source ): array {
    $cache_key = 'suzy_vmc_source_' . md5( $source['id'] );
    $cached = get_transient( $cache_key );
    if ( false !== $cached && is_array( $cached ) ) { return $cached; }
    $events = [];
    $status = 'empty';
    if ( 'luma' === $source['kind'] ) {
        // Reuse existing constrained Luma discovery parser and public resolver.
        // Only keep culturally relevant titles explicitly in Vancouver.
        $result = suzy_fetch_vancouver_tech_events_from_html_luma(
            [ 'url' => $source['url'], 'source' => $source['label'], 'discovery' => true, 'max_details' => 8 ], false
        );
        $raw = is_array( $result ) ? ( $result['events'] ?? [] ) : [];
        foreach ( $raw as $event ) {
            $title = (string) ( $event['title'] ?? '' );
            $loc = strtolower( (string) ( $event['location'] ?? '' ) );
            if ( ! preg_match( '/music|sound|jazz|poetry|poet|literary|book|philosoph|artist|improv|performance|film|creative/', $title )
                || ! str_contains( $loc, 'vancouver' ) ) { continue; }
            $event['category'] = suzy_vmc_infer_category( $title );
            $events[] = $event;
        }
        $status = ! empty( $events ) ? 'ok' : 'empty or blocked';
    } else {
        $response = wp_remote_get( $source['endpoint'], [
            'timeout' => 6, 'redirection' => 2,
            'headers' => [ 'Accept' => 'application/json', 'User-Agent' => 'SuzyMusicCultureCalendar/1.0' ],
        ] );
        if ( is_wp_error( $response ) ) {
            $status = 'request error';
        } else {
            $http_code = (int) wp_remote_retrieve_response_code( $response );
            $body = (string) wp_remote_retrieve_body( $response );
            $data = $http_code >= 200 && $http_code < 300 && strlen( $body ) < 1000000
                ? json_decode( $body, true ) : null;
            if ( is_array( $data ) ) {
                $events = 'tribe' === $source['kind']
                    ? suzy_vmc_parse_tribe( $data, $source )
                    : suzy_vmc_parse_squarespace( $data, $source );
                $status = ! empty( $events ) ? 'ok' : 'empty';
            } else {
                $status = 'HTTP ' . $http_code . ' / unavailable';
            }
        }
    }
    $payload = [ 'events' => $events, 'status' => $status ];
    // Cache failures briefly; no source failure may delete persisted history.
    set_transient( $cache_key, $payload, ! empty( $events ) ? 2 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
    return $payload;
}

/** Identity + title/date cross-source dedupe for listings that share an organizer. */
function suzy_vmc_dedupe( array $events ): array {
    $by_id = [];
    $by_title_date = [];
    foreach ( $events as $event ) {
        $item = is_array( $event ) ? suzy_vmc_normalize( $event ) : null;
        if ( null === $item ) { continue; }
        $key = strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', '', $item['title'] ) ) . ':' . (string) $item['start'];
        if ( isset( $by_title_date[ $key ] ) ) {
            $id = $by_title_date[ $key ];
            // Prefer verified organizer detail links when the event names and starts match.
            if ( 'verified' === $item['origin'] && 'verified' !== $by_id[ $id ]['origin'] ) {
                $by_id[ $id ] = $item;
            }
            continue;
        }
        if ( isset( $by_id[ $item['id'] ] ) ) { continue; }
        $by_title_date[ $key ] = $item['id'];
        $by_id[ $item['id'] ] = $item;
    }
    return array_values( $by_id );
}

/** Preserve history in an autoload-disabled WordPress option, capped to 1500. */
function suzy_vmc_capture_history( array $events ): void {
    $existing = get_option( 'suzy_vmc_archive_v1', [] );
    $existing = is_array( $existing ) ? $existing : [];
    $merged = suzy_vmc_dedupe( array_merge( $existing, $events ) );
    usort( $merged, static fn ( $a, $b ) => $b['start'] <=> $a['start'] );
    $merged = array_slice( $merged, 0, 1500 );
    if ( $merged !== $existing ) {
        update_option( 'suzy_vmc_archive_v1', $merged, false );
    }
}

function suzy_vmc_collect(): void {
    $events = suzy_vmc_verified_events();
    $statuses = [];
    foreach ( suzy_vmc_sources() as $source ) {
        $result = suzy_vmc_fetch_source( $source );
        $events = array_merge( $events, $result['events'] );
        $statuses[] = [ 'label' => $source['label'], 'status' => $result['status'], 'count' => count( $result['events'] ) ];
    }
    $events = suzy_vmc_dedupe( $events );
    suzy_vmc_capture_history( $events );
    update_option( 'suzy_vmc_latest_v1', $events, false );
    update_option( 'suzy_vmc_source_status_v1', [ 'time' => time(), 'sources' => $statuses ], false );
}

/** The public request never synchronously scrapes multiple external websites. */
function suzy_vmc_events( ?int $now = null ): array {
    $now = $now ?? time();
    $latest = get_option( 'suzy_vmc_latest_v1', [] );
    $records = suzy_vmc_dedupe( array_merge(
        is_array( $latest ) ? $latest : [], suzy_vmc_verified_events()
    ) );
    $upcoming = array_values( array_filter( $records, static function ( $event ) use ( $now ) {
        $start = (int) $event['start'];
        $end = (int) ( $event['end'] ?? 0 );
        return $start >= $now || $end >= $now;
    } ) );
    usort( $upcoming, static fn ( $a, $b ) => $a['start'] <=> $b['start'] );
    return array_slice( $upcoming, 0, 90 );
}

function suzy_vmc_history( ?int $now = null ): array {
    $now = $now ?? time();
    $saved = get_option( 'suzy_vmc_archive_v1', [] );
    $records = suzy_vmc_dedupe( array_merge(
        is_array( $saved ) ? $saved : [], suzy_vmc_verified_events()
    ) );
    $past = array_values( array_filter( $records, static function ( $event ) use ( $now ) {
        $end = (int) ( $event['end'] ?? 0 );
        return ( $end > 0 ? $end : $event['start'] ) < $now;
    } ) );
    usort( $past, static fn ( $a, $b ) => $b['start'] <=> $a['start'] );
    return array_slice( $past, 0, 1500 );
}

function suzy_vmc_schedule(): void {
    if ( ! wp_next_scheduled( 'suzy_vmc_refresh' ) ) {
        wp_schedule_event( time() + 60, 'hourly', 'suzy_vmc_refresh' );
    }
}
add_action( 'init', 'suzy_vmc_schedule' );
add_action( 'suzy_vmc_refresh', 'suzy_vmc_collect' );

/**
 * Exact public path makes the new page deployable without a manual WordPress
 * dashboard post/create operation. Does not intercept unrelated slugs.
 */
function suzy_vmc_is_route(): bool {
    if ( is_admin() ) { return false; }
    $path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
    $site_base = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    if ( '' !== $site_base ) {
        if ( ! str_starts_with( $path, $site_base . '/' ) ) { return false; }
        $path = substr( $path, strlen( $site_base ) + 1 );
    }
    return $path === 'vancouver-music-culture';
}
add_filter( 'pre_handle_404', static function ( $preempt ) {
    if ( suzy_vmc_is_route() ) { status_header( 200 ); return true; }
    return $preempt;
}, 10, 1 );
add_filter( 'redirect_canonical', static function ( $url ) {
    return suzy_vmc_is_route() ? false : $url;
} );
add_filter( 'template_include', static function ( $template ) {
    if ( ! suzy_vmc_is_route() ) { return $template; }
    $target = get_template_directory() . '/page-vancouver-music-culture.php';
    if ( ! is_file( $target ) ) { return $template; }
    global $wp_query;
    if ( $wp_query instanceof WP_Query ) { $wp_query->is_404 = false; }
    status_header( 200 );
    return $target;
}, 99 );

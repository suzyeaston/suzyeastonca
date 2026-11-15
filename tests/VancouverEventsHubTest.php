<?php
// WordPress-free integration fixture: php tests/VancouverEventsHubTest.php
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['registered_filters'] = [];
$GLOBALS['registered_actions'] = [];
$GLOBALS['tech_calls'] = 0;
$GLOBALS['music_calls'] = 0;
$GLOBALS['history_calls'] = 0;
function add_filter( $name, ...$args ) { $GLOBALS['registered_filters'][] = $name; }
function add_action( $name, ...$args ) { $GLOBALS['registered_actions'][] = $name; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_date( $format, $timestamp, $timezone = null ) {
    return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone ?: new DateTimeZone( 'UTC' ) )->format( $format );
}
function suzy_vte_vancouver_timezone( ?int $time = null ) {
    return null === $time || $time >= 1772964000 ? new DateTimeZone( '-07:00' ) : new DateTimeZone( 'America/Vancouver' );
}
function suzy_vte_archive_topic( array $event ) {
    $title = strtolower( $event['title'] ?? '' );
    if ( str_contains( $title, 'ai ' ) ) { return 'ai'; }
    if ( str_contains( $title, 'security' ) ) { return 'security'; }
    return 'other';
}
function is_admin() { return $GLOBALS['admin'] ?? false; }
function home_url( $path = '' ) { return 'https://www.suzyeaston.ca' . $path; }
function get_template_directory() { return dirname( __DIR__ ); }
function get_template_directory_uri() { return 'https://www.suzyeaston.ca/wp-content/themes/suzy'; }
function status_header( $code ) { $GLOBALS['status'] = $code; }
function wp_enqueue_style(...$args) { $GLOBALS['enqueued'][] = $args[0]; }
class WP_Query { public $is_404 = true; }

function fixture_time( $value ) { return strtotime( $value . ' -07:00' ); }
function suzy_get_vancouver_tech_events() {
    ++$GLOBALS['tech_calls'];
    return [ 'events' => [
        [ 'title' => 'AI Music and Technology Night', 'start' => fixture_time('2026-11-16 19:00'), 'url' => 'https://luma.com/abc', 'location' => 'Vancouver Lab', 'source' => 'Tech organizer' ],
        [ 'title' => 'Security Workshop', 'start' => fixture_time('2026-11-20 18:30'), 'url' => 'https://meetup.com/secure', 'location' => 'Downtown', 'source' => 'Security Meetup' ],
        [ 'title' => 'Poor record', 'start' => 0, 'url' => 'javascript:alert(1)', 'source' => 'Untrusted' ],
    ] ];
}
function suzy_vmc_events( ?int $now = null ) {
    ++$GLOBALS['music_calls'];
    return [
        [ 'title' => 'AI Music and Technology Night', 'start' => fixture_time('2026-11-16 19:00'), 'url' => 'https://luma.com/abc?utm_source=listing', 'location' => 'Vancouver Lab', 'source' => 'Culture calendar', 'category' => 'music-tech', 'origin' => 'verified' ],
        [ 'title' => 'Improvised Jazz', 'start' => fixture_time('2026-11-18 20:00'), 'url' => 'https://redgate.tv/tickets/', 'location' => 'Red Gate', 'source' => 'Rhythm Changes', 'category' => 'experimental', 'time_note' => 'Doors at 8 PM; show time unconfirmed' ],
        [ 'title' => 'Different Local Gig', 'start' => fixture_time('2026-11-18 20:00'), 'url' => 'https://redgate.tv/tickets/', 'location' => 'Red Gate', 'source' => 'Red Gate', 'category' => 'music' ],
    ];
}
function suzy_vte_archive_listings( ?int $now = null ) {
    ++$GLOBALS['history_calls'];
    return [
        [ 'title' => 'AI Archives Conference', 'start' => fixture_time('2026-11-07 12:00'), 'url' => 'https://luma.com/old-ai', 'location' => 'Downtown Vancouver', 'source' => 'Tech source', 'origin' => 'verified' ],
        [ 'title' => 'Security Talk in 2025', 'start' => strtotime('2025-11-07T18:00:00-08:00'), 'url' => 'https://luma.com/old-sec', 'source' => 'Tech source' ],
    ];
}
function suzy_vmc_history( ?int $now = null ) {
    ++$GLOBALS['history_calls'];
    return [
        [ 'title' => 'Poetry Salon', 'start' => fixture_time('2026-11-08 18:30'), 'url' => 'https://upstartandcrow.com/event/past', 'location' => 'Upstart & Crow', 'source' => 'Organizer', 'category' => 'poetry' ],
        [ 'title' => 'AI Archives Conference', 'start' => fixture_time('2026-11-07 12:00'), 'url' => 'https://luma.com/old-ai', 'source' => 'Culture source', 'category' => 'arts' ],
    ];
}
require_once __DIR__ . '/../inc/vancouver-events.php';
$tests = 0;
function check_hub( $condition, $message ) {
    global $tests;
    if ( ! $condition ) { fwrite(STDERR, "FAILED: $message\n"); exit(1); }
    ++$tests;
}
$now = fixture_time('2026-11-14 12:00');
$upcoming = suzy_yvr_events_records( 'upcoming', $now );
check_hub( count( $upcoming ) === 4, 'two original feeds combine without losing different gigs or duplicating the same event' );
check_hub( $upcoming[0]['title'] === 'AI Music and Technology Night', 'upcoming events sorted chronologically' );
check_hub( count( $upcoming[0]['tracks'] ) === 2, 'shared music-tech event has both scene badges' );
check_hub( in_array( 'tech:ai', $upcoming[0]['topics'], true ), 'tech subject retained on shared event' );
check_hub( in_array( 'culture:music-tech', $upcoming[0]['topics'], true ), 'culture subject retained on shared event' );
check_hub( $upcoming[0]['origin'] === 'verified', 'organizer-verified provenance retained' );
check_hub( count( array_filter( $upcoming, static fn ( $e ) => $e['url'] === 'https://redgate.tv/tickets/' ) ) === 2, 'shared ticket page can represent separate gigs' );
check_hub( in_array( 'Doors at 8 PM; show time unconfirmed', array_column( $upcoming, 'time_note' ), true ), 'doors note not lost' );
check_hub( $GLOBALS['tech_calls'] === 1 && $GLOBALS['music_calls'] === 1, 'uses each existing upcoming collection once' );
check_hub( null === suzy_yvr_events_normalize( [ 'title' => 'Bad', 'start' => $now, 'url' => 'javascript:alert(1)' ], 'tech' ), 'unsafe event URL discarded' );
check_hub( null === suzy_yvr_events_normalize( [ 'title' => 'Missing date', 'url' => 'https://example.com' ], 'culture' ), 'untimed events discarded' );

$filters = suzy_yvr_events_filters( [ 'yve_track' => 'culture', 'yve_topic' => 'culture:experimental', 'yve_q' => 'Red Gate', 'yve_period' => '7' ] );
$matched = suzy_yvr_events_filter( $upcoming, $filters, $now );
check_hub( count( $matched ) === 1 && $matched[0]['title'] === 'Improvised Jazz', 'scene+topic+venue+date filters combine' );
$tech = suzy_yvr_events_filter( $upcoming, suzy_yvr_events_filters( [ 'yve_track' => 'tech' ] ), $now );
check_hub( count( $tech ) === 2, 'tech scene returns cross-category match plus standalone tech event' );
$music = suzy_yvr_events_filter( $upcoming, suzy_yvr_events_filters( [ 'yve_track' => 'culture' ] ), $now );
check_hub( count( $music ) === 3, 'culture scene returns cross-category match plus two gigs' );
$short = suzy_yvr_events_filter( $upcoming, suzy_yvr_events_filters( [ 'yve_period' => '3' ] ), $now );
check_hub( count( $short ) === 4, 'invalid window resets safely to all upcoming' );
$short = suzy_yvr_events_filter( $upcoming, suzy_yvr_events_filters( [ 'yve_period' => '7' ] ), $now );
check_hub( count( $short ) === 4, 'seven-day window includes all near-term fixture events' );

$archives = suzy_yvr_events_records( 'archive', $now );
check_hub( count( $archives ) === 3, 'historical archives combined and cross-source occurrences deduplicated' );
check_hub( $archives[0]['title'] === 'Poetry Salon', 'past events sorted newest first' );
check_hub( $GLOBALS['tech_calls'] === 1 && $GLOBALS['music_calls'] === 1, 'archive view never refetches remote upcoming sources' );
check_hub( $GLOBALS['history_calls'] === 2, 'archive reads each existing persistent index once' );
check_hub( suzy_yvr_events_archive_years( $archives ) === [ '2026', '2025' ], 'archive year choices descending' );
$archive_filter = suzy_yvr_events_filters( [
    'yve_view' => 'archive', 'yve_year' => '2026', 'yve_month' => '11',
    'yve_topic' => 'culture:poetry', 'yve_q' => 'Upstart',
] );
$matches = suzy_yvr_events_filter( $archives, $archive_filter, $now );
check_hub( count( $matches ) === 1 && $matches[0]['title'] === 'Poetry Salon', 'archive filters support year, month, topic and organizer' );
$invalid = suzy_yvr_events_filters( [
    'yve_view' => 'delete', 'yve_track' => 'all; DROP TABLE',
    'yve_topic' => 'not-a-topic', 'yve_year' => '3000',
    'yve_month' => '23', 'yve_page' => '-200', 'yve_q' => [ 'malformed' ],
] );
check_hub( $invalid['view'] === 'upcoming' && $invalid['track'] === 'all' && $invalid['topic'] === 'all', 'invalid enumerations are rejected' );
check_hub( $invalid['year'] === '' && $invalid['month'] === 'all' && $invalid['page'] === 1 && $invalid['q'] === '', 'unsafe year/month/page/search values discarded' );
check_hub( strlen( suzy_yvr_events_filters( [ 'yve_q' => str_repeat( 'x', 300 ) ] )['q'] ) === 80, 'search length bounded' );

$_SERVER['REQUEST_URI'] = '/vancouver-events/?yve_track=tech&yve_view=archive';
check_hub( suzy_yvr_events_is_route(), 'exact hub request resolves with filters' );
$_SERVER['REQUEST_URI'] = '/vancouver-tech-events/';
check_hub( ! suzy_yvr_events_is_route(), 'legacy tech route remains independent' );
$_SERVER['REQUEST_URI'] = '/vancouver-music-culture/';
check_hub( ! suzy_yvr_events_is_route(), 'legacy culture route remains independent' );
$GLOBALS['admin'] = true;
$_SERVER['REQUEST_URI'] = '/vancouver-events/';
check_hub( ! suzy_yvr_events_is_route(), 'admin requests are never rewritten' );
check_hub( in_array( 'template_include', $GLOBALS['registered_filters'], true ), 'route template hook registered' );
check_hub( in_array( 'wp_enqueue_scripts', $GLOBALS['registered_actions'], true ), 'style enqueued only by route guard' );

echo "Passed $tests unified Vancouver Events tests.\n";

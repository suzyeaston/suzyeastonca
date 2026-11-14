<?php
// Run: php tests/VancouverTechEventsTest.php (no WordPress or network required).
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
function add_shortcode(...$args) {}
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_html($s); }
function get_option($key, $default = false) {
    if ($key === 'date_format') return 'F j, Y';
    if ($key === 'time_format') return 'g:i a';
    return $GLOBALS['options'][$key] ?? $default;
}
function add_option($key, $value, $deprecated = '', $autoload = null) {
    if (isset($GLOBALS['options'][$key])) return false;
    $GLOBALS['options'][$key] = $value;
    $GLOBALS['archive_autoload'] = $autoload;
    return true;
}
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function add_action(...$args) { $GLOBALS['registered_actions'][] = $args[0]; }
function wp_next_scheduled($hook) { return $GLOBALS['scheduled'][$hook] ?? false; }
function wp_schedule_event($timestamp, $recurrence, $hook) { $GLOBALS['scheduled'][$hook] = $timestamp; $GLOBALS['schedule_recurrence'] = $recurrence; return true; }
function home_url($path = '') { return 'https://www.suzyeaston.ca' . $path; }
function wp_date($format, $timestamp, $tz = null) {
    return (new DateTimeImmutable('@'.$timestamp))->setTimezone($tz ?? new DateTimeZone('UTC'))->format($format);
}
function current_user_can($capability) { return false; }
function get_transient($key) { return $GLOBALS['cache'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['cache'][$key] = $value; }
function add_query_arg($args, $url) { return $url.'?'.http_build_query($args); }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error { public function __construct(...$args) {} }
function wp_remote_get($url, $args) {
    $GLOBALS['requests'][] = $url;
    if (str_contains($url, '/url?') && str_contains($url, '3fkowyad')) {
        return ['body' => json_encode(['data'=>['event'=>[
            'name'=>'DEFRAG: Archive of Synthetic Dreams',
            'url'=>'3fkowyad',
            'start_at'=>'2026-11-18T21:00:00Z',
            'end_at'=>'2026-11-20T06:00:00Z',
            'timezone'=>'America/Vancouver',
        ]]])];
    }
    if (str_contains($url, '/url?')) { return ['body' => '{"calendar":{"api_id":"cal-test"}}']; }
    if (str_contains($url, 'luma.com/discover/vancouver/ai')) {
        return ['body'=>'<html><a href="/3fkowyad">DEFRAG</a><a href="/discover">Not an event</a><a href="https://evil.test/not-event">Untrusted</a></html>'];
    }
    if (str_contains($url, 'eventbrite.ca/d/')) {
        return ['body' => '<a href="https://www.eventbrite.com/e/example-ai-guild-tickets-1234567890?aff=search">Example guild</a>'];
    }
    if (str_contains($url, '/e/example-ai-guild-tickets-1234567890')) {
        return ['body' => '<script type="application/ld+json">'.json_encode([
            '@type' => 'Event', 'name' => 'Example AI Guild',
            'startDate' => '2026-11-20T18:30:00-07:00',
            'endDate' => '2026-11-20T21:30:00-07:00',
            'location' => ['name' => 'The Beaumont Studios'],
        ]).'</script>'];
    }
    if (str_contains($url, '/calendar/get-items')) { return ['body' => '{"entries":[],"has_more":false}']; }
    return ['body' => '<html></html>'];
}
function wp_remote_retrieve_body($r) { return $r['body']; }
function wp_remote_retrieve_response_code($r) { return 200; }
function wp_remote_retrieve_header($r, $key) { return 'text/html'; }
require __DIR__.'/../inc/vancouver-tech-events.php';
$checks = 0;
function check($ok, $label) {
    global $checks;
    if (!$ok) { fwrite(STDERR, 'FAIL: '.$label."\n"); exit(1); }
    $checks++;
}
function fixture($title, $url, $date = '2026-10-01T01:00:00Z') {
    return ['event' => ['name' => $title, 'url' => $url, 'start_at' => $date, 'timezone' => 'America/Vancouver']];
}
$data = ['featured_items' => [fixture('Builders night', 'builders-night')],
         'events' => [fixture('Builders night', 'builders-night'), fixture('Workshop', 'workshop')],
         'entries' => [fixture('Security social', 'security-social')]];
$html = '<script id="__NEXT_DATA__" type="application/json">'.json_encode(['props'=>['pageProps'=>['initialData'=>['data'=>$data]]]]).'</script>';
$parsed = suzy_vte_parse_luma_next_data_events($html, 'Test');
check(count($parsed) === 3, 'featured and ordinary entries survive, duplicate removed');
check(suzy_vte_parse_luma_next_data_events('<html>blocked</html>', 'Test') === [], 'blocked page produces no invented events');
check(suzy_vte_parse_luma_next_data_events('<script id="__NEXT_DATA__">invalid</script>', 'Test') === [], 'invalid JSON is harmless');
$one = ['title'=>'One', 'start'=>100, 'url'=>'https://lu.ma/AbC123?utm_source=x', 'location'=>'DCTRL'];
$two = ['title'=>'One', 'start'=>100, 'url'=>'https://luma.com/AbC123', 'location'=>'328 W Hastings'];
check(suzy_vte_event_identity_key($one) === suzy_vte_event_identity_key($two), 'cross-domain Luma copies dedupe despite venue spelling');
$two['start'] = 200;
check(suzy_vte_event_identity_key($one) !== suzy_vte_event_identity_key($two), 'recurring occurrences stay separate');
$two['start'] = 100; $two['url'] = 'https://luma.com/abc123';
check(suzy_vte_event_identity_key($one) !== suzy_vte_event_identity_key($two), 'case-sensitive event path preserved');
foreach (['vancouver.dev', 'Vantug', 'WhiteHatSecurityCommunityVancouver'] as $slug) {
    $GLOBALS['requests'] = [];
    suzy_fetch_vancouver_tech_events_from_luma_calendar(['slug'=>$slug, 'url'=>'https://luma.com/'.$slug], true);
    check(in_array('https://api.lu.ma/url?url='.urlencode($slug), $GLOBALS['requests'], true), 'slug preserved: '.$slug);
}
$now = time();
$events = [['title'=>'finished','start'=>$now-5000,'end'=>$now-1], ['title'=>'ongoing','start'=>$now-86400,'end'=>$now+86400], ['title'=>'future','start'=>$now+5000]];
check(count(suzy_vte_upcoming_events($events, $now)) === 2, 'finished removed, ongoing multiday preserved');
$GLOBALS['cache']['suzy_vancouver_tech_events_cache_v8'] = $events;
check(count(suzy_get_vancouver_tech_events()['events']) === 2, 'cached records are filtered again at read time');
$display = suzy_render_vancouver_tech_events_html([['title'=>'Safe <script>', 'start'=>strtotime('2026-10-01T01:00:00Z'), 'url'=>'https://luma.com/test', 'source'=>'DCTRL']]);
check(str_contains($display, 'Wednesday, September 30, 2026'), 'date bucket is Vancouver even if WordPress timezone is UTC');
check(str_contains($display, '6:00 pm'), 'summer time is Pacific daylight time');
check(str_contains($display, 'Safe &lt;script&gt;'), 'title escaped');
$winter = suzy_vte_render_event_list_item(['title'=>'Winter','start'=>strtotime('2025-12-01T02:00:00Z')]);
check(str_contains($winter, '6:00 pm'), 'historical winter date follows Vancouver timezone rules');
check(str_contains($display, 'https://vanhack.ca/events-calendar'), 'maker calendar remains discoverable');
check(str_contains($display, 'usr-XQ1OMFlMqL7Ajax'), 'DCTRL hosted events linked directly');
check(str_contains(suzy_render_vancouver_tech_events_html([]), 'Vancouver Hack Space'), 'community links remain when feed empty');
check(count(suzy_get_vancouver_tech_event_sources()) === 21, 'Vancouver AI and Tech discovery added to existing organizer sources');
$source_ids = array_column(suzy_get_vancouver_tech_event_sources(), 'id');
check(in_array('luma_discover_ai', $source_ids, true) && in_array('luma_discover_tech', $source_ids, true), 'both public Luma discovery categories are registered');
check(suzy_vte_luma_public_event_slug('https://luma.com/3fkowyad?utm_source=x') === '3fkowyad', 'discovery event slug preserves canonical case');
check(suzy_vte_luma_public_event_slug('https://luma.com/discover/vancouver/ai') === null, 'discovery pages are never considered individual events');
check(suzy_vte_luma_public_event_slug('https://evil.test/3fkowyad') === null, 'untrusted event host rejected');
$GLOBALS['requests'] = [];
$discovery = suzy_fetch_vancouver_tech_events_from_html_luma([
    'url'=>'https://luma.com/discover/vancouver/ai',
    'source'=>'Luma Vancouver AI',
    'discovery'=>true,
    'max_details'=>20,
], true);
check(count($discovery['events']) === 1, 'independent organizer event is imported from public Vancouver AI discovery');
check($discovery['events'][0]['title'] === 'DEFRAG: Archive of Synthetic Dreams', 'real Luma event title is preserved');
check($discovery['events'][0]['start'] === strtotime('2026-11-18T21:00:00Z'), 'event start comes from Luma public API, not a search-card guess');
check(in_array('https://api.lu.ma/url?url=3fkowyad', $GLOBALS['requests'], true), 'Luma public event URL resolver was requested');
$eb_source = ['url' => 'https://www.eventbrite.ca/d/canada--vancouver/tech/', 'source' => 'Eventbrite Vancouver Tech'];
$eb_html = '<a href="https://www.eventbrite.ca/e/ai-night-tickets-999?aff=foo">One</a>'
    .'<a href="/e/ai-night-tickets-999?aff=other">Duplicate</a>'
    .'<a href="https://evil.example/e/ai-night-tickets-111">Not Eventbrite</a>';
$eb_urls = suzy_vte_eventbrite_event_urls($eb_html, $eb_source['url']);
check(count($eb_urls) === 1 && str_contains($eb_urls[0], '/e/ai-night-tickets-999'), 'search URLs canonicalized, tracking removed, untrusted host rejected');
check(suzy_vte_eventbrite_event_urls('<html>blocked</html>', $eb_source['url']) === [], 'blocked search page does not invent events');
$eb_a = ['title'=>'AI Night', 'start'=>100, 'url'=>'https://www.eventbrite.ca/e/ai-night-tickets-999?aff=x'];
$eb_b = ['title'=>'AI Night', 'start'=>100, 'url'=>'https://www.eventbrite.com/e/ai-night-tickets-999?aff=y'];
check(suzy_vte_event_identity_key($eb_a) === suzy_vte_event_identity_key($eb_b), 'Eventbrite .com and .ca duplicates share ticket identity');
$eb_result = suzy_fetch_vancouver_tech_events_from_html_eventbrite($eb_source, true);
check(count($eb_result['events']) === 1, 'Eventbrite events parsed from real event details');
check($eb_result['events'][0]['title'] === 'Example AI Guild', 'Eventbrite detail title not guessed from a search card');
$eventbrite_time = wp_date('g:i a', $eb_result['events'][0]['start'], suzy_vte_vancouver_timezone($eb_result['events'][0]['start']));
check($eventbrite_time === '6:30 pm', 'Eventbrite offset converted to Vancouver time (actual: '.$eventbrite_time.', timestamp: '.$eb_result['events'][0]['start'].')');
$verified = suzy_get_vancouver_tech_verified_fallback_events();
check(count($verified) === 4, 'four source-verified one-off events available');
check($verified[2]['start'] === strtotime('2026-11-10T01:30:00Z'), 'AI Builders Guild starts November 9 6:30 Pacific UTC-7');
check($verified[2]['end'] === strtotime('2026-11-10T04:30:00Z'), 'AI Builders Guild end time preserved');
check($verified[3]['url'] === 'https://luma.com/3fkowyad', 'DEFRAG safety net links to the authoritative event page');
check($verified[3]['start'] === strtotime('2026-11-18T21:00:00Z'), 'DEFRAG starts Nov 18 at 2 pm Vancouver time');
check($verified[3]['end'] === strtotime('2026-11-20T06:00:00Z'), 'DEFRAG remains listed through Nov 19 at 11 pm Vancouver time');
check(str_contains($display, 'Vancouver Java User Group'), 'JUG organizer fallback link visible');
check(str_contains($display, 'Eventbrite Vancouver Tech'), 'Eventbrite discovery link visible');
$november_vancouver_time = strtotime('2026-11-13T01:30:00Z');
check(wp_date('g:i a', $november_vancouver_time, suzy_vte_vancouver_timezone($november_vancouver_time)) === '6:30 pm', 'November 2026 event uses permanent BC UTC-7');
$bc_label = suzy_vte_render_event_list_item(['title'=>'Film Club', 'start'=>$november_vancouver_time]);
check(str_contains($bc_label, '6:30 pm'), 'event list renders November UTC-7 regardless of outdated server tzdata');
$historical_time = strtotime('2025-12-01T02:00:00Z');
check(wp_date('g:i a', $historical_time, suzy_vte_vancouver_timezone($historical_time)) === '6:00 pm', 'historic winter times remain PST UTC-8');

// Archive: source observations survive expiration, transient resets and blocked feeds.
$fixed_now = strtotime('2026-11-13T20:00:00Z');
$archive_event = [
    'title' => 'AI Tools Night', 'start' => strtotime('2026-09-14T01:30:00Z'),
    'end' => strtotime('2026-09-14T04:00:00Z'),
    'url' => 'https://luma.com/ArchiveTest42', 'location' => 'Vancouver', 'source' => 'Test feed',
];
check(suzy_vte_archive_normalize(['title'=>'No URL', 'start'=>12345]) === null, 'unlinked/incomplete records are excluded');
check(suzy_vte_archive_normalize(['title'=>'No date', 'url'=>'https://luma.com/abc']) === null, 'undated records are excluded');
$GLOBALS['options'] = [];
suzy_vte_archive_capture([$archive_event, $archive_event]);
check(count($GLOBALS['options']['suzy_vte_event_archive_v1']) === 1, 'repeat observations of one event are idempotent');
check($GLOBALS['archive_autoload'] === false, 'archive WordPress option is non-autoloaded');
suzy_vte_archive_capture([]); // should not delete past observations
check(count($GLOBALS['options']['suzy_vte_event_archive_v1']) === 1, 'empty/blocked fetch preserves historical listings');
$archived = suzy_vte_archive_listings($fixed_now);
check(count($archived) === 5, 'four pre-archive verified records and one observed feed record appear; future excluded');
check($archived[0]['title'] === 'AI Tools Night' || str_contains($archived[0]['title'], 'MiningTech'), 'archive is sorted recent-first');
check(!in_array('DEFRAG: Archive of Synthetic Dreams', array_column($archived, 'title'), true), 'not-yet-finished events are excluded');
$other_year = [
    'title' => 'Security Meetup', 'start' => strtotime('2025-07-10T01:00:00Z'),
    'url' => 'https://luma.com/PreviousYear1', 'source' => 'Test',
];
suzy_vte_archive_capture([$other_year]);
check(count($GLOBALS['options']['suzy_vte_event_archive_v1']) === 2, 'multiple years persist together');
$all_archive = suzy_vte_archive_listings($fixed_now);
check(count($all_archive) === 6, 'saved prior-year record added to seeded archive');
check(suzy_vte_archive_topic(['title'=>'Live AI model workshop']) === 'ai', 'AI inferred from title');
check(suzy_vte_archive_topic(['title'=>'Creative film lab night']) === 'creative', 'film is classified as creative tech');
$_GET['vte_year'] = '2025';
$_GET['vte_topic'] = 'security';
$archive_html = suzy_vte_render_archive_html($all_archive, $fixed_now);
check(str_contains($archive_html, 'Security Meetup'), 'year/topic filters include matching records');
check(!str_contains($archive_html, 'AI Tools Night'), 'year/topic filters exclude other records');
check(str_contains($archive_html, 'Monthly listings'), 'trend chart has accessible label');
check(str_contains($archive_html, 'previously listed') && str_contains($archive_html, 'not confirmed attendance'), 'archive discloses provenance and limitations');
unset($_GET['vte_year'], $_GET['vte_topic']);
$archive_safe = suzy_vte_render_archive_html([[
    'title'=>'Script <script>alert(1)</script>', 'start'=>strtotime('2025-01-02T01:00:00Z'),
    'url'=>'https://luma.com/Safe123', 'source'=>'Test', 'location'=>'Anywhere',
]], $fixed_now);
check(str_contains($archive_safe, '&lt;script&gt;'), 'archived titles are escaped');
suzy_vte_archive_schedule();
check(isset($GLOBALS['scheduled']['suzy_vte_archive_refresh']), 'twice-daily archive refresh scheduled');
check($GLOBALS['schedule_recurrence'] === 'twicedaily', 'archive refresh cadence is twice-daily');
check(in_array('suzy_vte_archive_refresh', $GLOBALS['registered_actions'], true), 'archive cron callback registered');

echo "Passed $checks Vancouver event behavior checks.\n";

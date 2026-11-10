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
function get_option($key) { return $key === 'date_format' ? 'F j, Y' : 'g:i a'; }
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
    if (str_contains($url, '/url?')) { return ['body' => '{"calendar":{"api_id":"cal-test"}}']; }
    if (str_contains($url, 'eventbrite.ca/d/')) {
        return ['body' => '<a href="https://www.eventbrite.com/e/example-ai-guild-tickets-1234567890?aff=search">Example guild</a>'];
    }
    if (str_contains($url, '/e/example-ai-guild-tickets-1234567890')) {
        return ['body' => '<script type="application/ld+json">'.json_encode([
            '@type' => 'Event', 'name' => 'Example AI Guild',
            'startDate' => '2026-11-20T18:30:00-08:00',
            'endDate' => '2026-11-20T21:30:00-08:00',
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
$GLOBALS['cache']['suzy_vancouver_tech_events_cache_v7'] = $events;
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
check(count(suzy_get_vancouver_tech_event_sources()) === 19, 'Eventbrite discovery and CNCF calendar sources registered');
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
$eventbrite_time = wp_date('g:i a', $eb_result['events'][0]['start'], new DateTimeZone('America/Vancouver'));
check($eventbrite_time === '6:30 pm', 'Eventbrite offset converted to Vancouver time (actual: '.$eventbrite_time.', timestamp: '.$eb_result['events'][0]['start'].')');
$verified = suzy_get_vancouver_tech_verified_fallback_events();
check(count($verified) === 3, 'three source-verified one-off events available');
check($verified[2]['start'] === strtotime('2026-11-10T02:30:00Z'), 'AI Builders Guild starts November 9 6:30 PST');
check($verified[2]['end'] === strtotime('2026-11-10T05:30:00Z'), 'AI Builders Guild end time preserved');
check(str_contains($display, 'Vancouver Java User Group'), 'JUG organizer fallback link visible');
check(str_contains($display, 'Eventbrite Vancouver Tech'), 'Eventbrite discovery link visible');
echo "Passed $checks Vancouver event behavior checks.\n";

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
$GLOBALS['cache']['suzy_vancouver_tech_events_cache_v6'] = $events;
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
check(count(suzy_get_vancouver_tech_event_sources()) === 16, 'eight direct calendars added');
echo "Passed $checks Vancouver event behavior checks.\n";

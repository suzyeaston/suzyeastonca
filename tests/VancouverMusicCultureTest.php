<?php
// WordPress-free regression checks. Run: php tests/VancouverMusicCultureTest.php
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['options'] = [];
$GLOBALS['transients'] = [];
$GLOBALS['http_calls'] = [];
$GLOBALS['scheduled'] = [];
function add_action(...$args) { $GLOBALS['actions'][] = $args[0]; }
function add_filter(...$args) { $GLOBALS['filters'][] = $args[0]; }
function wp_parse_url($u, $component=-1) { return parse_url($u, $component); }
function wp_date($fmt,$ts,$tz=null) { return (new DateTimeImmutable('@'.$ts))->setTimezone($tz ?: new DateTimeZone('UTC'))->format($fmt); }
function get_option($key,$fallback=false) {
    if ($key === 'time_format') return 'g:i a';
    return $GLOBALS['options'][$key] ?? $fallback;
}
function update_option($key,$value,$autoload=null) { $GLOBALS['options'][$key] = $value; $GLOBALS['autoload'][$key] = $autoload; return true; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key,$val,$duration) { $GLOBALS['transients'][$key]=$val; return true; }
function is_wp_error($v) { return false; }
function is_admin() { return $GLOBALS['is_admin'] ?? false; }
function home_url($path='') { return 'https://www.suzyeaston.ca' . $path; }
function wp_next_scheduled($event) { return $GLOBALS['scheduled'][$event] ?? false; }
function wp_schedule_event($time,$recurrence,$event) { $GLOBALS['scheduled'][$event] = $time; $GLOBALS['recurrence'] = $recurrence; return true; }
function get_template_directory() { return dirname(__DIR__); }
function get_template_directory_uri() { return 'https://www.suzyeaston.ca/wp-content/themes/suzysmusic'; }
function status_header($status) { $GLOBALS['status'] = $status; }
function wp_enqueue_style(...$args) { $GLOBALS['styles'][] = $args[0]; }
class WP_Query { public $is_404 = true; }
function suzy_fetch_vancouver_tech_events_from_html_luma(...$args) {
    return ['events'=>[
        ['title'=>'Music & sound art listening lab','start'=>strtotime('2026-11-26T02:00:00Z'),'url'=>'https://luma.com/MusicalSound','location'=>'Vancouver, Canada','source'=>'Luma'],
        ['title'=>'Enterprise cloud conference','start'=>strtotime('2026-11-26T02:00:00Z'),'url'=>'https://luma.com/Cloud123','location'=>'Vancouver, Canada','source'=>'Luma'],
        ['title'=>'Music festival elsewhere','start'=>strtotime('2026-11-26T02:00:00Z'),'url'=>'https://luma.com/Elsewhere123','location'=>'Seattle','source'=>'Luma'],
    ]];
}
function wp_remote_get($url,$opts=[]) {
    $GLOBALS['http_calls'][] = $url;
    if (str_contains($url,'8east.ca')) {
        if (str_contains($url,'ical=1')) {
            return ['code'=>200,'body'=>"BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nSUMMARY:8EAST Improvisation Workshop\r\nDTSTART;TZID=America/Vancouver:20261122T140000\r\nURL:https://www.8east.ca/event/improv-workshop/\r\nEND:VEVENT\r\nEND:VCALENDAR"];
        }
        if (!empty($GLOBALS['8east_block_json'])) {
            return ['code'=>403,'body'=>'REST temporarily blocked'];
        }
        return ['code'=>200,'body'=>json_encode(['events'=>[
            ['title'=>'Free Improvisation Jam','url'=>'https://www.8east.ca/event/free-improv-jam/','start_date'=>'2026-11-19 19:00:00','end_date'=>'2026-11-19 22:00:00','venue'=>['venue'=>'8EAST','city'=>'Vancouver']],
            ['title'=>'Private off-city show','url'=>'https://www.8east.ca/event/elsewhere/','start_date'=>'2026-11-20 18:00:00','venue'=>['venue'=>'Elsewhere','city'=>'Surrey']]
        ]])];
    }
    if (str_contains($url,'roundhouse.ca')) {
        return ['code'=>403,'body'=>'blocked'];
    }
    if (str_contains($url,'squarespace')) {
        return ['code'=>200,'body'=>json_encode(['items'=>[[
            'title'=>'Modular Spatial Audio Lab','startDate'=>strtotime('2026-11-27T02:00:00Z')*1000,
            'endDate'=>strtotime('2026-11-27T04:00:00Z')*1000,
            'urlId'=>'modular-spatial-audio-lab'
        ]]])];
    }
    if (str_contains($url,'upstartandcrow.com')) {
        if (str_contains($url,'ical=1')) {
            return ['code'=>200,'body'=>"BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:Poetry Salon and Artist Talk\r\nDTSTART;TZID=America/Vancouver:20261128T190000\r\nDTEND;TZID=America/Vancouver:20261128T210000\r\nLOCATION:Upstart & Crow\, Vancouver\r\nURL:https://upstartandcrow.com/event/poetry-salon/\r\nEND:VEVENT\r\nEND:VCALENDAR"];
        }
        return ['code'=>403,'body'=>'REST blocked'];
    }
    if (str_contains($url,'redgate.tv')) {
        return ['code'=>200,'body'=>'<html><body><main><a href="https://www.paypal.com">Sunday November 15th: EVIL SUMMER CAMP $15 Doors at 8 PM (film screening)</a><a href="https://www.paypal.com">Friday November 13th: GOLERS $15 advance Doors at 8 PM</a><a>Thursday November 12th: UNTITLED SHOW no confirmed time</a></main></body></html>'];
    }
    if (str_contains($url,'rhythmchanges.ca')) {
        return ['code'=>200,'body'=>'<html><body><h4>Saturday, November 14</h4><ul><li>8:00 PM - Frankie\'s, Thomas Marriott Quartet ($28)</li><li>9:30 PM - Tyrant Studios, Dark Matter Machine ($20)</li><li>9:00 PM - Surrey Venue, Out-of-town show ($20)</li></ul><h4>Sunday, November 15</h4><ul><li>2:00 PM - 8EAST, Improvised Music Jam (by donation)</li></ul></body></html>'];
    }
    return ['code'=>404,'body'=>'not found'];
}
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
require_once __DIR__ . '/../inc/vancouver-music-culture.php';
$checks=0;
function check_vmc($good,$message) {
    global $checks;
    if (!$good) { fwrite(STDERR,"FAILED: $message\n"); exit(1); }
    ++$checks;
}
check_vmc( count(suzy_vmc_sources()) === 7, 'seven independent automatic source adapters including venues and jazz listings' );
check_vmc( count(suzy_vmc_directory()) >= 10, 'organizer directory spans more than one ecosystem' );
$directory = suzy_vmc_directory();
$groups = suzy_vmc_directory_groups();
check_vmc(count($directory) === 26, '26 source-checked venues and cultural calendars');
check_vmc(count($groups) === 4, 'venues grouped into four readable cultural scenes');
check_vmc(count(array_unique(array_column($directory, 'url'))) === count($directory), 'one distinct link per venue or organizer');
foreach ($directory as $entry) {
    check_vmc(isset($groups[$entry['group']]), 'directory entry belongs to a known group');
    check_vmc(str_starts_with($entry['url'], 'https://'), 'directory link is HTTPS');
}
$labels = implode(' ', array_column($directory, 'label'));
foreach (['Green Auto', 'Red Gate', 'Tyrant Studios', 'VIVO Media Arts', 'UNIT/PITT', 'Upstart & Crow', 'Poetry House', 'Rhythm Changes'] as $needle) {
    check_vmc(str_contains($labels, $needle), 'missing scene-space discovered: ' . $needle);
}
check_vmc(!str_contains($labels, 'Massy Arts'), 'permanently closed gallery not promoted as active');
check_vmc(!in_array('https://massyarts.com/', array_column($directory, 'url'), true), 'closed venue has no misleading link');

$seeds = suzy_vmc_verified_events();
check_vmc( count($seeds) >= 12, 'verified dated safety net includes music and culture' );
check_vmc(in_array('Dead Peasant: Lisa Baldissera in conversation', array_column($seeds, 'title'), true), 'Upstart & Crow book-launch verified listing added');
check_vmc(in_array('Radio Discord: Gregor Craigie book launch', array_column($seeds, 'title'), true), 'Upstart & Crow Dec 1 verified listing added');

$seedcats = array_column($seeds,'category');
foreach (['music-tech','poetry','ideas','experimental','arts'] as $category) {
    check_vmc(in_array($category,$seedcats,true),'verified events include '.$category);
}
check_vmc(suzy_vmc_at('2026-11-18 18:00') === strtotime('2026-11-19T01:00:00Z'),'Vancouver November UTC-7');
check_vmc(suzy_vmc_tz(strtotime('2025-12-01T02:00:00Z'))->getName() === 'America/Vancouver','older dates keep historical PST');
check_vmc(suzy_vmc_infer_category('4D Sound and Modular Synths') === 'music-tech','music tech classifier');
check_vmc(suzy_vmc_infer_category('A Poet Reads New Works') === 'poetry','poetry classifier');
check_vmc(suzy_vmc_infer_category('Philosophy Discussion') === 'ideas','philosophy classifier');
check_vmc(suzy_vmc_normalize(['title'=>'Bad','url'=>'javascript:alert(1)','start'=>time()]) === null,'reject dangerous URLs');
check_vmc(suzy_vmc_normalize(['title'=>'No start','url'=>'https://example.com/event']) === null,'reject missing times');
$fixture = suzy_vmc_sources()[0];
$tribe = suzy_vmc_parse_tribe(json_decode(wp_remote_get($fixture['endpoint'])['body'],true),$fixture);
check_vmc(count($tribe) === 1,'skip out of town Tribe items');
$monthly = suzy_vmc_parse_squarespace(['items'=>[['title'=>'4DSOUND Workshop','startDate'=>strtotime('2026-11-24T01:00:00Z')*1000,'urlId'=>'4dsound-workshop']]],suzy_vmc_sources()[2]);
check_vmc(count($monthly) === 1 && $monthly[0]['start'] === strtotime('2026-11-24T01:00:00Z'),'convert Squarespace ms timestamps');
check_vmc(str_starts_with($monthly[0]['url'],'https://roadrunner-red-yde9.squarespace.com/new-events/'),'Squarespace detail URL constrained to organizer host');

$ical_body = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:Free Improvisation Night\r\nDTSTART;TZID=America/Vancouver:20261119T190000\r\nDTEND;TZID=America/Vancouver:20261119T210000\r\nLOCATION:8EAST\, Vancouver\r\nURL:https://www.8east.ca/event/improv/\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nSUMMARY:Cancelled Show\r\nSTATUS:CANCELLED\r\nDTSTART:20261120T020000Z\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nSUMMARY:All Day Placeholder\r\nDTSTART;VALUE=DATE:20261122\r\nEND:VEVENT\r\nEND:VCALENDAR";
$ical = suzy_vmc_parse_ical($ical_body, $fixture);
check_vmc(count($ical) === 1,'iCalendar parser rejects cancelled and untimed all-day entries');
check_vmc($ical[0]['start'] === strtotime('2026-11-20T02:00:00Z'),'iCalendar timezone conversion is Vancouver-correct');
check_vmc($ical[0]['location'] === '8EAST, Vancouver','iCalendar escaped venue separator decoded');
check_vmc(suzy_vmc_ical_timestamp('DTSTART;VALUE=DATE-TIME', '20261119T190000') === strtotime('2026-11-20T02:00:00Z'),'DATE-TIME is not mistaken for untimed DATE');
check_vmc(suzy_vmc_ical_timestamp('DTSTART;VALUE=DATE', '20261119') === 0,'all-day date has no invented start hour');
$redgate = suzy_vmc_sources()[5];
$redgate_mock = wp_remote_get($redgate['endpoint'])['body'];
$redgate_parsed = suzy_vmc_parse_redgate_html($redgate_mock, $redgate, suzy_vmc_at('2026-11-14 12:00'));
check_vmc(count($redgate_parsed) === 2,'Red Gate tickets parser imports two timed organizer listings');
check_vmc(in_array('GOLERS', array_column($redgate_parsed,'title'), true),'Red Gate show name extracted without PayPal ticket content');
check_vmc(str_contains($redgate_parsed[0]['time_note'], 'show time unconfirmed'),'doors time is labelled, not misrepresented as performance start');
check_vmc(suzy_vmc_yearless_date('Friday', 'November', 13, suzy_vmc_at('2026-11-14 12:00')) === '2026-11-13', 'yearless dates only resolve with a matching weekday near crawl');
check_vmc(suzy_vmc_yearless_date('Saturday', 'November', 13, suzy_vmc_at('2026-11-14 12:00')) === '', 'weekday mismatch cannot create a future event');
$jazz = suzy_vmc_sources()[6];
$jazz_parsed = suzy_vmc_parse_jazz_html(wp_remote_get($jazz['endpoint'])['body'], $jazz, suzy_vmc_at('2026-11-14 12:00'));
check_vmc(count($jazz_parsed) === 3,'Vancouver jazz parser selects three known city rooms, not Surrey');
check_vmc(in_array('Tyrant Studios, Vancouver', array_column($jazz_parsed, 'location'), true),'Tyrant Studios included from secondary jazz source');
check_vmc(in_array('Frankie\'s, Vancouver', array_column($jazz_parsed, 'location'), true),'Frankies included from secondary jazz source');
check_vmc(count(suzy_vmc_dedupe([
    ['title'=>'Night A','start'=>suzy_vmc_at('2026-11-15 20:00'),'url'=>$redgate['url']],
    ['title'=>'Night B','start'=>suzy_vmc_at('2026-11-15 20:00'),'url'=>$redgate['url']],
])) === 2, 'distinct Red Gate events on a shared tickets page do not collide');
$GLOBALS['8east_block_json'] = true;
unset($GLOBALS['transients']['suzy_vmc_source_' . md5('8east')]);
$eight_fallback = suzy_vmc_fetch_source(suzy_vmc_sources()[0]);
check_vmc(str_contains($eight_fallback['status'], 'iCalendar fallback'), '8EAST iCalendar rescues blocked organizer JSON');
check_vmc(in_array('8EAST Improvisation Workshop', array_column($eight_fallback['events'],'title'), true), 'fallback actually imports 8EAST event data');
unset($GLOBALS['transients']['suzy_vmc_source_' . md5('8east')]);
$GLOBALS['8east_block_json'] = false;
$fallback_source = suzy_vmc_sources()[4];
$ics_fallback = suzy_vmc_fetch_source($fallback_source);
check_vmc(str_contains($ics_fallback['status'], 'iCalendar fallback'), 'Upstart and Crow falls back to timed iCalendar when REST is blocked');
check_vmc(in_array('Poetry Salon and Artist Talk',array_column($ics_fallback['events'],'title'),true),'Upstart & Crow iCalendar events enter the collected feed');

$original=$seeds[0];
check_vmc(count(suzy_vmc_dedupe([$original,$original])) === 1,'same event occurrence dedupes');
$next_now=suzy_vmc_at('2026-11-13 18:00');
check_vmc(!in_array('BlackGate: contemporary acoustic & electronic music',array_column(suzy_vmc_events($next_now),'title'),true),'finished events do not appear in upcoming');
check_vmc(in_array('Twisted Poets Literary Salon',array_column(suzy_vmc_events($next_now),'title'),true),'poetry present in upcoming');
check_vmc(in_array('at the root: listening party, poetry & open mic',array_column(suzy_vmc_history($next_now),'title'),true),'archive seeds actual historical events');
suzy_vmc_collect();
$statuses = get_option('suzy_vmc_source_status_v1',[]);
check_vmc(count($statuses['sources'] ?? []) === 7,'all seven sources separately report coverage');
check_vmc(str_contains($statuses['sources'][1]['status'],'HTTP 403'),'blocked source represented without fabricated events');
$live = get_option('suzy_vmc_latest_v1',[]);
check_vmc(in_array('Free Improvisation Jam',array_column($live,'title'),true),'Tribe source ingested');
check_vmc(in_array('Modular Spatial Audio Lab',array_column($live,'title'),true),'Squarespace source ingested');
check_vmc(in_array('Music & sound art listening lab',array_column($live,'title'),true),'Luma local cultural event ingested');
check_vmc(in_array('Poetry Salon and Artist Talk',array_column($live,'title'),true),'Upstart & Crow iCalendar fallback reached upcoming collector');
check_vmc(!in_array('Enterprise cloud conference',array_column($live,'title'),true),'exclude unrelated tech events');
check_vmc(!in_array('Music festival elsewhere',array_column($live,'title'),true),'exclude nonlocal Luma events');
$before = count(suzy_vmc_history(strtotime('2026-12-31T23:59:00Z')));
$archive = get_option('suzy_vmc_archive_v1',[]);
check_vmc(count($archive) >= count($seeds),'collector preserves verification seed into history storage');
check_vmc($GLOBALS['autoload']['suzy_vmc_archive_v1'] === false,'archive option not autoloaded');
unset($GLOBALS['transients']['suzy_vmc_source_'.md5('roundhouse')]);
$GLOBALS['http_calls']=[];
suzy_vmc_capture_history([]);
check_vmc(count(get_option('suzy_vmc_archive_v1',[])) === count($archive),'empty source cannot erase history');
suzy_vmc_schedule();
check_vmc(isset($GLOBALS['scheduled']['suzy_vmc_refresh']),'hourly collection scheduled');
check_vmc($GLOBALS['recurrence'] === 'hourly','hourly interval configured');
$_SERVER['REQUEST_URI']='/vancouver-music-culture/?vmc_category=poetry';
check_vmc(suzy_vmc_is_route(),'exact music culture route recognized');
$_SERVER['REQUEST_URI']='/vancouver-music-culture-else/';
check_vmc(!suzy_vmc_is_route(),'other routes not hijacked');
$_SERVER['REQUEST_URI']='/vancouver-tech-events/';
check_vmc(!suzy_vmc_is_route(),'tech calendar remains separate');
echo "Passed $checks Vancouver Music & Culture checks.\n";

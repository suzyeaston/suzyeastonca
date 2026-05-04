<?php
declare(strict_types=1);

namespace {
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS',60);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS',3600);
if (!function_exists('sanitize_key')) { function sanitize_key($k){ return strtolower(preg_replace('/[^a-z0-9_]/','',(string)$k) ?? ''); } }
if (!function_exists('sanitize_title_with_dashes')) { function sanitize_title_with_dashes($t){ $t=strtolower((string)$t); $t=preg_replace('/[^a-z0-9\-]+/','-',$t)??''; return trim($t,'-'); } }
if (!function_exists('sanitize_email')) { function sanitize_email($e){ return (string)$e; } }
if (!function_exists('is_email')) { function is_email($e){ return false!==strpos((string)$e,'@'); } }
if (!function_exists('get_option')) { function get_option($k,$d=null){ return \LousyOutages\Tests\Opt::get((string)$k,$d);} }
if (!function_exists('update_option')) { function update_option($k,$v,$a=false){ \LousyOutages\Tests\Opt::set((string)$k,$v); return true; } }
if (!function_exists('home_url')) { function home_url($p='/'){ return 'https://example.com'.$p; } }
if (!function_exists('add_query_arg')) { function add_query_arg($a,$u){ return (string)$u; } }
if (!function_exists('current_time')) { function current_time($type,$gmt=false){ return 1700000000; } }
if (!function_exists('do_action')) { function do_action($h,...$a): void {} }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v){ return json_encode($v); } }
if (!function_exists('wp_generate_uuid4')) { function wp_generate_uuid4(){ return 'uuid-1234'; } }
if (!function_exists('send_lo_outage_alert_email')) { function send_lo_outage_alert_email($email,$payload){ return \LousyOutages\Tests\Mail::$ok; } }
}

namespace LousyOutages\Tests { class Opt { public static array $o=[]; static function get(string $k,$d=null){ return self::$o[$k]??$d;} static function set(string $k,$v): void {self::$o[$k]=$v;} static function reset(): void {self::$o=[];} } class Mail { public static bool $ok=true; }}

namespace {
require_once __DIR__ . '/../lousy-outages/includes/Model/Incident.php';
require_once __DIR__ . '/../lousy-outages/includes/Storage/IncidentStore.php';
require_once __DIR__ . '/../lousy-outages/includes/IncidentAlerts.php';

use PHPUnit\Framework\TestCase;
use SuzyEaston\LousyOutages\IncidentAlerts;
use SuzyEaston\LousyOutages\Storage\IncidentStore;

final class IncidentAlertDeliveryTest extends TestCase {
    protected function setUp(): void { \LousyOutages\Tests\Opt::reset(); \LousyOutages\Tests\Mail::$ok=true; }

    public function testSyntheticIncidentDefaults(): void {
        $incident = IncidentAlerts::make_synthetic_incident();
        $this->assertSame('major_outage', $incident->status);
    }

    public function testCanSendDoesNotMutateButMarkSentSuppresses(): void {
        $store = new IncidentStore();
        $i = IncidentAlerts::make_synthetic_incident(['id'=>'fixed-1']);
        $this->assertTrue($store->canSend($i));
        $this->assertSame('', get_option('lousy_outages_last_guid_lousyoutagesqa', ''));
        $store->markSent($i);
        $this->assertFalse($store->canSend($i));
    }


    public function testFilterDigestItemsForSubscriberByProviderPreferences(): void {
        global $wpdb;
        if (!isset($wpdb)) { $wpdb = new class { public string $prefix='wp_'; public function esc_like($t){return (string)$t;} public function prepare($q,...$a){return ['type'=>'none'];} public function get_var($q){return 'wp_lousy_outages_subs';} public function get_col($q){return ['email','providers','realtime_alerts','daily_digest','newsletter','status','token','created_at','updated_at','ip_hash','consent_source','consent_version','confirmed_at'];} public function get_row($p,$o=null){return null;} }; }
        $items=[['provider_id'=>'github','provider'=>'GitHub','title'=>'a','status'=>'Open','url'=>'u'],['provider_id'=>'cloudflare','provider'=>'Cloudflare','title'=>'b','status'=>'Open','url'=>'u']];
        $filtered = IncidentAlerts::filter_digest_items_for_subscriber($items, 'nobody@example.com');
        $this->assertCount(2,$filtered);
    }

    public function testGroupDigestItemsCreatesSummaryForNoisyProvider(): void {
        $items=[];
        for($i=0;$i<5;$i++){$items[]=['provider_id'=>'cloudflare','provider'=>'Cloudflare','title'=>'t'.$i,'status'=>'Open','url'=>'https://www.cloudflarestatus.com','updated'=>100+$i];}
        $grouped = IncidentAlerts::group_digest_items($items,3);
        $real = array_values(array_filter($grouped, fn($it)=>empty($it['grouped'])));
        $summary = array_values(array_filter($grouped, fn($it)=>!empty($it['grouped'])));
        $this->assertLessThanOrEqual(3,count($real));
        $this->assertCount(1,$summary);
        $this->assertSame('Grouped updates',$summary[0]['status']);
    }

    public function testProcessIncidentsFailureWritesDiagnostics(): void {
        update_option('lousy_outages_email','qa@example.com',false);
        \LousyOutages\Tests\Mail::$ok=false;
        $result = IncidentAlerts::process_incidents([IncidentAlerts::make_synthetic_incident(['id'=>'fixed-2'])], ['synthetic'=>true,'notification_only'=>true]);
        $this->assertSame(1, $result['failed']);
        $this->assertNotEmpty(get_option('lousy_outages_alert_delivery_failure', []));
    }
}
}

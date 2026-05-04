<?php
declare(strict_types=1);

namespace {
if (!defined('ARRAY_A')) define('ARRAY_A','ARRAY_A');
if (!defined('OBJECT')) define('OBJECT','OBJECT');
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS',86400);
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
if (!function_exists('sanitize_key')) { function sanitize_key($k){ $k=strtolower((string)$k); return preg_replace('/[^a-z0-9_]/','',$k)??''; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v){ return json_encode($v); } }
if (!function_exists('is_email')) { function is_email($e){ return false!==strpos((string)$e,'@'); } }
if (!function_exists('sanitize_email')) { function sanitize_email($e){ return trim(strtolower((string)$e)); } }
if (!function_exists('wp_parse_url')) { function wp_parse_url($url,$component=-1){ return parse_url((string)$url,$component); } }
if (!function_exists('trailingslashit')) { function trailingslashit($v){ return rtrim((string)$v,'/').'/'; } }
if (!function_exists('add_action')) { function add_action($h,$c,$p=10,$a=1){} }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($h){ return false; } }
if (!function_exists('wp_schedule_event')) { function wp_schedule_event($t,$r,$h){ return true; } }
if (!function_exists('apply_filters')) { function apply_filters($tag,$value){ return $value; } }

require_once __DIR__.'/../lousy-outages/includes/Providers.php';
require_once __DIR__.'/../lousy-outages/includes/Subscriptions.php';

class FakeWpdb {
    public string $prefix='wp_'; public bool $table_exists=false; public array $rows=[];
    public function get_charset_collate(): string { return 'CHARSET=utf8mb4'; }
    public function prepare($q,...$args){ $l=strtolower((string)$q); if(str_contains($l,'show tables like')) return ['type'=>'exists']; if(str_contains($l,'where email')) return ['type'=>'email','value'=>(string)($args[0]??'')]; if(str_contains($l,'where token')) return ['type'=>'token','value'=>(string)($args[0]??'')]; return ['type'=>'raw']; }
    public function get_col($q){ return $this->table_exists?['id','email','status','token','created_at','updated_at','ip_hash','consent_source','providers','realtime_alerts','daily_digest','newsletter','consent_version','confirmed_at']:[]; }
    public function get_var($prepared){ return (is_array($prepared)&&($prepared['type']??'')==='exists'&&$this->table_exists)?'wp_lousy_outages_subs':null; }
    public function get_row($prepared,$output=OBJECT){ if(!is_array($prepared)) return null; foreach($this->rows as $row){ if(($prepared['type']??'')==='email'&&$row['email']===$prepared['value']) return $output===ARRAY_A?$row:(object)$row; if(($prepared['type']??'')==='token'&&$row['token']===$prepared['value']) return $output===ARRAY_A?$row:(object)$row; } return null; }
    public function update($table,$data,$where,$f=null,$wf=null): int { foreach($this->rows as $i=>$r){ $m=true; foreach($where as $k=>$v){ if(($r[$k]??null)!=$v){$m=false; break;} } if($m){ $this->rows[$i]=array_merge($this->rows[$i],$data); return 1; } } return 0; }
    public function insert($table,$data,$f=null): int { $data['id']=count($this->rows)+1; $this->rows[]=$data; return 1; }
    public function query($q){ return 0; }
    public function esc_like($t){ return addslashes((string)$t); }
}
}

namespace LousyOutages\Tests {
use SuzyEaston\LousyOutages\Subscriptions;
$tests=[];
$tests['normalize_provider_ids']=function(){ $out=Subscriptions::normalize_provider_ids(['github','GitHub','cloudflare','bad*id']); if($out!==['github','cloudflare']) throw new \RuntimeException('normalize_provider_ids failed');};
$tests['normalize_preferences_defaults_and_checkbox']=function(){ $d=Subscriptions::normalize_preferences([]); if(!$d['realtime_alerts']||$d['daily_digest']||$d['newsletter']) throw new \RuntimeException('defaults failed'); $v=Subscriptions::normalize_preferences(['daily_digest'=>'on','newsletter'=>'1','realtime_alerts'=>'']); if($v['realtime_alerts']||!$v['daily_digest']||!$v['newsletter']) throw new \RuntimeException('checkbox parse failed');};
$tests['save_pending_with_preferences_and_reconfirm']=function(){ global $wpdb; $wpdb=new \FakeWpdb(); $t1='tok1'; Subscriptions::save_pending_with_preferences('user@example.com',$t1,'h','form',['providers'=>['github','cloudflare'],'daily_digest'=>'on']); $row=$wpdb->rows[0]; if($row['providers']!==json_encode(['github','cloudflare'])) throw new \RuntimeException('providers json missing'); if((int)$row['daily_digest']!==1||(int)$row['realtime_alerts']!==1||(int)$row['newsletter']!==0) throw new \RuntimeException('flags wrong'); Subscriptions::save_pending_with_preferences('user@example.com','tok2','h2','form',['providers'=>['github'],'newsletter'=>'on']); if(count($wpdb->rows)!==1||$wpdb->rows[0]['token']!=='tok2') throw new \RuntimeException('reconfirm did not update token');};
$tests['get_preferences_known_unknown']=function(){ global $wpdb; $wpdb=new \FakeWpdb(); $wpdb->rows[]=['id'=>1,'email'=>'known@example.com','token'=>'t','providers'=>json_encode(['github']),'realtime_alerts'=>0,'daily_digest'=>1,'newsletter'=>1]; $k=Subscriptions::get_preferences_for_email('known@example.com'); if($k['providers']!==['github']||$k['realtime_alerts']||!$k['daily_digest']||!$k['newsletter']) throw new \RuntimeException('known prefs wrong'); $u=Subscriptions::get_preferences_for_email('unknown@example.com'); if($u!==['providers'=>[],'realtime_alerts'=>true,'daily_digest'=>false,'newsletter'=>false]) throw new \RuntimeException('unknown defaults wrong');};
$tests['subscriber_wants_provider']=function(){ global $wpdb; $wpdb=new \FakeWpdb(); $wpdb->rows[]=['id'=>1,'email'=>'all@example.com','token'=>'a','providers'=>'[]','realtime_alerts'=>1,'daily_digest'=>0,'newsletter'=>0]; $wpdb->rows[]=['id'=>2,'email'=>'one@example.com','token'=>'b','providers'=>'["github"]','realtime_alerts'=>1,'daily_digest'=>0,'newsletter'=>0]; if(!Subscriptions::subscriber_wants_provider('all@example.com','cloudflare')) throw new \RuntimeException('empty providers should allow all'); if(!Subscriptions::subscriber_wants_provider('one@example.com','github')) throw new \RuntimeException('selected should pass'); if(Subscriptions::subscriber_wants_provider('one@example.com','cloudflare')) throw new \RuntimeException('non-selected should fail');};

$failed=false; foreach($tests as $n=>$cb){ try{$cb(); echo "ok - {$n}\n";}catch(\Throwable $t){$failed=true; echo "not ok - {$n}: {$t->getMessage()}\n";}}
if($failed) exit(1); echo "All tests passed\n";
}

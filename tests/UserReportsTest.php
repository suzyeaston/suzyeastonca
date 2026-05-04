<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
if (!function_exists('get_option')) { function get_option($k,$d=null){ return $d; } }
if (!function_exists('update_option')) { function update_option($k,$v,$a=null){ return true; } }
require_once __DIR__.'/../lousy-outages/includes/Providers.php';
require_once __DIR__.'/../lousy-outages/includes/SignalEngine.php';
require_once __DIR__.'/../lousy-outages/includes/UserReports.php';
require_once __DIR__.'/../lousy-outages/includes/ExternalSignals.php';
use SuzyEaston\LousyOutages\UserReports;
use SuzyEaston\LousyOutages\ExternalSignals;

class FakeWpdbUserReports {
    public string $prefix='wp_'; public array $rows=[]; public bool $table_exists=true; public bool $external_table_exists=true;
    public function prepare($q,...$args){ return ['q'=>$q,'args'=>$args]; }
    public function insert($t,$d){ $this->rows[]=$d; return 1; }
    public function get_var($p){ $q=strtolower($p['q']); $a=$p['args']; if(str_contains($q,'show tables like')){ $like=(string)($a[0]??''); if(str_contains($like,'lo_user_reports')) return $this->table_exists?'wp_lo_user_reports':null; if(str_contains($like,'lo_external_signals')) return $this->external_table_exists?'wp_lo_external_signals':null; } if(str_contains($q,'provider_id = %s')){ $pid=$a[0]; $cut=$a[1]; return count(array_filter($this->rows,fn($r)=>$r['provider_id']===$pid&&$r['created_at']>=$cut)); } $cut=$a[0]??''; return count(array_filter($this->rows,fn($r)=>$r['created_at']>=$cut)); }
    public function get_results($p,$o){ $q=strtolower($p['q']); if(str_contains($q,'group by provider_id')){ $cut=$p['args'][0]; $counts=[]; foreach($this->rows as $r){ if($r['created_at']<$cut) continue; $counts[$r['provider_id']]=($counts[$r['provider_id']]??0)+1; } $out=[]; foreach($counts as $k=>$v){$out[]=['provider_id'=>$k,'report_count'=>$v];} return $out; } return array_slice(array_reverse($this->rows),0,25); }
    public function query($prepared){ $before=count($this->rows); $this->rows=array_values(array_filter($this->rows,fn($r)=>($r['source']??'')!=='demo_seed')); return $before-count($this->rows); }
    public function esc_like($value){ return addslashes((string)$value); }
}

$tests=[];
$tests['normalize_input']=function(){ $r=UserReports::normalize_input(['provider_id'=>'notreal']); if(!empty($r['ok'])) throw new RuntimeException('invalid provider'); $ok=UserReports::normalize_input(['provider_id'=>'cloudflare','symptom'=>'nope','details'=>str_repeat('a',600),'ip'=>'1.2.3.4']); if($ok['symptom']!=='other'||strlen($ok['details'])!==500||$ok['ip_hash']==='1.2.3.4') throw new RuntimeException('normalize'); };
$tests['seed_and_clear_demo_reports']=function(){ global $wpdb; $wpdb=new FakeWpdbUserReports(); $seed=UserReports::seed_demo_reports(); if(($seed['inserted']??0)<=0) throw new RuntimeException('no seed'); foreach($wpdb->rows as $r){ if(($r['source']??'')!=='demo_seed') throw new RuntimeException('wrong source'); } $wpdb->rows[]=['provider_id'=>'cloudflare','source'=>'public_form','created_at'=>gmdate('Y-m-d H:i:s')]; $deleted=UserReports::clear_demo_reports(); if($deleted<=0) throw new RuntimeException('not deleted'); if(count($wpdb->rows)!==1||$wpdb->rows[0]['source']!=='public_form') throw new RuntimeException('deleted non-demo'); };
$tests['diagnostics_shape']=function(){ global $wpdb; $wpdb=new FakeWpdbUserReports(); UserReports::seed_demo_reports(); $diag=UserReports::get_admin_diagnostics(60); foreach(['total_reports','provider_counts','signals','recent_reports','window_minutes'] as $k){ if(!array_key_exists($k,$diag)) throw new RuntimeException('missing '.$k); } };
$tests['recent_returns_empty_when_table_missing']=function(){ global $wpdb; $wpdb=new FakeWpdbUserReports(); $wpdb->table_exists=false; if(UserReports::recent(2,999)!==[]) throw new RuntimeException('expected empty'); };
$tests['external_recent_returns_empty_when_table_missing']=function(){ global $wpdb; $wpdb=new FakeWpdbUserReports(); $wpdb->external_table_exists=false; if(ExternalSignals::recent(2,999)!==[]) throw new RuntimeException('expected empty ext'); };

$f=false; foreach($tests as $n=>$cb){ try{$cb(); echo "ok - $n\n";}catch(Throwable $e){$f=true; echo "not ok - $n: {$e->getMessage()}\n";}}
if($f) exit(1); echo "All tests passed\n";

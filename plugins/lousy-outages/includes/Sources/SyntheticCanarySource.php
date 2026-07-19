<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;

class SyntheticCanarySource implements SignalSourceInterface {
    public function id(): string { return 'synthetic_canary'; }
    public function label(): string { return 'Synthetic Canary'; }
    public function is_configured(): bool { return (bool) apply_filters('lo_synthetic_canary_enabled', true); }
    public function collect(array $options = []): array {
        if (!$this->is_configured()) return [];
        $targets = apply_filters('lo_synthetic_canary_targets', [[ 'id'=>'site_home','label'=>'Site Home','url'=>home_url('/'),'provider_id'=>'site','category'=>'site','region'=>'local','method'=>'HEAD','expected_statuses'=>[200,204,301,302],'timeout_seconds'=>5 ]]);
        $max=max(1,min(10,(int)apply_filters('lo_synthetic_canary_max_targets',5)));
        $out=[];
        foreach(array_slice((array)$targets,0,$max) as $t){
            $method=strtoupper((string)($t['method']??'HEAD')); $timeout=max(1,min(5,(int)($t['timeout_seconds']??5))); $url=esc_url_raw((string)($t['url']??'')); if(!$url) continue;
            $start=microtime(true);
            $res = $method==='GET' ? wp_remote_get($url,['timeout'=>$timeout,'redirection'=>2]) : wp_remote_head($url,['timeout'=>$timeout,'redirection'=>2]);
            $elapsed=(microtime(true)-$start)*1000;
            $expected=array_map('intval',(array)($t['expected_statuses']??[200,204,301,302]));
            if (is_wp_error($res)) { $out[]=$this->failure($t,'http_check_failed','major','Synthetic check failed for '.($t['label']??$url),'A lightweight public canary check failed. This is an unconfirmed signal, not proof of a provider outage.'); continue; }
            $code=(int)wp_remote_retrieve_response_code($res);
            if (!in_array($code,$expected,true)) { $out[]=$this->failure($t,'http_check_failed','degraded','Synthetic check failed for '.($t['label']??$url),'Synthetic check failures observed. Official incident not yet confirmed.'); continue; }
            $th=(float)apply_filters('lo_synthetic_canary_latency_ms',2500);
            if ($elapsed > $th) { $out[]=$this->failure($t,'http_latency_high','degraded','Synthetic latency high for '.($t['label']??$url),'A lightweight public canary check failed. This is an unconfirmed signal.'); }
        }
        return $out;
    }
    private function failure(array $t,string $type,string $severity,string $title,string $msg): array { return ['source'=>'synthetic_canary','source_type'=>'synthetic_probe','adapter_id'=>'synthetic_probe_normalizer','evidence_quality'=>'moderate','official_confirmed'=>false,'unconfirmed_note'=>'Synthetic probe failure requires corroboration.','provider_id'=>sanitize_key((string)($t['provider_id']??$t['id']??'')),'provider_name'=>sanitize_text_field((string)($t['label']??'Synthetic Canary')),'category'=>sanitize_key((string)($t['category']??'synthetic')),'region'=>sanitize_text_field((string)($t['region']??'global')),'signal_type'=>$type,'severity'=>$severity,'confidence'=>40,'title'=>$title,'message'=>$msg,'url'=>esc_url_raw((string)($t['url']??'')),'observed_at'=>gmdate('Y-m-d H:i:s')]; }
}

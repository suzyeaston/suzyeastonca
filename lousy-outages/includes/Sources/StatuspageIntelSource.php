<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;

class StatuspageIntelSource implements SignalSourceInterface {
    public function id(): string { return 'statuspage_intel'; }
    public function label(): string { return 'Statuspage Intel'; }
    public function is_configured(): bool { return SourcePack::enabled() && count(SourcePack::statuspage_base_urls()) > 0; }
    private function host(string $url): string { return (string) wp_parse_url($url, PHP_URL_HOST); }
    private function issue(string $text): bool { return (bool) preg_match('/\b(down|outage|incident|degraded|error|failure|unavailable|latency|disruption|maintenance)\b/i', $text); }

    public function collect(array $options = []): array {
        $endpoints=['/api/v2/status.json','/api/v2/summary.json','/api/v2/components.json','/api/v2/incidents/unresolved.json','/api/v2/incidents.json','/api/v2/scheduled-maintenances/active.json'];
        $diag=['configured'=>$this->is_configured(),'attempted'=>false,'providers_checked'=>0,'endpoints_attempted'=>0,'incidents_found'=>0,'components_degraded'=>0,'rows_stored'=>0,'rows_suppressed'=>0,'skipped_reasons'=>[],'cooldown_active'=>false];
        $out=[];
        foreach (SourcePack::statuspage_base_urls() as $base) {
            $base=rtrim((string)$base,'/'); if(!$base) continue;
            $diag['providers_checked']++;
            $host=$this->host($base);
            $bucket=[];
            foreach($endpoints as $ep){
                $can=SourceBudgetManager::can_attempt($this->id(),$host,30);
                if(empty($can['ok'])){ $diag['cooldown_active']=true; $diag['skipped_reasons'][]='budget:'.$host; break; }
                $diag['attempted']=true; $diag['endpoints_attempted']++;
                $url=$base.$ep;
                $cache='lo_statuspage_'.md5($url);
                $json=get_transient($cache);
                if(!is_array($json)){
                    $r=wp_remote_get($url,['timeout'=>6]);
                    SourceBudgetManager::mark_attempt($this->id(),$host,8);
                    if(is_wp_error($r)){ SourceBudgetManager::mark_result($this->id(),false,0); $diag['skipped_reasons'][]='http_error:'.$host; continue; }
                    $code=(int)wp_remote_retrieve_response_code($r);
                    SourceBudgetManager::mark_result($this->id(), $code>=200&&$code<300, $code);
                    if($code<200||$code>=300){ if($code===429){$diag['cooldown_active']=true;} continue; }
                    $json=json_decode((string)wp_remote_retrieve_body($r),true);
                    if(!is_array($json)) $json=[];
                    set_transient($cache,$json,5*MINUTE_IN_SECONDS);
                }
                $bucket[$ep]=$json;
            }
            $incidents=array_merge((array)($bucket['/api/v2/incidents/unresolved.json']['incidents']??[]),(array)($bucket['/api/v2/incidents.json']['incidents']??[]),(array)($bucket['/api/v2/summary.json']['incidents']??[]));
            $hasIssue=false; $snips=[]; $urls=[];
            foreach($incidents as $inc){
                $name=sanitize_text_field((string)($inc['name']??'')); $status=sanitize_text_field((string)($inc['status']??''));
                if($name==='' && $status==='') continue;
                if(in_array($status,['investigating','identified','monitoring','in_progress'],true) || $this->issue($name.' '.$status)){
                    $hasIssue=true; $diag['incidents_found']++;
                    $snips[]=$name.($status?" ({$status})":'');
                    $urls[]=esc_url_raw((string)($inc['shortlink']??$base.'/api/v2/incidents.json'));
                }
            }
            foreach((array)($bucket['/api/v2/components.json']['components']??$bucket['/api/v2/summary.json']['components']??[]) as $component){
                $st=(string)($component['status']??'');
                if($st!=='' && $st!=='operational'){ $hasIssue=true; $diag['components_degraded']++; $snips[]=sanitize_text_field((string)($component['name']??'component')).' '.$st; }
            }
            foreach((array)($bucket['/api/v2/scheduled-maintenances/active.json']['scheduled_maintenances']??[]) as $m){
                $hasIssue=true; $snips[]='Maintenance: '.sanitize_text_field((string)($m['name']??'active maintenance'));
            }
            if(!$hasIssue){ $diag['rows_suppressed']++; continue; }
            $diag['rows_stored']++;
            $out[]=['source'=>'statuspage','source_type'=>'official_status','adapter_id'=>'statuspage_public','source_id'=>md5($base.implode('|',$snips)),'provider_id'=>sanitize_key($host),'provider_name'=>sanitize_text_field($host),'category'=>'service','region'=>'global','signal_type'=>'status_incident','severity'=>'major','confidence'=>95,'title'=>'Statuspage issue detected: '.$host,'message'=>implode(' | ',array_slice($snips,0,3)),'url'=>$base,'source_urls'=>array_values(array_unique(array_merge([$base],$urls))),'domains'=>[$host],'snippets'=>array_slice($snips,0,6),'confidence_reason'=>'Official provider statuspage indicates incident/degradation/maintenance.','evidence_quality'=>'official','official_confirmed'=>true,'observed_at'=>gmdate('Y-m-d H:i:s')];
        }
        update_option('lo_diag_'.$this->id(),$diag,false);
        return $out;
    }
}

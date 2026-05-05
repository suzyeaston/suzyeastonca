<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;

class StatuspageIntelSource implements SignalSourceInterface {
    public function id(): string { return 'statuspage_intel'; }
    public function label(): string { return 'Statuspage Intel'; }
    public function is_configured(): bool { return SourcePack::enabled() && count(SourcePack::statuspage_base_urls()) > 0; }
    private function has_issue_language(string $text): bool { return (bool) preg_match('/\b(down|outage|incident|degraded|error|failure|unavailable|latency|disruption)\b/i', $text); }
    private function host(string $url): string { return (string) wp_parse_url($url, PHP_URL_HOST); }

    public function collect(array $options = []): array {
        $out=[];
        foreach(SourcePack::statuspage_base_urls() as $base){
            $base=rtrim((string)$base,'/'); if(!$base) continue;
            $can=SourceBudgetManager::can_attempt($this->id(), $this->host($base), 30);
            if(empty($can['ok'])) continue;
            $incidentUrl=$base.'/api/v2/incidents/unresolved.json';
            $r=wp_remote_get($incidentUrl,['timeout'=>6]);
            SourceBudgetManager::mark_attempt($this->id(), $this->host($base), 10);
            if(is_wp_error($r)){ SourceBudgetManager::mark_result($this->id(), false, 0); continue; }
            $code=(int) wp_remote_retrieve_response_code($r);
            SourceBudgetManager::mark_result($this->id(), $code >= 200 && $code < 300, $code);
            if($code < 200 || $code >= 300) continue;
            $json=json_decode((string)wp_remote_retrieve_body($r),true);
            foreach((array)($json['incidents']??[]) as $inc){
                $name=sanitize_text_field((string)($inc['name']??'Statuspage incident'));
                $body=sanitize_text_field((string)($inc['status']??''));
                if(!$this->has_issue_language($name.' '.$body)) continue;
                $url=esc_url_raw((string)($inc['shortlink']??$base));
                $out[]=['source'=>'statuspage','source_type'=>'official_status','adapter_id'=>'statuspage_public','source_id'=>sanitize_text_field((string)($inc['id']??md5($url.$name))),'provider_id'=>sanitize_key($this->host($base)),'provider_name'=>sanitize_text_field($this->host($base)),'category'=>'service','region'=>'global','signal_type'=>'status_incident','severity'=>'major','confidence'=>95,'title'=>$name,'message'=>$body,'url'=>$url,'source_urls'=>[$url,$incidentUrl],'domains'=>[$this->host($base)],'snippets'=>[$name,$body],'confidence_reason'=>'Official provider statuspage incident','evidence_quality'=>'official','official_confirmed'=>true,'observed_at'=>gmdate('Y-m-d H:i:s')];
            }
        }
        return $out;
    }
}

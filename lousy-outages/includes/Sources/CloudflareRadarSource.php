<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;

class CloudflareRadarSource implements SignalSourceInterface {
    private function token(): string { if (defined('LOUSY_OUTAGES_CLOUDFLARE_RADAR_TOKEN')) return (string) LOUSY_OUTAGES_CLOUDFLARE_RADAR_TOKEN; $env=getenv('LOUSY_OUTAGES_CLOUDFLARE_RADAR_TOKEN'); if($env) return (string)$env; return (string)get_option('lousy_outages_cloudflare_radar_token',''); }
    public function id(): string { return 'cloudflare_radar'; }
    public function label(): string { return 'Cloudflare Radar'; }
    public function is_configured(): bool { return '' !== trim($this->token()); }
    public function collect(array $options = []): array {
        if (!$this->is_configured()) return [];
        $endpoints = [
            'internet_outage' => 'https://api.cloudflare.com/client/v4/radar/outages',
            'traffic_anomaly' => 'https://api.cloudflare.com/client/v4/radar/anomalies'
        ];
        $out=[];
        foreach($endpoints as $type=>$url){
            $res = wp_remote_get($url,['timeout'=>8,'headers'=>['Authorization'=>'Bearer '.$this->token(),'User-Agent'=>'LousyOutages/'.(defined('LOUSY_OUTAGES_VERSION')?LOUSY_OUTAGES_VERSION:'0.1.0')]]);
            if (is_wp_error($res)) continue;
            if ((int)wp_remote_retrieve_response_code($res)!==200) continue;
            $body=json_decode((string)wp_remote_retrieve_body($res),true);
            $items=(array)($body['result'] ?? []);
            foreach(array_slice($items,0,10) as $item){
                $out[]=[ 'source'=>'cloudflare_radar','provider_id'=>sanitize_key((string)($item['asn'] ?? '')),'provider_name'=>sanitize_text_field((string)($item['name'] ?? $item['asn_name'] ?? 'Internet Health')),'category'=>'internet_health','region'=>sanitize_text_field((string)($item['location'] ?? $item['country'] ?? 'global')),'signal_type'=>$type,'severity'=>'degraded','confidence'=>70,'title'=>sanitize_text_field((string)($item['title'] ?? 'Internet-health anomaly detected')),'message'=>sanitize_text_field((string)($item['description'] ?? 'Unconfirmed external signal from internet-health telemetry.')),'url'=>esc_url_raw((string)($item['url'] ?? 'https://radar.cloudflare.com')),'observed_at'=>sanitize_text_field((string)($item['time'] ?? gmdate('Y-m-d H:i:s'))) ];
            }
        }
        return $out;
    }
}

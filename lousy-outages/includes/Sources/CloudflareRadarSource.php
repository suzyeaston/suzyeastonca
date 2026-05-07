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
        $configured = $this->is_configured();
        $diag = [
            'configured' => $configured,
            'attempted' => false,
            'endpoint_attempted' => '',
            'response_code' => 0,
            'rows_seen' => 0,
            'rows_stored' => 0,
            'errors' => [],
            'lane' => 'external_telemetry',
            'ran_at' => gmdate('c'),
        ];
        if (!$configured) { update_option('lo_diag_'.$this->id(), $diag, false); return []; }

        // Current Cloudflare Radar API reference: GET /radar/annotations/outages retrieves latest Internet outages and anomalies.
        $url = (string) apply_filters('lo_cloudflare_radar_outages_endpoint', 'https://api.cloudflare.com/client/v4/radar/annotations/outages');
        $diag['attempted'] = true;
        $diag['endpoint_attempted'] = $url;
        $res = wp_remote_get($url,['timeout'=>8,'headers'=>['Authorization'=>'Bearer '.$this->token(),'User-Agent'=>'LousyOutages/'.(defined('LOUSY_OUTAGES_VERSION')?LOUSY_OUTAGES_VERSION:'0.1.0')]]);
        if (is_wp_error($res)) { $diag['errors'][] = 'request_error'; update_option('lo_diag_'.$this->id(), $diag, false); return []; }
        $code = (int)wp_remote_retrieve_response_code($res);
        $diag['response_code'] = $code;
        if ($code !== 200) { $diag['errors'][] = 'api_http_error'; update_option('lo_diag_'.$this->id(), $diag, false); return []; }

        $body=json_decode((string)wp_remote_retrieve_body($res),true);
        $annotations=(array)($body['result']['annotations'] ?? $body['result'] ?? []);
        $diag['rows_seen'] = count($annotations);
        $out=[];
        foreach(array_slice($annotations,0,10) as $item){
            if (!is_array($item)) { continue; }
            $asn = (string)($item['asn'] ?? $item['asNumber'] ?? '');
            $name = (string)($item['name'] ?? $item['asnName'] ?? $item['as_name'] ?? $item['scopeName'] ?? 'Internet Health');
            $kind = strtoupper((string)($item['eventType'] ?? $item['type'] ?? $item['annotationType'] ?? 'INTERNET_HEALTH'));
            $signalType = str_contains($kind, 'ANOMAL') ? 'traffic_anomaly' : 'internet_outage';
            $out[]=[
                'source'=>'cloudflare_radar',
                'source_type'=>'external_telemetry',
                'signal_lane'=>'external_telemetry',
                'provider_id'=>sanitize_key($asn !== '' ? 'asn_'.$asn : $name),
                'provider_name'=>sanitize_text_field($name),
                'category'=>'internet_health',
                'region'=>sanitize_text_field((string)($item['locationName'] ?? $item['location'] ?? $item['country'] ?? 'global')),
                'signal_type'=>$signalType,
                'severity'=>'degraded',
                'confidence'=>70,
                'title'=>sanitize_text_field((string)($item['title'] ?? 'Internet-health telemetry anomaly detected')),
                'message'=>sanitize_text_field((string)($item['description'] ?? 'External internet-health telemetry indicates a possible network disruption.')),
                'url'=>esc_url_raw((string)($item['url'] ?? 'https://radar.cloudflare.com/outage-center')),
                'observed_at'=>sanitize_text_field((string)($item['startTime'] ?? $item['startedAt'] ?? $item['time'] ?? gmdate('Y-m-d H:i:s'))),
            ];
        }
        $diag['rows_stored'] = count($out);
        update_option('lo_diag_'.$this->id(), $diag, false);
        return $out;
    }
}

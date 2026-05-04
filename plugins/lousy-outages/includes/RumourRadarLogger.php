<?php
declare(strict_types=1);
namespace SuzyEaston\LousyOutages;
class RumourRadarLogger {
    public static function enabled(): bool { $enabled = (bool) apply_filters('lousy_outages_rumour_radar_debug_logging', false); return $enabled; }
    public static function log(string $event, array $context=[]): void { if(!self::enabled()) return; $json = wp_json_encode(self::sanitize($context)); error_log('[lousy_outages][rumour_radar] '.$event.' '.($json?:'{}')); }
    private static function sanitize(array $ctx): array { foreach($ctx as $k=>$v){ if(is_string($v)){$ctx[$k]=mb_substr(sanitize_text_field($v),0,300);} elseif(is_array($v)){$ctx[$k]=array_slice(array_map(static fn($i)=>is_scalar($i)?mb_substr(sanitize_text_field((string)$i),0,140):'', $v),0,5);} } return $ctx; }
}

<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class ExternalSignals {
    public static function table_name(): string { global $wpdb; return $wpdb->prefix . 'lo_external_signals'; }
    private static function table_exists(): bool {
        global $wpdb;
        $table = self::table_name();
        $like = $wpdb->esc_like($table);
        $existing = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        return (string) $existing === $table;
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT unsigned NOT NULL AUTO_INCREMENT,
            source VARCHAR(80) NOT NULL,
            provider_id VARCHAR(80) NOT NULL DEFAULT '',
            provider_name VARCHAR(160) NOT NULL DEFAULT '',
            category VARCHAR(80) NOT NULL DEFAULT '',
            region VARCHAR(80) NOT NULL DEFAULT '',
            signal_type VARCHAR(80) NOT NULL DEFAULT '',
            severity VARCHAR(40) NOT NULL DEFAULT 'unknown',
            confidence TINYINT unsigned NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL DEFAULT '',
            message TEXT NULL,
            url TEXT NULL,
            observed_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            raw_hash VARCHAR(128) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY source (source), KEY provider_id (provider_id), KEY category (category), KEY region (region), KEY observed_at (observed_at), KEY expires_at (expires_at)
        ) {$charset};";
        dbDelta($sql);
    }

    public static function normalize_signal(array $signal): array {
        $observed = sanitize_text_field((string)($signal['observed_at'] ?? gmdate('Y-m-d H:i:s')));
        $expires = isset($signal['expires_at']) ? sanitize_text_field((string)$signal['expires_at']) : null;
        return [
            'source' => substr(sanitize_key((string)($signal['source'] ?? 'unknown_external')), 0, 80),
            'provider_id' => substr(sanitize_key((string)($signal['provider_id'] ?? '')), 0, 80),
            'provider_name' => substr(sanitize_text_field((string)($signal['provider_name'] ?? '')), 0, 160),
            'category' => substr(sanitize_key((string)($signal['category'] ?? 'general')), 0, 80),
            'region' => substr(sanitize_text_field((string)($signal['region'] ?? 'global')), 0, 80),
            'signal_type' => substr(sanitize_key((string)($signal['signal_type'] ?? 'unknown')), 0, 80),
            'severity' => substr(sanitize_key((string)($signal['severity'] ?? 'unknown')), 0, 40),
            'confidence' => max(0, min(100, (int)($signal['confidence'] ?? 0))),
            'title' => substr(sanitize_text_field((string)($signal['title'] ?? 'External signal observed')), 0, 255),
            'message' => substr(sanitize_textarea_field((string)($signal['message'] ?? '')), 0, 1000),
            'url' => substr(esc_url_raw((string)($signal['url'] ?? '')), 0, 1000),
            'observed_at' => $observed,
            'expires_at' => $expires ?: null,
            'raw_hash' => isset($signal['raw_hash']) ? substr(sanitize_text_field((string)$signal['raw_hash']),0,128) : hash('sha256', wp_json_encode($signal)),
        ];
    }

    public static function record_signal(array $signal): array {
        global $wpdb; $s=self::normalize_signal($signal);
        if (!empty($s['raw_hash'])) {
            $exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table_name().' WHERE raw_hash=%s LIMIT 1', $s['raw_hash']));
            if ($exists) return ['inserted'=>false,'id'=>(int)$exists,'signal'=>$s];
        }
        $wpdb->insert(self::table_name(), array_merge($s,['created_at'=>gmdate('Y-m-d H:i:s')]));
        return ['inserted'=>true,'id'=>(int)$wpdb->insert_id,'signal'=>$s];
    }
    public static function record_many(array $signals): array { $out=['inserted'=>0,'skipped'=>0,'rows'=>[]]; foreach($signals as $sig){$r=self::record_signal((array)$sig);$out['rows'][]=$r; $out[$r['inserted']?'inserted':'skipped']++;} return $out; }
    public static function get_recent_signals(array $args=[]): array { global $wpdb; if (!self::table_exists()) { return []; } $limit=max(1,min(200,(int)($args['limit']??50))); return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table_name().' WHERE observed_at >= %s ORDER BY observed_at DESC LIMIT %d', gmdate('Y-m-d H:i:s', time()-((int)($args['windowMinutes']??60))*60), $limit), ARRAY_A) ?: []; }
    public static function recent(int $windowMinutes = 60, int $limit = 100): array {
        if (!method_exists(__CLASS__, 'get_recent_signals') || !method_exists(__CLASS__, 'table_exists') || !self::table_exists()) {
            return [];
        }
        $windowMinutes = max(5, min(1440, $windowMinutes));
        $limit = max(1, min(500, $limit));
        return self::get_recent_signals(['windowMinutes' => $windowMinutes, 'window_minutes' => $windowMinutes, 'limit' => $limit]);
    }
    public static function get_recent_counts(int $windowMinutes=60): array { global $wpdb; $rows=$wpdb->get_results($wpdb->prepare('SELECT source, COUNT(*) as signal_count FROM '.self::table_name().' WHERE observed_at >= %s GROUP BY source', gmdate('Y-m-d H:i:s', time()-$windowMinutes*60)), ARRAY_A) ?: []; return $rows; }
    public static function clear_expired(): int { global $wpdb; $wpdb->query($wpdb->prepare('DELETE FROM '.self::table_name().' WHERE expires_at IS NOT NULL AND expires_at < %s', gmdate('Y-m-d H:i:s'))); return (int)$wpdb->rows_affected; }
    public static function clear_demo_signals(): int { global $wpdb; $wpdb->query($wpdb->prepare('DELETE FROM '.self::table_name().' WHERE source = %s', 'demo_external')); return (int)$wpdb->rows_affected; }
    public static function seed_demo_signals(array $options=[]): array {
        $now=gmdate('Y-m-d H:i:s');
        $demo=[[ 'source'=>'demo_external','provider_id'=>'cloudflare','provider_name'=>'Cloudflare','category'=>'internet_health','region'=>'us','signal_type'=>'traffic_anomaly','severity'=>'degraded','confidence'=>68,'title'=>'Internet-health anomaly detected','message'=>'Possible emerging issue from demo telemetry. Official incident not yet confirmed.','observed_at'=>$now ],[ 'source'=>'demo_external','provider_id'=>'local_isp','provider_name'=>'Local ISP','category'=>'isp','region'=>'vancouver','signal_type'=>'internet_outage','severity'=>'major','confidence'=>74,'title'=>'Unconfirmed external signal','message'=>'We are watching this provider due to demo external signal.','observed_at'=>$now ]];
        return self::record_many($demo);
    }
}

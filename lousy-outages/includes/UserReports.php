<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class UserReports {
    public const TABLE = 'lo_user_reports';
    public const ALLOWED_SYMPTOMS = ['login','checkout','payments','api','dashboard','dns','email','slow','full_outage','other'];
    public const ALLOWED_SEVERITY = ['minor','degraded','major','unknown'];

    public static function table_name(): string { global $wpdb; return $wpdb->prefix . self::TABLE; }
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
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            provider_id VARCHAR(80) NOT NULL,
            symptom VARCHAR(80) NOT NULL,
            severity VARCHAR(40) NOT NULL DEFAULT 'unknown',
            region VARCHAR(80) NOT NULL DEFAULT '',
            source VARCHAR(40) NOT NULL DEFAULT 'public_form',
            details TEXT NULL,
            email_hash VARCHAR(128) NULL,
            ip_hash VARCHAR(128) NULL,
            user_agent_hash VARCHAR(128) NULL,
            created_at DATETIME NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'new',
            PRIMARY KEY (id),
            KEY provider_id (provider_id),
            KEY created_at (created_at),
            KEY provider_created_at (provider_id, created_at),
            KEY ip_created_at (ip_hash, created_at)
        ) {$charset};";
        dbDelta($sql);
    }

    public static function hash_value(string $raw): string { return hash('sha256', wp_salt('auth') . '|' . trim($raw)); }

    public static function normalize_input(array $input): array {
        $provider = sanitize_key((string)($input['provider_id'] ?? ''));
        $providers = Providers::list();
        if (!isset($providers[$provider])) return ['ok'=>false,'error'=>'invalid_provider'];
        $symptom = sanitize_key((string)($input['symptom'] ?? 'other'));
        if (!in_array($symptom, self::ALLOWED_SYMPTOMS, true)) $symptom = 'other';
        $severity = sanitize_key((string)($input['severity'] ?? 'unknown'));
        if (!in_array($severity, self::ALLOWED_SEVERITY, true)) $severity = 'unknown';
        $region = substr(sanitize_text_field((string)($input['region'] ?? '')), 0, 80);
        $details = substr(sanitize_text_field((string)($input['details'] ?? '')), 0, 500);
        $email = sanitize_email((string)($input['email'] ?? ''));
        $email_hash = ($email && is_email($email)) ? self::hash_value(strtolower($email)) : null;
        $ip = (string)($input['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
        $ua = (string)($input['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return ['ok'=>true,'provider_id'=>$provider,'symptom'=>$symptom,'severity'=>$severity,'region'=>$region,'details'=>$details,'email_hash'=>$email_hash,'ip_hash'=>self::hash_value($ip),'user_agent_hash'=>self::hash_value($ua),'source'=>'public_form','status'=>'new'];
    }

    public static function is_rate_limited(string $ipHash, string $provider_id): bool {
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS);
        $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . self::table_name() . " WHERE ip_hash = %s AND provider_id = %s AND created_at >= %s", $ipHash, $provider_id, $cutoff));
        return $count > 0;
    }

    public static function record_report(array $input): array {
        global $wpdb;
        $normalized = self::normalize_input($input);
        if (empty($normalized['ok'])) return ['success'=>false,'error'=>'invalid_provider'];
        if (self::is_rate_limited((string)$normalized['ip_hash'], (string)$normalized['provider_id'])) return ['success'=>false,'rate_limited'=>true,'message'=>'Thanks, we already logged a recent report for this provider. We’re watching it.'];
        $now = gmdate('Y-m-d H:i:s');
        $data = $normalized; unset($data['ok']); $data['created_at'] = $now;
        $wpdb->insert(self::table_name(), $data);
        return ['success'=>true,'provider_id'=>$data['provider_id'],'created_at'=>$now];
    }

    public static function get_recent_reports(array $args = []): array {
        global $wpdb;
        if (!self::table_exists()) { return []; }
        $window = max(1, (int)($args['windowMinutes'] ?? 60));
        $limit = max(1, (int)($args['limit'] ?? 50));
        $provider = sanitize_key((string)($args['provider_id'] ?? ''));
        $source = sanitize_key((string)($args['source'] ?? ''));
        $status = sanitize_key((string)($args['status'] ?? ''));
        $cutoff = gmdate('Y-m-d H:i:s', time() - $window * MINUTE_IN_SECONDS);
        $sql = 'SELECT * FROM ' . self::table_name() . ' WHERE created_at >= %s';
        $params = [$cutoff];
        if ($provider) { $sql .= ' AND provider_id = %s'; $params[] = $provider; }
        if ($source) { $sql .= ' AND source = %s'; $params[] = $source; }
        if ($status) { $sql .= ' AND status = %s'; $params[] = $status; }
        $sql .= ' ORDER BY created_at DESC LIMIT %d';
        $params[] = $limit;
        return (array)$wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    }
    public static function recent(int $windowMinutes = 60, int $limit = 100): array {
        if (!method_exists(__CLASS__, 'get_recent_reports') || !method_exists(__CLASS__, 'table_exists') || !self::table_exists()) {
            return [];
        }
        $windowMinutes = max(5, min(1440, $windowMinutes));
        $limit = max(1, min(500, $limit));
        return self::get_recent_reports(['windowMinutes' => $windowMinutes, 'window_minutes' => $windowMinutes, 'limit' => $limit]);
    }

    public static function get_recent_report_count(int $windowMinutes = 60): int {
        global $wpdb;
        if (!self::table_exists()) { return 0; }
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1, $windowMinutes) * MINUTE_IN_SECONDS);
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE created_at >= %s', $cutoff));
    }

    public static function count_recent_reports(string $provider_id, int $windowMinutes = 60): int {
        global $wpdb;
        if (!self::table_exists()) { return 0; }
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1,$windowMinutes) * MINUTE_IN_SECONDS);
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE provider_id = %s AND created_at >= %s', sanitize_key($provider_id), $cutoff));
    }

    public static function get_recent_provider_counts(int $windowMinutes = 60): array {
        global $wpdb;
        if (!self::table_exists()) { return []; }
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1,$windowMinutes) * MINUTE_IN_SECONDS);
        return (array)$wpdb->get_results($wpdb->prepare('SELECT provider_id, COUNT(*) AS report_count FROM ' . self::table_name() . ' WHERE created_at >= %s GROUP BY provider_id ORDER BY report_count DESC', $cutoff), ARRAY_A);
    }

    public static function get_admin_diagnostics(int $windowMinutes = 60): array {
        return [
            'window_minutes' => max(1, $windowMinutes),
            'total_reports' => self::get_recent_report_count($windowMinutes),
            'provider_counts' => self::get_recent_provider_counts($windowMinutes),
            'signals' => SignalEngine::summarize_recent_signals($windowMinutes),
            'recent_reports' => self::get_recent_reports(['windowMinutes' => $windowMinutes, 'limit' => 25]),
        ];
    }

    public static function seed_demo_reports(array $options = []): array {
        global $wpdb;
        $providers = Providers::enabled();
        if (empty($providers)) $providers = Providers::list();
        $ids = array_values(array_keys($providers));
        if (count($ids) < 3) return ['inserted' => 0, 'provider_ids' => []];
        $plan = [[$ids[0], 5, 'api', 'major', 'us-east', 'Demo report: API timeout'],[$ids[1], 3, 'login', 'degraded', 'us-west', 'Demo report: login errors'],[$ids[2], 2, 'checkout', 'minor', 'ca-central', 'Demo report: checkout failures']];
        $inserted = 0;
        foreach ($plan as $idx => $rowPlan) {
            [$providerId, $count, $symptom, $severity, $region, $details] = $rowPlan;
            for ($i = 0; $i < $count; $i++) {
                $now = gmdate('Y-m-d H:i:s', time() - (($idx * 2 + $i) * 60));
                $seed = $providerId . '|' . $idx . '|' . $i;
                $wpdb->insert(self::table_name(), ['provider_id' => sanitize_key((string)$providerId), 'symptom' => $symptom, 'severity' => $severity, 'region' => $region, 'source' => 'demo_seed', 'details' => $details, 'email_hash' => self::hash_value('demo-email-' . $seed), 'ip_hash' => self::hash_value('demo-ip-' . $seed), 'user_agent_hash' => self::hash_value('demo-ua-' . $seed), 'created_at' => $now, 'status' => 'new']);
                $inserted++;
            }
        }
        return ['inserted' => $inserted, 'provider_ids' => [$ids[0], $ids[1], $ids[2]]];
    }

    public static function clear_demo_reports(): int {
        global $wpdb;
        $sql = $wpdb->prepare('DELETE FROM ' . self::table_name() . ' WHERE source = %s', 'demo_seed');
        return (int)$wpdb->query($sql);
    }
}

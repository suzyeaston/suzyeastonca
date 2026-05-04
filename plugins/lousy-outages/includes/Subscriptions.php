<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

class Subscriptions {
    public const TABLE = 'lousy_outages_subs';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBSCRIBED = 'subscribed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    /** @var bool */
    private static $schema_checked = false;

    private static function ensure_schema(): void {
        if (self::$schema_checked) {
            return;
        }

        global $wpdb;

        $table = self::table_name();
        $needs_upgrade = false;

        $like = $wpdb->esc_like($table);
        $existing = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        if (!$existing) {
            $needs_upgrade = true;
        } else {
            $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`");
            if (!is_array($columns)) {
                $columns = [];
            }

            $columns = array_map('strtolower', $columns);
            foreach (['email', 'status', 'token', 'created_at', 'updated_at', 'ip_hash', 'consent_source', 'providers', 'realtime_alerts', 'daily_digest', 'newsletter', 'consent_version', 'confirmed_at'] as $required) {
                if (!in_array($required, $columns, true)) {
                    $needs_upgrade = true;
                    break;
                }
            }
        }

        if ($needs_upgrade) {
            self::create_table();
        }

        self::$schema_checked = true;
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }
    public static function stats(): array {
        $zero = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'realtime' => 0, 'digest' => 0, 'newsletter' => 0];
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) {
            return $zero;
        }

        $table = self::table_name();
        $like = method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($table) : $table;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        if ((string) $exists !== $table) {
            return $zero;
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $confirmed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status IN (%s,%s)", self::STATUS_SUBSCRIBED, 'confirmed'));
        $pending = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", self::STATUS_PENDING));
        $realtime = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE COALESCE(realtime_alerts, 0) <> 0");
        $digest = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE COALESCE(daily_digest, 0) <> 0");
        $newsletter = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE COALESCE(newsletter, 0) <> 0");

        $provider_all = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status IN ('subscribed','confirmed') AND (providers = '' OR providers IS NULL)");
        $provider_specific = max(0, $confirmed - $provider_all);
        return ['total' => $total, 'confirmed' => $confirmed, 'pending' => $pending, 'realtime' => $realtime, 'digest' => $digest, 'newsletter' => $newsletter, 'provider_all' => $provider_all, 'provider_specific' => $provider_specific];
    }

    public static function create_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $table   = self::table_name();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(190) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            token CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            ip_hash CHAR(64) NOT NULL,
            consent_source VARCHAR(50) NOT NULL DEFAULT '',
            providers LONGTEXT NULL,
            realtime_alerts TINYINT(1) NOT NULL DEFAULT 1,
            daily_digest TINYINT(1) NOT NULL DEFAULT 0,
            newsletter TINYINT(1) NOT NULL DEFAULT 0,
            consent_version VARCHAR(30) NOT NULL DEFAULT '2026-05',
            confirmed_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            UNIQUE KEY token (token)
        ) {$charset};";

        dbDelta($sql);
    }

    public static function schedule_purge(): void {
        if (!wp_next_scheduled('lousy_outages_purge_pending')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'lousy_outages_purge_pending');
        }
    }

    public static function clear_schedule(): void {
        wp_clear_scheduled_hook('lousy_outages_purge_pending');
    }

    public static function purge_stale_pending(): int {
        global $wpdb;
        $table = self::table_name();
        $cutoff = gmdate('Y-m-d H:i:s', time() - 14 * DAY_IN_SECONDS);
        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE status = %s AND created_at < %s", self::STATUS_PENDING, $cutoff));
    }

    public static function save_pending(string $email, string $token, string $ip_hash, string $source = 'form'): void {
        self::save_pending_with_preferences($email, $token, $ip_hash, $source, []);
    }


    public static function normalize_provider_ids(array $ids): array {
        $providers = Providers::list();
        $allowed = array_fill_keys(array_keys($providers), true);
        $normalized = [];

        foreach ($ids as $id) {
            $key = sanitize_key((string) $id);
            if ('' === $key || !isset($allowed[$key])) {
                continue;
            }
            $normalized[$key] = $key;
        }

        return array_values($normalized);
    }

    public static function normalize_preferences(array $input): array {
        $providers = [];
        if (isset($input['providers']) && is_array($input['providers'])) {
            $providers = self::normalize_provider_ids($input['providers']);
        }

        $toBool = static function ($value, bool $default = false): bool {
            if (is_bool($value)) {
                return $value;
            }
            if (null === $value) {
                return $default;
            }
            if (is_string($value)) {
                $v = strtolower(trim($value));
                if (in_array($v, ['1', 'true', 'yes', 'on'], true)) {
                    return true;
                }
                if (in_array($v, ['0', 'false', 'no', 'off', ''], true)) {
                    return false;
                }
            }

            return !empty($value);
        };

        return [
            'providers' => $providers,
            'realtime_alerts' => $toBool($input['realtime_alerts'] ?? true, true),
            'daily_digest' => $toBool($input['daily_digest'] ?? false, false),
            'newsletter' => $toBool($input['newsletter'] ?? false, false),
        ];
    }

    public static function save_pending_with_preferences(string $email, string $token, string $ip_hash, string $source, array $preferences): void {
        global $wpdb;
        self::ensure_schema();
        $table = self::table_name();
        $now = gmdate('Y-m-d H:i:s');
        $prefs = self::normalize_preferences($preferences);

        $data = [
            'status' => self::STATUS_PENDING,
            'token' => $token,
            'created_at' => $now,
            'updated_at' => $now,
            'ip_hash' => $ip_hash,
            'consent_source' => $source,
            'providers' => wp_json_encode($prefs['providers']),
            'realtime_alerts' => $prefs['realtime_alerts'] ? 1 : 0,
            'daily_digest' => $prefs['daily_digest'] ? 1 : 0,
            'newsletter' => $prefs['newsletter'] ? 1 : 0,
            'consent_version' => '2026-05',
        ];

        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$table} WHERE email = %s", $email));
        if ($existing) {
            $wpdb->update($table, $data, ['id' => (int) $existing->id]);
            return;
        }

        $data['email'] = $email;
        $wpdb->insert($table, $data);
    }

    public static function get_preferences_for_email(string $email): array {
        global $wpdb;
        self::ensure_schema();

        $defaults = ['providers'=>[], 'realtime_alerts'=>true, 'daily_digest'=>false, 'newsletter'=>false];
        $table = self::table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT providers,realtime_alerts,daily_digest,newsletter FROM {$table} WHERE email = %s", $email), ARRAY_A);
        if (!is_array($row)) {
            return $defaults;
        }

        $providers = [];
        if (isset($row['providers']) && is_string($row['providers']) && '' !== trim($row['providers'])) {
            $decoded = json_decode($row['providers'], true);
            if (is_array($decoded)) {
                $providers = self::normalize_provider_ids($decoded);
            }
        }

        return [
            'providers' => $providers,
            'realtime_alerts' => !isset($row['realtime_alerts']) ? true : (int)$row['realtime_alerts'] === 1,
            'daily_digest' => isset($row['daily_digest']) && (int)$row['daily_digest'] === 1,
            'newsletter' => isset($row['newsletter']) && (int)$row['newsletter'] === 1,
        ];
    }

    public static function subscriber_wants_provider(string $email, string $provider_id): bool {
        $prefs = self::get_preferences_for_email($email);
        if (empty($prefs['providers'])) {
            return true;
        }
        return in_array(sanitize_key($provider_id), $prefs['providers'], true);
    }

    public static function subscriber_wants_realtime(string $email): bool { return (bool) self::get_preferences_for_email($email)['realtime_alerts']; }
    public static function subscriber_wants_digest(string $email): bool { return (bool) self::get_preferences_for_email($email)['daily_digest']; }
    public static function subscriber_wants_newsletter(string $email): bool { return (bool) self::get_preferences_for_email($email)['newsletter']; }
    public static function is_confirmed(string $email): bool {
        global $wpdb;
        self::ensure_schema();
        $table = self::table_name();
        $row = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE email = %s", strtolower(trim($email))));
        return in_array((string) $row, [self::STATUS_SUBSCRIBED, 'confirmed'], true);
    }
    public static function find_by_token(string $token): ?array {
        global $wpdb;

        self::ensure_schema();

        $table = self::table_name();
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE token = %s", $token), ARRAY_A);
        return $row ?: null;
    }

    public static function update_status_by_token(string $token, string $status): bool {
        global $wpdb;

        self::ensure_schema();

        $table = self::table_name();
        $data = [
            'status'     => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        if (self::STATUS_SUBSCRIBED === $status) {
            $data['confirmed_at'] = gmdate('Y-m-d H:i:s');
        }

        $updated = $wpdb->update(
            $table,
            $data,
            ['token' => $token]
        );

        return false !== $updated && $updated > 0;
    }

    public static function update_token_for_email(string $email, string $token): bool {
        global $wpdb;

        self::ensure_schema();

        $table = self::table_name();
        $updated = $wpdb->update(
            $table,
            [
                'token'      => $token,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['email' => $email],
            ['%s', '%s'],
            ['%s']
        );

        return false !== $updated && $updated > 0;
    }

    public static function mark_unsubscribed_by_email(string $email): bool {
        global $wpdb;
        $email = sanitize_email($email);
        if (!$email) {
            return false;
        }

        self::ensure_schema();

        $table = self::table_name();
        $updated = $wpdb->update(
            $table,
            [
                'status'     => self::STATUS_UNSUBSCRIBED,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['email' => $email],
            ['%s', '%s'],
            ['%s']
        );

        return false !== $updated && $updated > 0;
    }
}

add_action('template_redirect', static function (): void {
    if (!isset($_GET['lo_unsub'])) {
        return;
    }

    $raw_email = isset($_GET['email']) ? wp_unslash((string) $_GET['email']) : '';
    if ('' !== $raw_email) {
        $raw_email = rawurldecode($raw_email);
    }
    $raw_token = isset($_GET['token']) ? wp_unslash((string) $_GET['token']) : '';

    $email = sanitize_email($raw_email);
    $token = sanitize_text_field($raw_token);

    if (!$email || !is_email($email) || '' === $token) {
        wp_die('Invalid unsubscribe link.', 'Unsubscribe', ['response' => 400]);
    }

    $saved = IncidentAlerts::get_saved_unsubscribe_token($email);
    if ('' === $saved) {
        wp_die('Subscriber not found.', 'Unsubscribe', ['response' => 404]);
    }

    if (!hash_equals((string) $saved, (string) $token)) {
        wp_die('Invalid or expired unsubscribe link.', 'Unsubscribe', ['response' => 403]);
    }

    IncidentAlerts::remove_subscriber($email);
    Subscriptions::mark_unsubscribed_by_email($email);

    $redirect = add_query_arg('lo_unsub_success', 1, home_url('/lousy-outages/'));
    wp_safe_redirect($redirect);
    exit;
});

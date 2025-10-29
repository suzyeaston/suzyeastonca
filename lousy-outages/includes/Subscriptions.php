<?php
declare(strict_types=1);

namespace LousyOutages;

class Subscriptions {
    public const TABLE = 'lousy_outages_subs';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBSCRIBED = 'subscribed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
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
        global $wpdb;
        $table  = self::table_name();
        $now    = gmdate('Y-m-d H:i:s');

        $existing = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM {$table} WHERE email = %s", $email));

        if ($existing) {
            $wpdb->update(
                $table,
                [
                    'status'         => self::STATUS_PENDING,
                    'token'          => $token,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                    'ip_hash'        => $ip_hash,
                    'consent_source' => $source,
                ],
                ['id' => (int) $existing->id],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'email'          => $email,
                    'status'         => self::STATUS_PENDING,
                    'token'          => $token,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                    'ip_hash'        => $ip_hash,
                    'consent_source' => $source,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        }
    }

    public static function find_by_token(string $token): ?array {
        global $wpdb;
        $table = self::table_name();
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE token = %s", $token), ARRAY_A);
        return $row ?: null;
    }

    public static function update_status_by_token(string $token, string $status): bool {
        global $wpdb;
        $table = self::table_name();
        $updated = $wpdb->update(
            $table,
            [
                'status'     => $status,
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['token' => $token],
            ['%s', '%s'],
            ['%s']
        );

        return false !== $updated && $updated > 0;
    }

    public static function update_token_for_email(string $email, string $token): bool {
        global $wpdb;
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
}

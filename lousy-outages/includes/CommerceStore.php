<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

final class CommerceStore {
    public const SCHEMA_VERSION = '1.0.0';

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'lo_';
        dbDelta("CREATE TABLE {$p}customers (
          user_id bigint(20) unsigned NOT NULL, stripe_customer_id varchar(64) NULL, stripe_subscription_id varchar(64) NULL,
          plan varchar(16) NOT NULL DEFAULT 'free', subscription_status varchar(32) NOT NULL DEFAULT 'none', current_period_end datetime NULL,
          team_id bigint(20) unsigned NULL, updated_at datetime NOT NULL, PRIMARY KEY (user_id), UNIQUE KEY stripe_customer_id (stripe_customer_id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$p}watchlists (
          id bigint(20) unsigned NOT NULL AUTO_INCREMENT, owner_user_id bigint(20) unsigned NOT NULL, team_id bigint(20) unsigned NULL,
          name varchar(190) NOT NULL, providers longtext NOT NULL, filters longtext NULL, digest_preferences longtext NULL, is_shared tinyint(1) NOT NULL DEFAULT 0,
          created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), KEY owner (owner_user_id), KEY team (team_id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$p}alert_destinations (
          id bigint(20) unsigned NOT NULL AUTO_INCREMENT, owner_user_id bigint(20) unsigned NOT NULL, team_id bigint(20) unsigned NULL,
          type varchar(20) NOT NULL, label varchar(190) NOT NULL, endpoint_encrypted longtext NOT NULL, enabled tinyint(1) NOT NULL DEFAULT 1, created_at datetime NOT NULL,
          PRIMARY KEY (id), KEY owner (owner_user_id), KEY team (team_id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$p}api_tokens (
          id bigint(20) unsigned NOT NULL AUTO_INCREMENT, owner_user_id bigint(20) unsigned NOT NULL, team_id bigint(20) unsigned NULL,
          name varchar(190) NOT NULL, token_prefix varchar(16) NOT NULL, token_hash varchar(255) NOT NULL, last_used_at datetime NULL, created_at datetime NOT NULL, revoked_at datetime NULL,
          PRIMARY KEY (id), KEY token_prefix (token_prefix), KEY owner (owner_user_id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$p}stripe_events (
          event_id varchar(255) NOT NULL, event_type varchar(100) NOT NULL, processed_at datetime NOT NULL, PRIMARY KEY (event_id)
        ) {$charset};");
        update_option('lousy_outages_commerce_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function customer(int $user_id): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lo_customers WHERE user_id=%d", $user_id), ARRAY_A);
        return is_array($row) ? $row : ['user_id' => $user_id, 'plan' => 'free', 'subscription_status' => 'none'];
    }

    public static function plan(int $user_id): string {
        $customer = self::customer($user_id);
        return in_array((string)($customer['subscription_status'] ?? ''), ['active', 'trialing'], true)
            ? Entitlements::normalize_plan((string)($customer['plan'] ?? 'free')) : 'free';
    }

    public static function upsert_customer(int $user_id, array $values): void {
        global $wpdb;
        $old = self::customer($user_id);
        $data = array_merge($old, array_intersect_key($values, array_flip(['stripe_customer_id','stripe_subscription_id','plan','subscription_status','current_period_end','team_id'])));
        $data['user_id'] = $user_id;
        $data['updated_at'] = current_time('mysql', true);
        $wpdb->replace($wpdb->prefix . 'lo_customers', $data);
    }

    public static function event_seen(string $id): bool {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare("SELECT event_id FROM {$wpdb->prefix}lo_stripe_events WHERE event_id=%s", $id));
    }

    public static function mark_event(string $id, string $type): void {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'lo_stripe_events', ['event_id'=>$id, 'event_type'=>$type, 'processed_at'=>current_time('mysql', true)]);
    }
}

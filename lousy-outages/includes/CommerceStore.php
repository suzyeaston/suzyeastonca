<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

final class CommerceStore {
    public const SCHEMA_VERSION = '1.1.0';

    /** Provider-neutral entitlement provenance. Not payment credentials. */
    public const ACCESS_SOURCES = ['paypal', 'buymeacoffee', 'github_sponsors', 'complimentary', 'manual'];

    private const CUSTOMER_FIELDS = [
        'stripe_customer_id',
        'stripe_subscription_id',
        'plan',
        'subscription_status',
        'current_period_end',
        'team_id',
        'access_source',
        'access_expires_at',
        'access_note',
    ];

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'lo_';
        // dbDelta requires one field per line to add columns on upgrade.
        dbDelta("CREATE TABLE {$p}customers (
          user_id bigint(20) unsigned NOT NULL,
          stripe_customer_id varchar(64) NULL,
          stripe_subscription_id varchar(64) NULL,
          plan varchar(16) NOT NULL DEFAULT 'free',
          subscription_status varchar(32) NOT NULL DEFAULT 'none',
          current_period_end datetime NULL,
          team_id bigint(20) unsigned NULL,
          access_source varchar(32) NULL,
          access_expires_at datetime NULL,
          access_note varchar(255) NULL,
          updated_at datetime NOT NULL,
          PRIMARY KEY  (user_id),
          UNIQUE KEY stripe_customer_id (stripe_customer_id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$p}watchlists (
          id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          owner_user_id bigint(20) unsigned NOT NULL,
          team_id bigint(20) unsigned NULL,
          name varchar(190) NOT NULL,
          providers longtext NOT NULL,
          filters longtext NULL,
          digest_preferences longtext NULL,
          is_shared tinyint(1) NOT NULL DEFAULT 0,
          created_at datetime NOT NULL,
          updated_at datetime NOT NULL,
          PRIMARY KEY  (id),
          KEY owner (owner_user_id),
          KEY team (team_id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$p}alert_destinations (
          id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          owner_user_id bigint(20) unsigned NOT NULL,
          team_id bigint(20) unsigned NULL,
          type varchar(20) NOT NULL,
          label varchar(190) NOT NULL,
          endpoint_encrypted longtext NOT NULL,
          enabled tinyint(1) NOT NULL DEFAULT 1,
          created_at datetime NOT NULL,
          PRIMARY KEY  (id),
          KEY owner (owner_user_id),
          KEY team (team_id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$p}api_tokens (
          id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          owner_user_id bigint(20) unsigned NOT NULL,
          team_id bigint(20) unsigned NULL,
          name varchar(190) NOT NULL,
          token_prefix varchar(16) NOT NULL,
          token_hash varchar(255) NOT NULL,
          last_used_at datetime NULL,
          created_at datetime NOT NULL,
          revoked_at datetime NULL,
          PRIMARY KEY  (id),
          KEY token_prefix (token_prefix),
          KEY owner (owner_user_id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$p}stripe_events (
          event_id varchar(255) NOT NULL,
          event_type varchar(100) NOT NULL,
          processed_at datetime NOT NULL,
          PRIMARY KEY  (event_id)
        ) {$charset};");
        update_option('lousy_outages_commerce_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function customer(int $user_id): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lo_customers WHERE user_id=%d", $user_id), ARRAY_A);
        return is_array($row) ? $row : [
            'user_id' => $user_id,
            'plan' => 'free',
            'subscription_status' => 'none',
            'access_source' => null,
            'access_expires_at' => null,
            'access_note' => null,
        ];
    }

    public static function plan(int $user_id): string {
        return self::plan_from_customer(self::customer($user_id));
    }

    /**
     * Resolve effective plan from a customer row. Fail closed to free.
     * Stripe active/trialing and unexpired manual entitlements both grant paid plans.
     */
    public static function plan_from_customer(array $customer): string {
        $plan = Entitlements::normalize_plan((string) ($customer['plan'] ?? 'free'));
        if ($plan === 'free') {
            return 'free';
        }

        $status = (string) ($customer['subscription_status'] ?? '');
        if (in_array($status, ['active', 'trialing'], true)) {
            return $plan;
        }

        if (!self::manual_access_is_active($customer)) {
            return 'free';
        }

        return $plan;
    }

    public static function normalize_access_source(string $source): string {
        $source = strtolower(trim($source));
        return in_array($source, self::ACCESS_SOURCES, true) ? $source : '';
    }

    public static function manual_access_is_active(array $customer): bool {
        $plan = Entitlements::normalize_plan((string) ($customer['plan'] ?? 'free'));
        if ($plan === 'free') {
            return false;
        }

        $source = self::normalize_access_source((string) ($customer['access_source'] ?? ''));
        if ($source === '') {
            return false;
        }

        $expires = trim((string) ($customer['access_expires_at'] ?? ''));
        if ($expires === '') {
            return true;
        }

        $expires_ts = strtotime($expires . ' UTC');
        if ($expires_ts === false) {
            return false;
        }

        return $expires_ts >= time();
    }

    public static function upsert_customer(int $user_id, array $values): void {
        global $wpdb;
        $old = self::customer($user_id);
        $data = array_merge($old, array_intersect_key($values, array_flip(self::CUSTOMER_FIELDS)));
        $data['user_id'] = $user_id;
        $data['updated_at'] = current_time('mysql', true);
        $wpdb->replace($wpdb->prefix . 'lo_customers', $data);
    }

    /**
     * Grant or update provider-neutral paid access. Does not require Stripe IDs.
     *
     * @return true|\WP_Error
     */
    public static function grant_manual_access(int $user_id, string $plan, string $source, ?string $expires_at, string $note) {
        $plan = Entitlements::normalize_plan($plan);
        $source = self::normalize_access_source($source);
        if ($plan !== 'free' && $source === '') {
            return new \WP_Error('invalid_access_source', 'Choose a valid access source.');
        }

        $note = sanitize_text_field($note);
        if (strlen($note) > 255) {
            $note = substr($note, 0, 255);
        }

        $expires = null;
        if ($expires_at !== null && trim($expires_at) !== '') {
            $expires = self::normalize_expiry($expires_at);
            if ($expires === null) {
                return new \WP_Error('invalid_expiry', 'Expiry must be a valid date (YYYY-MM-DD).');
            }
        }

        if ($plan === 'free') {
            // Clearing manual access must not demote an active Stripe subscription.
            $customer = self::customer($user_id);
            $stripe_paid = in_array((string) ($customer['subscription_status'] ?? ''), ['active', 'trialing'], true);
            self::upsert_customer($user_id, [
                'plan' => $stripe_paid ? Entitlements::normalize_plan((string) ($customer['plan'] ?? 'free')) : 'free',
                'access_source' => null,
                'access_expires_at' => null,
                'access_note' => $note !== '' ? $note : null,
            ]);
            return true;
        }

        self::upsert_customer($user_id, [
            'plan' => $plan,
            'access_source' => $source,
            'access_expires_at' => $expires,
            'access_note' => $note !== '' ? $note : null,
        ]);
        return true;
    }

    /** Revoke manual paid access. Leaves Stripe customer/subscription columns untouched. */
    public static function revoke_manual_access(int $user_id): void {
        $customer = self::customer($user_id);
        $stripe_paid = in_array((string) ($customer['subscription_status'] ?? ''), ['active', 'trialing'], true);
        self::upsert_customer($user_id, [
            'plan' => $stripe_paid ? Entitlements::normalize_plan((string) ($customer['plan'] ?? 'free')) : 'free',
            'access_source' => null,
            'access_expires_at' => null,
            'access_note' => null,
        ]);
    }

    /**
     * Currently active manual paid entitlements (not Stripe-driven).
     *
     * @return list<array<string, mixed>>
     */
    public static function list_manual_entitlements(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'lo_customers';
        $sources = array_map(static fn(string $s): string => "'" . esc_sql($s) . "'", self::ACCESS_SOURCES);
        $in = implode(',', $sources);
        $now = gmdate('Y-m-d H:i:s');
        $sql = "SELECT c.*, u.user_email AS email
            FROM {$table} c
            INNER JOIN {$wpdb->users} u ON u.ID = c.user_id
            WHERE c.plan IN ('pro','team')
              AND c.access_source IN ({$in})
              AND (c.access_expires_at IS NULL OR c.access_expires_at = '' OR c.access_expires_at >= %s)
            ORDER BY c.updated_at DESC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $now), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public static function normalize_expiry(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . ' 23:59:59';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value) !== 1) {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        if (substr_count($value, ':') === 1) {
            $value .= ':00';
        }
        return $value;
    }

    public static function event_seen(string $id): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT event_id FROM {$wpdb->prefix}lo_stripe_events WHERE event_id=%s", $id));
    }

    public static function mark_event(string $id, string $type): void {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'lo_stripe_events', [
            'event_id' => $id,
            'event_type' => $type,
            'processed_at' => current_time('mysql', true),
        ]);
    }
}

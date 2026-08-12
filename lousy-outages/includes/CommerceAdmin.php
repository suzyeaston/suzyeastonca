<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

final class CommerceAdmin {
    private const OPTIONS = [
        'lousy_outages_stripe_publishable_key',
        'lousy_outages_stripe_secret_key',
        'lousy_outages_stripe_webhook_secret',
        'lousy_outages_stripe_price_pro',
        'lousy_outages_stripe_price_team',
        'lousy_outages_feature_commerce',
        'lousy_outages_feature_webhooks',
        'lousy_outages_feature_private_boards',
    ];

    public static function bootstrap(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'settings']);
        add_action('admin_post_lousy_outages_grant_manual_access', [self::class, 'handle_grant']);
        add_action('admin_post_lousy_outages_revoke_manual_access', [self::class, 'handle_revoke']);
    }

    public static function menu(): void {
        add_submenu_page(
            'lousy-outages',
            'Plans & Billing',
            'Plans & Billing',
            'manage_options',
            'lousy-outages-billing',
            [self::class, 'page']
        );
    }

    public static function settings(): void {
        foreach (self::OPTIONS as $option) {
            register_setting('lousy_outages_billing', $option, [
                'sanitize_callback' => str_contains($option, 'feature_') ? 'absint' : 'sanitize_text_field',
            ]);
        }
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $notice = self::consume_notice();
        $manual_rows = CommerceStore::list_manual_entitlements();
        ?>
        <div class="wrap">
            <h1>Plans &amp; Billing</h1>
            <?php if ($notice !== null) : ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible"><p><?php echo esc_html($notice['message']); ?></p></div>
            <?php endif; ?>

            <p>Stripe keys can also be supplied through deployment tooling. Secret values are stored as WordPress options and should never be committed.</p>
            <form action="options.php" method="post">
                <?php settings_fields('lousy_outages_billing'); ?>
                <table class="form-table">
                    <?php foreach (self::OPTIONS as $option) :
                        $secret = str_contains($option, 'secret');
                        $flag = str_contains($option, 'feature_');
                        ?>
                        <tr>
                            <th><label for="<?php echo esc_attr($option); ?>"><?php echo esc_html(ucwords(str_replace(['lousy_outages_', '_'], ' ', $option))); ?></label></th>
                            <td>
                                <input
                                    id="<?php echo esc_attr($option); ?>"
                                    name="<?php echo esc_attr($option); ?>"
                                    type="<?php echo $flag ? 'checkbox' : ($secret ? 'password' : 'text'); ?>"
                                    <?php if ($flag) {
                                        checked((int) get_option($option, 0), 1);
                                    } ?>
                                    value="<?php echo $flag ? '1' : esc_attr((string) get_option($option, '')); ?>"
                                    class="<?php echo $flag ? '' : 'regular-text'; ?>"
                                    autocomplete="off"
                                >
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button(); ?>
            </form>
            <p><strong>Webhook URL:</strong> <code><?php echo esc_html(rest_url('lousy-outages/v1/stripe/webhook')); ?></code></p>

            <hr>
            <h2>Manual Access</h2>
            <p>Grant Founding Pro / Team after you verify PayPal, Buy Me a Coffee, GitHub Sponsors, or complimentary access. No payment credentials or transaction payloads are stored here — only entitlement provenance.</p>
            <p>The buyer must already have a Lousy Outages account (magic-link sign-in). This form will not invent users.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="lousy_outages_grant_manual_access">
                <?php wp_nonce_field('lousy_outages_grant_manual_access'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="lo_manual_email">WordPress user email</label></th>
                        <td><input class="regular-text" type="email" name="lo_manual_email" id="lo_manual_email" required autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="lo_manual_plan">Plan</label></th>
                        <td>
                            <select name="lo_manual_plan" id="lo_manual_plan">
                                <?php foreach (Entitlements::PLANS as $plan) : ?>
                                    <option value="<?php echo esc_attr($plan); ?>" <?php selected($plan, 'pro'); ?>><?php echo esc_html(ucfirst($plan)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lo_manual_source">Access source</label></th>
                        <td>
                            <select name="lo_manual_source" id="lo_manual_source">
                                <?php foreach (CommerceStore::ACCESS_SOURCES as $source) : ?>
                                    <option value="<?php echo esc_attr($source); ?>"><?php echo esc_html(self::source_label($source)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lo_manual_expires">Expiry date</label></th>
                        <td>
                            <input type="date" name="lo_manual_expires" id="lo_manual_expires" class="regular-text">
                            <p class="description">Optional. Leave blank for no expiry. Expired access falls back to Free.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="lo_manual_note">Internal note</label></th>
                        <td>
                            <input class="regular-text" type="text" name="lo_manual_note" id="lo_manual_note" maxlength="255" placeholder="e.g. founding pro via paypal 2026-08">
                            <p class="description">Short provenance only. Do not paste transaction IDs with secrets, card data, or webhook credentials.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Grant / update access', 'primary', 'submit', false); ?>
            </form>

            <h3>Active manual entitlements</h3>
            <?php if ($manual_rows === []) : ?>
                <p>No active manual paid access right now.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Plan</th>
                            <th>Source</th>
                            <th>Expiry</th>
                            <th>Note</th>
                            <th>Revoke</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manual_rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($row['email'] ?? '')); ?></td>
                                <td><?php echo esc_html(ucfirst((string) ($row['plan'] ?? 'free'))); ?></td>
                                <td><?php echo esc_html(self::source_label((string) ($row['access_source'] ?? ''))); ?></td>
                                <td><?php echo esc_html(self::format_expiry((string) ($row['access_expires_at'] ?? ''))); ?></td>
                                <td><?php echo esc_html((string) ($row['access_note'] ?? '')); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                        <input type="hidden" name="action" value="lousy_outages_revoke_manual_access">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) ($row['user_id'] ?? 0)); ?>">
                                        <?php wp_nonce_field('lousy_outages_revoke_manual_access_' . (int) ($row['user_id'] ?? 0)); ?>
                                        <?php submit_button('Revoke', 'delete', 'submit', false); ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_grant(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }
        check_admin_referer('lousy_outages_grant_manual_access');

        $email = sanitize_email((string) ($_POST['lo_manual_email'] ?? ''));
        $plan = Entitlements::normalize_plan((string) ($_POST['lo_manual_plan'] ?? 'free'));
        $source = CommerceStore::normalize_access_source((string) ($_POST['lo_manual_source'] ?? ''));
        $expires_raw = sanitize_text_field((string) ($_POST['lo_manual_expires'] ?? ''));
        $note = sanitize_text_field((string) ($_POST['lo_manual_note'] ?? ''));

        if (!is_email($email)) {
            self::redirect_with_notice('error', 'Enter a real email address.');
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            self::redirect_with_notice(
                'error',
                'No Lousy Outages account exists for that email yet. The buyer must first create/sign into their account via the magic-link flow at /lousy-outages/account/, then you can grant access.'
            );
        }

        if ($plan !== 'free' && $source === '') {
            self::redirect_with_notice('error', 'Choose a valid access source for paid plans.');
        }

        // Reject plans that normalize away from what was submitted (invalid → free is OK only if free was chosen).
        $submitted_plan = strtolower(trim((string) ($_POST['lo_manual_plan'] ?? '')));
        if ($submitted_plan !== '' && !in_array($submitted_plan, Entitlements::PLANS, true)) {
            self::redirect_with_notice('error', 'Invalid plan. Access was not changed.');
        }

        $result = CommerceStore::grant_manual_access(
            (int) $user->ID,
            $plan,
            $source,
            $expires_raw !== '' ? $expires_raw : null,
            $note
        );
        if (is_wp_error($result)) {
            self::redirect_with_notice('error', $result->get_error_message());
        }

        $label = $plan === 'free' ? 'Free (manual paid access cleared)' : ucfirst($plan);
        self::redirect_with_notice('success', 'Updated access for ' . $email . ' → ' . $label . '.');
    }

    public static function handle_revoke(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }
        $user_id = absint($_POST['user_id'] ?? 0);
        check_admin_referer('lousy_outages_revoke_manual_access_' . $user_id);
        if ($user_id <= 0) {
            self::redirect_with_notice('error', 'Missing user for revoke.');
        }

        CommerceStore::revoke_manual_access($user_id);
        self::redirect_with_notice('success', 'Manual paid access revoked. Stripe fields were left alone.');
    }

    private static function source_label(string $source): string {
        return match ($source) {
            'paypal' => 'PayPal',
            'buymeacoffee' => 'Buy Me a Coffee',
            'github_sponsors' => 'GitHub Sponsors',
            'complimentary' => 'Complimentary',
            'manual' => 'Manual',
            default => $source !== '' ? $source : '—',
        };
    }

    private static function format_expiry(string $expires): string {
        $expires = trim($expires);
        if ($expires === '') {
            return 'Never';
        }
        return substr($expires, 0, 10);
    }

    private static function redirect_with_notice(string $type, string $message): void {
        set_transient('lo_billing_notice_' . get_current_user_id(), ['type' => $type, 'message' => $message], 60);
        wp_safe_redirect(admin_url('admin.php?page=lousy-outages-billing'));
        exit;
    }

    /** @return array{type:string,message:string}|null */
    private static function consume_notice(): ?array {
        $key = 'lo_billing_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if (is_array($notice) && isset($notice['type'], $notice['message'])) {
            delete_transient($key);
            return [
                'type' => sanitize_key((string) $notice['type']) === 'error' ? 'error' : 'success',
                'message' => sanitize_text_field((string) $notice['message']),
            ];
        }
        return null;
    }
}

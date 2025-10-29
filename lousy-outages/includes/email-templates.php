<?php
/**
 * Email template helpers for Lousy Outages.
 */

declare(strict_types=1);

if (!class_exists('Lousy_Outages_Email_Helper')) {
    class Lousy_Outages_Email_Helper {
        /** @var string */
        private static $alt_body = '';

        public static function set_alt_body(string $body): void {
            self::$alt_body = $body;
        }

        public static function get_alt_body(): string {
            return self::$alt_body;
        }

        public static function reset(): void {
            self::$alt_body = '';
        }

        public static function html_content_type(): string {
            return 'text/html; charset=UTF-8';
        }

        public static function inject_alt_body($phpmailer): void {
            if ($phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer) {
                $alt = self::get_alt_body();
                if ('' !== $alt) {
                    $phpmailer->AltBody = $alt;
                }
            }
        }
    }
}

if (!function_exists('send_lo_confirmation_email')) {
    /**
     * Sends the Lousy Outages confirmation email.
     */
    function send_lo_confirmation_email(string $email, string $unsubscribe_url, ?string $confirm_url = null): bool {
        $email = sanitize_email($email);
        if (!$email || !is_email($email)) {
            error_log('[lousy_outages] confirmation_email invalid recipient');
            return false;
        }

        $unsubscribe_url = $unsubscribe_url ?: home_url('/lousy-outages/');
        $unsubscribe_raw = esc_url_raw($unsubscribe_url);
        $unsubscribe_html = esc_url($unsubscribe_url);

        if (null === $confirm_url || '' === trim((string) $confirm_url)) {
            $confirm_url = apply_filters('lo_confirmation_cta_url', '', $email, $unsubscribe_url);
        }
        if (null === $confirm_url || '' === trim((string) $confirm_url)) {
            $confirm_url = add_query_arg(
                'email',
                rawurlencode($email),
                home_url('/lousy-outages/')
            );
        }
        $confirm_raw  = esc_url_raw($confirm_url);
        $confirm_html = esc_url($confirm_url);

        $subject = '🕹️ Confirm Access: Lousy Outages Command Console';

        $text_body_lines = [
            'Rogue signal detected. Ready to jack in?',
            '',
            'Confirm your access to the Lousy Outages command bunker:',
            $confirm_raw,
            '',
            'If this wasn\'t you, cut the wire instantly:',
            $unsubscribe_raw,
            '',
            'Stay sharp – Lousy Outages',
        ];
        $text_body = implode("\n", $text_body_lines);

        $html_body = '<!doctype html>' .
            '<meta charset="utf-8">' .
            '<body style="margin:0;background:#000;color:#FFD700;font-family:\'Courier New\',\'Lucida Console\',monospace;">' .
            '  <div style="max-width:640px;margin:0 auto;padding:36px 18px;">' .
            '    <div style="border:3px solid #00FF00;background:linear-gradient(155deg,rgba(0,0,0,0.95),rgba(12,20,0,0.92));border-radius:20px;padding:30px 26px;box-shadow:0 0 32px rgba(0,255,0,0.3);">' .
            '      <p style="margin:0 0 12px;font-size:13px;letter-spacing:0.18em;text-transform:uppercase;color:#00FF00;">rogue signal detected</p>' .
            '      <h1 style="margin:0 0 16px;font-size:28px;color:#FFD700;letter-spacing:0.08em;text-transform:uppercase;">Confirm bunker access</h1>' .
            '      <p style="margin:0 0 18px;font-size:16px;line-height:1.6;color:#FDF5A6;">One tap locks your feed into outage intel and neon-lit play-by-play. No confirmation, no transmissions.</p>' .
            '      <p style="margin:0 0 22px;">' .
            '        <a href="' . $confirm_html . '" style="display:inline-block;padding:16px 26px;border-radius:999px;border:2px solid #00FF00;background:#111;color:#00FF00;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:0.12em;">Confirm &amp; Jack In</a>' .
            '      </p>' .
            '      <p style="margin:0 0 18px;font-size:13px;color:#FDF5A6;">If the button stalls, copy this access link:<br><span style="color:#00FF00;">' . esc_html($confirm_raw) . '</span></p>' .
            '      <div style="margin:24px 0;padding:16px 18px;border:1px dashed rgba(0,255,0,0.6);border-radius:14px;background:rgba(4,25,4,0.7);color:#BFFFBF;font-size:13px;">' .
            '        <strong style="display:block;font-size:11px;letter-spacing:0.18em;color:#7BFF7B;margin-bottom:6px;text-transform:uppercase;">Unsubscribe</strong>' .
            '        Back out anytime: <a style="color:#7BFF7B;text-decoration:none;" href="' . $unsubscribe_html . '">' . esc_html($unsubscribe_raw) . '</a>' .
            '      </div>' .
            '      <p style="margin:0;font-size:12px;color:rgba(255,215,0,0.75);">Console tip: whitelist suzyeaston.ca so the alerts don\'t get ghosted.</p>' .
            '    </div>' .
            '  </div>' .
            '</body>';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'List-Unsubscribe: <' . $unsubscribe_raw . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        ];

        Lousy_Outages_Email_Helper::set_alt_body($text_body);
        add_filter('wp_mail_content_type', ['Lousy_Outages_Email_Helper', 'html_content_type']);
        add_action('phpmailer_init', ['Lousy_Outages_Email_Helper', 'inject_alt_body']);

        $sent = wp_mail($email, $subject, $html_body, $headers);

        remove_filter('wp_mail_content_type', ['Lousy_Outages_Email_Helper', 'html_content_type']);
        remove_action('phpmailer_init', ['Lousy_Outages_Email_Helper', 'inject_alt_body']);
        Lousy_Outages_Email_Helper::reset();

        if (!$sent) {
            error_log('[lousy_outages] confirmation_email send failed for ' . $email);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Sent Lousy Outages confirmation to ' . $email . ' at ' . current_time('mysql'));
        }

        return (bool) $sent;
    }
}

if (!function_exists('send_lo_outage_alert_email')) {
    /**
     * Sends a themed outage alert email.
     *
     * @param array<string,mixed> $incident_data
     */
    function send_lo_outage_alert_email(string $email, array $incident_data): bool {
        $email = sanitize_email($email);
        if (!$email || !is_email($email)) {
            error_log('[lousy_outages] outage_email invalid recipient');
            return false;
        }

        $service = isset($incident_data['service']) ? (string) $incident_data['service'] : 'Service';
        $status  = isset($incident_data['status']) ? (string) $incident_data['status'] : 'Status Change';
        $summary = isset($incident_data['summary']) ? (string) $incident_data['summary'] : '';
        $impact  = isset($incident_data['impact']) ? (string) $incident_data['impact'] : $status;
        $timestamp_raw = isset($incident_data['timestamp']) ? (string) $incident_data['timestamp'] : '';
        $components = isset($incident_data['components']) ? (string) $incident_data['components'] : '';
        $status_url = isset($incident_data['url']) ? (string) $incident_data['url'] : '';
        $extra_notes = isset($incident_data['notes']) ? (string) $incident_data['notes'] : '';

        $service_label = trim($service) ?: 'Service';
        $status_label  = trim($status) ?: (trim($impact) ?: 'Status Change');
        $summary_text  = trim($summary) ?: trim($extra_notes);
        $status_url    = $status_url ?: home_url('/lousy-outages/');
        $status_url_raw  = esc_url_raw($status_url);
        $status_url_html = esc_url($status_url);

        $timestamp_display = $timestamp_raw;
        if ('' !== $timestamp_raw) {
            $time = strtotime($timestamp_raw);
            if (false !== $time) {
                $timestamp_display = wp_date('M j, Y g:i A T', $time);
            }
        }
        $timestamp_display = $timestamp_display ?: wp_date('M j, Y g:i A T');

        $component_line = trim($components);
        if ('' === $component_line && isset($incident_data['components_list']) && is_array($incident_data['components_list'])) {
            $component_line = implode(' • ', array_map('strval', $incident_data['components_list']));
        }
        if ('' === $component_line) {
            $component_line = 'All monitored components';
        }

        $unsubscribe_url = '';
        if (isset($incident_data['unsubscribe_url']) && '' !== trim((string) $incident_data['unsubscribe_url'])) {
            $unsubscribe_url = (string) $incident_data['unsubscribe_url'];
        } elseif (class_exists('LousyOutages\\IncidentAlerts')) {
            $token = \LousyOutages\IncidentAlerts::build_unsubscribe_token($email);
            $unsubscribe_url = add_query_arg(
                [
                    'lo_unsub' => 1,
                    'email'    => rawurlencode($email),
                    'token'    => $token,
                ],
                home_url('/lousy-outages/')
            );
        } else {
            $unsubscribe_url = home_url('/lousy-outages/');
        }
        $unsubscribe_raw  = esc_url_raw($unsubscribe_url);
        $unsubscribe_html = esc_url($unsubscribe_url);

        $clean_summary = $summary_text ?: sprintf('%s status changed to %s.', $service_label, $status_label);

        $subject = sprintf('🚨 Outage Alert: %s – %s', $service_label, $status_label);

        $text_body_lines = [
            sprintf('%s outage alert', strtoupper($service_label)),
            sprintf('Status: %s', $status_label),
            sprintf('Detected: %s', $timestamp_display),
            '',
            $clean_summary,
            '',
            'Impacted components: ' . $component_line,
            '',
            'Track live status: ' . $status_url_raw,
            '',
            'Unsubscribe: ' . $unsubscribe_raw,
            '',
            'Hold the line — Lousy Outages',
        ];
        $text_body = implode("\n", $text_body_lines);

        $service_html = esc_html($service_label);
        $status_html  = esc_html($status_label);
        $timestamp_html = esc_html($timestamp_display);
        $summary_html = nl2br(esc_html($clean_summary));
        $components_html = esc_html($component_line);

        $html_body = '<!doctype html>' .
            '<meta charset="utf-8">' .
            '<body style="margin:0;background:#000;color:#FFD700;font-family:\'Courier New\',\'Lucida Console\',monospace;">' .
            '  <div style="max-width:680px;margin:0 auto;padding:36px 18px;">' .
            '    <div style="border:3px solid #FF3131;background:linear-gradient(155deg,rgba(24,0,0,0.95),rgba(6,0,12,0.92));border-radius:22px;padding:32px 28px;box-shadow:0 0 36px rgba(255,49,49,0.4);">' .
            '      <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;text-transform:uppercase;">' .
            '        <span style="font-size:14px;letter-spacing:0.16em;color:#FF3131;">lousy outages alert</span>' .
            '        <span style="font-size:12px;color:rgba(255,215,0,0.75);">command bunker feed</span>' .
            '      </header>' .
            '      <h1 style="margin:0 0 10px;font-size:30px;color:#FF3131;letter-spacing:0.1em;">' . $service_html . '</h1>' .
            '      <p style="margin:0 0 18px;font-size:20px;color:#FFD700;text-transform:uppercase;letter-spacing:0.08em;">' . $status_html . '</p>' .
            '      <div style="display:grid;gap:14px;margin:0 0 22px;">' .
            '        <div style="background:rgba(255,49,49,0.12);border:1px dashed rgba(255,49,49,0.6);border-radius:14px;padding:14px 18px;color:#FDF5A6;font-size:13px;">' .
            '          <strong style="display:block;font-size:11px;letter-spacing:0.18em;color:#FF9797;margin-bottom:4px;text-transform:uppercase;">detected</strong>' .
            '          ' . $timestamp_html .
            '        </div>' .
            '        <div style="background:rgba(0,255,0,0.08);border:1px dashed rgba(0,255,0,0.35);border-radius:14px;padding:14px 18px;color:#D6FFD6;font-size:13px;">' .
            '          <strong style="display:block;font-size:11px;letter-spacing:0.18em;color:#7BFF7B;margin-bottom:4px;text-transform:uppercase;">components</strong>' .
            '          ' . $components_html .
            '        </div>' .
            '      </div>' .
            '      <p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#FDF5A6;">' . $summary_html . '</p>' .
            '      <p style="margin:0 0 24px;">' .
            '        <a href="' . $status_url_html . '" style="display:inline-block;padding:16px 28px;border-radius:999px;border:2px solid #FF3131;background:#000;color:#FF3131;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:0.12em;">View live status</a>' .
            '      </p>' .
            '      <p style="margin:0 0 18px;font-size:12px;color:rgba(255,215,0,0.7);">Manual override URL: <a style="color:#FF9797;" href="' . $status_url_html . '">' . esc_html($status_url_raw) . '</a></p>' .
            '      <footer style="margin-top:26px;padding-top:18px;border-top:1px dashed rgba(255,49,49,0.4);font-size:12px;color:rgba(255,215,0,0.7);">' .
            '        Want out? <a style="color:#7BFF7B;" href="' . $unsubscribe_html . '">Unsubscribe instantly</a><br>' .
            '        Raw link: <span style="color:#7BFF7B;">' . esc_html($unsubscribe_raw) . '</span>' .
            '      </footer>' .
            '    </div>' .
            '  </div>' .
            '</body>';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'List-Unsubscribe: <' . $unsubscribe_raw . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        ];

        Lousy_Outages_Email_Helper::set_alt_body($text_body);
        add_filter('wp_mail_content_type', ['Lousy_Outages_Email_Helper', 'html_content_type']);
        add_action('phpmailer_init', ['Lousy_Outages_Email_Helper', 'inject_alt_body']);

        $sent = wp_mail($email, $subject, $html_body, $headers);

        remove_filter('wp_mail_content_type', ['Lousy_Outages_Email_Helper', 'html_content_type']);
        remove_action('phpmailer_init', ['Lousy_Outages_Email_Helper', 'inject_alt_body']);
        Lousy_Outages_Email_Helper::reset();

        if (!$sent) {
            error_log('[lousy_outages] outage_email send failed for ' . $email . ' subject=' . $subject);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Sent Lousy Outages alert to ' . $email . ' at ' . current_time('mysql'));
        }

        return (bool) $sent;
    }
}


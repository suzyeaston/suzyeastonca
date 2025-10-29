<?php
/**
 * Email template helpers for Lousy Outages.
 */

declare(strict_types=1);

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

        ob_start();
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Lousy Outages Confirmation</title>
        </head>
        <body style="margin:0;background:#000;color:#FFD700;font-family:'Courier New','Lucida Console',monospace;">
            <div style="max-width:640px;margin:0 auto;padding:36px 18px;">
                <div style="border:3px solid #00FF00;background:linear-gradient(155deg,rgba(0,0,0,0.95),rgba(12,20,0,0.92));border-radius:20px;padding:30px 26px;box-shadow:0 0 32px rgba(0,255,0,0.3);">
                    <p style="margin:0 0 12px;font-size:13px;letter-spacing:0.18em;text-transform:uppercase;color:#00FF00;">rogue signal detected</p>
                    <h1 style="margin:0 0 16px;font-size:28px;color:#FFD700;letter-spacing:0.08em;text-transform:uppercase;">Confirm bunker access</h1>
                    <p style="margin:0 0 18px;font-size:16px;line-height:1.6;color:#FDF5A6;">One tap locks your feed into outage intel and neon-lit play-by-play. No confirmation, no transmissions.</p>
                    <p style="margin:0 0 22px;">
                        <a href="<?php echo esc_url($confirm_html); ?>" style="display:inline-block;padding:16px 26px;border-radius:999px;border:2px solid #00FF00;background:#111;color:#00FF00;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:0.12em;">Confirm &amp; Jack In</a>
                    </p>
                    <p style="margin:0 0 18px;font-size:13px;color:#FDF5A6;">If the button stalls, copy this access link:<br><span style="color:#00FF00;"><?php echo esc_html($confirm_raw); ?></span></p>
                    <div style="margin:24px 0;padding:16px 18px;border:1px dashed rgba(0,255,0,0.6);border-radius:14px;background:rgba(4,25,4,0.7);color:#BFFFBF;font-size:13px;">
                        <strong style="display:block;font-size:11px;letter-spacing:0.18em;color:#7BFF7B;margin-bottom:6px;text-transform:uppercase;">Unsubscribe</strong>
                        Back out anytime: <a style="color:#7BFF7B;text-decoration:none;" href="<?php echo esc_url($unsubscribe_html); ?>"><?php echo esc_html($unsubscribe_raw); ?></a>
                    </div>
                    <p style="margin:0;font-size:12px;color:rgba(255,215,0,0.75);">Console tip: whitelist suzyeaston.ca so the alerts don&rsquo;t get ghosted.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        $html_body = trim((string) ob_get_clean());

        $headers = [
            'List-Unsubscribe: <' . $unsubscribe_raw . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        ];

        $sent = \LousyOutages\Mailer::send($email, $subject, $text_body, $html_body, $headers);

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

        ob_start();
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Lousy Outages Alert</title>
        </head>
        <body style="margin:0;background:#050607;color:#FFEB3B;font-family:'Courier New','Lucida Console',monospace;">
            <div style="max-width:720px;margin:0 auto;padding:40px 24px;">
                <div style="border:3px solid #FFEB3B;border-radius:24px;padding:34px 30px;background:linear-gradient(160deg,rgba(7,12,20,0.95),rgba(20,6,40,0.92));box-shadow:0 0 32px rgba(255,235,59,0.35);">
                    <p style="margin:0 0 10px;font-size:13px;letter-spacing:0.2em;text-transform:uppercase;color:#FF1744;">alert: <?php echo esc_html($service_label); ?></p>
                    <h1 style="margin:0 0 14px;font-size:32px;color:#FFEB3B;text-transform:uppercase;letter-spacing:0.05em;"><?php echo esc_html($service_label); ?> status: <?php echo esc_html(strtoupper($status_label)); ?></h1>
                    <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#FFF59D;"><?php echo esc_html($clean_summary); ?></p>
                    <dl style="margin:0 0 20px;color:#FFF59D;font-size:14px;line-height:1.6;">
                        <div style="display:flex;flex-wrap:wrap;gap:6px 16px;margin-bottom:10px;">
                            <dt style="width:120px;text-transform:uppercase;letter-spacing:0.08em;color:#80D8FF;">Status</dt>
                            <dd style="margin:0;font-weight:700;"><?php echo esc_html($status_label); ?></dd>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px 16px;margin-bottom:10px;">
                            <dt style="width:120px;text-transform:uppercase;letter-spacing:0.08em;color:#80D8FF;">Components</dt>
                            <dd style="margin:0;"><?php echo esc_html($component_line); ?></dd>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px 16px;margin-bottom:10px;">
                            <dt style="width:120px;text-transform:uppercase;letter-spacing:0.08em;color:#80D8FF;">Detected</dt>
                            <dd style="margin:0;"><?php echo esc_html($timestamp_display); ?></dd>
                        </div>
                    </dl>
                    <p style="margin:0 0 18px;font-size:14px;color:#FFCCBC;">Keep eyes on the incident console:</p>
                    <p style="margin:0 0 24px;">
                        <a href="<?php echo esc_url($status_url_html); ?>" style="display:inline-block;padding:16px 28px;border-radius:999px;border:2px solid #FF1744;background:#0A0418;color:#FFEB3B;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:0.14em;">View live status feed</a>
                    </p>
                    <p style="margin:0 0 14px;font-size:13px;color:#FFF59D;">Backup link:<br><span style="color:#FFAB40;"><?php echo esc_html($status_url_raw); ?></span></p>
                    <?php if ($extra_notes) : ?>
                        <p style="margin:0 0 28px;font-size:13px;color:#FFCDD2;"><?php echo esc_html($extra_notes); ?></p>
                    <?php endif; ?>
                    <div style="margin:26px 0;padding:18px;border:1px dashed rgba(255,235,59,0.7);border-radius:16px;background:rgba(29,8,48,0.8);color:#FFEB3B;font-size:13px;">
                        <strong style="display:block;font-size:11px;letter-spacing:0.18em;color:#FF1744;margin-bottom:6px;text-transform:uppercase;">Need to bail?</strong>
                        Unsubscribe instantly: <a style="color:#FFAB40;text-decoration:none;" href="<?php echo esc_url($unsubscribe_html); ?>"><?php echo esc_html($unsubscribe_raw); ?></a>
                    </div>
                    <p style="margin:0;font-size:12px;color:rgba(255,235,59,0.75);">Stay patched &mdash; Lousy Outages monitoring team.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        $html_body = trim((string) ob_get_clean());

        $headers = [
            'List-Unsubscribe: <' . $unsubscribe_raw . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        ];

        if (isset($incident_data['headers']) && is_array($incident_data['headers'])) {
            foreach ($incident_data['headers'] as $header) {
                if (!is_string($header)) {
                    continue;
                }
                $headers[] = $header;
            }
        }

        $sent = \LousyOutages\Mailer::send($email, $subject, $text_body, $html_body, $headers);

        if (!$sent) {
            error_log('[lousy_outages] outage_email send failed for ' . $email . ' subject=' . $subject);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Sent Lousy Outages alert to ' . $email . ' at ' . current_time('mysql'));
        }

        return (bool) $sent;
    }
}


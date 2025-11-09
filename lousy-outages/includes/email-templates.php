<?php
/**
 * Email template helpers for Lousy Outages.
 */

declare(strict_types=1);

if (!function_exists('lo_unsubscribe_url_for')) {
    function lo_unsubscribe_url_for(string $email): string {
        $email = sanitize_email($email);
        if (!$email || !is_email($email)) {
            return home_url('/lousy-outages/');
        }

        if (!class_exists('SuzyEaston\\LousyOutages\\IncidentAlerts')) {
            return home_url('/lousy-outages/');
        }

        $token = \SuzyEaston\LousyOutages\IncidentAlerts::build_unsubscribe_token($email);

        return add_query_arg(
            [
                'lo_unsub' => 1,
                'email'    => rawurlencode($email),
                'token'    => $token,
            ],
            home_url('/lousy-outages/')
        );
    }
}

if (!function_exists('lo_send_confirmation')) {
    function lo_send_confirmation(string $email, ?string $confirm_url = null): bool {
        $unsubscribe_url = lo_unsubscribe_url_for($email);

        do_action('lousy_outages_log', 'confirm_email_unsub_url', [
            'email' => sanitize_email($email),
            'unsub' => $unsubscribe_url,
        ]);

        return send_lo_confirmation_email($email, $unsubscribe_url, $confirm_url);
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

        if ('' === trim((string) $unsubscribe_url)) {
            $unsubscribe_url = lo_unsubscribe_url_for($email);
        }
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

        $subject = '🔔 Please confirm your subscription to Lousy Outages';

        $text_body_lines = [
            'Please confirm your subscription to Lousy Outages.',
            '',
            'Tap the link below to confirm and start receiving outage intel:',
            $confirm_raw,
            '',
            'Dashboard anytime: ' . home_url('/lousy-outages/'),
            '',
            'Unsubscribe instantly if this wasn’t you:',
            $unsubscribe_raw,
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
        <body style="margin:0;background:#05050a;color:#f7f4ff;font-family:Segoe UI,Roboto,system-ui,-apple-system,sans-serif;font-size:16px;line-height:1.5;">
            <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
                <div style="border-radius:18px;border:1px solid rgba(119,85,255,0.4);background:linear-gradient(145deg,#0d0c1f,#1b1540);box-shadow:0 22px 46px rgba(26,17,68,0.45);padding:32px 30px;">
                    <p style="margin:0 0 12px;font-size:14px;letter-spacing:0.12em;text-transform:uppercase;color:#8f80ff;">Confirm required</p>
                    <h1 style="margin:0 0 16px;font-size:30px;color:#f9f7ff;letter-spacing:0.04em;">Please confirm your subscription to Lousy Outages</h1>
                    <p style="margin:0 0 18px;font-size:16px;line-height:1.5;color:rgba(255,255,255,0.82);">Confirm your email to receive outage alerts, post-mortems, and status intel. One click keeps you in the loop.</p>
                    <p style="margin:0 0 22px;">
                        <a href="<?php echo esc_url($confirm_html); ?>" style="display:inline-block;padding:16px 28px;border-radius:999px;border:2px solid rgba(170,144,255,0.9);background:#6c5ce7;color:#fff;font-weight:700;text-decoration:none;letter-spacing:0.08em;">Confirm subscription</a>
                    </p>
                    <p style="margin:0 0 18px;font-size:14px;color:rgba(255,255,255,0.75);">Button sleepy? Copy this link instead:<br><span style="word-break:break-all;color:#b6a9ff;"><?php echo esc_html($confirm_raw); ?></span></p>
                    <p style="margin:0 0 18px;font-size:14px;color:rgba(255,255,255,0.75);">Visit the live dashboard any time:<br><a href="<?php echo esc_url(home_url('/lousy-outages/')); ?>" style="color:#8f80ff;"><?php echo esc_html(home_url('/lousy-outages/')); ?></a></p>
                    <div style="margin:24px 0 0;padding:16px 18px;border:1px dashed rgba(143,128,255,0.6);border-radius:14px;background:rgba(24,20,58,0.7);color:rgba(255,255,255,0.8);font-size:13px;">
                        <strong style="display:block;font-size:11px;letter-spacing:0.16em;color:#d0c6ff;margin-bottom:6px;text-transform:uppercase;">Unsubscribe</strong>
                        Leave immediately if this wasn’t you:<br><a style="color:#d0c6ff;text-decoration:none;" href="<?php echo esc_url($unsubscribe_html); ?>"><?php echo esc_html($unsubscribe_raw); ?></a>
                    </div>
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

        $sent = \SuzyEaston\LousyOutages\Mailer::send($email, $subject, $text_body, $html_body, $headers);

        if (!$sent) {
            error_log('[lousy_outages] confirmation_email send failed for ' . $email);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Sent Lousy Outages confirmation to ' . $email . ' at ' . current_time('mysql'));
        }

        return (bool) $sent;
    }
}

if (!function_exists('LO_compose_daily_digest')) {
    /**
     * Compose the daily digest email subject and bodies.
     *
     * @param array<int,array<string,mixed>> $incidents
     * @return array{subject:string,html:string,text:string}
     */
    function LO_compose_daily_digest(array $incidents): array {
        $items = [];
        foreach ($incidents as $incident) {
            if (!is_array($incident)) {
                continue;
            }

            $provider = isset($incident['provider']) ? trim((string) $incident['provider']) : '';
            $title    = isset($incident['title']) ? trim((string) $incident['title']) : '';
            $status   = isset($incident['status']) ? trim((string) $incident['status']) : '';
            $url      = isset($incident['url']) ? trim((string) $incident['url']) : '';

            if ('' === $provider || '' === $title) {
                continue;
            }

            if ('' === $status && isset($incident['statusRaw'])) {
                $status = trim((string) $incident['statusRaw']);
            }

            if ('' !== $url) {
                $url = esc_url_raw($url);
            }

            $items[] = [
                'provider' => $provider,
                'title'    => \SuzyEaston\LousyOutages\Email\Composer::shortTitle($title),
                'status'   => $status ?: 'Status update',
                'url'      => $url,
            ];
        }

        if (empty($items)) {
            return [];
        }

        $tz        = new \DateTimeZone('America/Vancouver');
        $dateLabel = wp_date('Y-m-d', null, $tz);
        $subject   = sprintf('[Outage Digest] Lousy Outages — %s (Local)', $dateLabel);
        $subject   = (string) apply_filters('lo_daily_digest_subject', $subject, $items);

        $intro = sprintf('Summary of outages and maintenance observed on %s (local time). Each provider is grouped below with the first detected timestamp.', $dateLabel);
        $intro = (string) apply_filters('lo_daily_digest_intro', $intro, $items);

        $heading = sprintf('Outage Digest — %s (Local)', $dateLabel);
        $heading = (string) apply_filters('lo_daily_digest_heading', $heading, $items);

        $signoff_text = (string) apply_filters('lo_daily_digest_signoff', 'Stay vigilant — Lousy Outages', $items);
        $signoff_html = (string) apply_filters('lo_daily_digest_signoff_html', $signoff_text, $items);

        $text_lines = [$heading, $intro, ''];
        $rows_html  = '';
        foreach ($items as $item) {
            $provider = $item['provider'];
            $title    = $item['title'];
            $status   = $item['status'];
            $url      = $item['url'];

            $text_line = sprintf('%s — %s (%s)', $provider, $title, $status);
            if ('' !== $url) {
                $text_line .= ' → ' . $url;
            }
            $text_lines[] = $text_line;

            $rows_html .= sprintf(
                '<tr><td style="padding:14px 0;border-bottom:1px solid rgba(143,128,255,0.25);"><strong style="display:block;font-size:16px;color:#f7f4ff;">%s</strong><span style="display:block;font-size:14px;color:rgba(255,255,255,0.8);margin-top:4px;">%s</span><span style="display:block;font-size:13px;color:#8f80ff;margin-top:6px;">%s</span>%s</td></tr>',
                esc_html($provider),
                esc_html($title),
                esc_html($status),
                $url ? sprintf(' <a href="%s" style="display:inline-block;margin-top:10px;font-size:14px;color:#8f80ff;text-decoration:none;">%s</a>', esc_url($url), esc_html(apply_filters('lo_daily_digest_link_label', 'View incident', $item))) : ''
            );
        }

        $text_lines[] = '';
        $text_lines[] = $signoff_text;
        $text_body = implode("\n", $text_lines);

        ob_start();
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html($heading); ?></title>
        </head>
        <body style="margin:0;background:#070412;color:#f7f4ff;font-family:Segoe UI,Roboto,system-ui,-apple-system,sans-serif;font-size:16px;line-height:1.5;">
            <div style="max-width:680px;margin:0 auto;padding:36px 24px;">
                <div style="border-radius:20px;border:1px solid rgba(143,128,255,0.35);background:linear-gradient(155deg,rgba(18,11,45,0.95),rgba(9,6,28,0.92));box-shadow:0 26px 48px rgba(20,14,46,0.6);padding:32px 28px;">
                    <h1 style="margin:0 0 12px;font-size:26px;letter-spacing:0.04em;color:#d0c6ff;"><?php echo esc_html($heading); ?></h1>
                    <p style="margin:0 0 20px;font-size:16px;line-height:1.5;color:rgba(255,255,255,0.85);"><?php echo esc_html($intro); ?></p>
                    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                        <tbody>
                            <?php echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </tbody>
                    </table>
                    <p style="margin:24px 0 0;font-size:14px;line-height:1.5;color:#c5bfff;"><?php echo esc_html($signoff_html); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        $html_body = trim((string) ob_get_clean());

        return [
            'subject' => $subject,
            'html'    => $html_body,
            'text'    => $text_body,
        ];
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
        $status  = isset($incident_data['status']) ? strtolower(trim((string) $incident_data['status'])) : 'degraded';
        $summary = isset($incident_data['summary']) ? (string) $incident_data['summary'] : '';
        $impact  = isset($incident_data['impact']) ? strtolower((string) $incident_data['impact']) : 'minor';
        $timestamp_raw = isset($incident_data['timestamp']) ? (string) $incident_data['timestamp'] : '';
        $components = isset($incident_data['components']) ? (string) $incident_data['components'] : '';
        $status_url = isset($incident_data['url']) ? (string) $incident_data['url'] : '';
        $extra_notes = isset($incident_data['notes']) ? (string) $incident_data['notes'] : '';

        $service_label = trim($service) ?: 'Service';
        $status_slug   = $status ?: 'degraded';
        $status_map = [
            'degraded'       => 'Degraded',
            'partial_outage' => 'Partial Outage',
            'major_outage'   => 'Major Outage',
            'maintenance'    => 'Maintenance',
            'resolved'       => 'Resolved',
            'operational'    => 'Operational',
        ];
        $status_label  = $status_map[$status_slug] ?? ucfirst(str_replace('_', ' ', $status_slug));
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
        } else {
            $unsubscribe_url = lo_unsubscribe_url_for($email);
        }
        $unsubscribe_raw  = esc_url_raw($unsubscribe_url);
        $unsubscribe_html = esc_url($unsubscribe_url);

        $clean_summary = $summary_text ?: sprintf('%s status changed to %s.', $service_label, $status_label);

        $impact_slug = in_array($impact, ['none', 'minor', 'major', 'critical'], true) ? $impact : 'minor';
        $detected_epoch = strtotime($timestamp_raw) ?: time();
        $resolved_epoch = ('resolved' === $status_slug) ? $detected_epoch : null;

        $incident_object = new \SuzyEaston\LousyOutages\Model\Incident(
            $service_label,
            md5($service_label . '|' . $clean_summary . '|' . $status_slug . '|' . $timestamp_display),
            $clean_summary,
            $status_slug,
            $status_url,
            $components ?: null,
            $impact_slug,
            $detected_epoch,
            $resolved_epoch
        );

        $subject = \SuzyEaston\LousyOutages\Email\Composer::subjectForIncident($incident_object, $status_slug);

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
        <body style="margin:0;background:#050607;color:#FFEB3B;font-family:'Courier New','Lucida Console',monospace;font-size:16px;line-height:1.5;">
            <div style="max-width:720px;margin:0 auto;padding:40px 24px;">
                <div style="border:3px solid #FFEB3B;border-radius:24px;padding:34px 30px;background:linear-gradient(160deg,rgba(7,12,20,0.95),rgba(20,6,40,0.92));box-shadow:0 0 32px rgba(255,235,59,0.35);">
                    <p style="margin:0 0 10px;font-size:13px;letter-spacing:0.2em;text-transform:uppercase;color:#FF1744;">alert: <?php echo esc_html($service_label); ?></p>
                    <h1 style="margin:0 0 14px;font-size:32px;color:#FFEB3B;text-transform:uppercase;letter-spacing:0.05em;"><?php echo esc_html($service_label); ?> status: <?php echo esc_html(strtoupper($status_label)); ?></h1>
                    <p style="margin:0 0 18px;font-size:16px;line-height:1.5;color:#FFF59D;"><?php echo esc_html($clean_summary); ?></p>
                    <dl style="margin:0 0 20px;color:#FFF59D;font-size:14px;line-height:1.5;">
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
                    <?php
                    $follow_line = apply_filters(
                        'lo_incident_follow_line',
                        'We won’t email every update. Follow the status page for the latest, and look for the daily digest tonight.',
                        $incident_data
                    );
                    if (is_string($follow_line) && '' !== trim($follow_line)) :
                    ?>
                        <p style="margin:0 0 20px;font-size:14px;color:#FFE082;">
                            <?php echo esc_html($follow_line); ?>
                        </p>
                    <?php endif; ?>
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

        $sent = \SuzyEaston\LousyOutages\Mailer::send($email, $subject, $text_body, $html_body, $headers);

        if (!$sent) {
            error_log('[lousy_outages] outage_email send failed for ' . $email . ' subject=' . $subject);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Sent Lousy Outages alert to ' . $email . ' at ' . current_time('mysql'));
        }

        return (bool) $sent;
    }
}


<?php
/**
 * Email template helpers for Lousy Outages.
 */

declare(strict_types=1);

if (!function_exists('lo_email_dark_mode_head')) {
    /**
     * Shared head markup so dark-themed emails stay readable in Gmail/Apple Mail dark mode.
     *
     * Clients that auto-invert light text on dark backgrounds need explicit overrides.
     */
    function lo_email_dark_mode_head(string $title): string {
        $title = esc_html($title);
        return <<<HTML
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{$title}</title>
<style type="text/css">
:root { color-scheme: light dark; supported-color-schemes: light dark; }
@media (prefers-color-scheme: dark) {
  .lo-email-body { background-color: #050607 !important; background-image: linear-gradient(#050607, #050607) !important; color: #FFEB3B !important; }
  .lo-email-shell { background-color: #050607 !important; background-image: linear-gradient(#050607, #050607) !important; }
  .lo-email-panel { background-color: #0a0418 !important; background-image: linear-gradient(#0a0418, #140628) !important; }
  .lo-email-panel-purple { background-color: #0d0c1f !important; background-image: linear-gradient(#0d0c1f, #1b1540) !important; }
  .lo-email-panel-digest { background-color: #120b2d !important; background-image: linear-gradient(#120b2d, #09061c) !important; }
  .lo-text-primary, .lo-text-primary * { color: #FFEB3B !important; }
  .lo-text-secondary, .lo-text-secondary * { color: #FFF59D !important; }
  .lo-text-muted, .lo-text-muted * { color: #FFE082 !important; }
  .lo-text-accent, .lo-text-accent * { color: #80D8FF !important; }
  .lo-text-alert, .lo-text-alert * { color: #FF1744 !important; }
  .lo-text-link, .lo-text-link * { color: #FFAB40 !important; }
  .lo-text-body, .lo-text-body * { color: #FFCCBC !important; }
  .lo-text-light, .lo-text-light * { color: #f7f4ff !important; }
  .lo-text-light-muted, .lo-text-light-muted * { color: #d0c6ff !important; }
  .lo-text-light-accent, .lo-text-light-accent * { color: #8f80ff !important; }
  .lo-text-light-soft, .lo-text-light-soft * { color: #c5bfff !important; }
  .lo-text-ops, .lo-text-ops * { color: #f7f8ff !important; }
  .lo-text-ops-muted, .lo-text-ops-muted * { color: rgba(247, 248, 255, 0.88) !important; }
  .lo-email-btn { color: #FFEB3B !important; background-color: #0A0418 !important; border-color: #FF1744 !important; }
  .lo-email-btn-purple { color: #ffffff !important; background-color: #6c5ce7 !important; border-color: rgba(170, 144, 255, 0.9) !important; }
}
</style>
HTML;
    }
}

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
    function lo_send_confirmation(string $email, ?string $confirm_url = null, array $preferences = []): bool {
        $unsubscribe_url = lo_unsubscribe_url_for($email);

        do_action('lousy_outages_log', 'confirm_email_unsub_url', [
            'email' => sanitize_email($email),
            'unsub' => $unsubscribe_url,
        ]);

        return send_lo_confirmation_email($email, $unsubscribe_url, $confirm_url, $preferences);
    }
}

if (!function_exists('send_lo_confirmation_email')) {
    /**
     * Sends the Lousy Outages confirmation email.
     */
    function send_lo_confirmation_email(string $email, string $unsubscribe_url, ?string $confirm_url = null, array $preferences = []): bool {
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


        $prefs = class_exists('SuzyEaston\LousyOutages\Subscriptions')
            ? \SuzyEaston\LousyOutages\Subscriptions::normalize_preferences($preferences)
            : ['providers'=>[], 'realtime_alerts'=>true, 'daily_digest'=>false, 'newsletter'=>false];
        $providerNames = [];
        if (class_exists('SuzyEaston\LousyOutages\Providers')) {
            $providerMap = \SuzyEaston\LousyOutages\Providers::list();
            foreach ((array) ($prefs['providers'] ?? []) as $providerId) {
                $providerId = sanitize_key((string) $providerId);
                if (isset($providerMap[$providerId]['name'])) {
                    $providerNames[] = (string) $providerMap[$providerId]['name'];
                }
            }
        }
        $providerSummary = empty($providerNames) ? 'All monitored providers' : implode(', ', $providerNames);
        $realtimeSummary = !empty($prefs['realtime_alerts']) ? 'on' : 'off';
        $digestSummary = !empty($prefs['daily_digest']) ? 'on' : 'off';

        $subject = '🔔 Please confirm your subscription to Lousy Outages';

        $text_body_lines = [
            'Please confirm your subscription to Lousy Outages.',
            '',
            'Tap the link below to confirm and start receiving outage intel:',
            $confirm_raw,
            '',
            'Dashboard anytime: ' . home_url('/lousy-outages/'),
            '',
            'You’re set for real-time alerts: ' . $realtimeSummary,
            'Daily digest: ' . $digestSummary,
            'Providers: ' . $providerSummary,
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
            <?php echo lo_email_dark_mode_head('Lousy Outages Confirmation'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
            <?php echo lo_email_dark_mode_head($heading); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </head>
        <body class="lo-email-body" style="margin:0;background:#070412;color:#f7f4ff;font-family:Segoe UI,Roboto,system-ui,-apple-system,sans-serif;font-size:16px;line-height:1.5;">
            <div class="lo-email-shell" style="max-width:680px;margin:0 auto;padding:36px 24px;">
                <div class="lo-email-panel-digest" style="border-radius:20px;border:1px solid rgba(143,128,255,0.35);background:linear-gradient(155deg,rgba(18,11,45,0.95),rgba(9,6,28,0.92));box-shadow:0 26px 48px rgba(20,14,46,0.6);padding:32px 28px;">
                    <h1 class="lo-text-light-muted" style="margin:0 0 12px;font-size:26px;letter-spacing:0.04em;color:#d0c6ff;"><?php echo esc_html($heading); ?></h1>
                    <p class="lo-text-light-soft" style="margin:0 0 20px;font-size:16px;line-height:1.5;color:rgba(255,255,255,0.85);"><?php echo esc_html($intro); ?></p>
                    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                        <tbody>
                            <?php echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </tbody>
                    </table>
                    <p class="lo-text-light-soft" style="margin:24px 0 0;font-size:14px;line-height:1.5;color:#c5bfff;"><?php echo esc_html($signoff_html); ?></p>
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
            <?php echo lo_email_dark_mode_head('Lousy Outages Alert'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </head>
        <body class="lo-email-body" style="margin:0;background:#050607;color:#FFEB3B;font-family:'Courier New','Lucida Console',monospace;font-size:16px;line-height:1.5;">
            <div class="lo-email-shell" style="max-width:720px;margin:0 auto;padding:40px 24px;">
                <div class="lo-email-panel" style="border:3px solid #FFEB3B;border-radius:24px;padding:34px 30px;background:linear-gradient(160deg,rgba(7,12,20,0.95),rgba(20,6,40,0.92));box-shadow:0 0 32px rgba(255,235,59,0.35);">
                    <p class="lo-text-alert" style="margin:0 0 10px;font-size:13px;letter-spacing:0.2em;text-transform:uppercase;color:#FF1744;">alert: <?php echo esc_html($service_label); ?></p>
                    <h1 class="lo-text-primary" style="margin:0 0 14px;font-size:32px;color:#FFEB3B;text-transform:uppercase;letter-spacing:0.05em;"><?php echo esc_html($service_label); ?> status: <?php echo esc_html(strtoupper($status_label)); ?></h1>
                    <p class="lo-text-secondary" style="margin:0 0 18px;font-size:16px;line-height:1.5;color:#FFF59D;"><?php echo esc_html($clean_summary); ?></p>
                    <dl class="lo-text-secondary" style="margin:0 0 20px;color:#FFF59D;font-size:14px;line-height:1.5;">
                        <div style="display:flex;flex-wrap:wrap;gap:6px 16px;margin-bottom:10px;">
                            <dt class="lo-text-accent" style="width:120px;text-transform:uppercase;letter-spacing:0.08em;color:#80D8FF;">Status</dt>
                            <dd class="lo-text-secondary" style="margin:0;font-weight:700;"><?php echo esc_html($status_label); ?></dd>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px 16px;margin-bottom:10px;">
                            <dt class="lo-text-accent" style="width:120px;text-transform:uppercase;letter-spacing:0.08em;color:#80D8FF;">Components</dt>
                            <dd class="lo-text-secondary" style="margin:0;"><?php echo esc_html($component_line); ?></dd>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px 16px;margin-bottom:10px;">
                            <dt class="lo-text-accent" style="width:120px;text-transform:uppercase;letter-spacing:0.08em;color:#80D8FF;">Detected</dt>
                            <dd class="lo-text-secondary" style="margin:0;"><?php echo esc_html($timestamp_display); ?></dd>
                        </div>
                    </dl>
                    <p class="lo-text-body" style="margin:0 0 18px;font-size:14px;color:#FFCCBC;">Keep eyes on the incident console:</p>
                    <p style="margin:0 0 24px;">
                        <a class="lo-email-btn" href="<?php echo esc_url($status_url_html); ?>" style="display:inline-block;padding:16px 28px;border-radius:999px;border:2px solid #FF1744;background:#0A0418;color:#FFEB3B;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:0.14em;">View live status feed</a>
                    </p>
                    <?php
                    $follow_line = apply_filters(
                        'lo_incident_follow_line',
                        'We won’t email every update. Follow the status page for the latest, and look for the daily digest tonight.',
                        $incident_data
                    );
                    if (is_string($follow_line) && '' !== trim($follow_line)) :
                    ?>
                        <p class="lo-text-muted" style="margin:0 0 20px;font-size:14px;color:#FFE082;">
                            <?php echo esc_html($follow_line); ?>
                        </p>
                    <?php endif; ?>
                    <p class="lo-text-secondary" style="margin:0 0 14px;font-size:13px;color:#FFF59D;">Backup link:<br><span class="lo-text-link" style="color:#FFAB40;"><?php echo esc_html($status_url_raw); ?></span></p>
                    <?php if ($extra_notes) : ?>
                        <p class="lo-text-body" style="margin:0 0 28px;font-size:13px;color:#FFCDD2;"><?php echo esc_html($extra_notes); ?></p>
                    <?php endif; ?>
                    <div class="lo-text-primary" style="margin:26px 0;padding:18px;border:1px dashed rgba(255,235,59,0.7);border-radius:16px;background:rgba(29,8,48,0.8);color:#FFEB3B;font-size:13px;">
                        <strong class="lo-text-alert" style="display:block;font-size:11px;letter-spacing:0.18em;color:#FF1744;margin-bottom:6px;text-transform:uppercase;">Need to bail?</strong>
                        <span class="lo-text-secondary">Unsubscribe instantly:</span> <a class="lo-text-link" style="color:#FFAB40;text-decoration:none;" href="<?php echo esc_url($unsubscribe_html); ?>"><?php echo esc_html($unsubscribe_raw); ?></a>
                    </div>
                    <p class="lo-text-muted" style="margin:0;font-size:12px;color:rgba(255,235,59,0.75);">Stay patched &mdash; Lousy Outages monitoring team.</p>
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

if (!function_exists('send_lo_burst_alert_email')) {
    /**
     * Sends one combined alert when multiple provider episodes queue up at once.
     *
     * @param array<int,array<string,mixed>> $episodes
     */
    function send_lo_burst_alert_email(string $email, array $episodes): bool {
        $email = sanitize_email($email);
        if (!$email || !is_email($email) || empty($episodes)) {
            return false;
        }

        $status_map = [
            'degraded'       => 'Degraded',
            'partial_outage' => 'Partial Outage',
            'major_outage'   => 'Major Outage',
            'maintenance'    => 'Maintenance',
            'resolved'       => 'Resolved',
            'operational'    => 'Operational',
        ];

        $items = [];
        foreach ($episodes as $episode) {
            if (!is_array($episode)) {
                continue;
            }
            $provider = trim((string) ($episode['provider_id'] ?? $episode['provider'] ?? ''));
            $title    = trim((string) ($episode['title'] ?? ''));
            $status   = strtolower(trim((string) ($episode['status'] ?? 'degraded')));
            $url      = trim((string) ($episode['url'] ?? ''));
            if ('' === $provider) {
                continue;
            }
            $status_label = $status_map[$status] ?? ucfirst(str_replace('_', ' ', $status));
            $detected = (int) ($episode['first_detected'] ?? 0);
            $detected_label = $detected > 0 ? wp_date('M j, Y g:i A T', $detected) : wp_date('M j, Y g:i A T');
            if ('' === $url) {
                $url = home_url('/lousy-outages/');
            }
            $items[] = [
                'provider' => $provider,
                'title'    => $title ?: ($provider . ' status update'),
                'status'   => $status_label,
                'detected' => $detected_label,
                'url'      => esc_url_raw($url),
            ];
        }

        if (empty($items)) {
            return false;
        }

        $count = count($items);
        $subject = sprintf('[Outage Alert] %d providers flagged at once', $count);
        $subject = (string) apply_filters('lo_burst_alert_subject', $subject, $items, $email);

        $unsubscribe_url = lo_unsubscribe_url_for($email);
        $unsubscribe_raw  = esc_url_raw($unsubscribe_url);
        $unsubscribe_html = esc_url($unsubscribe_url);
        $dashboard_url    = esc_url(home_url('/lousy-outages/'));

        $text_lines = [
            sprintf('Multiple outage alerts (%d providers)', $count),
            'Several providers flipped at once. One email instead of a pile.',
            '',
        ];
        foreach ($items as $item) {
            $text_lines[] = sprintf('%s — %s (%s) — detected %s', $item['provider'], $item['title'], $item['status'], $item['detected']);
            $text_lines[] = $item['url'];
            $text_lines[] = '';
        }
        $text_lines[] = 'Live board: ' . esc_url_raw(home_url('/lousy-outages/'));
        $text_lines[] = 'Unsubscribe: ' . $unsubscribe_raw;
        $text_body = implode("\n", $text_lines);

        $rows_html = '';
        foreach ($items as $item) {
            $rows_html .= sprintf(
                '<tr><td style="padding:14px 0;border-bottom:1px solid rgba(255,235,59,0.25);"><strong class="lo-text-primary" style="display:block;font-size:16px;color:#FFEB3B;">%s</strong><span class="lo-text-secondary" style="display:block;font-size:14px;color:#FFF59D;margin-top:4px;">%s</span><span class="lo-text-accent" style="display:block;font-size:13px;color:#80D8FF;margin-top:6px;">%s · detected %s</span><a class="lo-text-link" href="%s" style="display:inline-block;margin-top:10px;font-size:13px;color:#FFAB40;text-decoration:none;">View status</a></td></tr>',
                esc_html($item['provider']),
                esc_html($item['title']),
                esc_html($item['status']),
                esc_html($item['detected']),
                esc_url($item['url'])
            );
        }

        ob_start();
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <?php echo lo_email_dark_mode_head('Lousy Outages Alert'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </head>
        <body class="lo-email-body" style="margin:0;background:#050607;color:#FFEB3B;font-family:'Courier New','Lucida Console',monospace;font-size:16px;line-height:1.5;">
            <div class="lo-email-shell" style="max-width:720px;margin:0 auto;padding:40px 24px;">
                <div class="lo-email-panel" style="border:3px solid #FFEB3B;border-radius:24px;padding:34px 30px;background:linear-gradient(160deg,rgba(7,12,20,0.95),rgba(20,6,40,0.92));box-shadow:0 0 32px rgba(255,235,59,0.35);">
                    <p class="lo-text-alert" style="margin:0 0 10px;font-size:13px;letter-spacing:0.2em;text-transform:uppercase;color:#FF1744;">multi-provider alert</p>
                    <h1 class="lo-text-primary" style="margin:0 0 14px;font-size:28px;color:#FFEB3B;text-transform:uppercase;letter-spacing:0.05em;"><?php echo esc_html((string) $count); ?> providers flagged</h1>
                    <p class="lo-text-secondary" style="margin:0 0 18px;font-size:16px;line-height:1.5;color:#FFF59D;">Several services flipped around the same time. One ping instead of a pile in your inbox.</p>
                    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                        <tbody>
                            <?php echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </tbody>
                    </table>
                    <p class="lo-text-body" style="margin:20px 0 18px;font-size:14px;color:#FFCCBC;">Keep eyes on the live board:</p>
                    <p style="margin:0 0 24px;">
                        <a class="lo-email-btn" href="<?php echo $dashboard_url; ?>" style="display:inline-block;padding:16px 28px;border-radius:999px;border:2px solid #FF1744;background:#0A0418;color:#FFEB3B;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:0.14em;">Open Lousy Outages</a>
                    </p>
                    <p class="lo-text-muted" style="margin:0 0 20px;font-size:14px;color:#FFE082;">We won't email every update. Follow each status page for play-by-play.</p>
                    <div class="lo-text-primary" style="margin:26px 0;padding:18px;border:1px dashed rgba(255,235,59,0.7);border-radius:16px;background:rgba(29,8,48,0.8);color:#FFEB3B;font-size:13px;">
                        <strong class="lo-text-alert" style="display:block;font-size:11px;letter-spacing:0.18em;color:#FF1744;margin-bottom:6px;text-transform:uppercase;">Need to bail?</strong>
                        <span class="lo-text-secondary">Unsubscribe instantly:</span> <a class="lo-text-link" style="color:#FFAB40;text-decoration:none;" href="<?php echo esc_url($unsubscribe_html); ?>"><?php echo esc_html($unsubscribe_raw); ?></a>
                    </div>
                    <p class="lo-text-muted" style="margin:0;font-size:12px;color:rgba(255,235,59,0.75);">Stay patched &mdash; Lousy Outages monitoring team.</p>
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
            error_log('[lousy_outages] burst_outage_email send failed for ' . $email . ' subject=' . $subject);
        }

        return (bool) $sent;
    }
}


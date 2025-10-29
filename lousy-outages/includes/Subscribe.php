<?php
declare(strict_types=1);

use LousyOutages\IncidentAlerts;
use LousyOutages\Mailer;
use LousyOutages\Subscriptions;

class Lousy_Outages_Subscribe {
    public static function bootstrap(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route('lousy-outages/v1', '/subscribe', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'args'                => [
                'email' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => 'is_email',
                ],
            ],
            'callback'            => 'lo_handle_subscribe',
        ]);

        register_rest_route('lousy-outages/v1', '/confirm', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'confirm'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('lousy-outages/v1', '/unsubscribe', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'unsubscribe'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function confirm(\WP_REST_Request $request) {
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ('' === $token) {
            return self::redirect_with_status('invalid');
        }

        $record = Subscriptions::find_by_token($token);
        if (!$record) {
            return self::redirect_with_status('invalid');
        }

        $status = strtolower((string) ($record['status'] ?? ''));
        $email  = isset($record['email']) ? sanitize_email((string) $record['email']) : '';

        if (Subscriptions::STATUS_SUBSCRIBED === $status) {
            if ($email && is_email($email)) {
                self::send_already_subscribed_email($email, $token);
            }
            return self::redirect_with_status('confirmed');
        }

        if (!self::mark_confirmed($token)) {
            return self::redirect_with_status('invalid');
        }

        if ($email && is_email($email)) {
            IncidentAlerts::add_subscriber($email);
            self::send_welcome_email($email, $token);
        }

        return self::redirect_with_status('confirmed');
    }

    public static function unsubscribe(\WP_REST_Request $request) {
        $token = sanitize_text_field((string) $request->get_param('token'));
        if ('' === $token) {
            return self::redirect_with_status('invalid');
        }

        $record = Subscriptions::find_by_token($token);
        if (!$record) {
            return self::redirect_with_status('invalid');
        }

        if (!self::mark_unsubscribed($token)) {
            return self::redirect_with_status('invalid');
        }

        $email = isset($record['email']) ? sanitize_email((string) $record['email']) : '';
        if ($email && is_email($email)) {
            IncidentAlerts::remove_subscriber($email);
            self::send_unsubscribed_email($email);
        }

        return self::redirect_with_status('unsubscribed');
    }

    public static function send_confirm_email(string $email, string $token): void {
        $email = sanitize_email($email);
        if (!$email || !is_email($email)) {
            return;
        }

        $confirm_url     = add_query_arg('token', rawurlencode($token), rest_url('lousy-outages/v1/confirm'));
        $unsubscribe_url = add_query_arg('token', rawurlencode($token), rest_url('lousy-outages/v1/unsubscribe'));

        $subject = '👾 jack in to Lousy Outages (confirm your email)';

        $text_body = <<<TEXT
hey there, console cowboy ⚡

you (or a very stylish bot) asked to receive Lousy Outages alerts.
click to confirm your human-ness and join the swarm:

CONFIRM ▶ {$confirm_url}

if you didn't request this, chill—no packets were harmed.
ignore this or nuke it here: {$unsubscribe_url}

— lousy outages • inspired by wrangling third-party providers in IT. built by a bassist-turned-builder
TEXT;

        $html_body = <<<HTML
<!doctype html>
<meta charset="utf-8">
<body style="font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Arial; background:#0b0b0b; color:#e6ffe6;">
  <div style="max-width:560px;margin:24px auto;padding:16px;border:2px solid #39ff14;border-radius:12px;">
    <h2 style="margin:0 0 8px;font-size:20px;">👾 jack in to Lousy Outages</h2>
    <p>you (or a very stylish bot) asked to receive outage alerts.</p>
    <p><a href="{$confirm_url}" style="display:inline-block;padding:10px 14px;border:2px solid #39ff14;border-radius:10px;text-decoration:none;color:#39ff14;">CONFIRM SUBSCRIPTION</a></p>
    <p style="opacity:.8">not you? <a href="{$unsubscribe_url}" style="color:#39ff14;">unsubscribe</a> or just ignore this.</p>
    <hr style="border:none;border-top:1px dashed #39ff14;opacity:.4">
    <p style="font-size:12px;opacity:.75;">lousy outages — inspired by wrangling third-party providers in IT.</p>
  </div>
</body>
HTML;

        Mailer::send($email, $subject, $text_body, $html_body);
    }

    private static function mark_confirmed(string $token): bool {
        $record = Subscriptions::find_by_token($token);
        if (!$record) {
            return false;
        }

        $status  = strtolower((string) ($record['status'] ?? ''));
        $created = isset($record['created_at']) ? strtotime((string) $record['created_at']) : false;
        $cutoff  = time() - 14 * DAY_IN_SECONDS;

        if (Subscriptions::STATUS_PENDING === $status) {
            if (!$created || $created < $cutoff) {
                Subscriptions::update_status_by_token($token, Subscriptions::STATUS_UNSUBSCRIBED);
                return false;
            }

            return Subscriptions::update_status_by_token($token, Subscriptions::STATUS_SUBSCRIBED);
        }

        if (Subscriptions::STATUS_SUBSCRIBED === $status) {
            return true;
        }

        return false;
    }

    private static function mark_unsubscribed(string $token): bool {
        $record = Subscriptions::find_by_token($token);
        if (!$record) {
            return false;
        }

        if (Subscriptions::STATUS_UNSUBSCRIBED === strtolower((string) ($record['status'] ?? ''))) {
            return true;
        }

        return Subscriptions::update_status_by_token($token, Subscriptions::STATUS_UNSUBSCRIBED);
    }

    private static function redirect_with_status(string $status) {
        $status = sanitize_key($status);
        if ($status) {
            self::store_flash_cookie($status);
        }
        return self::redirect('/lousy-outages/?sub=' . rawurlencode($status ?: '')); 
    }

    private static function store_flash_cookie(string $status): void {
        if (headers_sent()) {
            return;
        }

        $value = sanitize_key($status);
        if ('' === $value) {
            return;
        }

        $params = [
            'expires'  => time() + 300,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ];

        setcookie('lo_sub_msg', $value, $params);
    }

    private static function redirect(string $path) {
        status_header(302);
        header('Location: ' . home_url($path));
        exit;
    }

    private static function send_welcome_email(string $email, string $token): void {
        $unsubscribe_url = add_query_arg('token', rawurlencode($token), rest_url('lousy-outages/v1/unsubscribe'));
        $dashboard_url   = home_url('/lousy-outages/');

        $subject = '✅ link established: you\'re on the outage radar';

        $text_body = <<<TEXT
confirmed. welcome to the status underground. 🛰️

you'll get tidy alerts when third-party providers wobble:
cloudflare • aws • azure • gcp • stripe • pagerduty • zscaler (and friends)

pro tip: bookmark the live dashboard:
{$dashboard_url}

done with the fun? one-click escape hatch:
{$unsubscribe_url}

"This is what it feels like to be hunted by something smarter than you."
— Grimes, Artificial Angels
TEXT;

        $html_body = <<<HTML
<!doctype html>
<meta charset="utf-8">
<body style="font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Arial; background:#0b0b0b; color:#e6ffe6;">
  <div style="max-width:560px;margin:24px auto;padding:16px;border:2px solid #39ff14;border-radius:12px;">
    <h2 style="margin:0 0 8px;font-size:20px;">✅ link established</h2>
    <p>welcome to the status underground.</p>
    <p>you'll get alerts when providers wobble: cloudflare • aws • azure • gcp • stripe • pagerduty • zscaler.</p>
    <p><a href="{$dashboard_url}" style="display:inline-block;padding:10px 14px;border:2px solid #39ff14;border-radius:10px;text-decoration:none;color:#39ff14;">OPEN LIVE DASHBOARD</a></p>
    <blockquote style="margin:12px 0;padding:8px 12px;border-left:3px solid #39ff14;opacity:.85;">
      “This is what it feels like to be hunted by something smarter than you.”<br>
      — Grimes, <a href="https://www.youtube.com/watch?v=tvGnYM14-1A" style="color:#39ff14;">Artificial Angels</a>
    </blockquote>
    <p style="font-size:12px;opacity:.75;">unsubscribe anytime: <a href="{$unsubscribe_url}" style="color:#39ff14;">{$unsubscribe_url}</a></p>
  </div>
</body>
HTML;

        Mailer::send($email, $subject, $text_body, $html_body);
    }

    private static function send_already_subscribed_email(string $email, string $token): void {
        $dashboard_url   = home_url('/lousy-outages/');
        $unsubscribe_url = add_query_arg('token', rawurlencode($token), rest_url('lousy-outages/v1/unsubscribe'));

        $subject = 'you\'re already on the radar (all good)';

        $text_body = <<<TEXT
no double-hacking needed—your email is already subscribed.
dashboard: {$dashboard_url}
unsubscribe: {$unsubscribe_url}
TEXT;

        $html_body = <<<HTML
<!doctype html>
<meta charset="utf-8">
<body style="font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Arial; background:#0b0b0b; color:#e6ffe6;">
  <div style="max-width:560px;margin:24px auto;padding:16px;border:2px solid #39ff14;border-radius:12px;">
    <h2 style="margin:0 0 8px;font-size:20px;">already connected</h2>
    <p>no double-hacking needed—your email is already subscribed.</p>
    <p><a href="{$dashboard_url}" style="color:#39ff14;">open the dashboard</a> • <a href="{$unsubscribe_url}" style="color:#39ff14;">unsubscribe</a></p>
  </div>
</body>
HTML;

        Mailer::send($email, $subject, $text_body, $html_body);
    }

    private static function send_unsubscribed_email(string $email): void {
        $resubscribe_url = home_url('/lousy-outages/?sub=check-email');

        $subject = '🧹 wire cut. you\'re unsubscribed.';

        $text_body = <<<TEXT
you've been cleanly removed from Lousy Outages alerts.

if this was a mistake, jack back in here:
{$resubscribe_url}  (or just re-enter your email on the site)

until next breach window—stay weird, stay patched.
TEXT;

        $html_body = <<<HTML
<!doctype html>
<meta charset="utf-8">
<body style="font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Arial; background:#0b0b0b; color:#e6ffe6;">
  <div style="max-width:560px;margin:24px auto;padding:16px;border:2px solid #39ff14;border-radius:12px;">
    <h2 style="margin:0 0 8px;font-size:20px;">🧹 wire cut</h2>
    <p>you're unsubscribed and off the grid.</p>
    <p style="opacity:.85">if that was accidental, <a href="{$resubscribe_url}" style="color:#39ff14;">re-subscribe</a> any time.</p>
    <p style="font-size:12px;opacity:.7;">built in vancouver with coffee, riffs, and command-line confidence.</p>
  </div>
</body>
HTML;

        Mailer::send($email, $subject, $text_body, $html_body);
    }
}

function lo_handle_subscribe(\WP_REST_Request $request) {
    $emailParam = $request->get_param('email');
    $email      = is_string($emailParam) ? sanitize_email($emailParam) : '';
    if (!$email || !is_email($email)) {
        return new \WP_Error(
            'invalid_email',
            'Please provide a valid email address.',
            [
                'status' => 400,
                'ok'     => false,
            ]
        );
    }

    $email = strtolower($email);
    $subscribers = get_option('suzy_newsletter', []);
    if (!is_array($subscribers)) {
        $subscribers = [];
    }
    if (!in_array($email, $subscribers, true)) {
        $subscribers[] = $email;
        update_option('suzy_newsletter', array_values(array_unique($subscribers)), false);
    }

    $domain = wp_parse_url(home_url(), PHP_URL_HOST);
    if (!is_string($domain) || '' === $domain) {
        $domain = (string) parse_url(site_url('/'), PHP_URL_HOST);
    }
    if ('' === $domain) {
        $domain = 'example.com';
    }
    $domain  = strtolower($domain);
    $address = sprintf('no-reply@%s', $domain);

    $headers = [
        sprintf('From: <%s>', $address),
        sprintf('Reply-To: <%s>', $address),
        'Content-Type: text/html; charset=UTF-8',
    ];

    $transport    = getenv('SMTP_HOST') ? 'smtp' : 'mail';
    $sendmailPath = ini_get('sendmail_path') ?: '';
    $debugParam   = $request->get_param('deliverability');
    if (is_string($debugParam) && 'debug' === strtolower($debugParam)) {
        return rest_ensure_response([
            'transport'     => $transport,
            'headers'       => $headers,
            'domain'        => $domain,
            'sendmail_path' => $sendmailPath,
        ]);
    }

    $subject  = 'you’re in — suzy easton updates';
    $bodyHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>you’re in — suzy easton updates</title>
</head>
<body style="margin:0;padding:0;background-color:#050505;color:#00ff9d;font-family:'Press Start 2P','Courier New',monospace;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#050505;">
    <tr>
      <td align="center" style="padding:40px 10px;">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background-color:#111111;border:3px solid #00ff9d;box-shadow:0 0 25px rgba(0,255,157,0.35);">
          <tr>
            <td style="padding:30px 30px 20px;text-align:center;background-color:#0b0b0b;border-bottom:3px solid #e60073;">
              <p style="margin:0;font-size:12px;letter-spacing:4px;color:#f5fff8;text-transform:uppercase;">Neon Transmission</p>
              <h1 style="margin:16px 0 0;font-size:20px;letter-spacing:3px;color:#00ff9d;text-transform:uppercase;">you’re in</h1>
              <p style="margin:12px 0 0;font-size:10px;color:#ffffff;opacity:0.8;letter-spacing:2px;text-transform:uppercase;">suzy easton updates</p>
            </td>
          </tr>
          <tr>
            <td style="padding:30px;background-color:#111111;">
              <p style="margin:0 0 18px;font-size:12px;line-height:1.6;color:#00ff9d;">
                Hey legend — thanks for stepping into the <span style="color:#e60073;">retro signal</span>.
              </p>
              <p style="margin:0 0 18px;font-size:12px;line-height:1.6;color:#c1ffe2;">
                Your inbox is now cleared for dispatches about new tracks, live shows, midnight mixes, and whatever neon trouble I’m coding next.
              </p>
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:30px auto;">
                <tr>
                  <td align="center" style="background-color:#e60073;border:2px solid #00ff9d;padding:14px 28px;">
                    <a href="https://www.suzyeaston.ca" style="display:inline-block;text-decoration:none;font-size:12px;letter-spacing:2px;color:#0b0b0b;font-weight:bold;text-transform:uppercase;">Visit the Website</a>
                  </td>
                </tr>
              </table>
              <p style="margin:0 0 18px;font-size:11px;line-height:1.6;color:#9efff3;">
                Want the latest now? Dive into the arcade, spin the midnight mixes, or meet the Canucks app that started it all.
              </p>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:20px;">
                <tr>
                  <td style="padding:12px;border:1px solid #1f1f1f;background-color:#0b0b0b;color:#00ff9d;font-size:10px;text-transform:uppercase;letter-spacing:2px;">Signal Boosts</td>
                  <td style="padding:12px;border:1px solid #1f1f1f;background-color:#161616;color:#c1ffe2;font-size:10px;">
                    <a href="https://www.suzyeaston.ca/page-music-releases.php" style="color:#c1ffe2;text-decoration:none;">New Releases</a> •
                    <a href="https://www.suzyeaston.ca/page-podcast.php" style="color:#c1ffe2;text-decoration:none;">Podcasts</a> •
                    <a href="https://www.suzyeaston.ca/page-arcade.php" style="color:#c1ffe2;text-decoration:none;">Arcade</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:24px 30px 36px;background-color:#0b0b0b;border-top:3px solid #00ff9d;text-align:center;">
              <p style="margin:0 0 12px;font-size:10px;color:#8afff7;letter-spacing:2px;text-transform:uppercase;">Stay neon. Stay loud.</p>
              <p style="margin:0;font-size:9px;color:#586d6d;letter-spacing:1px;">If you ever need to bail, slam the unsubscribe link in any email. No hard feelings.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    $ok_user = wp_mail($email, $subject, $bodyHtml, $headers);

    $admin_email = get_option('admin_email');
    $ok_admin    = false;
    if ($admin_email && is_email($admin_email)) {
        $ok_admin = wp_mail($admin_email, 'new subscriber', esc_html($email), $headers);
    }

    error_log(sprintf('subscribe email=%s ok_user=%d ok_admin=%d', $email, $ok_user ? 1 : 0, $ok_admin ? 1 : 0));

    if (!$ok_user) {
        return new \WP_Error(
            'send_fail',
            'Mail send failed — check SMTP or server mailer',
            [
                'status'     => 500,
                'ok'         => false,
                'transport'  => $transport,
                'ok_admin'   => (bool) $ok_admin,
                'sendmail'   => $sendmailPath,
            ]
        );
    }

    return rest_ensure_response(['ok' => true]);
}

Lousy_Outages_Subscribe::bootstrap();

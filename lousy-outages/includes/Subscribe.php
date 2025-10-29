<?php
declare(strict_types=1);

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
    $bodyHtml = '<p>thanks for subscribing</p>';

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

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
<body style="font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Arial; background:#050505; color:#ffe9c4;">
  <div style="max-width:560px;margin:24px auto;padding:20px;border:2px solid #ffb81c;border-radius:14px;background:linear-gradient(140deg,#0b0b0b,#1a0b00);box-shadow:0 18px 36px rgba(0,0,0,0.45);">
    <h2 style="margin:0 0 8px;font-size:20px;color:#ffb81c;letter-spacing:0.05em;text-transform:uppercase;">👾 jack in to Lousy Outages</h2>
    <p style="margin:0 0 16px;">you (or a very stylish bot) asked to receive outage alerts.</p>
    <p style="margin:0 0 18px;"><a href="{$confirm_url}" style="display:inline-block;padding:12px 18px;border-radius:999px;border:2px solid #ffb81c;background:#f04e23;color:#050505;font-weight:700;text-decoration:none;">CONFIRM SUBSCRIPTION</a></p>
    <p style="margin:0 0 12px;opacity:.85;">not you? <a href="{$unsubscribe_url}" style="color:#ffb81c;">unsubscribe here</a> or just ignore this.</p>
    <hr style="border:none;border-top:1px dashed rgba(255,184,28,0.5);margin:18px 0;">
    <p style="margin:0;font-size:12px;opacity:.75;">lousy outages — inspired by wrangling third-party providers in IT.</p>
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
confirmed. you're now getting the Lousy Outages briefings. 🛰️

expect:
- outage and degradation alerts for cloudflare, aws, azure, gcp, stripe, pagerduty, zscaler (and their friends)
- mini postmortems with timelines + impact notes
- security watch items so you can harden things before the next wobble

open the live dashboard any time:
{$dashboard_url}

need out? one-click escape hatch:
{$unsubscribe_url}

"This is what it feels like to be hunted by something smarter than you."
— Grimes, Artificial Angels
 
 ps: make sure the alerts land — add suzyeaston.ca to your safe senders and peek at spam if nothing shows up.
TEXT;

        $html_body = <<<HTML
<!doctype html>
<meta charset="utf-8">
<body style="font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Arial; background:#050505; color:#ffe9c4;">
  <div style="max-width:560px;margin:24px auto;padding:20px;border:2px solid #ffb81c;border-radius:14px;background:linear-gradient(140deg,#0b0b0b,#1a0b00);box-shadow:0 18px 36px rgba(0,0,0,0.45);">
    <h2 style="margin:0 0 10px;font-size:20px;color:#ffb81c;text-transform:uppercase;letter-spacing:0.05em;">✅ link established</h2>
    <p style="margin:0 0 12px;">welcome to the status underground.</p>
    <p style="margin:0 0 18px;">you'll get alerts when providers wobble: cloudflare • aws • azure • gcp • stripe • pagerduty • zscaler.</p>
    <p style="margin:0 0 18px;"><a href="{$dashboard_url}" style="display:inline-block;padding:12px 18px;border-radius:999px;border:2px solid #ffb81c;background:#f04e23;color:#050505;font-weight:700;text-decoration:none;">OPEN LIVE DASHBOARD</a></p>
    <blockquote style="margin:12px 0;padding:10px 14px;border-left:3px solid rgba(255,184,28,0.8);background:rgba(255,184,28,0.08);">
      “This is what it feels like to be hunted by something smarter than you.”<br>
      — Grimes, <a href="https://www.youtube.com/watch?v=tvGnYM14-1A" style="color:#ffb81c;">Artificial Angels</a>
    </blockquote>
    <p style="margin:14px 0 0;font-size:13px;opacity:.85;">unsubscribe anytime: <a href="{$unsubscribe_url}" style="color:#ffb81c;">{$unsubscribe_url}</a></p>
    <p style="margin:10px 0 0;font-size:12px;opacity:.85;">pro tip: add suzyeaston.ca to your safe senders so outage intel doesn’t get iced in spam.</p>
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
<body style="font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Arial; background:#050505; color:#ffe9c4;">
  <div style="max-width:560px;margin:24px auto;padding:20px;border:2px solid #ffb81c;border-radius:14px;background:linear-gradient(140deg,#0b0b0b,#1a0b00);box-shadow:0 18px 36px rgba(0,0,0,0.45);">
    <h2 style="margin:0 0 8px;font-size:20px;color:#ffb81c;text-transform:uppercase;letter-spacing:0.05em;">already connected</h2>
    <p style="margin:0 0 14px;">no double-hacking needed—your email is already subscribed.</p>
    <p style="margin:0;font-weight:600;"><a href="{$dashboard_url}" style="color:#ffb81c;">open the dashboard</a> • <a href="{$unsubscribe_url}" style="color:#f04e23;">unsubscribe</a></p>
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
<body style="font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Arial; background:#050505; color:#ffe9c4;">
  <div style="max-width:560px;margin:24px auto;padding:20px;border:2px solid #ffb81c;border-radius:14px;background:linear-gradient(140deg,#0b0b0b,#1a0b00);box-shadow:0 18px 36px rgba(0,0,0,0.45);">
    <h2 style="margin:0 0 10px;font-size:20px;color:#ffb81c;text-transform:uppercase;letter-spacing:0.05em;">🧹 wire cut</h2>
    <p style="margin:0 0 12px;">you're unsubscribed and off the grid.</p>
    <p style="margin:0 0 10px;opacity:.85;">if that was accidental, <a href="{$resubscribe_url}" style="color:#f04e23;">re-subscribe</a> any time.</p>
    <p style="margin:0;font-size:12px;opacity:.7;">built in vancouver with coffee, riffs, and command-line confidence.</p>
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

    $subject  = 'Lousy Outages — you’re on the radar';
    $bodyHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Lousy Outages — welcome aboard</title>
</head>
<body style="margin:0;padding:32px;background-color:#0b0b0b;color:#ffe9c4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background-color:#130700;border-radius:14px;border:1px solid rgba(255,184,28,0.35);box-shadow:0 18px 42px rgba(0,0,0,0.45);overflow:hidden;">
          <tr>
            <td style="padding:32px 32px 18px;background:linear-gradient(140deg,#050505,#201000);color:#ffe9c4;text-align:left;">
              <p style="margin:0 0 6px;font-size:12px;letter-spacing:3px;text-transform:uppercase;opacity:0.85;">suzyeaston.ca/lousy-outages</p>
              <h1 style="margin:0;font-size:26px;line-height:1.3;">Welcome to <span style="color:#ffb81c;">Lousy Outages</span></h1>
              <p style="margin:12px 0 0;font-size:15px;line-height:1.5;max-width:460px;">You’re locked in for concise rundowns on third-party provider incidents, mitigation tactics, and security quirks that could sideswipe your stack.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 32px 14px;background-color:#0b0b0b;">
              <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#ffe9c4;">Here’s what to expect in your inbox:</p>
              <ul style="margin:0 0 20px;padding-left:20px;font-size:14px;line-height:1.7;color:#fddca6;">
                <li>Signal boosts on the outages and degradations hitting providers like Cloudflare, AWS, Azure, Stripe, and more.</li>
                <li>Quick-hit postmortems with timelines, affected components, and lessons learned.</li>
                <li>Security watch notes so you can harden your own services before things go sideways.</li>
              </ul>
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 28px;">
                <tr>
                  <td style="border-radius:999px;background:#f04e23;">
                    <a href="https://www.suzyeaston.ca/lousy-outages/" style="display:inline-block;padding:14px 26px;font-size:14px;font-weight:600;color:#050505;text-decoration:none;border-radius:999px;border:2px solid #ffb81c;">View the latest briefing</a>
                  </td>
                </tr>
              </table>
              <p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#fddca6;">Need to tune your alerts? Hop into the dashboard above for live incident tracking, archives, and more utilities from the status bunker.</p>
              <p style="margin:0;font-size:13px;line-height:1.6;color:#fddca6;">Pro tip: add suzyeaston.ca to your safe-sender list so alerts don’t get trapped in spam.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 32px;background-color:#140a00;border-top:1px solid rgba(255,184,28,0.35);font-size:12px;line-height:1.6;color:#fddca6;">
              <p style="margin:0 0 8px;">If this wasn’t you, or you’re done hearing about outage chaos, you can <a href="{$unsubscribe_url}" style="color:#ffb81c;font-weight:600;text-decoration:none;">unsubscribe instantly</a>.</p>
              <p style="margin:0;">Questions or tips? Reply to this email or ping me on the site — no robots, just Suzy.</p>
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

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

        $confirm_url = add_query_arg('token', rawurlencode($token), rest_url('lousy-outages/v1/confirm'));

        $unsubscribe_url = lo_unsubscribe_url_for($email);
        $sent            = lo_send_confirmation($email, $confirm_url);

        do_action('lousy_outages_log', 'confirmation_email_dispatched', [
            'email'           => $email,
            'sent'            => $sent,
            'confirm_url'     => $confirm_url,
            'unsubscribe_url' => $unsubscribe_url,
        ]);
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
        $unsubscribe_url = lo_unsubscribe_url_for($email);
        $dashboard_url = home_url('/lousy-outages/');

        $subject = '🔔 Subscribed to Lousy Outages';

        $text_body = <<<TEXT
confirmed. you're now getting the Lousy Outages briefings. 🛰️

expect:
- outage and degradation alerts for cloudflare, aws, azure, gcp, stripe, pagerduty, zscaler (and their friends)
- mini postmortems with timelines + impact notes
- security watch items so you can harden things before the next wobble

open the live dashboard any time:
{$dashboard_url}

need out? one-click escape hatch:
<{$unsubscribe_url}>

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
        $dashboard_url      = home_url('/lousy-outages/');
        $unsubscribe_url    = lo_unsubscribe_url_for($email);

        $subject = 'you\'re already on the radar (all good)';

        $text_body = <<<TEXT
no double-hacking needed—your email is already subscribed.
dashboard: {$dashboard_url}
unsubscribe: <{$unsubscribe_url}>
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
    $responseParam  = $request->get_param('challenge_response');
    $noscriptParam  = $request->get_param('lo_noscript_challenge');
    $challengeReply = is_string($responseParam) ? trim((string) $responseParam) : '';
    $noscriptFlag   = !empty($noscriptParam);

    if (!lo_validate_lyric_captcha($challengeReply, $noscriptFlag)) {
        return new \WP_Error(
            'invalid_challenge',
            'Please solve the human check to subscribe.',
            [
                'status' => 400,
                'ok'     => false,
            ]
        );
    }

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
    $domain = strtolower($domain);

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
    ];

    $transport    = getenv('SMTP_HOST') ? 'smtp' : 'mail';
    $sendmailPath = ini_get('sendmail_path') ?: '';
    $debugParam   = $request->get_param('deliverability');
    if (is_string($debugParam) && 'debug' === strtolower($debugParam)) {
        return rest_ensure_response([
            'transport'     => $transport,
            'headers'       => $headers,
            'mailer'        => 'LousyOutages\\Mailer',
            'domain'        => $domain,
            'sendmail_path' => $sendmailPath,
        ]);
    }

    $confirm_url_filter = apply_filters('lo_subscribe_confirmation_url', '', $email, $request);
    if (!is_string($confirm_url_filter) || '' === trim($confirm_url_filter)) {
        $confirm_url_filter = null;
    }

    $sent = lo_send_confirmation($email, $confirm_url_filter);

    $admin_email = apply_filters('lo_admin_notification_email', 'admin@suzyeaston.ca', $email, $request);
    $ok_admin    = false;
    if ($admin_email && is_email($admin_email)) {
        $subscriber_email   = esc_html($email);
        $subscriber_count   = count($subscribers);
        $subscriber_counter = number_format_i18n($subscriber_count);
        $timestamp_display  = esc_html(wp_date('F j, Y g:i A T'));
        $list_manage_url    = esc_url(admin_url('admin.php?page=lousy-outages'));

        $admin_subject = '🌩️ Neon ping: a new Lousy Outages subscriber';
        $admin_body    = <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="margin:0;background:#0d0221;color:#f8f6ff;font-family:Helvetica,Arial,sans-serif;">
    <div style="max-width:520px;margin:0 auto;padding:32px 28px;">
        <div style="background:linear-gradient(135deg,#341671,#f72585);padding:24px 26px;border-radius:18px;box-shadow:0 14px 35px rgba(247,37,133,0.32);">
            <h1 style="margin:0 0 12px;font-size:26px;letter-spacing:0.08em;text-transform:uppercase;color:#fdf8ff;">New Signal Locked</h1>
            <p style="margin:0;font-size:15px;line-height:1.6;">A fresh human just hacked into the neon grid.</p>
        </div>
        <div style="background:#140631;padding:28px;border-radius:18px;margin-top:22px;border:1px solid rgba(255,184,28,0.28);">
            <p style="margin:0 0 12px;font-size:14px;color:#ffb81c;letter-spacing:0.12em;text-transform:uppercase;">Subscriber Intel</p>
            <p style="margin:0 0 10px;font-size:16px;font-weight:600;color:#fef4ff;">{$subscriber_email}</p>
            <p style="margin:0 0 4px;font-size:13px;color:#d0c6ff;">Captured: {$timestamp_display}</p>
            <p style="margin:0;font-size:13px;color:#d0c6ff;">Total crew on deck: <strong style="color:#ff4ecd;">{$subscriber_counter}</strong></p>
        </div>
        <p style="margin:22px 0 0;font-size:13px;line-height:1.6;color:#bfb6ff;">Need to audit the roster? <a href="{$list_manage_url}" style="color:#ffb81c;text-decoration:none;font-weight:600;">Beam into the dashboard</a> anytime.</p>
        <p style="margin:18px 0 0;font-size:12px;color:#8c7dd6;text-transform:uppercase;letter-spacing:0.18em;">Stay rad &middot; Stay noisy</p>
    </div>
</body>
</html>
HTML;

        $ok_admin = wp_mail($admin_email, $admin_subject, $admin_body, $headers);
    }

    do_action('lousy_outages_log', 'subscribe_confirmation_send', [
        'email'        => $email,
        'sent'         => $sent,
        'admin_notice' => $ok_admin,
    ]);

    if (!$sent) {
        return new \WP_Error(
            'send_fail',
            'Mail send failed — check SMTP or server mailer',
            [
                'status'     => 500,
                'ok'         => false,
                'transport'  => $transport,
                'sendmail'   => $sendmailPath,
                'ok_admin'   => (bool) $ok_admin,
            ]
        );
    }

    return rest_ensure_response(['ok' => true]);
}

if (!function_exists('lo_normalize_lyric_answer')) {
    function lo_normalize_lyric_answer(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '', $value);

        return (string) $value;
    }
}

if (!function_exists('lo_lyric_fragment_bank')) {
    function lo_lyric_fragment_bank(): array
    {
        return [
            'desert_highway' => [
                'fragment' => 'On a dark desert highway',
                'answer'   => 'cool wind in my hair',
            ],
            'warm_smell' => [
                'fragment' => 'Warm smell of colitas',
                'answer'   => 'rising up through the air',
            ],
            'distance_light' => [
                'fragment' => 'Up ahead in the distance',
                'answer'   => 'I saw a shimmering light',
            ],
            'tiffany_twisted' => [
                'fragment' => 'Her mind is Tiffany-twisted',
                'answer'   => 'she got the Mercedes bends',
            ],
            'mirrors_ceiling' => [
                'fragment' => 'Mirrors on the ceiling',
                'answer'   => 'the pink champagne on ice',
            ],
            'programmed_receive' => [
                'fragment' => 'We are programmed to receive',
                'answer'   => 'you can check out any time you like but you can never leave',
            ],
            'welcome_chorus' => [
                'fragment' => 'Welcome to the Hotel California',
                'answer'   => 'such a lovely place',
            ],
        ];
    }
}

if (!function_exists('lo_lyric_passphrases')) {
    function lo_lyric_passphrases(): array
    {
        $bank = lo_lyric_fragment_bank();
        $defaults = [];
        foreach ($bank as $row) {
            if (!is_array($row) || empty($row['answer'])) {
                continue;
            }
            $defaults[] = (string) $row['answer'];
        }

        $custom = apply_filters('lo_subscribe_passphrases', $defaults);
        if (!is_array($custom)) {
            $custom = $defaults;
        }

        $filtered = [];
        foreach ($custom as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $trimmed = trim($phrase);
            if ('' === $trimmed) {
                continue;
            }
            $filtered[] = $trimmed;
        }

        return array_values(array_unique($filtered));
    }
}

if (!function_exists('lo_validate_lyric_captcha')) {
    function lo_validate_lyric_captcha(string $response, bool $allowFallback = false): bool
    {
        $normalized = lo_normalize_lyric_answer($response);
        if ('' === $normalized) {
            return false;
        }

        $answers = array_map('lo_normalize_lyric_answer', lo_lyric_passphrases());
        foreach ($answers as $answer) {
            if ('' === $answer) {
                continue;
            }
            if (hash_equals($answer, $normalized)) {
                return true;
            }
        }

        if ($allowFallback) {
            $fallback = lo_normalize_lyric_answer(apply_filters('lo_subscribe_noscript_word', 'hotel'));
            if ('' !== $fallback && hash_equals($fallback, $normalized)) {
                return true;
            }
        }

        return false;
    }
}

Lousy_Outages_Subscribe::bootstrap();

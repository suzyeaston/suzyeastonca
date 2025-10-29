<?php
declare(strict_types=1);

namespace LousyOutages;

class Mailer {
    /**
     * Send an HTML email via WordPress with optional extra headers and plain-text fallback.
     *
     * @param array<int,string> $extra_headers
     */
    public static function send(string $email, string $subject, string $text_body, string $html_body, array $extra_headers = []): bool {
        $email = sanitize_email($email);
        if (!$email || !is_email($email)) {
            do_action('lousy_outages_log', 'email_skip', ['reason' => 'invalid_recipient']);
            return false;
        }

        $from_email = get_option('admin_email');
        if (!is_email($from_email)) {
            $host = wp_parse_url(home_url(), PHP_URL_HOST);
            if (!is_string($host) || '' === $host) {
                $host = 'example.com';
            }
            $from_email = 'no-reply@' . ltrim($host, '@');
        }
        $from_email = (string) apply_filters('lousy_outages_mail_from_email', $from_email, $email);
        if (!is_email($from_email)) {
            $from_email = $email;
        }

        $from_name = wp_specialchars_decode(get_bloginfo('name', 'display'), ENT_QUOTES);
        if ('' === trim((string) $from_name)) {
            $from_name = 'Lousy Outages';
        }
        $from_name = (string) apply_filters('lousy_outages_mail_from_name', $from_name, $email);
        $from_name = trim(preg_replace('/[\r\n]+/', ' ', $from_name));

        $reply_to = apply_filters('lousy_outages_mail_reply_to', $from_email, $email);

        $headers  = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];

        if (!empty($extra_headers)) {
            foreach ($extra_headers as $header) {
                if (!is_string($header)) {
                    continue;
                }
                $header = trim($header);
                if ('' === $header) {
                    continue;
                }
                $headers[] = $header;
            }
        }

        if (is_email($from_email)) {
            $headers[] = 'From: ' . trim($from_name ? sprintf('%s <%s>', $from_name, $from_email) : $from_email);
        }

        if (is_string($reply_to) && is_email($reply_to)) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        $alt_body_callback = static function ($phpmailer) use ($text_body): void {
            if (!is_string($text_body) || '' === $text_body) {
                return;
            }
            if (is_object($phpmailer) && property_exists($phpmailer, 'AltBody')) {
                $phpmailer->AltBody = $text_body;
            }
        };

        add_action('phpmailer_init', $alt_body_callback);

        $sent = wp_mail($email, $subject, $html_body, $headers);

        remove_action('phpmailer_init', $alt_body_callback);

        if (! $sent && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[lousy_outages] mail_send_failed recipient=%s subject=%s', $email, $subject));
        }

        return (bool) $sent;
    }
}

<?php
declare(strict_types=1);

namespace LousyOutages;

class Mailer {
    public static function send(string $email, string $subject, string $text_body, string $html_body): void {
        $email = sanitize_email($email);
        if (!$email || !is_email($email)) {
            do_action('lousy_outages_log', 'email_skip', ['reason' => 'invalid_recipient']);
            return;
        }

        $boundary = 'lo-' . wp_generate_password(24, false, false);

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
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"; charset=UTF-8',
        ];

        if (is_email($from_email)) {
            $headers[] = 'From: ' . trim($from_name ? sprintf('%s <%s>', $from_name, $from_email) : $from_email);
        }

        if (is_string($reply_to) && is_email($reply_to)) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        $parts = [];
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/plain; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: 8bit';
        $parts[] = '';
        $parts[] = $text_body;
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/html; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: 8bit';
        $parts[] = '';
        $parts[] = $html_body;
        $parts[] = '--' . $boundary . '--';
        $parts[] = '';

        $message = implode("\r\n", $parts);

        wp_mail($email, $subject, $message, $headers);
    }
}

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
        $headers  = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"; charset=UTF-8',
        ];

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

<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use PHPMailer\PHPMailer\PHPMailer;
use WP_Error;

class MailTransport
{
    private const SENDMAIL_PATH = '/usr/sbin/sendmail';

    private static bool $forceMail = false;
    private static string $transport = 'mail';
    private static bool $fallbackAttempted = false;

    public static function bootstrap(): void
    {
        add_action('phpmailer_init', [self::class, 'configure']);
        add_action('wp_mail_failed', [self::class, 'handleFailure']);
        add_filter('wp_mail_from', [self::class, 'fromAddress']);
        add_filter('wp_mail_from_name', [self::class, 'fromName']);
    }

    public static function configure($phpmailer): void
    {
        if (! $phpmailer instanceof PHPMailer) {
            return;
        }

        self::$fallbackAttempted = false;

        if (self::$forceMail) {
            $phpmailer->isMail();
            $phpmailer->Mailer = 'mail';
            self::$transport = 'mail';
            self::$forceMail = false;
            return;
        }

        $sendmailPath = self::SENDMAIL_PATH;
        if (is_string($sendmailPath) && is_executable($sendmailPath)) {
            $phpmailer->isSendmail();
            $phpmailer->Sendmail = $sendmailPath . ' -t -i';
            self::$transport = 'sendmail';
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = '127.0.0.1';
        $phpmailer->Port = 25;
        $phpmailer->SMTPAuth = false;
        $phpmailer->SMTPAutoTLS = false;
        $phpmailer->SMTPKeepAlive = false;
        self::$transport = 'smtp';
    }

    public static function fromAddress(string $address = ''): string
    {
        $desired = sanitize_email('noreply@suzyeaston.ca');

        return $desired ?: $address;
    }

    public static function fromName(string $name = ''): string
    {
        $desired = sanitize_text_field('Lousy Outages');

        return $desired ?: $name;
    }

    public static function handleFailure(WP_Error $error): void
    {
        self::logFailure($error);

        if ('smtp' !== self::$transport || self::$fallbackAttempted) {
            return;
        }

        $message = strtolower($error->get_error_message());
        if (false === strpos($message, 'smtp connect() failed') && false === strpos($message, 'could not connect to smtp')) {
            return;
        }

        $data = $error->get_error_data();
        if (! is_array($data)) {
            return;
        }

        $to          = $data['to'] ?? '';
        $subject     = $data['subject'] ?? '';
        $body        = $data['message'] ?? '';
        $headers     = $data['headers'] ?? [];
        $attachments = $data['attachments'] ?? [];

        if (empty($to)) {
            return;
        }

        self::$fallbackAttempted = true;
        self::$forceMail = true;

        wp_mail($to, (string) $subject, (string) $body, $headers, $attachments);

        self::$forceMail = false;
    }

    private static function logFailure(WP_Error $error): void
    {
        $logMessage = '[' . gmdate('c') . '] ' . $error->get_error_message() . "\n";
        $data = $error->get_error_data();
        if ($data) {
            $logMessage .= print_r($data, true) . "\n";
        }

        $target = trailingslashit(WP_CONTENT_DIR) . 'uploads/lo-mail.log';
        error_log($logMessage, 3, $target);
    }
}

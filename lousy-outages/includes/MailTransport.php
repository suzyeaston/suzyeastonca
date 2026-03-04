<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use PHPMailer\PHPMailer\PHPMailer;
use WP_Error;

class MailTransport
{
    private const SENDMAIL_PATH = '/usr/sbin/sendmail';

    private static bool $active = false;
    /** @var array<string,mixed> */
    private static array $context = [];
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

    /**
     * @param array<string,mixed> $context
     */
    public static function begin(array $context = []): void
    {
        self::$active = true;
        self::$context = $context;
        self::$fallbackAttempted = false;
    }

    public static function end(): void
    {
        self::$active = false;
        self::$context = [];
        self::$forceMail = false;
        self::$fallbackAttempted = false;
        self::$transport = 'mail';
    }

    public static function isActive(): bool
    {
        return self::$active;
    }

    public static function configure($phpmailer): void
    {
        if (! self::isActive() || ! $phpmailer instanceof PHPMailer) {
            return;
        }

        $fromAddress = self::resolveFromAddress();
        $envelopeSender = (string) apply_filters('lousy_outages_envelope_from', $fromAddress);
        $envelopeSender = self::cleanAddress($envelopeSender);
        if (! is_email($envelopeSender)) {
            $envelopeSender = $fromAddress;
        }

        $phpmailer->Sender = $envelopeSender;

        if (self::$forceMail) {
            $phpmailer->isMail();
            $phpmailer->Mailer = 'mail';
            self::$transport = 'mail';
            self::$forceMail = false;
            self::addDebugHeaders($phpmailer, $envelopeSender, $fromAddress);
            return;
        }

        $sendmailPath = self::SENDMAIL_PATH;
        if (is_string($sendmailPath) && is_executable($sendmailPath)) {
            $phpmailer->isSendmail();
            $phpmailer->Sendmail = $sendmailPath . ' -t -i -f ' . escapeshellarg($envelopeSender);
            self::$transport = 'sendmail';
            self::addDebugHeaders($phpmailer, $envelopeSender, $fromAddress);
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = '127.0.0.1';
        $phpmailer->Port = 25;
        $phpmailer->SMTPAuth = false;
        $phpmailer->SMTPAutoTLS = false;
        $phpmailer->SMTPKeepAlive = false;
        self::$transport = 'smtp';
        self::addDebugHeaders($phpmailer, $envelopeSender, $fromAddress);
    }

    public static function fromAddress(string $address = ''): string
    {
        if (! self::isActive()) {
            return $address;
        }

        $desired = sanitize_email('noreply@suzyeaston.ca');
        if (isset(self::$context['from_address'])) {
            $contextAddress = self::cleanAddress((string) self::$context['from_address']);
            if (is_email($contextAddress)) {
                $desired = $contextAddress;
            }
        }

        return $desired ?: $address;
    }

    public static function fromName(string $name = ''): string
    {
        if (! self::isActive()) {
            return $name;
        }

        $desired = sanitize_text_field('Lousy Outages');
        if (isset(self::$context['from_name'])) {
            $contextName = trim(preg_replace('/[\r\n]+/', ' ', (string) self::$context['from_name']));
            if ('' !== $contextName) {
                $desired = sanitize_text_field($contextName);
            }
        }

        return $desired ?: $name;
    }

    public static function handleFailure(WP_Error $error): void
    {
        if (! self::isActive()) {
            return;
        }

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

    private static function resolveFromAddress(): string
    {
        $fromAddress = 'noreply@suzyeaston.ca';
        if (isset(self::$context['from_address'])) {
            $fromAddress = (string) self::$context['from_address'];
        }

        $fromAddress = self::cleanAddress((string) sanitize_email($fromAddress));
        if (! is_email($fromAddress)) {
            $fromAddress = 'noreply@suzyeaston.ca';
        }

        return $fromAddress;
    }

    private static function cleanAddress(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private static function addDebugHeaders(PHPMailer $phpmailer, string $envelopeSender, string $fromAddress): void
    {
        $phpmailer->addCustomHeader('X-Lousy-Outages-Transport', self::$transport);
        $phpmailer->addCustomHeader('X-Lousy-Outages-Envelope-From', $envelopeSender);
        $phpmailer->addCustomHeader('X-Lousy-Outages-From', $fromAddress);
    }
}

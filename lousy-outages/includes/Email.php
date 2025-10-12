<?php
declare(strict_types=1);

namespace LousyOutages;

class Email {
    private function send( string $subject, string $body ): void {
        $to = get_option( 'lousy_outages_email', get_option( 'admin_email' ) );
        if ( ! $to || ! is_email( $to ) ) {
            do_action( 'lousy_outages_log', 'email_skip', [ 'reason' => 'invalid_to' ] );
            return;
        }

        $headers = [];
        $host    = parse_url( home_url(), PHP_URL_HOST ) ?: wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'localhost';
        $from    = get_option( 'blogname' ) . ' <no-reply@' . $host . '>';
        $headers[] = 'From: ' . $from;
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        $ok = wp_mail( $to, $subject, $body, $headers );
        update_option(
            'lousy_outages_last_email',
            [
                'to'      => $to,
                'subject' => $subject,
                'ok'      => $ok,
                'ts'      => gmdate( 'c' ),
            ],
            false
        );

        do_action( 'lousy_outages_log', 'email_send', [ 'ok' => $ok, 'to' => $to, 'subject' => $subject ] );
    }

    public function send_alert( string $provider, string $status, string $message, string $link ): void {
        $subject = sprintf( '⚠️ Lousy Outages: %s %s', $provider, $status );
        $body    = sprintf( "%s\n\nMore info: %s", $message, $link );
        $this->send( $subject, $body );
    }

    public function send_recovery( string $provider, string $link ): void {
        $subject = sprintf( '✅ Lousy Outages: %s recovered', $provider );
        $body    = sprintf( "Back to normal.\n\n%s", $link );
        $this->send( $subject, $body );
    }
}

<?php
namespace LousyOutages;

class Email {
    private function send( string $subject, string $body ): void {
        $to = get_option( 'lousy_outages_email', get_option( 'admin_email' ) );
        if ( ! $to ) {
            return;
        }
        wp_mail( $to, $subject, $body );
    }

    public function send_alert( string $provider, string $status, string $message, string $link ): void {
        $subject = sprintf( '🚨 Lousy Outages: %s %s', $provider, $status );
        $body    = sprintf( "%s\n\nMore info: %s", $message, $link );
        $this->send( $subject, $body );
    }

    public function send_recovery( string $provider, string $link ): void {
        $subject = sprintf( '✅ Lousy Outages: %s recovered', $provider );
        $body    = sprintf( "Back to normal.\n\n%s", $link );
        $this->send( $subject, $body );
    }
}

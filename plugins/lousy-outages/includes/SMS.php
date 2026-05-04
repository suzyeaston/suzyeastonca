<?php
namespace SuzyEaston\LousyOutages;

class SMS {
    private function send( string $body ): void {
        $sid   = get_option( 'lousy_outages_twilio_sid' );
        $token = get_option( 'lousy_outages_twilio_token' );
        $from  = get_option( 'lousy_outages_twilio_from' );
        $to    = get_option( 'lousy_outages_phone' );
        if ( ! $sid || ! $token || ! $from || ! $to ) {
            return;
        }
        $url = sprintf( 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $sid );
        wp_remote_post( $url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ),
            ],
            'body'    => [
                'From' => $from,
                'To'   => $to,
                'Body' => $body,
            ],
        ] );
    }

    public function send_alert( string $provider, string $status, string $message, string $link ): void {
        $body = sprintf( '🚨 Lousy Outages: %s -> %s. %s (%s)', $provider, $status, $message, $link );
        $this->send( $body );
    }

    public function send_recovery( string $provider, string $link ): void {
        $body = sprintf( '✅ Lousy Outages: %s recovered. (%s)', $provider, $link );
        $this->send( $body );
    }
}

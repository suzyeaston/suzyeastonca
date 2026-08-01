<?php
/**
 * TransLink service alerts for the homepage SkyTrain scanner widget.
 * Source: https://getaway.translink.ca/api/allalerts (public JSON, no API key).
 */

function se_translink_alerts_skytrain_haystack( array $row ): string {
    return strtolower(
        ( $row['route'] ?? '' ) . ' ' . ( $row['header'] ?? '' ) . ' ' . ( $row['text'] ?? '' )
    );
}

function se_translink_alert_is_skytrain( array $row ): bool {
    if ( isset( $row['group'] ) && (int) $row['group'] === 1 ) {
        return true;
    }
    $hay = se_translink_alerts_skytrain_haystack( $row );
    $needles = array(
        'skytrain',
        'expo line',
        'canada line',
        'millennium',
        'seabus',
        'west coast express',
        'vcc-clark',
        'production way',
        'waterfront station',
    );
    foreach ( $needles as $needle ) {
        if ( str_contains( $hay, $needle ) ) {
            return true;
        }
    }
    return false;
}

function se_fetch_translink_alerts(): array {
    $cached = get_transient( 'se_translink_alerts' );
    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    $response = wp_remote_get(
        'https://getaway.translink.ca/api/allalerts',
        array(
            'timeout' => 12,
            'headers' => array( 'Accept' => 'application/json' ),
        )
    );

    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return array();
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) ) {
        return array();
    }

    $out = array();
    foreach ( $body as $row ) {
        if ( ! is_array( $row ) || ! empty( $row['closed'] ) ) {
            continue;
        }

        $header = wp_strip_all_tags( (string) ( $row['header'] ?? '' ) );
        $raw    = (string) ( $row['description'] ?? $row['alertText'] ?? $header );
        $text   = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( $raw ) ) );
        if ( mb_strlen( $text ) > 320 ) {
            $text = mb_substr( $text, 0, 317 ) . '…';
        }

        $entry = array(
            'id'       => $row['alertId'] ?? $row['id'] ?? null,
            'group'    => isset( $row['group'] ) ? (int) $row['group'] : null,
            'route'    => wp_strip_all_tags( (string) ( $row['routeLongName'] ?? '' ) ),
            'header'   => $header,
            'text'     => $text,
            'critical' => ! empty( $row['critical'] ),
        );

        $entry['skytrain'] = se_translink_alert_is_skytrain( $entry );
        $out[] = $entry;
    }

    set_transient( 'se_translink_alerts', $out, 3 * MINUTE_IN_SECONDS );
    return $out;
}

function se_get_translink_alerts_rest( WP_REST_Request $request ): WP_REST_Response {
    $alerts   = se_fetch_translink_alerts();
    $skytrain = array_values(
        array_filter(
            $alerts,
            static function ( $row ) {
                return ! empty( $row['skytrain'] );
            }
        )
    );

    return rest_ensure_response(
        array(
            'updated'  => current_time( 'mysql' ),
            'source'   => 'TransLink Open Alerts API',
            'alerts'   => $alerts,
            'skytrain' => $skytrain,
        )
    );
}

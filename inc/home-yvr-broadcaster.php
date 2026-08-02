<?php
/**
 * YVR homepage broadcaster feeds — speakable scripts for computer voice channels.
 */

function se_broadcaster_trim_script( string $text, int $max = 520 ): string {
    $text = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( $text ) ) );
    if ( mb_strlen( $text ) > $max ) {
        $text = mb_substr( $text, 0, $max - 1 ) . '…';
    }
    return $text;
}

function se_broadcaster_translink_script(): array {
    $alerts = function_exists( 'se_fetch_translink_alerts' ) ? se_fetch_translink_alerts() : array();
    $sky    = array_values(
        array_filter(
            $alerts,
            static function ( $row ) {
                return ! empty( $row['skytrain'] );
            }
        )
    );

    if ( ! $sky ) {
        $sky = array_slice( $alerts, 0, 5 );
    }

    $lines = array();
    foreach ( array_slice( $sky, 0, 3 ) as $row ) {
        $line = $row['header'] ?? '';
        if ( ! empty( $row['text'] ) && $row['text'] !== $line ) {
            $line = trim( $line . '. ' . $row['text'] );
        }
        if ( $line ) {
            $lines[] = se_broadcaster_trim_script( $line, 200 );
        }
    }

    if ( ! $lines ) {
        return array(
            'caption' => 'TransLink reports all clear on SkyTrain lines.',
            'script'  => 'TransLink feed is quiet. No major SkyTrain service alerts right now.',
        );
    }

    $script = 'TransLink update. ' . implode( ' Next. ', $lines );
    return array(
        'caption' => se_broadcaster_trim_script( $lines[0], 220 ),
        'script'  => se_broadcaster_trim_script( $script ),
    );
}

function se_broadcaster_vancouver_event_match( array $event ): bool {
    $hay = strtolower(
        ( $event['headline'] ?? '' ) . ' ' . ( $event['description'] ?? '' ) . ' ' . ( $event['event_type'] ?? '' )
    );
    $needles = array(
        'vancouver',
        'richmond',
        'surrey',
        'delta',
        'burnaby',
        'coquitlam',
        'new westminster',
        'north vancouver',
        'west vancouver',
        'langley',
        'highway 1',
        'transcanada',
        'trans canada',
        'sea to sky',
        'ironworkers',
        'massey',
        'tsawwassen',
        'horseshoe bay',
        'port mann',
        'patullo',
        'george massey',
    );
    foreach ( $needles as $needle ) {
        if ( str_contains( $hay, $needle ) ) {
            return true;
        }
    }
    return false;
}

function se_fetch_open511_bc_events(): array {
    $cached = get_transient( 'se_open511_bc_events' );
    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    $response = wp_remote_get(
        'https://api.open511.gov.bc.ca/events?status=ACTIVE&limit=80',
        array(
            'timeout' => 15,
            'headers' => array( 'Accept' => 'application/json' ),
        )
    );

    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return array();
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || empty( $body['events'] ) || ! is_array( $body['events'] ) ) {
        return array();
    }

    $out = array();
    foreach ( $body['events'] as $event ) {
        if ( ! is_array( $event ) ) {
            continue;
        }
        if ( ! se_broadcaster_vancouver_event_match( $event ) ) {
            continue;
        }
        $headline = wp_strip_all_tags( (string) ( $event['headline'] ?? '' ) );
        $desc     = wp_strip_all_tags( (string) ( $event['description'] ?? '' ) );
        $out[]    = array(
            'headline'    => $headline,
            'description' => se_broadcaster_trim_script( $desc, 240 ),
            'type'        => (string) ( $event['event_type'] ?? '' ),
        );
    }

    set_transient( 'se_open511_bc_events', $out, 5 * MINUTE_IN_SECONDS );
    return $out;
}

function se_broadcaster_drivers_script(): array {
    $events = se_fetch_open511_bc_events();
    if ( ! $events ) {
        return array(
            'caption' => 'BC Open511 — no active Lower Mainland road alerts.',
            'script'  => 'DriveBC and Open511 show no major active incidents around Metro Vancouver right now.',
            'source'  => 'BC Open511',
        );
    }

    $lines = array();
    foreach ( array_slice( $events, 0, 3 ) as $event ) {
        $line = $event['headline'];
        if ( ! empty( $event['description'] ) ) {
            $line .= '. ' . $event['description'];
        }
        $lines[] = se_broadcaster_trim_script( $line, 200 );
    }

    $script = 'Lower Mainland roads. ' . implode( ' Next. ', $lines );
    return array(
        'caption' => se_broadcaster_trim_script( $lines[0], 220 ),
        'script'  => se_broadcaster_trim_script( $script ),
        'source'  => 'BC Open511',
    );
}

function se_fetch_bc_ferries_capacity(): array {
    $cached = get_transient( 'se_bc_ferries_capacity' );
    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    $response = wp_remote_get(
        'https://www.bcferriesapi.ca/v2/capacity/',
        array(
            'timeout' => 15,
            'headers' => array( 'Accept' => 'application/json' ),
        )
    );

    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return array();
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || empty( $body['routes'] ) || ! is_array( $body['routes'] ) ) {
        return array();
    }

    $wanted = array( 'TSA', 'SWB', 'HSB', 'NAN', 'DUK', 'FUL' );
    $routes = array();
    foreach ( $body['routes'] as $route ) {
        if ( ! is_array( $route ) ) {
            continue;
        }
        $from = (string) ( $route['fromTerminalCode'] ?? '' );
        $to   = (string) ( $route['toTerminalCode'] ?? '' );
        if ( ! in_array( $from, $wanted, true ) && ! in_array( $to, $wanted, true ) ) {
            continue;
        }
        $routes[] = $route;
    }

    set_transient( 'se_bc_ferries_capacity', $routes, 5 * MINUTE_IN_SECONDS );
    return $routes;
}

function se_terminal_label( string $code ): string {
    $map = array(
        'TSA' => 'Tsawwassen',
        'SWB' => 'Swartz Bay',
        'HSB' => 'Horseshoe Bay',
        'NAN' => 'Nanaimo',
        'DUK' => 'Duke Point',
        'FUL' => 'Fulford Harbour',
        'SGI' => 'Salt Spring',
    );
    return $map[ $code ] ?? $code;
}

function se_broadcaster_ferries_script(): array {
    $routes = se_fetch_bc_ferries_capacity();
    if ( ! $routes ) {
        return array(
            'caption' => 'BC Ferries capacity feed unavailable.',
            'script'  => 'BC Ferries sailing data is offline right now. Check bcferries.com before you drive to the terminal.',
            'source'  => 'bcferriesapi.ca',
        );
    }

    $lines = array();
    foreach ( array_slice( $routes, 0, 4 ) as $route ) {
        $from = se_terminal_label( (string) ( $route['fromTerminalCode'] ?? '' ) );
        $to   = se_terminal_label( (string) ( $route['toTerminalCode'] ?? '' ) );
        $sail = isset( $route['sailings'][0] ) && is_array( $route['sailings'][0] ) ? $route['sailings'][0] : array();
        $time = (string) ( $sail['time'] ?? 'unknown time' );
        $fill = isset( $sail['carFill'] ) ? (int) $sail['carFill'] : null;
        $vessel = (string) ( $sail['vesselName'] ?? '' );
        $status = (string) ( $sail['vesselStatus'] ?? '' );

        $bit = $from . ' to ' . $to . ', next sailing ' . $time;
        if ( $vessel ) {
            $bit .= ' on ' . $vessel;
        }
        if ( null !== $fill ) {
            $bit .= ', car deck at ' . $fill . ' percent';
        }
        if ( $status ) {
            $bit .= ', status ' . $status;
        }
        $lines[] = $bit;
    }

    $script = 'BC Ferries check. ' . implode( ' Next route. ', $lines );
    return array(
        'caption' => se_broadcaster_trim_script( $lines[0] ?? 'BC Ferries routes loaded.', 220 ),
        'script'  => se_broadcaster_trim_script( $script ),
        'source'  => 'bcferriesapi.ca',
    );
}

function se_get_broadcaster_feeds_rest( WP_REST_Request $request ): WP_REST_Response {
    return rest_ensure_response(
        array(
            'updated'   => current_time( 'mysql' ),
            'translink' => se_broadcaster_translink_script(),
            'drivers'   => se_broadcaster_drivers_script(),
            'ferries'   => se_broadcaster_ferries_script(),
        )
    );
}

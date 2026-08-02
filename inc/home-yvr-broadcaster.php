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

function se_broadcaster_format_posted( string $raw ): string {
    if ( ! $raw ) {
        return '';
    }
    $ts = strtotime( $raw );
    if ( ! $ts ) {
        return '';
    }
    return wp_date( 'M j, Y g:i a', $ts );
}

function se_broadcaster_format_arcgis_ms( mixed $raw ): string {
    if ( ! $raw || ! is_numeric( $raw ) ) {
        return '';
    }
    $ms = (float) $raw;
    if ( $ms < 1e11 ) {
        return '';
    }
    return wp_date( 'M j, Y g:i a', (int) round( $ms / 1000 ) );
}

function se_broadcaster_drivebc_url( string $api_url ): string {
    if ( preg_match( '/(DBC-\d+)/', $api_url, $m ) ) {
        return 'https://www.drivebc.ca/mobile/event/' . $m[1];
    }
    return 'https://www.drivebc.ca/';
}

function se_broadcaster_pack_from_items( array $items, string $prefix, string $source, string $source_url = '' ): array {
    $lines = array();
    foreach ( $items as $item ) {
        if ( ! empty( $item['text'] ) ) {
            $lines[] = $item['text'];
        }
    }

    $script = $lines ? $prefix . implode( ' Next. ', $lines ) : '';

    return array(
        'caption'       => se_broadcaster_trim_script( $lines[0] ?? $prefix, 220 ),
        'script'        => se_broadcaster_trim_script( $script ),
        'source'        => $source,
        'source_url'    => $source_url,
        'items'         => $items,
        'fetched_label' => 'Pulled ' . wp_date( 'M j, Y g:i a T' ),
    );
}

/**
 * Sort rows by a datetime field (newest first), preferring recent window then older.
 */
function se_broadcaster_prioritize_recent( array $rows, string $key = 'posted', int $recent_days = 21 ): array {
    $cutoff = time() - ( $recent_days * DAY_IN_SECONDS );
    $recent = array();
    $older  = array();

    foreach ( $rows as $row ) {
        $ts = strtotime( (string) ( $row[ $key ] ?? '' ) ) ?: 0;
        if ( $ts >= $cutoff ) {
            $recent[] = $row;
        } else {
            $older[] = $row;
        }
    }

    $sort = static function ( array $a, array $b ) use ( $key ): int {
        $ta = strtotime( (string) ( $a[ $key ] ?? '' ) ) ?: 0;
        $tb = strtotime( (string) ( $b[ $key ] ?? '' ) ) ?: 0;
        return $tb <=> $ta;
    };

    usort( $recent, $sort );
    usort( $older, $sort );

    return array_merge( $recent, $older );
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
        $sky = array_slice( $alerts, 0, 8 );
    }

    $sky = se_broadcaster_prioritize_recent( $sky, 'posted', 21 );

    $items = array();
    foreach ( array_slice( $sky, 0, 3 ) as $row ) {
        $snippet = (string) ( $row['alert_text'] ?? '' );
        if ( ! $snippet ) {
            $snippet = $row['header'] ?? '';
        }
        if ( ! empty( $row['text'] ) && $row['text'] !== $snippet && mb_strlen( $snippet ) < 40 ) {
            $snippet = trim( $snippet . '. ' . mb_substr( $row['text'], 0, 120 ) );
        }
        if ( ! $snippet ) {
            continue;
        }
        $title = $row['route'] ? $row['route'] : ( $row['header'] ?? 'TransLink alert' );
        $items[] = array(
            'title'        => se_broadcaster_trim_script( $title, 80 ),
            'text'         => se_broadcaster_trim_script( $snippet, 180 ),
            'posted'       => (string) ( $row['posted'] ?? '' ),
            'posted_label' => (string) ( $row['posted_label'] ?? '' ),
            'url'          => (string) ( $row['url'] ?? '' ),
            'link_label'   => 'TransLink alert',
        );
    }

    if ( ! $items ) {
        return array(
            'caption'       => 'TransLink reports all clear on SkyTrain lines.',
            'script'        => 'TransLink feed is quiet. No major SkyTrain service alerts right now.',
            'source'        => 'TransLink',
            'source_url'    => 'https://www.translink.ca/alerts',
            'items'         => array(),
            'fetched_label' => 'Pulled ' . wp_date( 'M j, Y g:i a T' ),
        );
    }

    return se_broadcaster_pack_from_items(
        $items,
        'TransLink update. ',
        'TransLink',
        'https://www.translink.ca/alerts'
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
        $api_url   = (string) ( $event['url'] ?? '' );
        $updated   = (string) ( $event['updated'] ?? $event['created'] ?? '' );
        $out[]     = array(
            'headline'     => $headline,
            'description'  => se_broadcaster_trim_script( $desc, 240 ),
            'type'         => (string) ( $event['event_type'] ?? '' ),
            'updated'      => $updated,
            'updated_label' => se_broadcaster_format_posted( $updated ),
            'url'          => se_broadcaster_drivebc_url( $api_url ),
        );
    }

    set_transient( 'se_open511_bc_events', $out, 2 * MINUTE_IN_SECONDS );

    return se_broadcaster_prioritize_recent( $out, 'updated', 14 );
}

function se_broadcaster_drivers_script(): array {
    $events = se_fetch_open511_bc_events();
    if ( ! $events ) {
        return array(
            'caption'       => 'BC Open511 — no active Lower Mainland road alerts.',
            'script'        => 'DriveBC and Open511 show no major active incidents around Metro Vancouver right now.',
            'source'        => 'BC Open511',
            'source_url'    => 'https://www.drivebc.ca/',
            'items'         => array(),
            'fetched_label' => 'Pulled ' . wp_date( 'M j, Y g:i a T' ),
        );
    }

    $items = array();
    foreach ( array_slice( $events, 0, 3 ) as $event ) {
        $line = $event['headline'];
        if ( ! empty( $event['description'] ) ) {
            $line .= '. ' . $event['description'];
        }
        $items[] = array(
            'title'        => se_broadcaster_trim_script( $event['headline'], 80 ),
            'text'         => se_broadcaster_trim_script( $line, 200 ),
            'posted'       => (string) ( $event['updated'] ?? '' ),
            'posted_label' => (string) ( $event['updated_label'] ?? '' ),
            'url'          => (string) ( $event['url'] ?? '' ),
            'link_label'   => 'DriveBC incident',
        );
    }

    return se_broadcaster_pack_from_items(
        $items,
        'Lower Mainland roads. ',
        'BC Open511',
        'https://www.drivebc.ca/'
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

    set_transient( 'se_bc_ferries_capacity', $routes, 2 * MINUTE_IN_SECONDS );
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
            'caption'       => 'BC Ferries capacity feed unavailable.',
            'script'        => 'BC Ferries sailing data is offline right now. Check bcferries.com before you drive to the terminal.',
            'source'        => 'bcferriesapi.ca',
            'source_url'    => 'https://www.bcferries.com/current_conditions',
            'items'         => array(),
            'fetched_label' => 'Pulled ' . wp_date( 'M j, Y g:i a T' ),
        );
    }

    $items = array();
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

        $items[] = array(
            'title'        => $from . ' → ' . $to,
            'text'         => $bit,
            'posted'       => $time,
            'posted_label' => 'Next sail ' . $time . ' PT',
            'url'          => 'https://www.bcferries.com/current_conditions',
            'link_label'   => 'Live conditions',
        );
    }

    return se_broadcaster_pack_from_items(
        $items,
        'BC Ferries check. ',
        'bcferriesapi.ca',
        'https://www.bcferries.com/current_conditions'
    );
}

function se_aqhi_risk_label( float $aqhi ): string {
    if ( $aqhi >= 11 ) {
        return 'very high risk';
    }
    if ( $aqhi >= 7 ) {
        return 'high risk';
    }
    if ( $aqhi >= 4 ) {
        return 'moderate risk';
    }
    return 'low risk';
}

function se_fetch_ec_weather_alerts(): array {
    $cached = get_transient( 'se_ec_weather_alerts' );
    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    $bbox = '-123.6,48.9,-122.1,49.6';
    $response = wp_remote_get(
        'https://api.weather.gc.ca/collections/weather-alerts/items?bbox=' . rawurlencode( $bbox ) . '&limit=25',
        array(
            'timeout' => 15,
            'headers' => array( 'Accept' => 'application/geo+json' ),
        )
    );

    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return array();
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || empty( $body['features'] ) || ! is_array( $body['features'] ) ) {
        set_transient( 'se_ec_weather_alerts', array(), 5 * MINUTE_IN_SECONDS );
        return array();
    }

    $out = array();
    foreach ( $body['features'] as $feature ) {
        if ( ! is_array( $feature ) ) {
            continue;
        }
        $props = $feature['properties'] ?? array();
        if ( ! is_array( $props ) ) {
            continue;
        }
        $event    = wp_strip_all_tags( (string) ( $props['event'] ?? $props['alert_type'] ?? 'Weather alert' ) );
        $headline = wp_strip_all_tags( (string) ( $props['headline'] ?? '' ) );
        $desc     = wp_strip_all_tags( (string) ( $props['description'] ?? '' ) );
        $effective = (string) ( $props['effective'] ?? $props['sent'] ?? '' );
        $areas    = (string) ( $props['area_desc'] ?? $props['location_name_en'] ?? 'Metro Vancouver' );

        $out[] = array(
            'event'         => $event,
            'headline'      => $headline,
            'description'   => se_broadcaster_trim_script( $desc, 240 ),
            'effective'     => $effective,
            'effective_label' => se_broadcaster_format_posted( $effective ),
            'areas'         => se_broadcaster_trim_script( $areas, 120 ),
            'url'           => 'https://weather.gc.ca/warnings/index_e.html?prov=bc',
        );
    }

    set_transient( 'se_ec_weather_alerts', $out, 5 * MINUTE_IN_SECONDS );

    return se_broadcaster_prioritize_recent( $out, 'effective', 30 );
}

function se_broadcaster_weather_script(): array {
    $alerts = se_fetch_ec_weather_alerts();
    if ( ! $alerts ) {
        return array(
            'caption'       => 'No active Environment Canada warnings for Metro Vancouver.',
            'script'        => 'Environment Canada shows no weather warnings for Metro Vancouver right now.',
            'source'        => 'Environment Canada',
            'source_url'    => 'https://weather.gc.ca/warnings/index_e.html?prov=bc',
            'items'         => array(),
            'fetched_label' => 'Pulled ' . wp_date( 'M j, Y g:i a T' ),
        );
    }

    $items = array();
    foreach ( array_slice( $alerts, 0, 3 ) as $alert ) {
        $line = $alert['event'];
        if ( ! empty( $alert['headline'] ) ) {
            $line .= '. ' . $alert['headline'];
        }
        if ( ! empty( $alert['description'] ) ) {
            $line .= '. ' . $alert['description'];
        }
        $items[] = array(
            'title'        => se_broadcaster_trim_script( $alert['event'], 80 ),
            'text'         => se_broadcaster_trim_script( $line, 200 ),
            'posted'       => (string) ( $alert['effective'] ?? '' ),
            'posted_label' => (string) ( $alert['effective_label'] ?? '' ),
            'url'          => (string) ( $alert['url'] ?? '' ),
            'link_label'   => 'EC weather warnings',
        );
    }

    return se_broadcaster_pack_from_items(
        $items,
        'Environment Canada weather. ',
        'Environment Canada',
        'https://weather.gc.ca/warnings/index_e.html?prov=bc'
    );
}

function se_broadcaster_wildfire_name( array $row ): string {
    $geo = wp_strip_all_tags( (string) ( $row['GEOGRAPHIC_DESCRIPTION'] ?? '' ) );
    $inc = wp_strip_all_tags( (string) ( $row['INCIDENT_NAME'] ?? '' ) );
    if ( $geo ) {
        return $geo;
    }
    if ( $inc && ! preg_match( '/^V\d+$/i', $inc ) ) {
        return $inc;
    }
    return $inc ? 'BC wildfire ' . $inc : 'BC wildfire';
}

function se_broadcaster_wildfire_line( array $fire ): string {
    $parts = array( $fire['name'] );
    if ( ! empty( $fire['status'] ) ) {
        $parts[] = strtolower( $fire['status'] );
    }
    if ( ! empty( $fire['age_label'] ) ) {
        $parts[] = $fire['age_label'];
    }
    return implode( ', ', $parts ) . '.';
}

/**
 * BC's open-data layer only has ignition time — not last update. Label accordingly.
 */
function se_broadcaster_wildfire_age_label( int $ignition_ts, string $full_label ): string {
    if ( ! $ignition_ts || ! $full_label ) {
        return '';
    }
    $days = ( time() - $ignition_ts ) / DAY_IN_SECONDS;
    if ( $days <= 4 ) {
        return 'started ' . $full_label;
    }
    return 'active since ' . wp_date( 'M j', $ignition_ts );
}

/**
 * Prefer incidents that still need attention; skip stale mop-up under control.
 */
function se_broadcaster_wildfire_pick_for_bulletin( array $fires, int $limit = 3 ): array {
    $out_of_control = array();
    $being_held     = array();
    $recent_other   = array();

    foreach ( $fires as $fire ) {
        $status = strtolower( (string) ( $fire['status'] ?? '' ) );
        if ( str_contains( $status, 'out of control' ) ) {
            $out_of_control[] = $fire;
            continue;
        }
        if ( str_contains( $status, 'being held' ) ) {
            $being_held[] = $fire;
            continue;
        }
        $ts = strtotime( (string) ( $fire['posted'] ?? '' ) ) ?: 0;
        if ( $ts >= time() - ( 4 * DAY_IN_SECONDS ) ) {
            $recent_other[] = $fire;
        }
    }

    $sort_recent = static function ( array $a, array $b ): int {
        $ta = strtotime( (string) ( $a['posted'] ?? '' ) ) ?: 0;
        $tb = strtotime( (string) ( $b['posted'] ?? '' ) ) ?: 0;
        return $tb <=> $ta;
    };

    usort( $out_of_control, $sort_recent );
    usort( $being_held, $sort_recent );
    usort( $recent_other, $sort_recent );

    $picked = array_merge( $out_of_control, $being_held, $recent_other );

    return array_slice( $picked, 0, $limit );
}

function se_broadcaster_wildfire_summary_prefix( array $fires ): string {
    $ooc = 0;
    foreach ( $fires as $fire ) {
        if ( str_contains( strtolower( (string) ( $fire['status'] ?? '' ) ), 'out of control' ) ) {
            $ooc++;
        }
    }
    $total = count( $fires );

    if ( $ooc > 0 ) {
        $prefix = 'BC Wildfire — ' . $ooc . ' out of control on the southwest coast';
        if ( $total > $ooc ) {
            $prefix .= ', ' . $total . ' incidents still open';
        }
        return $prefix . '. ';
    }

    if ( $total > 3 ) {
        return 'BC Wildfire — ' . $total . ' incidents still open on the southwest coast. ';
    }

    return 'BC Wildfire bulletin for the southwest coastal corridor. ';
}

function se_fetch_bc_wildfire_near_yvr(): array {
    $cached = get_transient( 'se_bc_wildfire_near_yvr' );
    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    // Coastal Fire Centre, southwest sector (Metro Vancouver through Sea to Sky / Fraser Canyon south).
    $query = add_query_arg(
        array(
            'where'              => "FIRE_CENTRE = 2 AND FIRE_STATUS NOT IN ('Out') AND LATITUDE >= 48.95 AND LATITUDE <= 50.35",
            'outFields'          => 'INCIDENT_NAME,GEOGRAPHIC_DESCRIPTION,FIRE_STATUS,FIRE_URL,IGNITION_DATE',
            'returnGeometry'     => 'false',
            'resultRecordCount'  => '60',
            'orderByFields'      => 'IGNITION_DATE DESC',
            'f'                  => 'json',
        ),
        'https://delivery.maps.gov.bc.ca/arcgis/rest/services/mpcm/bcgwpub/MapServer/502/query'
    );

    $response = wp_remote_get(
        $query,
        array(
            'timeout' => 15,
            'headers' => array( 'Accept' => 'application/json' ),
        )
    );

    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        set_transient( 'se_bc_wildfire_near_yvr_error', 1, 2 * MINUTE_IN_SECONDS );
        return array();
    }

    delete_transient( 'se_bc_wildfire_near_yvr_error' );

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || empty( $body['features'] ) || ! is_array( $body['features'] ) ) {
        set_transient( 'se_bc_wildfire_near_yvr', array(), 5 * MINUTE_IN_SECONDS );
        return array();
    }

    $out = array();
    foreach ( $body['features'] as $feature ) {
        if ( ! is_array( $feature ) ) {
            continue;
        }
        $row = $feature['attributes'] ?? array();
        if ( ! is_array( $row ) ) {
            continue;
        }
        $name   = se_broadcaster_wildfire_name( $row );
        $status = wp_strip_all_tags( (string) ( $row['FIRE_STATUS'] ?? '' ) );
        $url    = esc_url_raw( (string) ( $row['FIRE_URL'] ?? 'https://wildfiresituation.nrs.gov.bc.ca/map' ) );
        $ignition_ms = is_numeric( $row['IGNITION_DATE'] ?? null ) ? (float) $row['IGNITION_DATE'] : 0;
        if ( $ignition_ms < 1.5e12 ) {
            $ignition_ms = 0;
        }
        $ignition_iso = $ignition_ms ? gmdate( 'c', (int) round( $ignition_ms / 1000 ) ) : '';
        $ignition_ts  = $ignition_ms ? (int) round( $ignition_ms / 1000 ) : 0;
        $ignition_label = $ignition_ms ? se_broadcaster_format_arcgis_ms( $ignition_ms ) : '';
        $age_label    = se_broadcaster_wildfire_age_label( $ignition_ts, $ignition_label );

        $out[] = array(
            'name'           => $name,
            'status'         => $status,
            'url'            => $url,
            'ignition'       => (string) ( $row['IGNITION_DATE'] ?? '' ),
            'posted'         => $ignition_iso,
            'ignition_label' => $ignition_label,
            'age_label'      => $age_label,
        );
    }

    usort(
        $out,
        static function ( array $a, array $b ): int {
            $ta = strtotime( (string) ( $a['posted'] ?? '' ) ) ?: 0;
            $tb = strtotime( (string) ( $b['posted'] ?? '' ) ) ?: 0;
            return $tb <=> $ta;
        }
    );

    set_transient( 'se_bc_wildfire_near_yvr', $out, 5 * MINUTE_IN_SECONDS );
    return $out;
}

function se_broadcaster_wildfire_script(): array {
    $fires = se_fetch_bc_wildfire_near_yvr();
    if ( ! $fires ) {
        if ( get_transient( 'se_bc_wildfire_near_yvr_error' ) ) {
            return array(
                'caption'       => 'BC Wildfire feed temporarily unavailable.',
                'script'        => 'BC Wildfire Service data did not load. Try again in a minute.',
                'source'        => 'BC Wildfire Service',
                'source_url'    => 'https://wildfiresituation.nrs.gov.bc.ca/map',
                'items'         => array(),
                'fetched_label' => 'Pulled ' . wp_date( 'M j, Y g:i a T' ),
            );
        }

        return array(
            'caption'       => 'No active wildfires in the southwest Coastal corridor.',
            'script'        => 'BC Wildfire Service reports no active fires in the southwest Coastal corridor.',
            'source'        => 'BC Wildfire Service',
            'source_url'    => 'https://wildfiresituation.nrs.gov.bc.ca/map',
            'items'         => array(),
            'fetched_label' => 'Pulled ' . wp_date( 'M j, Y g:i a T' ),
        );
    }

    $bulletin_fires = se_broadcaster_wildfire_pick_for_bulletin( $fires, 3 );
    $items          = array();
    foreach ( $bulletin_fires as $fire ) {
        $line = se_broadcaster_wildfire_line( $fire );
        $items[] = array(
            'title'        => se_broadcaster_trim_script( $fire['name'], 80 ),
            'text'         => se_broadcaster_trim_script( $line, 200 ),
            'posted'       => (string) ( $fire['posted'] ?? $fire['ignition'] ?? '' ),
            'posted_label' => (string) ( $fire['age_label'] ?? '' ),
            'url'          => (string) ( $fire['url'] ?? '' ),
            'link_label'   => 'BC Wildfire incident',
        );
    }

    return se_broadcaster_pack_from_items(
        $items,
        se_broadcaster_wildfire_summary_prefix( $fires ),
        'BC Wildfire Service',
        'https://wildfiresituation.nrs.gov.bc.ca/map'
    );
}

function se_fetch_ec_aqhi_metro(): array {
    $cached = get_transient( 'se_ec_aqhi_metro' );
    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    $bbox = '-123.4,49.1,-122.5,49.4';
    $response = wp_remote_get(
        'https://api.weather.gc.ca/collections/aqhi-observations-realtime/items?bbox=' . rawurlencode( $bbox ) . '&latest=true&limit=20',
        array(
            'timeout' => 15,
            'headers' => array( 'Accept' => 'application/geo+json' ),
        )
    );

    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return array();
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || empty( $body['features'] ) || ! is_array( $body['features'] ) ) {
        set_transient( 'se_ec_aqhi_metro', array(), 5 * MINUTE_IN_SECONDS );
        return array();
    }

    $out = array();
    $seen = array();
    foreach ( $body['features'] as $feature ) {
        if ( ! is_array( $feature ) ) {
            continue;
        }
        $props = $feature['properties'] ?? array();
        if ( ! is_array( $props ) ) {
            continue;
        }
        $lid = (string) ( $props['location_id'] ?? '' );
        if ( $lid && isset( $seen[ $lid ] ) ) {
            continue;
        }
        if ( $lid ) {
            $seen[ $lid ] = true;
        }
        $aqhi = isset( $props['aqhi'] ) ? (float) $props['aqhi'] : 0;
        $out[] = array(
            'location'       => wp_strip_all_tags( (string) ( $props['location_name_en'] ?? 'Metro Vancouver' ) ),
            'aqhi'           => $aqhi,
            'risk'           => se_aqhi_risk_label( $aqhi ),
            'observed'       => (string) ( $props['observation_datetime'] ?? '' ),
            'observed_label' => wp_strip_all_tags( (string) ( $props['observation_datetime_text_en'] ?? '' ) ),
        );
    }

    usort(
        $out,
        static function ( array $a, array $b ): int {
            return ( $b['aqhi'] ?? 0 ) <=> ( $a['aqhi'] ?? 0 );
        }
    );

    set_transient( 'se_ec_aqhi_metro', $out, 5 * MINUTE_IN_SECONDS );
    return $out;
}

function se_broadcaster_air_script(): array {
    $readings = se_fetch_ec_aqhi_metro();
    if ( ! $readings ) {
        return array(
            'caption'       => 'Metro Vancouver AQHI unavailable.',
            'script'        => 'Environment Canada air quality data is offline right now. Check metrovancouver.org for AQHI.',
            'source'        => 'Environment Canada',
            'source_url'    => 'https://metrovancouver.org/services/air-quality-climate-action/air-quality-data-and-advisories',
            'items'         => array(),
            'fetched_label' => 'Pulled ' . wp_date( 'M j, Y g:i a T' ),
        );
    }

    $items = array();
    foreach ( array_slice( $readings, 0, 4 ) as $row ) {
        $aqhi_round = round( (float) $row['aqhi'], 1 );
        $line       = $row['location'] . ', AQHI ' . $aqhi_round . ', ' . $row['risk'];
        $items[] = array(
            'title'        => se_broadcaster_trim_script( $row['location'], 80 ),
            'text'         => se_broadcaster_trim_script( $line, 160 ),
            'posted'       => (string) ( $row['observed'] ?? '' ),
            'posted_label' => (string) ( $row['observed_label'] ?? '' ),
            'url'          => 'https://metrovancouver.org/services/air-quality-climate-action/air-quality-data-and-advisories',
            'link_label'   => 'Metro Vancouver AQ',
        );
    }

    $max = $readings[0];
    $prefix = 'Metro Vancouver air quality. Highest reading ' . round( (float) $max['aqhi'], 1 ) . ' at ' . $max['location'] . '. ';

    return se_broadcaster_pack_from_items(
        $items,
        $prefix,
        'Environment Canada AQHI',
        'https://metrovancouver.org/services/air-quality-climate-action/air-quality-data-and-advisories'
    );
}

function se_get_broadcaster_feeds_rest( WP_REST_Request $request ): WP_REST_Response {
    if ( $request->get_param( 'refresh' ) ) {
        delete_transient( 'se_translink_alerts' );
        delete_transient( 'se_open511_bc_events' );
        delete_transient( 'se_bc_ferries_capacity' );
        delete_transient( 'se_ec_weather_alerts' );
        delete_transient( 'se_bc_wildfire_near_yvr' );
        delete_transient( 'se_bc_wildfire_near_yvr_error' );
        delete_transient( 'se_ec_aqhi_metro' );
    }

    return rest_ensure_response(
        array(
            'updated'       => current_time( 'mysql' ),
            'fetched_label' => 'Pulled ' . wp_date( 'M j, Y g:i a T' ),
            'translink'     => se_broadcaster_translink_script(),
            'drivers'       => se_broadcaster_drivers_script(),
            'ferries'       => se_broadcaster_ferries_script(),
            'weather'       => se_broadcaster_weather_script(),
            'wildfire'      => se_broadcaster_wildfire_script(),
            'air'           => se_broadcaster_air_script(),
        )
    );
}

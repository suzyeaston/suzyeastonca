<?php
/**
 * Vancouver Tech Events aggregation helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Returns a list of external event sources for Vancouver tech / music events.
 *
 * @return array<int, array<string, mixed>>
 */
function suzy_get_vancouver_tech_event_sources(): array {
    return [
        [
            'id'     => 'bctech_tnet',
            'label'  => 'BC Tech / T-Net Events',
            'url'    => 'https://www.bctechnology.com/scripts/event_rss.cfm',
            'source' => 'BC Tech / T-Net',
            'format' => 'rss',
        ],
        // Meetup ICS feeds: add more groups by appending their ICS URLs below.
        // Format: https://www.meetup.com/<group-name>/events/ical/ or organizer ICS.
        [
            'id'     => 'meetup_placeholder',
            'label'  => 'Meetup: Sample Vancouver Tech Group',
            'url'    => 'https://www.meetup.com/sample-vancouver-tech-group/events/ical/',
            'source' => 'Meetup',
            'format' => 'ics',
        ],
        [
            'id'     => 'eventbrite_api',
            'label'  => 'Eventbrite – Vancouver Tech',
            'url'    => 'https://www.eventbriteapi.com/v3/events/search/',
            'source' => 'Eventbrite',
            'format' => 'eventbrite_api',
        ],
        // Vancouver Tech Journal / Luma JSON API
        [
            'id'     => 'vtj_luma',
            'label'  => 'Vancouver Tech Journal (Luma)',
            // Base calendar API endpoint from Luma:
            // GET https://api2.luma.com/calendar/get?api_id=cal-i2SXCQcJZBMq8NN
            'url'    => 'https://api2.luma.com/calendar/get?api_id=cal-i2SXCQcJZBMq8NN',
            'source' => 'Vancouver Tech Journal',
            'format' => 'json_luma',
        ],
        // Optional: more Luma calendars can be added later with format "json_luma".
    ];
}

/**
 * Fetches raw events from all configured sources and normalizes them.
 *
 * @return array<int, array<string, mixed>>
 */
function suzy_fetch_vancouver_tech_events_raw( bool $debug = false, array &$debug_report = [] ): array {
    $sources = suzy_get_vancouver_tech_event_sources();
    $events  = [];

    foreach ( $sources as $source ) {
        $format       = isset( $source['format'] ) ? $source['format'] : 'rss';
        $source_label = $source['label'] ?? ( $source['id'] ?? 'unknown source' );

        switch ( $format ) {
            case 'rss':
                $source_events = suzy_fetch_vancouver_tech_events_from_rss( $source, $debug );
                break;
            case 'ics':
                $source_events = suzy_fetch_vancouver_tech_events_from_ics( $source, $debug );
                break;
            case 'json_luma':
                $source_events = suzy_fetch_vancouver_tech_events_from_luma_json( $source, $debug );
                break;
            case 'eventbrite_api':
                $source_events = suzy_fetch_vancouver_tech_events_from_eventbrite_api( $source, $debug );
                break;
            default:
                $source_events = new WP_Error( 'vte_unknown_format', 'Unknown event source format.' );
                break;
        }

        $debug_entry = [
            'id'      => $source['id'] ?? '',
            'label'   => $source_label,
            'format'  => $format,
            'status'  => 'ok',
            'count'   => 0,
            'message' => '',
        ];

        if ( is_wp_error( $source_events ) ) {
            $debug_entry['status']  = 'error';
            $debug_entry['message'] = $source_events->get_error_message();
            $source_events          = [];
        }

        if ( ! empty( $source_events ) && is_array( $source_events ) ) {
            $events                 = array_merge( $events, $source_events );
            $debug_entry['count']   = count( $source_events );
        } else {
            if ( empty( $debug_entry['message'] ) ) {
                $debug_entry['message'] = 'No events returned.';
            }
            $debug_entry['status'] = 'empty';
        }

        if ( $debug ) {
            $debug_report[] = $debug_entry;
        }
    }

    return $events;
}

/**
 * Fetch events from an RSS feed.
 *
 * @param array<string, mixed> $source Source configuration.
 * @return array<int, array<string, mixed>>
 */
function suzy_fetch_vancouver_tech_events_from_rss( array $source, bool $debug = false ) {
    if ( empty( $source['url'] ) ) {
        return [];
    }

    require_once ABSPATH . WPINC . '/feed.php';

    $feed = fetch_feed( $source['url'] );

    if ( is_wp_error( $feed ) ) {
        return $debug ? $feed : [];
    }

    $max_items = $feed->get_item_quantity( 15 );
    $items     = $feed->get_items( 0, $max_items );
    $events    = [];

    if ( empty( $items ) ) {
        return [];
    }

    foreach ( $items as $item ) {
        $title = $item->get_title();
        $start = $item->get_date( 'U' );
        $link  = $item->get_permalink();

        $location = null;
        if ( method_exists( $item, 'get_item_tags' ) ) {
            $location_tags = $item->get_item_tags( '', 'location' );
            if ( ! empty( $location_tags[0]['data'] ) ) {
                $location = $location_tags[0]['data'];
            }
        }

        if ( empty( $title ) || empty( $start ) ) {
            continue;
        }

        $events[] = [
            'title'    => (string) $title,
            'start'    => (int) $start,
            'end'      => null,
            'location' => $location ? (string) $location : null,
            'url'      => $link ? (string) $link : '',
            'source'   => isset( $source['source'] ) ? (string) $source['source'] : '',
        ];
    }

    return $events;
}

/**
 * Fetch events from an ICS feed.
 *
 * Placeholder implementation for future ICS feeds.
 *
 * @param array<string, mixed> $source Source configuration.
 * @return array<int, array<string, mixed>>
 */
function suzy_fetch_vancouver_tech_events_from_ics( array $source, bool $debug = false ) {
    if ( empty( $source['url'] ) ) {
        return [];
    }

    $transient_key = 'suzy_vte_ics_' . md5( $source['url'] );

    if ( ! $debug ) {
        $cached = get_transient( $transient_key );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }
    }

    $response = wp_remote_get(
        $source['url'],
        [
            'timeout' => 12,
            'headers' => [
                'User-Agent' => 'suzy-vancouver-tech-events/1.0',
                'Accept'     => 'text/calendar, */*',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $debug ? $response : [];
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return [];
    }

    $lines          = preg_split( '/\r\n|\r|\n/', $body );
    $unfolded_lines = [];
    foreach ( $lines as $line ) {
        if ( '' === $line ) {
            continue;
        }
        if ( isset( $unfolded_lines[ count( $unfolded_lines ) - 1 ] ) && ( str_starts_with( $line, ' ' ) || str_starts_with( $line, "\t" ) ) ) {
            $unfolded_lines[ count( $unfolded_lines ) - 1 ] .= ltrim( $line );
        } else {
            $unfolded_lines[] = $line;
        }
    }

    $events          = [];
    $current_event   = [];
    $in_event        = false;
    $timezone_string = wp_timezone_string() ?: 'America/Vancouver';

    foreach ( $unfolded_lines as $line ) {
        if ( 'BEGIN:VEVENT' === trim( $line ) ) {
            $in_event      = true;
            $current_event = [];
            continue;
        }

        if ( 'END:VEVENT' === trim( $line ) ) {
            if ( ! empty( $current_event['SUMMARY'] ) && ! empty( $current_event['DTSTART'] ) ) {
                $events[] = [
                    'title'    => (string) $current_event['SUMMARY'],
                    'start'    => (int) $current_event['DTSTART'],
                    'end'      => isset( $current_event['DTEND'] ) ? (int) $current_event['DTEND'] : null,
                    'location' => isset( $current_event['LOCATION'] ) ? (string) $current_event['LOCATION'] : null,
                    'url'      => isset( $current_event['URL'] ) ? (string) $current_event['URL'] : '',
                    'source'   => isset( $source['source'] ) ? (string) $source['source'] : '',
                ];
            }

            $in_event      = false;
            $current_event = [];
            continue;
        }

        if ( ! $in_event ) {
            continue;
        }

        $parts = explode( ':', $line, 2 );
        if ( count( $parts ) < 2 ) {
            continue;
        }

        [$property, $value] = $parts;

        $property = trim( $property );
        $value    = trim( $value );

        if ( str_starts_with( $property, 'DTSTART' ) ) {
            $current_event['DTSTART'] = suzy_vte_parse_ics_datetime( $property, $value, $timezone_string );
            continue;
        }

        if ( str_starts_with( $property, 'DTEND' ) ) {
            $current_event['DTEND'] = suzy_vte_parse_ics_datetime( $property, $value, $timezone_string );
            continue;
        }

        $property_key = strtoupper( $property );

        switch ( $property_key ) {
            case 'SUMMARY':
                $current_event['SUMMARY'] = $value;
                break;
            case 'LOCATION':
                $current_event['LOCATION'] = $value;
                break;
            case 'URL':
            case 'X-MICROSOFT-SKYPETEAMSMEETINGURL':
                $current_event['URL'] = $value;
                break;
        }
    }

    if ( ! $debug ) {
        set_transient( $transient_key, $events, 30 * MINUTE_IN_SECONDS );
    }

    return $events;
}

/**
 * Parse an ICS datetime line into a Unix timestamp.
 *
 * @param string $property        The property with optional parameters (e.g., DTSTART;TZID=America/Vancouver).
 * @param string $value           The datetime value.
 * @param string $fallback_tz_str Fallback timezone string.
 * @return int|null
 */
function suzy_vte_parse_ics_datetime( string $property, string $value, string $fallback_tz_str ) {
    $tzid = null;

    if ( str_contains( $property, 'TZID=' ) ) {
        $property_parts = explode( ';', $property );
        foreach ( $property_parts as $part ) {
            if ( str_starts_with( $part, 'TZID=' ) ) {
                $tzid = substr( $part, 5 );
                break;
            }
        }
    }

    $value = trim( $value );

    $format = 'Ymd\THis';
    $tz     = null;

    if ( str_ends_with( $value, 'Z' ) ) {
        $format = 'Ymd\THis\Z';
        $tz     = new DateTimeZone( 'UTC' );
    } elseif ( $tzid ) {
        try {
            $tz = new DateTimeZone( $tzid );
        } catch ( Exception $e ) {
            $tz = null;
        }
    }

    if ( null === $tz ) {
        try {
            $tz = new DateTimeZone( $fallback_tz_str );
        } catch ( Exception $e ) {
            $tz = wp_timezone();
        }
    }

    // Date-only values.
    if ( preg_match( '/^\d{8}$/', $value ) ) {
        $format = 'Ymd';
    }

    $dt = DateTime::createFromFormat( $format, $value, $tz );
    if ( ! $dt ) {
        // Fallback: try strtotime.
        $fallback = strtotime( $value );
        return false !== $fallback ? $fallback : null;
    }

    return $dt->getTimestamp();
}

/**
 * Fetch events from a Luma JSON calendar endpoint.
 *
 * @param array<string, mixed> $source Source configuration.
 * @return array<int, array<string, mixed>>
 */
function suzy_fetch_vancouver_tech_events_from_luma_json( array $source, bool $debug = false ) {
    if ( empty( $source['url'] ) ) {
        return [];
    }

    $headers = [
        'Accept'     => 'application/json',
        'User-Agent' => 'suzy-vancouver-tech-events/1.0',
    ];

    $response = wp_remote_get(
        $source['url'],
        [
            'timeout' => 12,
            'headers' => $headers,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $debug ? $response : [];
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return [];
    }

    $data = json_decode( $body, true );

    if ( null === $data || ! is_array( $data ) ) {
        return [];
    }

    $calendar_api_id = $data['data']['calendar']['api_id'] ?? ( $data['calendar']['api_id'] ?? null );
    $api_id          = $data['data']['calendar_api_id'] ?? ( $data['calendar_api_id'] ?? null );

    if ( ! $calendar_api_id && $api_id && str_starts_with( $api_id, 'cal-' ) ) {
        $calendar_api_id = $api_id;
    }

    $pending_events = [];

    if ( $calendar_api_id ) {
        $pending_url  = 'https://api2.luma.com/calendar/get-pending-items?calendar_api_id=' . rawurlencode( $calendar_api_id );
        $pending_resp = wp_remote_get(
            $pending_url,
            [
                'timeout' => 12,
                'headers' => $headers,
            ]
        );

        if ( ! is_wp_error( $pending_resp ) ) {
            $pending_body = wp_remote_retrieve_body( $pending_resp );
            $pending_data = $pending_body ? json_decode( $pending_body, true ) : null;
            if ( is_array( $pending_data ) ) {
                $pending_events = $pending_data['items'] ?? ( $pending_data['data']['items'] ?? [] );
            }
        }
    }

    $events_data = [];

    if ( ! empty( $pending_events ) && is_array( $pending_events ) ) {
        foreach ( $pending_events as $pending ) {
            $events_data[] = suzy_vte_extract_luma_event( $pending );
        }

        $events_data = array_filter( $events_data );
    }

    if ( empty( $events_data ) ) {
        $events_data_raw = [];
        if ( isset( $data['data']['events'] ) && is_array( $data['data']['events'] ) ) {
            $events_data_raw = $data['data']['events'];
        } elseif ( isset( $data['events'] ) && is_array( $data['events'] ) ) {
            $events_data_raw = $data['events'];
        } elseif ( isset( $data['calendar']['events'] ) && is_array( $data['calendar']['events'] ) ) {
            $events_data_raw = $data['calendar']['events'];
        }

        foreach ( $events_data_raw as $event_raw ) {
            $events_data[] = suzy_vte_extract_luma_event( $event_raw );
        }

        $events_data = array_filter( $events_data );
    }

    $events = [];

    foreach ( $events_data as $event ) {
        $title   = $event['title'] ?? '';
        $start   = $event['start'] ?? null;
        $end     = $event['end'] ?? null;
        $url     = $event['url'] ?? '';
        $loc_str = $event['location'] ?? null;

        if ( empty( $title ) || empty( $start ) ) {
            continue;
        }

        $events[] = [
            'title'    => (string) $title,
            'start'    => (int) $start,
            'end'      => $end ? (int) $end : null,
            'location' => $loc_str ? (string) $loc_str : null,
            'url'      => (string) $url,
            'source'   => isset( $source['source'] ) ? (string) $source['source'] : '',
        ];
    }

    return $events;
}

/**
 * Extracts Luma event data from nested structures and normalizes timestamps.
 *
 * @param array<string, mixed> $event_raw Raw event data from Luma.
 * @return array<string, mixed>|null
 */
function suzy_vte_extract_luma_event( array $event_raw ) {
    $event = $event_raw;

    if ( isset( $event_raw['event'] ) && is_array( $event_raw['event'] ) ) {
        $event = $event_raw['event'];
    } elseif ( isset( $event_raw['data']['event'] ) && is_array( $event_raw['data']['event'] ) ) {
        $event = $event_raw['data']['event'];
    }

    $title = $event['name'] ?? ( $event['title'] ?? '' );

    $start_raw = $event['start_time'] ?? ( $event['start_at'] ?? ( $event['start'] ?? null ) );
    $end_raw   = $event['end_time'] ?? ( $event['end_at'] ?? ( $event['end'] ?? null ) );

    $url = $event['url'] ?? ( $event['event_url'] ?? ( $event['link'] ?? '' ) );

    $location = null;
    if ( isset( $event['location'] ) && is_string( $event['location'] ) ) {
        $location = $event['location'];
    } elseif ( isset( $event['venue']['name'] ) ) {
        $location = $event['venue']['name'];
    }

    $start_ts = suzy_vte_luma_ts_to_unix( $start_raw );
    $end_ts   = suzy_vte_luma_ts_to_unix( $end_raw );

    if ( empty( $title ) || null === $start_ts ) {
        return null;
    }

    return [
        'title'    => (string) $title,
        'start'    => (int) $start_ts,
        'end'      => $end_ts ? (int) $end_ts : null,
        'location' => $location ? (string) $location : null,
        'url'      => (string) $url,
    ];
}

/**
 * Converts Luma timestamps (seconds or milliseconds) or date strings into Unix timestamps.
 *
 * @param mixed $value Raw timestamp.
 * @return int|null
 */
function suzy_vte_luma_ts_to_unix( $value ) {
    if ( null === $value || '' === $value ) {
        return null;
    }

    if ( is_numeric( $value ) ) {
        $int_val = (int) $value;
        // Detect milliseconds.
        if ( $int_val > 2000000000 ) {
            $int_val = (int) floor( $int_val / 1000 );
        }

        return $int_val;
    }

    $ts = strtotime( (string) $value );

    return false !== $ts ? $ts : null;
}

/**
 * Fetch events from Eventbrite API.
 *
 * @param array<string, mixed> $source Source configuration.
 * @param bool                 $debug  Whether debug mode is enabled.
 * @return array<int, array<string, mixed>>|WP_Error
 */
function suzy_fetch_vancouver_tech_events_from_eventbrite_api( array $source, bool $debug = false ) {
    $token = null;

    if ( defined( 'EVENTBRITE_OAUTH_TOKEN' ) && EVENTBRITE_OAUTH_TOKEN ) {
        $token = EVENTBRITE_OAUTH_TOKEN;
    } else {
        $option_token = get_option( 'suzy_eventbrite_oauth_token' );
        if ( ! empty( $option_token ) ) {
            $token = $option_token;
        }
    }

    if ( ! $token ) {
        return $debug ? new WP_Error( 'eventbrite_missing_token', 'Eventbrite OAuth token not configured. Define EVENTBRITE_OAUTH_TOKEN or set option suzy_eventbrite_oauth_token.' ) : [];
    }

    $transient_key = 'suzy_vte_eventbrite_cache';

    if ( ! $debug ) {
        $cached = get_transient( $transient_key );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }
    }

    $url      = $source['url'] ?? 'https://www.eventbriteapi.com/v3/events/search/';
    $params   = [
        'location.address'        => 'Vancouver, BC',
        'sort_by'                 => 'date',
        'expand'                  => 'venue',
        'categories'              => '102', // Science & Tech
        'q'                       => 'tech',
        'start_date.range_start'  => gmdate( 'Y-m-d\TH:i:s\Z' ),
        'page_size'               => 50,
    ];
    $url_with_params = add_query_arg( $params, $url );

    $response = wp_remote_get(
        $url_with_params,
        [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'User-Agent'    => 'suzy-vancouver-tech-events/1.0',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $debug ? $response : [];
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return [];
    }

    $data = json_decode( $body, true );
    if ( ! is_array( $data ) || empty( $data['events'] ) || ! is_array( $data['events'] ) ) {
        return [];
    }

    $events = [];
    foreach ( $data['events'] as $event ) {
        $title = $event['name']['text'] ?? '';
        $url   = $event['url'] ?? '';

        $start_raw = $event['start']['utc'] ?? null;
        $end_raw   = $event['end']['utc'] ?? null;

        $start_ts = $start_raw ? strtotime( $start_raw ) : null;
        $end_ts   = $end_raw ? strtotime( $end_raw ) : null;

        $location = null;
        if ( isset( $event['venue']['address']['localized_address_display'] ) ) {
            $location = $event['venue']['address']['localized_address_display'];
        } elseif ( isset( $event['venue']['name'] ) ) {
            $location = $event['venue']['name'];
        }

        if ( empty( $title ) || null === $start_ts ) {
            continue;
        }

        $events[] = [
            'title'    => (string) $title,
            'start'    => (int) $start_ts,
            'end'      => $end_ts ? (int) $end_ts : null,
            'location' => $location ? (string) $location : null,
            'url'      => (string) $url,
            'source'   => 'Eventbrite',
        ];
    }

    if ( ! $debug ) {
        set_transient( $transient_key, $events, 45 * MINUTE_IN_SECONDS );
    }

    return $events;
}

/**
 * Returns a cached list of upcoming Vancouver tech events.
 *
 * @return array<int, array<string, mixed>>
 */
function suzy_get_vancouver_tech_events(): array {
    $debug          = ( isset( $_GET['vte_debug'] ) && '1' === $_GET['vte_debug'] ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
    $transient_key  = 'suzy_vancouver_tech_events_cache';
    $cached         = $debug ? false : get_transient( $transient_key );
    $debug_report   = [];
    $cache_bypassed = $debug;

    if ( false !== $cached && is_array( $cached ) ) {
        return [
            'events'         => $cached,
            'debug'          => $debug ? $debug_report : null,
            'cache_bypassed' => $cache_bypassed,
        ];
    }

    $events = suzy_fetch_vancouver_tech_events_raw( $debug, $debug_report );

    $now = time();

    // Filter out events that are clearly in the past (older than 6 hours ago).
    $events = array_filter(
        $events,
        static function ( $event ) use ( $now ) {
            if ( ! isset( $event['start'] ) ) {
                return false;
            }

            return (int) $event['start'] >= ( $now - ( 6 * HOUR_IN_SECONDS ) );
        }
    );

    // Basic dedupe across sources.
    $seen_keys = [];
    $deduped   = [];

    foreach ( $events as $event ) {
        $title     = strtolower( trim( $event['title'] ?? '' ) );
        $start     = isset( $event['start'] ) ? (int) $event['start'] : 0;
        $location  = isset( $event['location'] ) ? strtolower( trim( (string) $event['location'] ) ) : '';
        $rounded   = (int) round( $start / 600 ) * 600;
        $dedupe_id = $title . '|' . $rounded . '|' . $location;

        if ( isset( $seen_keys[ $dedupe_id ] ) ) {
            continue;
        }

        $seen_keys[ $dedupe_id ] = true;
        $deduped[]               = $event;
    }

    $events = $deduped;

    // Sort ascending by start time.
    usort(
        $events,
        static function ( $a, $b ) {
            $a_start = isset( $a['start'] ) ? (int) $a['start'] : 0;
            $b_start = isset( $b['start'] ) ? (int) $b['start'] : 0;

            return $a_start <=> $b_start;
        }
    );

    // Limit to 50 upcoming events.
    if ( count( $events ) > 50 ) {
        $events = array_slice( $events, 0, 50 );
    }

    if ( ! $debug ) {
        // Cache for 15 minutes.
        set_transient( $transient_key, $events, 15 * MINUTE_IN_SECONDS );
    }

    return [
        'events'         => $events,
        'debug'          => $debug ? $debug_report : null,
        'cache_bypassed' => $cache_bypassed,
    ];
}

/**
 * Render events markup for the Vancouver Tech Events list.
 *
 * @param array<int, array<string, mixed>>|null $events Optional pre-fetched events.
 * @return string
 */
function suzy_render_vancouver_tech_events_html( ?array $events = null ): string {
    if ( null === $events ) {
        $events = suzy_get_vancouver_tech_events();
    }

    $debug_data      = null;
    $cache_bypassed  = false;
    if ( isset( $events['events'] ) ) {
        $debug_data     = $events['debug'] ?? null;
        $cache_bypassed = ! empty( $events['cache_bypassed'] );
        $events         = $events['events'];
    }

    ob_start();
    ?>
    <section class="vancouver-tech-events">
        <h1>Vancouver Tech Events</h1>
        <p>Aggregated from multiple community sources (Meetup ICS, Eventbrite, BC Tech / T-Net, Luma calendars).</p>

        <?php if ( empty( $events ) ) : ?>
            <p>No upcoming Vancouver tech events found right now. Check back soon!</p>
        <?php else : ?>
            <?php
            $events_by_date = [];
            foreach ( $events as $event ) {
                $start = isset( $event['start'] ) ? (int) $event['start'] : time();
                $date_key = wp_date( 'Y-m-d', $start );
                if ( ! isset( $events_by_date[ $date_key ] ) ) {
                    $events_by_date[ $date_key ] = [];
                }
                $events_by_date[ $date_key ][] = $event;
            }
            ksort( $events_by_date );
            ?>

            <?php foreach ( $events_by_date as $date_key => $date_events ) : ?>
                <h2 class="vte-date">
                    <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $date_key ) ) ); ?>
                </h2>
                <ul class="vte-event-list">
                    <?php foreach ( $date_events as $event ) : ?>
                        <li class="vte-event">
                            <a href="<?php echo esc_url( $event['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="vte-title">
                                <?php echo esc_html( $event['title'] ); ?>
                            </a>
                            <div class="vte-meta">
                                <?php if ( isset( $event['start'] ) ) : ?>
                                    <span class="vte-time">
                                        <?php echo esc_html( wp_date( get_option( 'time_format' ), (int) $event['start'] ) ); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ( ! empty( $event['location'] ) ) : ?>
                                    <span class="vte-location"><?php echo esc_html( $event['location'] ); ?></span>
                                <?php endif; ?>
                                <span class="vte-source"><?php echo esc_html( $event['source'] ?? '' ); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ( ! empty( $debug_data ) ) : ?>
            <div class="vte-debug" style="margin-top:2rem; padding:1rem; border:1px dashed #888; background:#111; color:#ddd;">
                <strong>DEBUG</strong>
                <p style="margin:0.5rem 0;">Cache bypassed: <?php echo $cache_bypassed ? 'yes' : 'no'; ?></p>
                <ul style="list-style: disc; padding-left:1.2rem; margin:0;">
                    <?php foreach ( $debug_data as $entry ) : ?>
                        <li>
                            <strong><?php echo esc_html( $entry['label'] ?? 'Source' ); ?></strong>
                            — status: <?php echo esc_html( $entry['status'] ?? 'unknown' ); ?>,
                            count: <?php echo isset( $entry['count'] ) ? (int) $entry['count'] : 0; ?>
                            <?php if ( ! empty( $entry['message'] ) ) : ?>
                                <br><small><?php echo esc_html( $entry['message'] ); ?></small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </section>
    <?php

    return ob_get_clean();
}

/**
 * Shortcode to render Vancouver Tech Events list.
 *
 * @return string
 */
function suzy_vancouver_tech_events_shortcode(): string {
    return suzy_render_vancouver_tech_events_html();
}
add_shortcode( 'vancouver_tech_events', 'suzy_vancouver_tech_events_shortcode' );

<?php
/**
 * Vancouver Tech Events aggregation helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Returns a list of external event sources for Vancouver tech events.
 *
 * @return array<int, array<string, mixed>>
 */
function suzy_get_vancouver_tech_event_sources(): array {
    return [
        [
            'id'     => 'meetup_techvan',
            'label'  => 'Meetup: TechVAN',
            'url'    => 'https://www.meetup.com/techvancouverorg/events/ical/',
            'source' => 'Meetup TechVAN',
            'format' => 'ics',
        ],
        [
            'id'     => 'meetup_vandev',
            'label'  => 'Meetup: Vancouver Developers',
            'url'    => 'https://www.meetup.com/vancouver-developer-meetup/events/ical/',
            'source' => 'Meetup Vancouver Developers',
            'format' => 'ics',
        ],
        [
            'id'     => 'meetup_aws',
            'label'  => 'Meetup: Vancouver AWS',
            'url'    => 'https://www.meetup.com/vanawsug/events/ical/',
            'source' => 'Meetup Vancouver AWS',
            'format' => 'ics',
        ],
        // Add more Vancouver Meetup ICS feeds here using format "ics".
        [
            'id'     => 'luma_vancouver',
            'label'  => 'Luma Vancouver',
            'url'    => 'https://luma.com/vancouver',
            'source' => 'Luma Vancouver',
            'format' => 'html_luma',
        ],
        [
            'id'     => 'bctech_tnet',
            'label'  => 'BC Tech / T-Net Events',
            'url'    => 'https://www.bctechnology.com/events/',
            'source' => 'BC Tech',
            'format' => 'html_tnet',
        ],
        [
            'id'     => 'meetup_search',
            'label'  => 'Meetup Search Events',
            'url'    => 'https://www.meetup.com/find/ca--vancouver/technology/',
            'source' => 'Meetup Search',
            'format' => 'html_meetup_search',
        ],
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
        $format = isset( $source['format'] ) ? $source['format'] : 'ics';

        switch ( $format ) {
            case 'ics':
                $result = suzy_fetch_vancouver_tech_events_from_ics( $source, $debug );
                break;
            case 'html_luma':
                $result = suzy_fetch_vancouver_tech_events_from_html_luma( $source, $debug );
                break;
            case 'html_tnet':
                $result = suzy_fetch_vancouver_tech_events_from_html_tnet( $source, $debug );
                break;
            case 'html_meetup_search':
                $result = suzy_fetch_vancouver_tech_events_from_html_meetup_search( $source, $debug );
                break;
            default:
                $result = new WP_Error( 'vte_unknown_format', 'Unknown event source format.' );
                break;
        }

        $source_events = [];
        $meta          = [];

        if ( is_array( $result ) ) {
            $source_events = $result['events'] ?? [];
            $meta          = $result['meta'] ?? [];
        }

        $debug_entry = [
            'id'           => $source['id'] ?? '',
            'label'        => $source['label'] ?? ( $source['id'] ?? 'source' ),
            'format'       => $format,
            'status'       => 'ok',
            'count'        => is_array( $source_events ) ? count( $source_events ) : 0,
            'message'      => '',
            'http_status'  => $meta['http_status'] ?? null,
            'content_type' => $meta['content_type'] ?? null,
            'bytes'        => $meta['bytes'] ?? null,
            'parser'       => $meta['parser'] ?? strtoupper( $format ),
        ];

        if ( is_wp_error( $result ) ) {
            $debug_entry['status']  = 'error';
            $debug_entry['message'] = $result->get_error_message();
            $source_events          = [];
        } elseif ( empty( $source_events ) ) {
            $debug_entry['status']  = 'empty';
            $debug_entry['message'] = $debug_entry['message'] ?: 'No events returned.';
        }

        if ( ! empty( $source_events ) && is_array( $source_events ) ) {
            $events = array_merge( $events, $source_events );
        }

        if ( $debug ) {
            $debug_report[] = $debug_entry;
        }
    }

    return $events;
}

/**
 * Fetch events from an ICS feed.
 *
 * @param array<string, mixed> $source Source configuration.
 * @return array<string, mixed>|WP_Error
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
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'suzy-vancouver-tech-events/1.1',
                'Accept'     => 'text/calendar, */*',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $debug ? $response : [];
    }

    $body         = wp_remote_retrieve_body( $response );
    $status_code  = wp_remote_retrieve_response_code( $response );
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    $bytes        = is_string( $body ) ? strlen( $body ) : 0;

    if ( empty( $body ) ) {
        return [
            'events' => [],
            'meta'   => [
                'http_status'  => $status_code,
                'content_type' => $content_type,
                'bytes'        => $bytes,
                'parser'       => 'ICS',
            ],
        ];
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
    $timezone_string = 'America/Vancouver';

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

    $result = [
        'events' => $events,
        'meta'   => [
            'http_status'  => $status_code,
            'content_type' => $content_type,
            'bytes'        => $bytes,
            'parser'       => 'ICS',
        ],
    ];

    if ( ! $debug ) {
        set_transient( $transient_key, $result, 30 * MINUTE_IN_SECONDS );
    }

    return $result;
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
        $fallback = strtotime( $value );
        return false !== $fallback ? $fallback : null;
    }

    return $dt->getTimestamp();
}

/**
 * Fetch events from the Luma Vancouver HTML page and optional event JSON-LD.
 *
 * @param array<string, mixed> $source Source configuration.
 * @return array<string, mixed>|WP_Error
 */
function suzy_fetch_vancouver_tech_events_from_html_luma( array $source, bool $debug = false ) {
    if ( empty( $source['url'] ) ) {
        return [];
    }

    $transient_key = 'suzy_vte_luma_' . md5( $source['url'] );

    if ( ! $debug ) {
        $cached = get_transient( $transient_key );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }
    }

    $response = wp_remote_get(
        $source['url'],
        [
            'timeout' => 15,
            'user-agent' => 'suzy-vancouver-tech-events/1.1',
            'headers'    => [
                'Accept' => 'text/html, */*',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $debug ? $response : [];
    }

    $body         = wp_remote_retrieve_body( $response );
    $status_code  = wp_remote_retrieve_response_code( $response );
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    $bytes        = is_string( $body ) ? strlen( $body ) : 0;

    if ( empty( $body ) ) {
        return [
            'events' => [],
            'meta'   => [
                'http_status'  => $status_code,
                'content_type' => $content_type,
                'bytes'        => $bytes,
                'parser'       => 'HTML Luma',
            ],
        ];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors( true );
    $dom->loadHTML( $body );
    libxml_clear_errors();
    $xpath = new DOMXPath( $dom );

    $events  = [];
    $anchors = $xpath->query( "//a[@href]" );
    $seen    = [];
    $tz      = new DateTimeZone( 'America/Vancouver' );

    foreach ( $anchors as $anchor ) {
        $href = trim( $anchor->getAttribute( 'href' ) );
        if ( empty( $href ) ) {
            continue;
        }

        $absolute_url = suzy_vte_make_absolute_url( $href, $source['url'] );
        if ( empty( $absolute_url ) || isset( $seen[ $absolute_url ] ) ) {
            continue;
        }

        $parsed = wp_parse_url( $absolute_url );
        $host   = $parsed['host'] ?? '';
        $path   = $parsed['path'] ?? '';

        if ( empty( $host ) || ( false === stripos( $host, 'luma.com' ) && false === stripos( $host, 'lu.ma' ) ) ) {
            continue;
        }

        if ( ! preg_match( '#^/(event|events)/#', $path ) && ! preg_match( '#^/[A-Za-z0-9]{6,}$#', $path ) ) {
            continue;
        }

        $seen[ $absolute_url ] = true;

        $title_node = $xpath->query( './/*[self::h2 or self::h3]', $anchor )->item( 0 );
        $title      = $title_node ? trim( $title_node->textContent ) : trim( $anchor->textContent );
        if ( empty( $title ) && $anchor->parentNode ) {
            $title = trim( $anchor->parentNode->textContent );
        }

        // Attempt to extract a date string from the surrounding card text.
        $card_text = $anchor->parentNode ? trim( $anchor->parentNode->textContent ) : '';
        $start     = suzy_vte_parse_human_datetime( $card_text, $tz );

        $detail_data = suzy_vte_fetch_event_json_ld( $absolute_url, $debug );
        if ( $detail_data ) {
            $title = $detail_data['title'] ?: $title;
            $start = $detail_data['start'] ?: $start;
            $end   = $detail_data['end'] ?? null;
            $loc   = $detail_data['location'] ?? null;
        } else {
            $end = null;
            $loc = null;
        }

        if ( empty( $title ) || null === $start ) {
            continue;
        }

        $events[] = [
            'title'    => $title,
            'start'    => (int) $start,
            'end'      => $end ? (int) $end : null,
            'location' => $loc,
            'url'      => $absolute_url,
            'source'   => $source['source'] ?? 'Luma Vancouver',
        ];
    }

    $result = [
        'events' => $events,
        'meta'   => [
            'http_status'  => $status_code,
            'content_type' => $content_type,
            'bytes'        => $bytes,
            'parser'       => 'HTML Luma',
        ],
    ];

    if ( ! $debug ) {
        set_transient( $transient_key, $result, 30 * MINUTE_IN_SECONDS );
    }

    return $result;
}

/**
 * Fetch JSON-LD structured data from an event detail page.
 *
 * @param string $url   Event URL.
 * @param bool   $debug Debug mode.
 * @return array<string, mixed>|null
 */
function suzy_vte_fetch_event_json_ld( string $url, bool $debug = false ) {
    $transient_key = 'suzy_vte_event_detail_' . md5( $url );

    if ( ! $debug ) {
        $cached = get_transient( $transient_key );
        if ( false !== $cached ) {
            return isset( $cached['missing'] ) ? null : $cached;
        }
    }

    $response = wp_remote_get(
        $url,
        [
            'timeout' => 12,
            'headers' => [
                'User-Agent' => 'suzy-vancouver-tech-events/1.1',
                'Accept'     => 'text/html, */*',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return null;
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return null;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors( true );
    $dom->loadHTML( $body );
    libxml_clear_errors();
    $xpath = new DOMXPath( $dom );
    $nodes = $xpath->query( '//script[@type="application/ld+json"]' );

    $tz = new DateTimeZone( 'America/Vancouver' );

    $detail = null;

    foreach ( $nodes as $node ) {
        $json = trim( $node->textContent );
        if ( empty( $json ) ) {
            continue;
        }

        $data = json_decode( $json, true );
        if ( ! $data ) {
            continue;
        }

        if ( isset( $data['@type'] ) && 'Event' === $data['@type'] ) {
            $detail = [
                'title'    => $data['name'] ?? '',
                'start'    => suzy_vte_parse_iso_datetime( $data['startDate'] ?? null, $tz ),
                'end'      => suzy_vte_parse_iso_datetime( $data['endDate'] ?? null, $tz ),
                'location' => suzy_vte_extract_location_from_json_ld( $data['location'] ?? null ),
            ];
            break;
        }

        if ( isset( $data[0] ) && is_array( $data[0] ) ) {
            foreach ( $data as $item ) {
                if ( isset( $item['@type'] ) && 'Event' === $item['@type'] ) {
                    $detail = [
                        'title'    => $item['name'] ?? '',
                        'start'    => suzy_vte_parse_iso_datetime( $item['startDate'] ?? null, $tz ),
                        'end'      => suzy_vte_parse_iso_datetime( $item['endDate'] ?? null, $tz ),
                        'location' => suzy_vte_extract_location_from_json_ld( $item['location'] ?? null ),
                    ];
                    break 2;
                }
            }
        }
    }

    if ( ! $detail ) {
        $title_node = $xpath->query( '//h1' )->item( 0 );
        $title      = $title_node ? trim( $title_node->textContent ) : '';

        $time_nodes = $xpath->query( '//time' );
        $start      = null;
        $end        = null;

        if ( $time_nodes->length > 0 ) {
            $start_attr = $time_nodes->item( 0 )->getAttribute( 'datetime' );
            $start_text = $start_attr ?: trim( $time_nodes->item( 0 )->textContent );
            $start      = $start_attr ? suzy_vte_parse_iso_datetime( $start_attr, $tz ) : suzy_vte_parse_human_datetime( $start_text, $tz );
        }

        if ( $time_nodes->length > 1 ) {
            $end_attr = $time_nodes->item( 1 )->getAttribute( 'datetime' );
            $end_text = $end_attr ?: trim( $time_nodes->item( 1 )->textContent );
            $end      = $end_attr ? suzy_vte_parse_iso_datetime( $end_attr, $tz ) : suzy_vte_parse_human_datetime( $end_text, $tz );
        }

        $location_node = $xpath->query( "//*[contains(@class,'location') or contains(@class,'Location')]" )->item( 0 );
        $location      = $location_node ? trim( $location_node->textContent ) : null;

        if ( $title || null !== $start || $location ) {
            $detail = [
                'title'    => $title,
                'start'    => $start,
                'end'      => $end,
                'location' => $location,
            ];
        }
    }

    if ( ! $debug ) {
        set_transient( $transient_key, $detail ? $detail : [ 'missing' => true ], 30 * MINUTE_IN_SECONDS );
    }

    return $detail;
}

/**
 * Extract a displayable location string from JSON-LD location fields.
 *
 * @param mixed $location Location data.
 * @return string|null
 */
function suzy_vte_extract_location_from_json_ld( $location ): ?string {
    if ( empty( $location ) ) {
        return null;
    }

    if ( is_string( $location ) ) {
        return $location;
    }

    if ( is_array( $location ) ) {
        if ( isset( $location['name'] ) ) {
            return (string) $location['name'];
        }

        if ( isset( $location['address'] ) && is_array( $location['address'] ) ) {
            $parts = [];
            foreach ( [ 'streetAddress', 'addressLocality', 'addressRegion' ] as $key ) {
                if ( ! empty( $location['address'][ $key ] ) ) {
                    $parts[] = $location['address'][ $key ];
                }
            }

            return ! empty( $parts ) ? implode( ', ', $parts ) : null;
        }
    }

    return null;
}

/**
 * Parse ISO8601 date string into timestamp.
 *
 * @param string|null      $value Date string.
 * @param DateTimeZone     $tz    Timezone.
 * @return int|null
 */
function suzy_vte_parse_iso_datetime( ?string $value, DateTimeZone $tz ): ?int {
    if ( empty( $value ) ) {
        return null;
    }

    try {
        $dt = new DateTime( $value, $tz );
        return $dt->getTimestamp();
    } catch ( Exception $e ) {
        $fallback = strtotime( $value );
        return false !== $fallback ? $fallback : null;
    }
}

/**
 * Parse BC Tech / T-Net event datetime from the detail text.
 *
 * Examples seen on the site:
 * - "January 1, 2026 (6:30 PM to 8:30 PM)"
 * - "January 22, 2026 (5 PM to 7 PM)"
 * - "January 12 to 15, 2026."
 */
function suzy_vte_parse_tnet_datetime( string $text, DateTimeZone $tz ): ?int {
    $clean = preg_replace( '/\s+/', ' ', trim( $text ) );
    if ( empty( $clean ) ) {
        return null;
    }

    $date_str = null;

    // Multi-day: "January 12 to 15, 2026"
    if ( preg_match( '/\b([A-Za-z]+)\s+(\d{1,2})\s+to\s+(\d{1,2}),\s*(\d{4})\b/i', $clean, $m ) ) {
        $date_str = sprintf( '%s %d, %d', $m[1], (int) $m[2], (int) $m[4] );
    } elseif ( preg_match( '/\b([A-Za-z]+)\s+(\d{1,2}),\s*(\d{4})\b/i', $clean, $m ) ) {
        // Single day: "January 1, 2026"
        $date_str = sprintf( '%s %d, %d', $m[1], (int) $m[2], (int) $m[3] );
    }

    if ( null === $date_str ) {
        return null;
    }

    // Prefer the first time in a range: "(6:30 PM to 8:30 PM)" or "(5 PM to 7 PM)"
    $time_str = null;
    if ( preg_match( '/\(\s*([0-9]{1,2}(?::[0-9]{2})?\s*[AP]M)\s*(?:to|\-)/i', $clean, $tm ) ) {
        $time_str = trim( $tm[1] );
    } elseif ( preg_match( '/\b([0-9]{1,2}(?::[0-9]{2})?\s*[AP]M)\b/i', $clean, $tm ) ) {
        $time_str = trim( $tm[1] );
    }

    // If no time is present, default to noon to avoid “midnight weirdness”.
    $dt_input = $date_str . ' ' . ( $time_str ? $time_str : '12:00 PM' );

    try {
        $dt = new DateTime( $dt_input, $tz );
        return $dt->getTimestamp();
    } catch ( Exception $e ) {
        return null;
    }
}

/**
 * Fetch BC Tech / T-Net HTML events and parse them.
 *
 * @param array<string, mixed> $source Source configuration.
 * @return array<string, mixed>|WP_Error
 */
function suzy_fetch_vancouver_tech_events_from_html_tnet( array $source, bool $debug = false ) {
    if ( empty( $source['url'] ) ) {
        return [];
    }

    $transient_key = 'suzy_vte_tnet_' . md5( $source['url'] );

    if ( ! $debug ) {
        $cached = get_transient( $transient_key );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }
    }

    $response = wp_remote_get(
        $source['url'],
        [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'suzy-vancouver-tech-events/1.1',
                'Accept'     => 'text/html, */*',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $debug ? $response : [];
    }

    $body         = wp_remote_retrieve_body( $response );
    $status_code  = wp_remote_retrieve_response_code( $response );
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    $bytes        = is_string( $body ) ? strlen( $body ) : 0;

    $events = [];

    if ( ! empty( $body ) ) {
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( $body );
        libxml_clear_errors();
        $xpath = new DOMXPath( $dom );
        $tz    = new DateTimeZone( 'America/Vancouver' );

        $rows = $xpath->query( '//table//tr' );
        foreach ( $rows as $row ) {
            $cells = $row->getElementsByTagName( 'td' );
            if ( $cells->length < 2 ) {
                continue;
            }

            $date_text  = trim( $cells->item( 0 )->textContent );
            $title_node = $cells->item( 1 );
            $title      = trim( $title_node->textContent );
            $link_nodes = $title_node->getElementsByTagName( 'a' );
            $link       = $link_nodes->length > 0 ? $link_nodes->item( 0 )->getAttribute( 'href' ) : '';
            $url        = suzy_vte_make_absolute_url( $link, $source['url'] );

            $details_text = trim( $title_node->textContent );

            // Use the “real” date that includes year/time inside the details.
            $start = suzy_vte_parse_tnet_datetime( $details_text, $tz );

            // Fallback: try combined text if the pattern changes.
            if ( null === $start ) {
                $start = suzy_vte_parse_human_datetime( $date_text . ' ' . $details_text, $tz );
            }

            // Optional: make title cleaner (anchor text usually == actual event title).
            if ( $link_nodes->length > 0 ) {
                $anchor_title = trim( $link_nodes->item( 0 )->textContent );
                if ( ! empty( $anchor_title ) ) {
                    $title = $anchor_title;
                }
            }

            if ( empty( $title ) || null === $start ) {
                continue;
            }

            $events[] = [
                'title'    => $title,
                'start'    => (int) $start,
                'end'      => null,
                'location' => null,
                'url'      => $url,
                'source'   => $source['source'] ?? 'BC Tech',
            ];
        }

        // Fallback: generic event cards.
        if ( empty( $events ) ) {
            $cards = $xpath->query( "//*[contains(@class,'event') or contains(@class,'Event')]");
            foreach ( $cards as $card ) {
                $title_node = $card->getElementsByTagName( 'a' )->item( 0 );
                $title      = $title_node ? trim( $title_node->textContent ) : trim( $card->textContent );
                $link       = $title_node ? $title_node->getAttribute( 'href' ) : '';
                $url        = suzy_vte_make_absolute_url( $link, $source['url'] );
                $start      = suzy_vte_parse_human_datetime( trim( $card->textContent ), $tz );

                if ( empty( $title ) || null === $start ) {
                    continue;
                }

                $events[] = [
                    'title'    => $title,
                    'start'    => (int) $start,
                    'end'      => null,
                    'location' => null,
                    'url'      => $url,
                    'source'   => $source['source'] ?? 'BC Tech',
                ];
            }
        }
    }

    $result = [
        'events' => $events,
        'meta'   => [
            'http_status'  => $status_code,
            'content_type' => $content_type,
            'bytes'        => $bytes,
            'parser'       => 'HTML T-Net',
        ],
    ];

    if ( ! $debug ) {
        set_transient( $transient_key, $result, 30 * MINUTE_IN_SECONDS );
    }

    return $result;
}

/**
 * Fetch events from Meetup search HTML listings.
 *
 * @param array<string, mixed> $source Source configuration.
 * @return array<string, mixed>|WP_Error
 */
function suzy_fetch_vancouver_tech_events_from_html_meetup_search( array $source, bool $debug = false ) {
    if ( empty( $source['url'] ) ) {
        return [];
    }

    $transient_key = 'suzy_vte_meetup_search_' . md5( $source['url'] );

    if ( ! $debug ) {
        $cached = get_transient( $transient_key );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }
    }

    $response = wp_remote_get(
        $source['url'],
        [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'suzy-vancouver-tech-events/1.1',
                'Accept'     => 'text/html, */*',
            ],
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $debug ? $response : [];
    }

    $body         = wp_remote_retrieve_body( $response );
    $status_code  = wp_remote_retrieve_response_code( $response );
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    $bytes        = is_string( $body ) ? strlen( $body ) : 0;

    $events = [];

    if ( ! empty( $body ) ) {
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="UTF-8">' . $body );
        libxml_clear_errors();
        $xpath = new DOMXPath( $dom );
        $tz    = new DateTimeZone( 'America/Vancouver' );

        $anchors = $xpath->query( "//a[contains(@href,'/events/') or contains(@href,'/e/')]" );
        $seen    = [];

        foreach ( $anchors as $anchor ) {
            $href = trim( $anchor->getAttribute( 'href' ) );
            if ( empty( $href ) ) {
                continue;
            }

            $url = suzy_vte_make_absolute_url( $href, $source['url'] );
            if ( empty( $url ) || isset( $seen[ $url ] ) ) {
                continue;
            }

            $seen[ $url ] = true;

            $anchor_text = preg_replace( '/\s+/', ' ', trim( $anchor->textContent ) );
            $title_node  = $xpath->query( './/*[self::h2 or self::h3]', $anchor )->item( 0 );
            $title       = $title_node ? trim( $title_node->textContent ) : $anchor_text;

            $date_node = $xpath->query( './/time', $anchor )->item( 0 );
            if ( ! $date_node && $anchor->parentNode ) {
                $date_node = $xpath->query( './/time', $anchor->parentNode )->item( 0 );
            }

            $date_text   = $date_node ? trim( $date_node->textContent ) : '';
            $date_offset = null;
            $date_regex  = '/\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Sept|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{1,2}(?:,\s*\d{4})?\s*[·•]?\s*\d{1,2}:\d{2}\s*(?:AM|PM)(?:\s*(?:PST|PDT|PT))?/i';

            if ( preg_match( $date_regex, $anchor_text, $matches, PREG_OFFSET_CAPTURE ) ) {
                $date_text   = trim( $matches[0][0] );
                $date_offset = (int) $matches[0][1];
            } elseif ( empty( $date_text ) ) {
                $date_text = $anchor_text;
            }

            $start = suzy_vte_parse_meetup_find_datetime( $date_text, $tz );

            if ( null !== $date_offset ) {
                $title_candidate = trim( substr( $anchor_text, $date_offset + strlen( $date_text ) ) );
                $title_candidate = ltrim( $title_candidate, " ·•-–" );
                $title_candidate = preg_replace( '/\s+by\s+.+$/i', '', $title_candidate );
                $title_candidate = preg_replace( '/\b\d+\+?\s+attendees\b/i', '', $title_candidate );
                $title_candidate = preg_replace( '/\s+Meetup$/i', '', $title_candidate );
                $title_candidate = trim( preg_replace( '/\s+/', ' ', $title_candidate ) );

                if ( ! empty( $title_candidate ) ) {
                    $title = $title_candidate;
                }
            }

            $location = stripos( $anchor_text, 'online' ) !== false ? 'Online' : null;

            if ( empty( $title ) || null === $start ) {
                continue;
            }

            $events[] = [
                'title'    => $title,
                'start'    => (int) $start,
                'end'      => null,
                'location' => $location,
                'url'      => $url,
                'source'   => $source['source'] ?? 'Meetup Search',
            ];
        }
    }

    $result = [
        'events' => $events,
        'meta'   => [
            'http_status'  => $status_code,
            'content_type' => $content_type,
            'bytes'        => $bytes,
            'parser'       => 'HTML Meetup Search',
        ],
    ];

    if ( ! $debug ) {
        set_transient( $transient_key, $result, 30 * MINUTE_IN_SECONDS );
    }

    return $result;
}

/**
 * Parse a human-readable date/time string using Vancouver timezone.
 *
 * @param string           $text Date/time text.
 * @param DateTimeZone     $tz   Timezone instance.
 * @return int|null
 */
function suzy_vte_parse_human_datetime( string $text, DateTimeZone $tz ): ?int {
    $clean = preg_replace( '/\s+/', ' ', trim( $text ) );
    if ( empty( $clean ) ) {
        return null;
    }

    try {
        $dt = new DateTime( $clean, $tz );
        return $dt->getTimestamp();
    } catch ( Exception $e ) {
        $fallback = strtotime( $clean );
        return false !== $fallback ? $fallback : null;
    }
}

/**
 * Parse Meetup "Find" page dates, inferring the year when missing.
 *
 * @param string       $text Date/time text.
 * @param DateTimeZone $tz   Timezone instance.
 * @return int|null
 */
function suzy_vte_parse_meetup_find_datetime( string $text, DateTimeZone $tz ): ?int {
    $clean = preg_replace( '/\s+/', ' ', trim( $text ) );
    if ( empty( $clean ) ) {
        return null;
    }

    $clean = str_replace( [ '·', '•' ], ' ', $clean );
    $clean = preg_replace( '/\b(?:PST|PDT|PT)\b/i', '', $clean );
    $clean = preg_replace(
        '/^(?:Every\s+)?(?:Sun|Mon|Tue|Tues|Wed|Thu|Thur|Thurs|Fri|Sat|Sunday|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday)\b[,\s]*/i',
        '',
        $clean
    );
    $clean = trim( preg_replace( '/\s+/', ' ', $clean ) );

    if ( preg_match( '/\b\d{4}\b/', $clean ) ) {
        return suzy_vte_parse_human_datetime( $clean, $tz );
    }

    $year = (int) wp_date( 'Y' );

    try {
        $dt = new DateTime( $clean . ' ' . $year, $tz );
    } catch ( Exception $e ) {
        return suzy_vte_parse_human_datetime( $clean, $tz );
    }

    $now = new DateTime( 'now', $tz );
    if ( $dt->getTimestamp() < ( $now->getTimestamp() - DAY_IN_SECONDS ) ) {
        $dt->modify( '+1 year' );
    }

    return $dt->getTimestamp();
}

/**
 * Make an absolute URL from a possibly relative path.
 *
 * @param string $url      URL or path.
 * @param string $base_url Base URL.
 * @return string
 */
function suzy_vte_make_absolute_url( string $url, string $base_url ): string {
    if ( empty( $url ) ) {
        return '';
    }

    if ( str_starts_with( $url, 'http://' ) || str_starts_with( $url, 'https://' ) ) {
        return $url;
    }

    $parsed_base = wp_parse_url( $base_url );
    if ( ! $parsed_base ) {
        return $url;
    }

    $scheme = $parsed_base['scheme'] ?? 'https';
    $host   = $parsed_base['host'] ?? '';
    $path   = $parsed_base['path'] ?? '';

    if ( str_starts_with( $url, '/' ) ) {
        return $scheme . '://' . $host . $url;
    }

    $dir = rtrim( dirname( $path ), "/\\" );
    return $scheme . '://' . $host . $dir . '/' . ltrim( $url, '/' );
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

    // Dedupe across sources based on title, start, and location.
    $seen_keys = [];
    $deduped   = [];

    foreach ( $events as $event ) {
        $title    = strtolower( trim( $event['title'] ?? '' ) );
        $start    = isset( $event['start'] ) ? (int) $event['start'] : 0;
        $location = isset( $event['location'] ) ? strtolower( trim( (string) $event['location'] ) ) : '';
        $key      = $title . '|' . $start . '|' . $location;

        if ( isset( $seen_keys[ $key ] ) ) {
            continue;
        }

        $seen_keys[ $key ] = true;
        $deduped[]         = $event;
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
        // Cache for 30 minutes.
        set_transient( $transient_key, $events, 30 * MINUTE_IN_SECONDS );
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
        <p>Aggregated from public community sources (Meetup ICS, Luma Vancouver, BC Tech / T-Net, Meetup search).</p>

        <?php if ( empty( $events ) ) : ?>
            <p>No upcoming Vancouver tech events found right now. Check back soon!</p>
        <?php else : ?>
            <?php
            $events_by_date = [];
            foreach ( $events as $event ) {
                $start    = isset( $event['start'] ) ? (int) $event['start'] : time();
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
                            parser: <?php echo esc_html( $entry['parser'] ?? '' ); ?>,
                            count: <?php echo isset( $entry['count'] ) ? (int) $entry['count'] : 0; ?>
                            <?php if ( isset( $entry['http_status'] ) ) : ?>
                                , HTTP: <?php echo esc_html( (string) $entry['http_status'] ); ?>
                            <?php endif; ?>
                            <?php if ( isset( $entry['content_type'] ) ) : ?>
                                , Content-Type: <?php echo esc_html( (string) $entry['content_type'] ); ?>
                            <?php endif; ?>
                            <?php if ( isset( $entry['bytes'] ) ) : ?>
                                , Bytes: <?php echo esc_html( (string) $entry['bytes'] ); ?>
                            <?php endif; ?>
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

<?php
/**
 * Bounded, read-only parsers for organizer calendars and community gig listings.
 * All network URLs are fixed in suzy_vmc_sources(), not supplied by visitors.
 */

/** Parse an explicitly dated iCalendar DATE-TIME, never invent an hour for all-day entries. */
function suzy_vmc_ical_timestamp( string $header, string $value ): int {
    $value = trim( $value );
    if ( str_contains( strtoupper( $header ), 'VALUE=DATE' ) || ! preg_match( '/^(\d{8})T(\d{6}|\d{4})(Z?)$/', $value, $m ) ) {
        return 0;
    }
    $date = $m[1];
    $clock = str_pad( $m[2], 6, '0' );
    $stamp = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 )
        . ' ' . substr( $clock, 0, 2 ) . ':' . substr( $clock, 2, 2 ) . ':' . substr( $clock, 4, 2 );
    if ( 'Z' === $m[3] ) {
        return (int) strtotime( $stamp . ' UTC' );
    }
    $timezone = suzy_vmc_tz();
    if ( preg_match( '/TZID=([^;:]+)/i', $header, $zone ) ) {
        try {
            $timezone = new DateTimeZone( trim( $zone[1], '"' ) );
        } catch ( Exception $e ) {
            return 0; // Unknown source TZ: do not guess a local time.
        }
    }
    try {
        return ( new DateTimeImmutable( $stamp, $timezone ) )->getTimestamp();
    } catch ( Exception $e ) {
        return 0;
    }
}

function suzy_vmc_parse_ical( string $body, array $source ): array {
    if ( ! str_contains( $body, 'BEGIN:VCALENDAR' ) || strlen( $body ) > 1000000 ) {
        return [];
    }
    // RFC 5545 unfolds CRLF followed by one space or tab.
    $lines = preg_split( '/\n/', preg_replace( '/\n[ \t]/', '', str_replace( [ "\r\n", "\r" ], "\n", $body ) ) );
    $items = [];
    $current = null;
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( 'BEGIN:VEVENT' === $line ) { $current = []; continue; }
        if ( 'END:VEVENT' === $line ) {
            if ( is_array( $current ) && count( $items ) < 120 ) {
                $items[] = $current;
            }
            $current = null;
            continue;
        }
        if ( null === $current || false === strpos( $line, ':' ) ) { continue; }
        [ $header, $value ] = explode( ':', $line, 2 );
        $key = strtoupper( strtok( $header, ';' ) );
        if ( in_array( $key, [ 'SUMMARY', 'DTSTART', 'DTEND', 'URL', 'LOCATION', 'STATUS' ], true ) ) {
            $current[ $key ] = [ $header, $value ];
        }
    }

    $events = [];
    foreach ( $items as $item ) {
        if ( strtoupper( $item['STATUS'][1] ?? '' ) === 'CANCELLED' ) { continue; }
        $start = isset( $item['DTSTART'] ) ? suzy_vmc_ical_timestamp( ...$item['DTSTART'] ) : 0;
        if ( ! $start ) { continue; }
        $end = isset( $item['DTEND'] ) ? suzy_vmc_ical_timestamp( ...$item['DTEND'] ) : 0;
        $decode = static function ( string $v ): string {
            return trim( preg_replace( '/\\\\([,;\\\\])/', '$1', str_replace( '\\n', ' ', strip_tags( $v ) ) ) );
        };
        $title = $decode( (string) ( $item['SUMMARY'][1] ?? '' ) );
        $location = $decode( (string) ( $item['LOCATION'][1] ?? '' ) );
        if ( '' === $title ) { continue; }
        if ( preg_match( '/\b(?:surrey|burnaby|langley|coquitlam|richmond|north vancouver|west vancouver|new westminster|white rock)\b/i', $location ) ) {
            continue;
        }
        $url = (string) ( $item['URL'][1] ?? $source['url'] );
        $events[] = [
            'title' => $title,
            'start' => $start, 'end' => $end,
            'url' => $url, 'location' => $location ?: $source['venue'],
            'source' => $source['label'],
            'category' => suzy_vmc_infer_category( $title, $source['category'] ),
            'time_note' => 'Organizer calendar time; check details before attending',
        ];
    }
    return $events;
}

/**
 * Yearless ticket listings are accepted only if their stated weekday matches
 * a day within a narrow window around the crawl. Stale old pages cannot be
 * magically shifted to next year's show dates.
 */
function suzy_vmc_yearless_date( string $weekday, string $month, int $day, ?int $now = null ): string {
    $now = $now ?? time();
    $timezone = suzy_vmc_tz( $now );
    $year = (int) ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $timezone )->format( 'Y' );
    for ( $candidate = $year - 1; $candidate <= $year + 1; $candidate++ ) {
        $date = DateTimeImmutable::createFromFormat( '!F j Y', "$month $day $candidate", $timezone );
        if ( false === $date || strtolower( $date->format( 'l' ) ) !== strtolower( $weekday ) ) { continue; }
        $days = ( $date->getTimestamp() - $now ) / DAY_IN_SECONDS;
        if ( $days >= -45 && $days <= 160 ) { return $date->format( 'Y-m-d' ); }
    }
    return '';
}

/** Red Gate publishes show names and *door* times on its own tickets page. */
function suzy_vmc_parse_redgate_html( string $html, array $source, ?int $now = null ): array {
    if ( ! class_exists( 'DOMDocument' ) || strlen( $html ) > 1000000 ) { return []; }
    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors( true );
    $ok = $dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );
    if ( ! $ok ) { return []; }
    $xpath = new DOMXPath( $dom );
    $events = [];
    foreach ( $xpath->query( '//a' ) as $anchor ) {
        $text = preg_replace( '/\s+/u', ' ', trim( $anchor->textContent ) );
        if ( ! preg_match( '/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2})(?:st|nd|rd|th)?\s*:\s*(.+)$/iu', $text, $m ) ) { continue; }
        $date = suzy_vmc_yearless_date( $m[1], $m[2], (int) $m[3], $now );
        if ( '' === $date ) { continue; }
        if ( ! preg_match( '/\bDoors?\s+(?:at|open)\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $m[4], $doors ) ) {
            continue; // No timed listing: refuse to substitute an invented show start.
        }
        $hour = (int) $doors[1] % 12 + ( 'pm' === strtolower( $doors[3] ) ? 12 : 0 );
        $minute = (int) ( $doors[2] !== '' ? $doors[2] : 0 );
        $title = trim( preg_split( '/\s+(?:\$\d|Doors?\s+(?:at|open))/i', $m[4], 2 )[0] );
        if ( '' === $title || strlen( $title ) > 160 ) { continue; }
        $events[] = [
            'title' => $title,
            'start' => suzy_vmc_at( sprintf( '%s %02d:%02d', $date, $hour, $minute ) ),
            'url' => $source['url'], 'location' => $source['venue'],
            'source' => $source['label'],
            'category' => suzy_vmc_infer_category( $title, $source['category'] ),
            'time_note' => 'Doors at ' . $doors[1] . ( '' !== $doors[2] ? ':' . $doors[2] : '' ) . ' ' . strtoupper( $doors[3] ) . '; show time unconfirmed',
        ];
        if ( count( $events ) >= 45 ) { break; }
    }
    return $events;
}

/**
 * Secondary jazz-listing source: parse only published day/time lines for a
 * conservative list of Vancouver rooms; never interpret suburban listings as
 * Vancouver city gigs. A listing is not proof of attendance or confirmation.
 */
function suzy_vmc_parse_jazz_html( string $html, array $source, ?int $now = null ): array {
    if ( ! class_exists( 'DOMDocument' ) || strlen( $html ) > 1000000 ) { return []; }
    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors( true );
    $ok = $dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev );
    if ( ! $ok ) { return []; }
    $known_venues = [
        '8EAST', "Frankie's", "Guilt & Co.", 'Tyrant Studios', 'The Heatley',
        'La Fabrique St-George', '2nd Floor Gastown', 'Flamingo Room',
        'Meo Chinatown', 'Sylvia Hotel', "Apollo's", 'Rickshaw Theatre',
        'Aperture Coffee Bar', 'The American', 'The Hargrove',
        "Hero's Welcome", 'St. Andrew\'s-Wesley United Church', 'District Bar',
        'Moose\'s Down Under', 'Chan Centre', 'Boteco Brasil', 'Charlatan Gastropub',
        'Provence Marinaside', 'Zawa Restaurant', 'Green Auto', 'Red Gate',
        'The Wise', 'Lobe', 'The Fox Cabaret', 'Hollywood Theatre',
        'Roundhouse', 'Granville Island', 'The Cobalt', 'Moberly Arts',
        'Admiral Pub', 'Pearl', 'The Railway', 'Vancouver Playhouse',
    ];
    $date = '';
    $events = [];
    $xpath = new DOMXPath( $dom );
    foreach ( $xpath->query( '//h3 | //h4 | //li' ) as $node ) {
        $text = preg_replace( '/\s+/u', ' ', trim( $node->textContent ) );
        if ( in_array( strtolower( $node->nodeName ), [ 'h3', 'h4' ], true ) ) {
            if ( preg_match( '/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday),?\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2})\b/i', $text, $match ) ) {
                $date = suzy_vmc_yearless_date( $match[1], $match[2], (int) $match[3], $now );
            }
            continue;
        }
        if ( '' === $date || ! preg_match( '/^(\d{1,2}):(\d{2})\s*(AM|PM)\s*[-–]\s*(.+)$/iu', $text, $m ) ) { continue; }
        $venue = '';
        $details = '';
        foreach ( $known_venues as $name ) {
            if ( stripos( $m[4], $name . ',' ) === 0 ) {
                $venue = $name;
                $details = trim( substr( $m[4], strlen( $name ) + 1 ) );
                break;
            }
        }
        if ( '' === $venue || '' === $details ) { continue; }
        $title = trim( preg_replace( '/\s*\([^()]{0,70}(?:cover|donation|\$\d|no cover|PWYC)[^()]{0,70}\)\s*$/iu', '', strip_tags( $details ) ) );
        if ( '' === $title || strlen( $title ) > 240 ) { continue; }
        $hour = (int) $m[1] % 12 + ( 'PM' === strtoupper( $m[3] ) ? 12 : 0 );
        $stamp = suzy_vmc_at( sprintf( '%s %02d:%02d', $date, $hour, (int) $m[2] ) );
        $events[] = [
            'title' => $title,
            'start' => $stamp, 'url' => $source['url'],
            'location' => $venue . ', Vancouver', 'source' => $source['label'],
            'category' => suzy_vmc_infer_category( $title, 'music' ),
            'time_note' => 'Secondary gig-list time; verify with venue',
        ];
        if ( count( $events ) >= 110 ) { break; }
    }
    return $events;
}

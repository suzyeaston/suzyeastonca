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
            'id'     => 'techcouver',
            'label'  => 'Techcouver – Vancouver Tech News',
            'url'    => 'https://techcouver.com/feed',
            'source' => 'Techcouver',
            'format' => 'rss',
        ],
        [
            'id'     => 'bctech_tnet',
            'label'  => 'BC Tech / T-Net Events',
            'url'    => 'https://www.bctechnology.com/scripts/event_rss.cfm',
            'source' => 'BC Tech / T-Net',
            'format' => 'rss',
        ],
        [
            'id'     => 'do604_all',
            'label'  => 'Do604 – All Events',
            'url'    => 'https://do604.com/feed',
            'source' => 'Do604',
            'format' => 'rss',
        ],
        [
            'id'     => 'do604_local_music',
            'label'  => 'Do604 – Local Music',
            'url'    => 'https://do604.com/feeds/local-music/tagged',
            'source' => 'Do604 Local Music',
            'format' => 'rss',
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
        // Example placeholder:
        [
            'id'     => 'vancouvertech_luma',
            'label'  => 'Vancouver Tech Events (Luma)',
            'url'    => 'https://api2.luma.com/calendar/get?api_id=REPLACE_WITH_OTHER_CAL_ID',
            'source' => 'Vancouver Tech Events',
            'format' => 'json_luma',
        ],
    ];
}

/**
 * Fetches raw events from all configured sources and normalizes them.
 *
 * @return array<int, array<string, mixed>>
 */
function suzy_fetch_vancouver_tech_events_raw(): array {
    $sources = suzy_get_vancouver_tech_event_sources();
    $events  = [];

    foreach ( $sources as $source ) {
        $format = isset( $source['format'] ) ? $source['format'] : 'rss';

        switch ( $format ) {
            case 'rss':
                $source_events = suzy_fetch_vancouver_tech_events_from_rss( $source );
                break;
            case 'ics':
                $source_events = suzy_fetch_vancouver_tech_events_from_ics( $source );
                break;
            case 'json_luma':
                $source_events = suzy_fetch_vancouver_tech_events_from_luma_json( $source );
                break;
            default:
                $source_events = [];
                break;
        }

        if ( ! empty( $source_events ) && is_array( $source_events ) ) {
            $events = array_merge( $events, $source_events );
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
function suzy_fetch_vancouver_tech_events_from_rss( array $source ): array {
    if ( empty( $source['url'] ) ) {
        return [];
    }

    require_once ABSPATH . WPINC . '/feed.php';

    $feed = fetch_feed( $source['url'] );

    if ( is_wp_error( $feed ) ) {
        return [];
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
function suzy_fetch_vancouver_tech_events_from_ics( array $source ): array {
    // Placeholder: ICS parsing can be added when a feed is configured.
    return [];
}

/**
 * Fetch events from a Luma JSON calendar endpoint.
 *
 * @param array<string, mixed> $source Source configuration.
 * @return array<int, array<string, mixed>>
 */
function suzy_fetch_vancouver_tech_events_from_luma_json( array $source ): array {
    if ( empty( $source['url'] ) ) {
        return [];
    }

    $response = wp_remote_get( $source['url'], [
        'timeout' => 10,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        return [];
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return [];
    }

    $data = json_decode( $body, true );

    if ( null === $data || ! is_array( $data ) ) {
        return [];
    }

    $events_data = [];

    if ( isset( $data['data']['events'] ) && is_array( $data['data']['events'] ) ) {
        $events_data = $data['data']['events'];
    } elseif ( isset( $data['events'] ) && is_array( $data['events'] ) ) {
        $events_data = $data['events'];
    } elseif ( isset( $data['calendar']['events'] ) && is_array( $data['calendar']['events'] ) ) {
        $events_data = $data['calendar']['events'];
    }

    if ( empty( $events_data ) ) {
        return [];
    }

    $events = [];

    foreach ( $events_data as $event ) {
        if ( ! is_array( $event ) ) {
            continue;
        }

        $title     = isset( $event['name'] ) ? $event['name'] : ( $event['title'] ?? '' );
        $start_raw = $event['start_time'] ?? ( $event['start_at'] ?? ( $event['start'] ?? null ) );
        $end_raw   = $event['end_time'] ?? ( $event['end_at'] ?? ( $event['end'] ?? null ) );
        $url       = $event['url'] ?? ( $event['event_url'] ?? ( $event['link'] ?? '' ) );

        $location = null;
        if ( isset( $event['location'] ) && is_string( $event['location'] ) ) {
            $location = $event['location'];
        } elseif ( isset( $event['venue']['name'] ) ) {
            $location = $event['venue']['name'];
        }

        if ( empty( $title ) || empty( $start_raw ) ) {
            continue;
        }

        $start_ts = strtotime( $start_raw );
        $end_ts   = $end_raw ? strtotime( $end_raw ) : null;

        if ( false === $start_ts || null === $start_ts ) {
            continue;
        }

        $events[] = [
            'title'    => (string) $title,
            'start'    => (int) $start_ts,
            'end'      => $end_ts ? (int) $end_ts : null,
            'location' => $location ? (string) $location : null,
            'url'      => (string) $url,
            'source'   => isset( $source['source'] ) ? (string) $source['source'] : '',
        ];
    }

    return $events;
}

/**
 * Returns a cached list of upcoming Vancouver tech events.
 *
 * @return array<int, array<string, mixed>>
 */
function suzy_get_vancouver_tech_events(): array {
    $transient_key = 'suzy_vancouver_tech_events_cache';
    $cached        = get_transient( $transient_key );

    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    $events = suzy_fetch_vancouver_tech_events_raw();

    $now = time();

    // Filter out events that are clearly in the past (older than 1 hour ago).
    $events = array_filter(
        $events,
        static function ( $event ) use ( $now ) {
            if ( ! isset( $event['start'] ) ) {
                return false;
            }

            return (int) $event['start'] >= ( $now - HOUR_IN_SECONDS );
        }
    );

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

    // Cache for 15 minutes.
    set_transient( $transient_key, $events, 15 * MINUTE_IN_SECONDS );

    return $events;
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

    ob_start();
    ?>
    <section class="vancouver-tech-events">
        <h1>Vancouver Tech Events</h1>
        <p>Aggregated from multiple community sources (Techcouver, BC Tech, Do604, Vancouver Tech Journal / Luma, and more).</p>

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

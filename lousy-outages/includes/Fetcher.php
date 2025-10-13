<?php
declare(strict_types=1);

namespace LousyOutages;

class Fetcher {
    private const STATUS_LABELS = [
        'operational' => 'Operational',
        'degraded'    => 'Degraded',
        'outage'      => 'Outage',
        'maintenance' => 'Maintenance',
        'unknown'     => 'Unknown',
    ];

    // Remove typed property for PHP 7.x compatibility
    private $timeout;

    public function __construct( int $timeout = 8 ) {
        $this->timeout = max( 2, $timeout );
    }

    public static function status_label( string $code ): string {
        $code = strtolower( $code );
        return self::STATUS_LABELS[ $code ] ?? self::STATUS_LABELS['unknown'];
    }

    public function fetch( array $provider ): array {
        $id        = (string) ( $provider['id'] ?? '' );
        $name      = (string) ( $provider['name'] ?? $id );
        $type      = strtolower( (string) ( $provider['type'] ?? 'statuspage' ) );
        $endpoint  = $this->resolve_endpoint( $provider );
        $statusUrl = isset($provider['status_url']) ? $provider['status_url'] : ( isset($provider['url']) ? $provider['url'] : '' );

        $defaults = [
            'id'           => $id,
            'name'         => $name,
            'provider'     => isset($provider['provider']) ? $provider['provider'] : $name,
            'status'       => 'unknown',
            'status_label' => self::status_label( 'unknown' ),
            'summary'      => 'Waiting for status…',
            'message'      => 'Waiting for status…',
            'updated_at'   => gmdate( 'c' ),
            'url'          => is_string( $statusUrl ) ? $statusUrl : '',
            'incidents'    => [],
            'error'        => null,
        ];

        if ( ! $endpoint ) {
            $defaults['summary'] = 'No public status endpoint available';
            $defaults['message'] = $defaults['summary'];
            return $defaults;
        }

        $response = wp_remote_get(
            $endpoint,
            [
                'timeout' => $this->timeout,
                'headers' => [
                    'Accept'        => 'application/json, application/xml, text/xml;q=0.9,*/*;q=0.8',
                    'Cache-Control' => 'no-cache',
                    'User-Agent'    => 'LousyOutagesBot/3.0 (' . home_url() . ')',
                ],
            ]
        );

        $optional = ( 'rss-optional' === $type );

        if ( is_wp_error( $response ) ) {
            if ( $optional ) {
                return $this->optional_unavailable( $defaults, $response->get_error_message() );
            }
            return $this->failed_defaults( $defaults, 'request_failed', $response->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code >= 400 ) {
            if ( $optional ) {
                return $this->optional_unavailable( $defaults, 'HTTP ' . $code );
            }
            return $this->failed_defaults( $defaults, 'http_error', sprintf( 'HTTP %d response', $code ) );
        }

        $body = (string) wp_remote_retrieve_body( $response );
        if ( '' === trim( $body ) ) {
            if ( $optional ) {
                return $this->optional_unavailable( $defaults, 'Empty body' );
            }
            return $this->failed_defaults( $defaults, 'empty_body', 'Empty response from status endpoint' );
        }

        if ( 'atom' === $type ) {
            $parsed = $this->parse_feed( $provider, $body, 'atom' );
        } elseif ( 'rss' === $type || 'rss-optional' === $type ) {
            $parsed = $this->parse_feed( $provider, $body, 'rss' );
        } elseif ( 'slack' === $type ) {
            $parsed = $this->parse_slack_current( $body );
        } else {
            $parsed = $this->parse_statuspage( $body );
        }

        $result = array_merge( $defaults, $parsed );

        $result['status']       = $this->normalize_status_code( isset($result['status']) ? $result['status'] : 'unknown' );
        $result['status_label'] = self::status_label( $result['status'] );
        $result['summary']      = $this->sanitize( isset($result['summary']) ? $result['summary'] : '' ) ?: self::status_label( $result['status'] );
        $result['message']      = $result['summary'];
        $result['updated_at']   = isset($result['updated_at']) ? $result['updated_at'] : gmdate( 'c' );
        $result['incidents']    = $this->normalize_incidents( isset($result['incidents']) ? $result['incidents'] : [] );

        return $result;
    }

    private function resolve_endpoint( array $provider ): ?string {
        $type = strtolower( (string) ( $provider['type'] ?? 'statuspage' ) );

        if ( 'slack' === $type ) {
            $url = isset($provider['current']) ? $provider['current'] : ( isset($provider['endpoint']) ? $provider['endpoint'] : null );
            if ( ! $url || ! is_string( $url ) ) {
                return null;
            }
            return $url;
        }

        $keys = [
            'statuspage'   => 'summary',
            'rss'          => 'rss',
            'rss-optional' => 'rss',
            'atom'         => 'atom',
        ];

        $key = isset($keys[$type]) ? $keys[$type] : 'summary';
        $url = isset($provider[$key]) ? $provider[$key] : ( isset($provider['endpoint']) ? $provider['endpoint'] : null );

        if ( ! $url || ! is_string( $url ) ) {
            return null;
        }
        return $url;
    }

    private function parse_slack_current( string $body ): array {
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return [
                'status'  => 'unknown',
                'summary' => 'Unable to parse Slack status',
            ];
        }

        $title   = $this->sanitize( isset($decoded['title']) ? (string) $decoded['title'] : '' );
        $date    = isset($decoded['date']) ? $this->iso( $decoded['date'] ) : null;
        $checks  = [ 'connection', 'service', 'api' ];
        $all_ok  = true;
        $signals = [];

        foreach ( $checks as $check ) {
            $value = strtolower( (string) ( isset($decoded[ $check ]) ? $decoded[ $check ] : '' ) );
            $signals[] = $value;
            if ( $value && 'ok' !== $value ) {
                $all_ok = false;
            }
        }

        $status_text = strtolower(
            trim(
                implode(
                    ' ',
                    array_filter(
                        [
                            isset($decoded['status']) ? (string) $decoded['status'] : '',
                            implode( ' ', $signals ),
                            $title,
                        ],
                        static function ( $part ) {
                            return '' !== trim( (string) $part );
                        }
                    )
                )
            )
        );

        $status = 'operational';
        if ( ! $all_ok ) {
            $status = 'degraded';
            foreach ( [ 'outage', 'major', 'critical', 'down' ] as $keyword ) {
                if ( false !== strpos( $status_text, $keyword ) ) {
                    $status = 'outage';
                    break;
                }
            }
        }

        $summary = $title ?: 'All systems operational';

        return [
            'status'     => $status,
            'summary'    => $summary,
            'updated_at' => $date ?: gmdate( 'c' ),
            'incidents'  => [],
        ];
    }

    private function parse_statuspage( string $body ): array {
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return [
                'status'  => 'unknown',
                'summary' => 'Unrecognized Statuspage payload',
            ];
        }

        $indicator   = strtolower( (string) ( isset($decoded['status']['indicator']) ? $decoded['status']['indicator'] : '' ) );
        $description = (string) ( isset($decoded['status']['description']) ? $decoded['status']['description'] : '' );
        $map         = [
            'none'           => 'operational',
            'minor'          => 'degraded',
            'major'          => 'outage',
            'critical'       => 'outage',
            'maintenance'    => 'maintenance',
            'partial_outage' => 'degraded',
        ];
        $status      = isset($map[$indicator]) ? $map[$indicator] : $this->guess_status_from_text( $description );

        $incidents = [];
        foreach ( [ 'incidents', 'active_incidents' ] as $key ) {
            if ( empty( $decoded[ $key ] ) || ! is_array( $decoded[ $key ] ) ) {
                continue;
            }
            foreach ( $decoded[ $key ] as $incident ) {
                if ( ! is_array( $incident ) ) {
                    continue;
                }
                $normalized = $this->normalize_statuspage_incident( $incident );
                if ( $normalized ) {
                    $incidents[] = $normalized;
                }
            }
        }

        $summary = $description ?: ( isset($incidents[0]['title']) ? $incidents[0]['title'] : '' );
        if ( ! $summary ) {
            $summary = 'All systems operational';
        }

        return [
            'status'     => $status,
            'summary'    => $summary,
            'incidents'  => $incidents,
            'updated_at' => $this->iso( isset($decoded['page']['updated_at']) ? $decoded['page']['updated_at'] : null ) ?: gmdate( 'c' ),
        ];
    }

    private function parse_feed( array $provider, string $body, string $format ): array {
        $previous = libxml_use_internal_errors( true );
        $xml      = simplexml_load_string( $body );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $xml ) {
            return [
                'status'  => 'unknown',
                'summary' => 'Unable to parse status feed',
            ];
        }

        $entries = [];
        if ( 'atom' === $format && isset( $xml->entry ) ) {
            foreach ( $xml->entry as $entry ) {
                $entries[] = $entry;
            }
        } elseif ( isset( $xml->channel->item ) ) {
            foreach ( $xml->channel->item as $item ) {
                $entries[] = $item;
            }
        }

        if ( ! $entries ) {
            return [
                'status'    => 'operational',
                'summary'   => 'All systems operational',
                'incidents' => [],
            ];
        }

        $incidents = [];
        $highest   = 'operational';
        foreach ( $entries as $entry ) {
            $data = $this->normalize_feed_entry( $entry, $provider );
            if ( ! $data ) {
                continue;
            }

            $incidents[] = $data;
            if ( 'outage' === $data['impact'] ) {
                $highest = 'outage';
            } elseif ( 'degraded' === $data['impact'] && 'outage' !== $highest ) {
                $highest = 'degraded';
            }
        }

        $status  = $highest;
        if ( 'operational' === $status && ! empty( $incidents ) ) {
            $status = 'degraded';
        }

        $summary = $incidents ? ( isset($incidents[0]['title']) ? $incidents[0]['title'] : 'Service disruption detected' ) : 'All systems operational';
        $updated = $incidents ? ( isset($incidents[0]['updated_at']) ? $incidents[0]['updated_at'] : ( isset($incidents[0]['started_at']) ? $incidents[0]['started_at'] : null ) ) : null;

        return [
            'status'     => $status,
            'summary'    => $summary,
            'incidents'  => $incidents,
            'updated_at' => $updated ? $this->iso( $updated ) : gmdate( 'c' ),
        ];
    }

    private function normalize_feed_entry( \SimpleXMLElement $entry, array $provider ): ?array {
        $title   = $this->sanitize( (string) ( isset($entry->title) ? $entry->title : '' ) );
        $summary = $this->sanitize( (string) ( isset($entry->summary) ? $entry->summary : ( isset($entry->description) ? $entry->description : '' ) ) );
        $link    = '';

        if ( isset( $entry->link ) ) {
            if ( isset( $entry->link['href'] ) ) {
                $link = (string) $entry->link['href'];
            } else {
                $link = (string) $entry->link;
            }
        }

        if ( isset( $entry->guid ) && ! $link ) {
            $link = (string) $entry->guid;
        }

        $published = (string) ( isset($entry->pubDate) ? $entry->pubDate : ( isset($entry->published) ? $entry->published : ( isset($entry->updated) ? $entry->updated : '' ) ) );
        $timestamp = $published ? strtotime( $published ) : false;
        if ( $timestamp && $timestamp < strtotime( '-7 days' ) ) {
            return null;
        }
        $isoStart = $this->iso( $published );

        $text   = strtolower( $title . ' ' . $summary );
        $impact = $this->guess_status_from_text( $text );

        if ( 'operational' === $impact && ! $summary ) {
            return null;
        }

        return [
            'id'         => substr( md5( (string) ( isset($entry->guid) ? $entry->guid : $title ) . $published ), 0, 12 ),
            'title'      => $title ?: 'Incident',
            'summary'    => $summary ?: $title,
            'started_at' => $isoStart,
            'updated_at' => $this->iso( (string) ( isset($entry->updated) ? $entry->updated : $published ) ) ?: $isoStart,
            'impact'     => $impact,
            'eta'        => '',
            'url'        => $link ?: ( isset($provider['status_url']) ? $provider['status_url'] : '' ),
        ];
    }

    private function normalize_statuspage_incident( array $incident ): ?array {
        $state = strtolower( (string) ( isset($incident['status']) ? $incident['status'] : '' ) );
        if ( in_array( $state, [ 'resolved', 'completed', 'postmortem' ], true ) ) {
            return null;
        }

        $updates = [];
        if ( ! empty( $incident['incident_updates'] ) && is_array( $incident['incident_updates'] ) ) {
            $updates = $incident['incident_updates'];
            usort(
                $updates,
                static function ( $a, $b ) {
                    $aTime = isset( $a['created_at'] ) ? strtotime( (string) $a['created_at'] ) : 0;
                    $bTime = isset( $b['created_at'] ) ? strtotime( (string) $b['created_at'] ) : 0;
                    return $bTime <=> $aTime;
                }
            );
        }

        $latest = isset($updates[0]) ? $updates[0] : [];
        $body   = is_array( $latest ) ? ( isset($latest['body']) ? $latest['body'] : ( isset($latest['display_at']) ? $latest['display_at'] : '' ) ) : '';

        return [
            'id'         => (string) ( isset($incident['id']) ? $incident['id'] : md5( wp_json_encode( $incident ) ) ),
            'title'      => $this->sanitize( isset($incident['name']) ? $incident['name'] : 'Incident' ),
            'summary'    => $this->sanitize( $body ?: ( isset($incident['impact_override']) ? $incident['impact_override'] : '' ) ),
            'started_at' => $this->iso( isset($incident['started_at']) ? $incident['started_at'] : ( isset($incident['created_at']) ? $incident['created_at'] : null ) ),
            'updated_at' => $this->iso( isset($incident['updated_at']) ? $incident['updated_at'] : null ),
            'impact'     => $this->map_impact( isset($incident['impact']) ? $incident['impact'] : $state ),
            'eta'        => '',
            'url'        => isset($incident['shortlink']) ? $incident['shortlink'] : ( isset($incident['postmortem_body']) ? $incident['postmortem_body'] : '' ),
        ];
    }

    private function normalize_incidents( array $incidents ): array {
        $normalized = [];
        foreach ( $incidents as $incident ) {
            if ( ! is_array( $incident ) ) {
                continue;
            }

            $normalized[] = [
                'id'         => (string) ( isset($incident['id']) ? $incident['id'] : md5( wp_json_encode( $incident ) ) ),
                'title'      => $this->sanitize( isset($incident['title']) ? $incident['title'] : 'Incident' ),
                'summary'    => $this->sanitize( isset($incident['summary']) ? $incident['summary'] : '' ),
                'started_at' => $this->iso( isset($incident['started_at']) ? $incident['started_at'] : null ),
                'updated_at' => $this->iso( isset($incident['updated_at']) ? $incident['updated_at'] : null ),
                'impact'     => $this->map_impact( isset($incident['impact']) ? $incident['impact'] : 'minor' ),
                'eta'        => $this->sanitize( isset($incident['eta']) ? $incident['eta'] : '' ),
                'url'        => isset($incident['url']) ? $incident['url'] : '',
            ];
        }
        return $normalized;
    }

    private function optional_unavailable( array $defaults, string $error ): array {
        $defaults['status']  = 'unknown';
        $defaults['summary'] = 'Optional source unavailable';
        $defaults['message'] = $defaults['summary'];
        $defaults['error']   = $error ? 'optional_unavailable: ' . $error : 'optional_unavailable';
        return $defaults;
    }

    private function failed_defaults( array $defaults, string $code, string $message ): array {
        $defaults['status']  = 'unknown';
        $defaults['summary'] = 'Status fetch failed';
        $defaults['message'] = $defaults['summary'];
        $defaults['error']   = $code . ( $message ? ': ' . $message : '' );
        return $defaults;
    }

    private function sanitize( ?string $text ): string {
        if ( ! $text ) {
            return '';
        }
        return trim( wp_strip_all_tags( $text ) );
    }

    private function iso( $value ): ?string {
        if ( ! $value ) {
            return null;
        }
        if ( $value instanceof \DateTimeInterface ) {
            return gmdate( 'c', (int) $value->getTimestamp() );
        }
        $timestamp = strtotime( (string) $value );
        if ( ! $timestamp ) {
            return null;
        }
        return gmdate( 'c', $timestamp );
    }

    private function map_impact( $impact ): string {
        $impact = strtolower( (string) $impact );
        switch ( $impact ) {
            case 'critical':
            case 'major':
            case 'outage':
                return 'outage';
            case 'maintenance':
                return 'maintenance';
            case 'minor':
            case 'degraded':
            case 'partial':
            case 'partial_outage':
                return 'degraded';
            default:
                return 'minor';
        }
    }

    private function guess_status_from_text( string $text ): string {
        $text = strtolower( $text );
        if ( ! $text ) {
            return 'unknown';
        }
        if (
            false !== strpos( $text, 'partial outage' ) ||
            false !== strpos( $text, 'degrad' ) ||
            false !== strpos( $text, 'partial' ) ||
            false !== strpos( $text, 'performance' )
        ) {
            return 'degraded';
        }
        if (
            false !== strpos( $text, 'outage' ) ||
            false !== strpos( $text, 'disruption' ) ||
            false !== strpos( $text, 'major' ) ||
            false !== strpos( $text, 'critical' ) ||
            false !== strpos( $text, 'down' )
        ) {
            return 'outage';
        }
        if ( false !== strpos( $text, 'operational' ) || false !== strpos( $text, 'normal' ) ) {
            return 'operational';
        }
        return 'unknown';
    }

    private function normalize_status_code( string $status ): string {
        $status = strtolower( $status );
        switch ( $status ) {
            case 'operational':
            case 'up':
                return 'operational';
            case 'degraded':
            case 'partial':
            case 'minor':
                return 'degraded';
            case 'outage':
            case 'major':
            case 'critical':
            case 'down':
                return 'outage';
            case 'maintenance':
            case 'scheduled':
                return 'maintenance';
            default:
                return 'unknown';
        }
    }
}

<?php
namespace LousyOutages;

class Fetcher {
    private const STATUS_LABELS = [
        'operational' => 'Operational',
        'degraded'    => 'Degraded',
        'outage'      => 'Outage',
        'maintenance' => 'Maintenance',
        'unknown'     => 'Unknown',
    ];

    private const IMPACT_LEVELS = ['minor', 'major', 'critical'];

    private int $timeout;

    public function __construct( int $timeout = 8 ) {
        $this->timeout = max( 2, $timeout );
    }

    public static function status_label( string $code ): string {
        $code = strtolower( $code );
        return self::STATUS_LABELS[ $code ] ?? self::STATUS_LABELS['unknown'];
    }

    /**
     * Fetch and normalize data for a provider.
     */
    public function fetch( array $provider ): array {
        $defaults = [
            'id'           => $provider['id'],
            'name'         => $provider['name'],
            'provider'     => $provider['provider'] ?? $provider['name'],
            'status'       => 'unknown',
            'status_label' => self::status_label( 'unknown' ),
            'summary'      => isset( $provider['note'] ) ? $this->sanitize( (string) $provider['note'] ) : '',
            'message'      => isset( $provider['note'] ) ? $this->sanitize( (string) $provider['note'] ) : '',
            'updated_at'   => gmdate( 'c' ),
            'url'          => $provider['url'] ?? '',
            'incidents'    => [],
            'error'        => null,
        ];

        if ( empty( $provider['endpoint'] ) ) {
            if ( empty( $defaults['summary'] ) ) {
                $defaults['summary'] = 'No public status API';
                $defaults['message'] = $defaults['summary'];
            }
            return $defaults;
        }

        $response = wp_remote_get(
            $provider['endpoint'],
            [
                'timeout' => $this->timeout,
                'headers' => [
                    'Accept'        => 'application/json, text/xml;q=0.9,*/*;q=0.8',
                    'Cache-Control' => 'no-cache',
                    'User-Agent'    => 'LousyOutagesBot/2.0 (' . home_url() . ')',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            $defaults['summary'] = 'Request failed';
            $defaults['message'] = $defaults['summary'];
            $defaults['error']   = $response->get_error_message();
            return $defaults;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 400 ) {
            $fallback = $this->scrape_status_page( $provider );
            if ( $fallback ) {
                return array_merge( $defaults, $fallback );
            }

            $defaults['summary'] = sprintf( 'HTTP %d from status API', $code );
            $defaults['message'] = $defaults['summary'];
            $defaults['error']   = 'http_error';
            return $defaults;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( ! $body ) {
            $fallback = $this->scrape_status_page( $provider );
            if ( $fallback ) {
                return array_merge( $defaults, $fallback );
            }

            $defaults['summary'] = 'Empty response from status API';
            $defaults['message'] = $defaults['summary'];
            $defaults['error']   = 'empty_body';
            return $defaults;
        }

        $parsed  = $this->parse_body( $provider, $body );
        if ( ! empty( $provider['scrape'] ) ) {
            if ( empty( $parsed['summary'] ) || 'unknown' === ( $parsed['status'] ?? 'unknown' ) ) {
                $fallback = $this->scrape_status_page( $provider );
                if ( $fallback ) {
                    $parsed = array_merge( $parsed, $fallback );
                }
            }
        }
        $result  = array_merge( $defaults, $parsed );
        $summary = $result['summary'] ?? $result['message'] ?? '';
        if ( ! $summary ) {
            $summary = self::status_label( $result['status'] ?? 'unknown' );
        }
        $summary             = $this->sanitize( $summary );
        $result['summary']   = $summary;
        $result['message']   = $summary;
        $result['status']    = $result['status'] ?? 'unknown';
        $result['status_label'] = self::status_label( $result['status'] );
        $result['updated_at']   = $result['updated_at'] ?? gmdate( 'c' );
        $result['incidents']    = array_values( array_filter( $result['incidents'] ?? [], 'is_array' ) );

        return $result;
    }

    private function parse_body( array $provider, string $body ): array {
        $type = strtolower( $provider['type'] ?? 'statuspage' );
        if ( 'slack' === $type ) {
            return $this->parse_slack( $provider, $body );
        }
        if ( 'statuspage' === $type ) {
            return $this->parse_statuspage( $body );
        }

        if ( 'json' === $type ) {
            return $this->parse_json_feed( $provider, $body );
        }

        return $this->parse_feed( $provider, $body );
    }

    private function parse_statuspage( string $body ): array {
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return [
                'status'    => 'unknown',
                'summary'   => 'Unrecognized response payload',
                'incidents' => [],
            ];
        }

        $indicator    = strtolower( (string) ( $decoded['status']['indicator'] ?? '' ) );
        $description  = strtolower( (string) ( $decoded['status']['description'] ?? '' ) );
        $map       = [
            'none'           => 'operational',
            'minor'          => 'degraded',
            'major'          => 'outage',
            'critical'       => 'outage',
            'maintenance'    => 'maintenance',
            'partial_outage' => 'degraded',
        ];
        $status    = $map[ $indicator ] ?? 'unknown';
        if ( 'unknown' === $status && $description ) {
            if ( false !== strpos( $description, 'operational' ) ) {
                $status = 'operational';
            } elseif ( false !== strpos( $description, 'degrad' ) || false !== strpos( $description, 'partial' ) ) {
                $status = 'degraded';
            } elseif ( false !== strpos( $description, 'outage' ) || false !== strpos( $description, 'disruption' ) ) {
                $status = 'outage';
            }
        }
        $summary   = $decoded['status']['description'] ?? '';

        $incidents = [];
        $sources   = [];
        foreach ( [ 'incidents', 'active_incidents' ] as $key ) {
            if ( empty( $decoded[ $key ] ) || ! is_array( $decoded[ $key ] ) ) {
                continue;
            }
            foreach ( $decoded[ $key ] as $incident ) {
                if ( ! is_array( $incident ) ) {
                    continue;
                }
                $state = strtolower( (string) ( $incident['status'] ?? '' ) );
                if ( in_array( $state, [ 'resolved', 'completed', 'postmortem', 'scheduled' ], true ) ) {
                    continue;
                }
                $normalised = $this->normalize_statuspage_incident( $incident );
                if ( $normalised['id'] && isset( $sources[ $normalised['id'] ] ) ) {
                    continue;
                }
                if ( $normalised['id'] ) {
                    $sources[ $normalised['id'] ] = true;
                }
                $incidents[] = $normalised;
            }
        }

        $updated = $this->iso( $decoded['page']['updated_at'] ?? null );
        if ( ! $summary ) {
            $summary = $incidents ? ( $incidents[0]['title'] ?? 'Investigating incidents' ) : 'All systems operational';
        }

        return [
            'status'     => $status,
            'summary'    => $summary,
            'incidents'  => $incidents,
            'updated_at' => $updated ?: gmdate( 'c' ),
        ];
    }

    private function normalize_statuspage_incident( array $incident ): array {
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

        $latest = $updates[0] ?? [];
        $body   = $latest['body'] ?? '';
        $eta    = $this->extract_eta_from_updates( $updates );

        return [
            'id'         => (string) ( $incident['id'] ?? md5( wp_json_encode( $incident ) ) ),
            'title'      => $this->sanitize( $incident['name'] ?? 'Incident' ),
            'summary'    => $this->sanitize( $body ?: ( $incident['impact_override'] ?? '' ) ),
            'started_at' => $this->iso( $incident['started_at'] ?? $incident['created_at'] ?? null ),
            'updated_at' => $this->iso( $incident['updated_at'] ?? null ),
            'impact'     => $this->normalize_impact( $incident['impact'] ?? null ),
            'eta'        => $eta,
            'url'        => $incident['shortlink'] ?? $incident['postmortem_body'] ?? '',
        ];
    }

    private function parse_feed( array $provider, string $body ): array {
        $previous = libxml_use_internal_errors( true );
        $xml      = simplexml_load_string( $body );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $xml ) {
            return [
                'status'    => 'unknown',
                'summary'   => 'Unable to read status feed',
                'incidents' => [],
            ];
        }

        $entries = [];
        if ( isset( $xml->channel->item ) ) {
            foreach ( $xml->channel->item as $item ) {
                $entries[] = $item;
            }
        } elseif ( isset( $xml->entry ) ) {
            foreach ( $xml->entry as $entry ) {
                $entries[] = $entry;
            }
        }

        $incidents = [];
        $now       = time();
        foreach ( $entries as $entry ) {
            $title     = isset( $entry->title ) ? (string) $entry->title : 'Incident';
            $summary   = isset( $entry->summary ) ? (string) $entry->summary : ( isset( $entry->description ) ? (string) $entry->description : '' );
            $published = isset( $entry->published ) ? (string) $entry->published : ( isset( $entry->pubDate ) ? (string) $entry->pubDate : '' );
            $updated   = isset( $entry->updated ) ? (string) $entry->updated : $published;
            $link      = '';
            if ( isset( $entry->link ) ) {
                if ( isset( $entry->link['href'] ) ) {
                    $link = (string) $entry->link['href'];
                } else {
                    $link = (string) $entry->link;
                }
            }

            $ts = $published ? strtotime( $published ) : false;
            if ( $ts && $ts < strtotime( '-7 days', $now ) ) {
                continue;
            }

            $incidents[] = [
                'id'         => substr( md5( (string) ( $entry->guid ?? $entry->id ?? $title ) . $published ), 0, 12 ),
                'title'      => $this->sanitize( $title ),
                'summary'    => $this->sanitize( $summary ),
                'started_at' => $this->iso( $published ),
                'updated_at' => $this->iso( $updated ),
                'impact'     => 'major',
                'eta'        => 'investigating',
                'url'        => $link ?: ( $provider['url'] ?? '' ),
            ];
        }

        $status  = $incidents ? 'degraded' : 'operational';
        $summary = $incidents ? ( $incidents[0]['title'] ?? 'Service disruption detected' ) : 'All systems operational';
        $updated = $incidents && ! empty( $incidents[0]['updated_at'] ) ? $incidents[0]['updated_at'] : gmdate( 'c' );

        return [
            'status'     => $status,
            'summary'    => $summary,
            'incidents'  => $incidents,
            'updated_at' => $updated,
        ];
    }

    private function parse_json_feed( array $provider, string $body ): array {
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return [
                'status'    => 'unknown',
                'summary'   => 'Unrecognized response payload',
                'incidents' => [],
            ];
        }

        $items = [];
        if ( isset( $decoded['items'] ) && is_array( $decoded['items'] ) ) {
            $items = $decoded['items'];
        } elseif ( isset( $decoded[0] ) ) {
            $items = $decoded;
        }

        $incidents = [];
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $state = strtolower( $item['status'] ?? $item['state'] ?? '' );
            if ( in_array( $state, [ 'closed', 'resolved', 'completed' ], true ) ) {
                continue;
            }

            $updates = [];
            if ( isset( $item['most_recent_update'] ) ) {
                $updates = is_array( $item['most_recent_update'] ) ? [ $item['most_recent_update'] ] : [ [ 'text' => $item['most_recent_update'] ] ];
            } elseif ( isset( $item['updates'] ) && is_array( $item['updates'] ) ) {
                $updates = $item['updates'];
            }

            $latest = $updates[0] ?? [];
            $summary = '';
            if ( is_array( $latest ) ) {
                $summary = $latest['text'] ?? $latest['body'] ?? '';
            } elseif ( is_string( $latest ) ) {
                $summary = $latest;
            }

            $incidents[] = [
                'id'         => (string) ( $item['id'] ?? md5( wp_json_encode( $item ) ) ),
                'title'      => $this->sanitize( $item['title'] ?? $item['summary'] ?? 'Incident' ),
                'summary'    => $this->sanitize( $summary ?: ( $item['summary'] ?? '' ) ),
                'started_at' => $this->iso( $item['begin'] ?? $item['created'] ?? null ),
                'updated_at' => $this->iso( $item['end'] ?? $item['modified'] ?? ( $latest['when'] ?? null ) ),
                'impact'     => $this->normalize_impact( $item['severity'] ?? ( $item['impact'] ?? null ) ),
                'eta'        => $this->extract_eta_from_text( $item['eta'] ?? ( is_array( $latest ) ? ( $latest['eta'] ?? '' ) : '' ) ),
                'url'        => $item['externalUrl'] ?? $item['uri'] ?? $provider['url'] ?? '',
            ];
        }

        $status  = $incidents ? 'degraded' : 'operational';
        $summary = $incidents ? ( $incidents[0]['title'] ?? 'Active incident reported' ) : 'All systems operational';
        $updated = $incidents && ! empty( $incidents[0]['updated_at'] ) ? $incidents[0]['updated_at'] : gmdate( 'c' );

        return [
            'status'     => $status,
            'summary'    => $summary,
            'incidents'  => $incidents,
            'updated_at' => $updated,
        ];
    }

    private function parse_slack( array $provider, string $body ): array {
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return [
                'status'    => 'unknown',
                'summary'   => 'Unrecognized response payload',
                'incidents' => [],
            ];
        }

        $raw_incidents = [];
        if ( isset( $decoded['active_incidents'] ) && is_array( $decoded['active_incidents'] ) ) {
            $raw_incidents = $decoded['active_incidents'];
        } elseif ( isset( $decoded['incidents'] ) && is_array( $decoded['incidents'] ) ) {
            $raw_incidents = $decoded['incidents'];
        }

        $incidents = [];
        foreach ( $raw_incidents as $incident ) {
            if ( ! is_array( $incident ) ) {
                continue;
            }

            $updates = [];
            if ( isset( $incident['updates'] ) && is_array( $incident['updates'] ) ) {
                $updates = $incident['updates'];
                $self    = $this;
                usort(
                    $updates,
                    static function ( $a, $b ) use ( $self ) {
                        $aTime = $self->slack_update_timestamp( $a );
                        $bTime = $self->slack_update_timestamp( $b );
                        return $bTime <=> $aTime;
                    }
                );
            }

            $latest  = $updates[0] ?? [];
            $summary = '';
            if ( is_array( $latest ) ) {
                $summary = $latest['body'] ?? $latest['text'] ?? ( $latest['description'] ?? '' );
            } elseif ( is_string( $latest ) ) {
                $summary = $latest;
            }

            if ( ! $summary && isset( $incident['summary'] ) ) {
                $summary = $incident['summary'];
            }

            $start = $incident['created'] ?? $incident['begin'] ?? $this->combine_datetime( $incident['date'] ?? '', $incident['time'] ?? '' );
            $end   = $incident['updated'] ?? ( is_array( $latest ) ? ( $latest['updated'] ?? $this->combine_datetime( $latest['date'] ?? '', $latest['time'] ?? '' ) ) : null );

            $incidents[] = [
                'id'         => (string) ( $incident['id'] ?? md5( wp_json_encode( $incident ) ) ),
                'title'      => $this->sanitize( $incident['title'] ?? $incident['name'] ?? 'Incident' ),
                'summary'    => $this->sanitize( $summary ?: ( $incident['description'] ?? '' ) ),
                'started_at' => $this->iso( $start ),
                'updated_at' => $this->iso( $end ),
                'impact'     => $this->normalize_impact( $incident['status'] ?? 'major' ),
                'eta'        => $this->extract_eta_from_text( $incident['next_update'] ?? ( is_array( $latest ) ? ( $latest['next_update'] ?? $latest['next'] ?? '' ) : '' ) ),
                'url'        => $incident['url'] ?? $incident['permalink'] ?? $provider['url'] ?? '',
            ];
        }

        $status_code = strtolower( (string) ( $decoded['status'] ?? '' ) );
        $map         = [
            'ok'          => 'operational',
            'active'      => 'degraded',
            'incident'    => 'degraded',
            'minor'       => 'degraded',
            'notice'      => 'maintenance',
            'maintenance' => 'maintenance',
            'major'       => 'outage',
            'critical'    => 'outage',
            'outage'      => 'outage',
        ];
        $status      = $map[ $status_code ] ?? 'unknown';
        if ( 'unknown' === $status ) {
            $status = $incidents ? 'degraded' : 'operational';
        }

        $summary = '';
        if ( isset( $decoded['current_status'] ) && is_array( $decoded['current_status'] ) ) {
            $summary = $decoded['current_status']['title'] ?? $decoded['current_status']['body'] ?? '';
        }
        if ( ! $summary ) {
            $summary = $incidents ? ( $incidents[0]['title'] ?? '' ) : '';
        }
        if ( ! $summary ) {
            $summary = 'All systems operational';
        }

        $updated = $this->iso( $this->combine_datetime( $decoded['date'] ?? '', $decoded['time'] ?? '' ) );
        if ( ! $updated && $incidents ) {
            $updated = $incidents[0]['updated_at'] ?? '';
        }
        if ( ! $updated ) {
            $updated = gmdate( 'c' );
        }

        return [
            'status'     => $status,
            'summary'    => $summary,
            'incidents'  => $incidents,
            'updated_at' => $updated,
        ];
    }

    private function slack_update_timestamp( $update ): int {
        if ( ! is_array( $update ) ) {
            return 0;
        }

        foreach ( [ 'updated', 'timestamp', 'ts', 'created', 'when' ] as $field ) {
            if ( ! empty( $update[ $field ] ) ) {
                $time = strtotime( (string) $update[ $field ] );
                if ( $time ) {
                    return $time;
                }
            }
        }

        $combined = $this->combine_datetime( $update['date'] ?? '', $update['time'] ?? '' );
        if ( $combined ) {
            $time = strtotime( $combined );
            if ( $time ) {
                return $time;
            }
        }

        return 0;
    }

    private function combine_datetime( ?string $date, ?string $time ): string {
        $date = $date ? trim( $date ) : '';
        $time = $time ? trim( $time ) : '';
        if ( ! $date && ! $time ) {
            return '';
        }
        if ( $date && $time ) {
            return $date . ' ' . $time;
        }
        return $date ?: $time;
    }

    private function scrape_status_page( array $provider ): ?array {
        if ( empty( $provider['scrape'] ) ) {
            return null;
        }

        $url = $provider['scrape_url'] ?? $provider['url'] ?? '';
        if ( ! $url ) {
            return null;
        }

        $response = wp_remote_get(
            $url,
            [
                'timeout' => min( $this->timeout, 6 ),
                'headers' => [
                    'Accept'     => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                    'User-Agent' => 'LousyOutagesBot/2.0 (' . home_url() . ')',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 400 ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( ! $body ) {
            return null;
        }

        $summary = $this->extract_summary_from_html( $body );
        if ( ! $summary ) {
            return null;
        }

        $status = $this->infer_status_from_text( $summary );

        return [
            'status'     => $status,
            'summary'    => $summary,
            'message'    => $summary,
            'incidents'  => [],
            'updated_at' => gmdate( 'c' ),
        ];
    }

    private function extract_summary_from_html( string $html ): string {
        $candidates = [];
        if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)/i', $html, $matches ) ) {
            $candidates[] = $matches[1];
        }
        if ( preg_match( '/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)/i', $html, $matches ) ) {
            $candidates[] = $matches[1];
        }
        if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches ) ) {
            $candidates[] = $matches[1];
        }

        foreach ( $candidates as $candidate ) {
            $decoded = html_entity_decode( $candidate, ENT_QUOTES | ENT_HTML5 );
            $clean   = $this->sanitize( $decoded );
            if ( $clean ) {
                return $clean;
            }
        }

        return '';
    }

    private function infer_status_from_text( string $text ): string {
        $text = strtolower( $text );
        if ( preg_match( '/maintenance|scheduled work|planned work/', $text ) ) {
            return 'maintenance';
        }
        if ( preg_match( '/outage|disruption|downtime|major incident|service interruption|degraded/', $text ) ) {
            if ( preg_match( '/partial|intermittent|degrad/', $text ) ) {
                return 'degraded';
            }
            return 'outage';
        }
        if ( preg_match( '/degrad|intermittent|partial outage/', $text ) ) {
            return 'degraded';
        }
        if ( preg_match( '/all systems operational|no incidents reported|operating normally|running normally/', $text ) ) {
            return 'operational';
        }
        return 'unknown';
    }

    private function normalize_impact( $value ): string {
        $value = strtolower( (string) $value );
        if ( in_array( $value, self::IMPACT_LEVELS, true ) ) {
            return $value;
        }
        if ( 'critical' === $value || 'severe' === $value ) {
            return 'critical';
        }
        if ( 'major' === $value || 'medium' === $value ) {
            return 'major';
        }
        if ( 'minor' === $value || 'low' === $value ) {
            return 'minor';
        }
        return 'minor';
    }

    private function extract_eta_from_updates( array $updates ): string {
        foreach ( $updates as $update ) {
            if ( ! is_array( $update ) ) {
                continue;
            }
            if ( ! empty( $update['eta'] ) ) {
                return $this->sanitize( (string) $update['eta'] );
            }
            if ( ! empty( $update['metadata']['eta'] ) ) {
                return $this->sanitize( (string) $update['metadata']['eta'] );
            }
            if ( ! empty( $update['next_update'] ) ) {
                return $this->sanitize( (string) $update['next_update'] );
            }
            $body = (string) ( $update['body'] ?? '' );
            if ( preg_match( '/ETA[^:]*[:\-]\s*([^
]+)/i', $body, $matches ) ) {
                return $this->sanitize( $matches[1] );
            }
        }
        return 'investigating';
    }

    private function extract_eta_from_text( $value ): string {
        if ( is_string( $value ) && $value ) {
            return $this->sanitize( $value );
        }
        if ( is_array( $value ) && isset( $value['text'] ) ) {
            return $this->sanitize( (string) $value['text'] );
        }
        return 'investigating';
    }

    private function iso( $time ): string {
        if ( empty( $time ) ) {
            return '';
        }
        if ( is_numeric( $time ) ) {
            $timestamp = (int) $time;
        } else {
            $timestamp = strtotime( (string) $time );
        }
        if ( ! $timestamp ) {
            return '';
        }
        return gmdate( 'c', $timestamp );
    }

    private function sanitize( string $text ): string {
        if ( function_exists( 'wp_strip_all_tags' ) ) {
            return trim( wp_strip_all_tags( $text ) );
        }
        return trim( strip_tags( $text ) );
    }
}

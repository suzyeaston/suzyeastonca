<?php
namespace LousyOutages;

class Fetcher {
    private const STATUS_LABELS = [
        'operational' => 'Operational',
        'degraded'    => 'Degraded',
        'outage'      => 'Outage',
        'unknown'     => 'Unknown',
    ];

    private int $timeout;

    public function __construct( int $timeout = 8 ) {
        $this->timeout = $timeout;
    }

    public static function status_label( string $code ): string {
        $code = strtolower( $code );
        return self::STATUS_LABELS[ $code ] ?? self::STATUS_LABELS['unknown'];
    }

    /**
     * Fetch and normalize data for a provider.
     */
    public function fetch( array $provider ): array {
        $result = [
            'id'           => $provider['id'],
            'name'         => $provider['name'],
            'provider'     => $provider['id'],
            'status'       => 'unknown',
            'status_label' => self::status_label( 'unknown' ),
            'message'      => isset( $provider['note'] ) ? wp_strip_all_tags( (string) $provider['note'] ) : '',
            'updated_at'   => gmdate( 'c' ),
            'url'          => $provider['url'],
            'error'        => null,
        ];

        if ( empty( $provider['endpoint'] ) ) {
            if ( empty( $result['message'] ) ) {
                $result['message'] = 'No public status API';
            }
            return $result;
        }

        $response = wp_remote_get(
            $provider['endpoint'],
            [
                'timeout' => $this->timeout,
                'headers' => [
                    'Accept'        => 'application/json, text/xml;q=0.9,*/*;q=0.8',
                    'Cache-Control' => 'no-cache',
                    'User-Agent'    => 'LousyOutagesBot/1.0 (' . home_url() . ')',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            $result['message'] = 'Request failed';
            $result['error']   = $response->get_error_message();
            return $result;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 400 ) {
            $result['message'] = sprintf( 'HTTP %d from status API', $code );
            $result['error']   = 'http_error';
            return $result;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( ! $body ) {
            $result['message'] = 'Empty response from status API';
            $result['error']   = 'empty_body';
            return $result;
        }

        $parsed = $this->parse_body( $provider, $body );
        $result = array_merge( $result, $parsed );
        $result['status_label'] = self::status_label( $result['status'] );
        $result['message']      = wp_strip_all_tags( (string) $result['message'] );
        $result['updated_at']   = $parsed['updated_at'] ?? gmdate( 'c' );

        return $result;
    }

    private function parse_body( array $provider, string $body ): array {
        if ( 'statuspage' === $provider['type'] ) {
            return $this->parse_statuspage( $body );
        }

        return $this->parse_feed( $body );
    }

    private function parse_statuspage( string $body ): array {
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return [
                'status'  => 'unknown',
                'message' => 'Unrecognized response payload',
            ];
        }

        $indicator = strtolower( $decoded['status']['indicator'] ?? 'none' );
        $map       = [
            'none'           => 'operational',
            'minor'          => 'degraded',
            'major'          => 'outage',
            'critical'       => 'outage',
            'maintenance'    => 'degraded',
            'partial_outage' => 'degraded',
        ];
        $status  = $map[ $indicator ] ?? 'unknown';
        $message = $decoded['status']['description'] ?? '';
        if ( empty( $message ) && ! empty( $decoded['page']['name'] ) ) {
            $message = sprintf( '%s is operational', $decoded['page']['name'] );
        }

        $updated    = $decoded['page']['updated_at'] ?? null;
        $timestamp  = $updated ? strtotime( (string) $updated ) : false;

        return [
            'status'     => $status,
            'message'    => $message,
            'updated_at' => $timestamp ? gmdate( 'c', (int) $timestamp ) : gmdate( 'c' ),
        ];
    }

    private function parse_feed( string $body ): array {
        $previous = libxml_use_internal_errors( true );
        $xml      = simplexml_load_string( $body );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $xml ) {
            return [
                'status'  => 'unknown',
                'message' => 'Unable to read feed',
            ];
        }

        $items = [];
        if ( isset( $xml->channel->item ) ) {
            foreach ( $xml->channel->item as $item ) {
                $items[] = $item;
            }
        } elseif ( isset( $xml->entry ) ) {
            foreach ( $xml->entry as $entry ) {
                $items[] = $entry;
            }
        }

        $has_items = ! empty( $items );
        $message   = '';
        if ( $has_items ) {
            $first = $items[0];
            if ( isset( $first->title ) ) {
                $message = (string) $first->title;
            } elseif ( isset( $first->summary ) ) {
                $message = (string) $first->summary;
            }
        }

        $updated   = null;
        if ( isset( $xml->channel->pubDate ) ) {
            $updated = (string) $xml->channel->pubDate;
        } elseif ( isset( $xml->updated ) ) {
            $updated = (string) $xml->updated;
        }
        $timestamp = $updated ? strtotime( $updated ) : false;

        return [
            'status'     => $has_items ? 'degraded' : 'operational',
            'message'    => $has_items ? $message : 'All systems operational',
            'updated_at' => $timestamp ? gmdate( 'c', (int) $timestamp ) : gmdate( 'c' ),
        ];
    }
}

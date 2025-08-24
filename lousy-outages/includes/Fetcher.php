<?php
namespace LousyOutages;

class Fetcher {
    /**
     * Fetch and normalize data for a provider.
     */
    public function fetch( array $provider ): ?array {
        $cache_key = 'lo_fetch_' . $provider['id'];
        $cached    = get_transient( $cache_key );
        if ( $cached ) {
            return $cached;
        }
        $response = wp_remote_get( $provider['endpoint'], [ 'timeout' => 8 ] );
        if ( is_wp_error( $response ) ) {
            return null;
        }
        $body = wp_remote_retrieve_body( $response );
        $data = null;
        if ( 'statuspage' === $provider['type'] ) {
            $decoded = json_decode( $body, true );
            if ( ! $decoded ) {
                return null;
            }
            $indicator = $decoded['status']['indicator'] ?? 'none';
            $map       = [
                'none'     => 'operational',
                'minor'    => 'degraded',
                'major'    => 'major_outage',
                'critical' => 'major_outage',
            ];
            $status = $map[ $indicator ] ?? 'operational';
            $message = $decoded['status']['description'] ?? '';
            $updated = $decoded['page']['updated_at'] ?? gmdate( 'c' );
        } else { // rss/atom
            $xml = @simplexml_load_string( $body );
            if ( ! $xml ) {
                return null;
            }
            $has_items = isset( $xml->channel->item ) && count( $xml->channel->item ) > 0;
            if ( ! $has_items && isset( $xml->entry ) ) {
                $has_items = count( $xml->entry ) > 0;
            }
            $status  = $has_items ? 'degraded' : 'operational';
            $message = $has_items ? (string) ( $xml->channel->item[0]->title ?? $xml->entry[0]->title ) : 'All systems go';
            $updated = gmdate( 'c' );
        }
        $data = [
            'id'         => $provider['id'],
            'name'       => $provider['name'],
            'status'     => $status,
            'message'    => wp_strip_all_tags( (string) $message ),
            'updated_at' => $updated,
            'url'        => $provider['url'],
        ];
        set_transient( $cache_key, $data, 120 );
        return $data;
    }
}

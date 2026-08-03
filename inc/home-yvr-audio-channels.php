<?php
/**
 * YVR BCAST live audio channels — streams, link-outs, soundscapes, map pins.
 */

function se_broadcaster_orcasound_live_hls_url( string $node_name ): ?string {
    $node_name = preg_replace( '/[^a-z0-9_]/', '', strtolower( $node_name ) );
    if ( ! $node_name ) {
        return null;
    }

    $cache_key = 'se_orcasound_latest_' . $node_name;
    $cached    = get_transient( $cache_key );
    if ( is_array( $cached ) && ! empty( $cached['url'] ) && ( time() - (int) ( $cached['ts'] ?? 0 ) ) < 120 ) {
        return (string) $cached['url'];
    }

    $latest_url = sprintf(
        'https://audio-orcasound-net.s3.us-west-2.amazonaws.com/%s/latest.txt',
        $node_name
    );
    $response   = wp_remote_get( $latest_url, array( 'timeout' => 8 ) );
    if ( is_wp_error( $response ) ) {
        return null;
    }

    $stamp = trim( (string) wp_remote_retrieve_body( $response ) );
    if ( ! preg_match( '/^\d+$/', $stamp ) ) {
        return null;
    }

    $hls = sprintf(
        'https://audio-orcasound-net.s3.us-west-2.amazonaws.com/%s/hls/%s/live.m3u8',
        $node_name,
        $stamp
    );

    set_transient(
        $cache_key,
        array( 'url' => $hls, 'ts' => time() ),
        2 * MINUTE_IN_SECONDS
    );

    return $hls;
}

function se_broadcaster_broadcastify_stream_url( int $feed_id ): ?string {
    if ( $feed_id < 1 ) {
        return null;
    }

    $cache_key = 'se_bcfy_stream_' . $feed_id;
    $cached    = get_transient( $cache_key );
    if ( is_string( $cached ) && $cached !== '' ) {
        return $cached;
    }

    $page_url = 'https://www.broadcastify.com/listen/feed/' . $feed_id . '/web';
    $page     = wp_remote_get(
        $page_url,
        array(
            'timeout' => 12,
            'headers' => array(
                'User-Agent' => 'suzyeastonca-yvr-bcast/1.0',
            ),
        )
    );
    if ( is_wp_error( $page ) ) {
        set_transient( $cache_key, '', 5 * MINUTE_IN_SECONDS );
        return null;
    }

    $body = (string) wp_remote_retrieve_body( $page );
    if ( ! preg_match( '/"webAuth":\s*"([^"]+)"/', $body, $auth_match ) ) {
        set_transient( $cache_key, '', 5 * MINUTE_IN_SECONDS );
        return null;
    }

    $relay = wp_remote_post(
        'https://www.broadcastify.com/listen/webpl.php?feedId=' . $feed_id,
        array(
            'timeout' => 12,
            'headers' => array(
                'webAuth'    => $auth_match[1],
                'User-Agent' => 'suzyeastonca-yvr-bcast/1.0',
            ),
            'body'    => 't=14',
        )
    );
    if ( is_wp_error( $relay ) ) {
        set_transient( $cache_key, '', 5 * MINUTE_IN_SECONDS );
        return null;
    }

    $relay_body = (string) wp_remote_retrieve_body( $relay );
    if ( ! preg_match( '/https?:\/\/[^"\s]+\.mp3[^"\s]*/', $relay_body, $url_match ) ) {
        set_transient( $cache_key, '', 5 * MINUTE_IN_SECONDS );
        return null;
    }

    $stream_url = html_entity_decode( $url_match[0] );
    set_transient( $cache_key, $stream_url, 10 * MINUTE_IN_SECONDS );

    return $stream_url;
}

function se_broadcaster_audio_channel_catalog(): array {
    $theme_uri = get_template_directory_uri();

    return array(
        'cknw'           => array(
            'key'         => 'cknw',
            'label'       => 'CKNW 980',
            'hint'        => 'News talk live',
            'freq'        => '980.000',
            'mode'        => 'stream',
            'format'      => 'mp3',
            'stream_url'  => 'https://live.leanstream.co/CKNWAM',
            'map_lat'     => 49.2863,
            'map_lon'     => -123.1240,
            'source'      => 'CKNW 980',
            'source_url'  => 'https://www.cknw.com/',
            'pin_tier'    => 'radio',
        ),
        'cbc'            => array(
            'key'         => 'cbc',
            'label'       => 'CBC R1',
            'hint'        => 'Vancouver live',
            'freq'        => '690.000',
            'mode'        => 'stream',
            'format'      => 'hls',
            'stream_url'  => 'https://cbcradiolive.akamaized.net/hls/live/2041050/ES_R1PVC/master.m3u8',
            'map_lat'     => 49.2820,
            'map_lon'     => -123.1180,
            'source'      => 'CBC Radio One Vancouver',
            'source_url'  => 'https://www.cbc.ca/listen/live-radio/1-cbc-radio-one-vancouver',
            'pin_tier'    => 'radio',
        ),
        'yvr_tower'      => array(
            'key'         => 'yvr_tower',
            'label'       => 'YVR TWR',
            'hint'        => 'LiveATC approach',
            'freq'        => '119.300',
            'mode'        => 'link_out',
            'link_url'    => 'https://www.liveatc.net/hlisten.php?mount=cyvr1_app&icao=cyvr',
            'link_label'  => 'Listen on LiveATC',
            'map_lat'     => 49.1939,
            'map_lon'     => -123.1764,
            'source'      => 'LiveATC.net',
            'source_url'  => 'https://www.liveatc.net/search/?icao=CYVR',
            'pin_tier'    => 'atc',
        ),
        'marine_vhf'     => array(
            'key'               => 'marine_vhf',
            'label'             => 'MARINE VHF',
            'hint'              => 'Port traffic scan',
            'freq'              => '156.800',
            'mode'              => 'broadcastify',
            'broadcastify_id'   => 47189,
            'link_url'          => 'https://www.broadcastify.com/listen/feed/47189',
            'link_label'        => 'Broadcastify marine feed',
            'map_lat'           => 49.2700,
            'map_lon'           => -123.1500,
            'source'            => 'Broadcastify',
            'source_url'        => 'https://www.broadcastify.com/listen/feed/47189',
            'pin_tier'          => 'marine',
        ),
        'hydro_bush'     => array(
            'key'             => 'hydro_bush',
            'label'           => 'HYDRO BUSH',
            'hint'            => 'Salish live hydro',
            'freq'            => 'H2O.001',
            'mode'            => 'stream',
            'format'          => 'hls',
            'orcasound_node'  => 'rpi_bush_point',
            'map_lat'         => 49.0337,
            'map_lon'         => -122.6040,
            'source'          => 'Orcasound — Bush Point',
            'source_url'      => 'https://live.orcasound.net/listen/bush-point',
            'pin_tier'        => 'hydro',
        ),
        'hydro_mast'     => array(
            'key'             => 'hydro_mast',
            'label'           => 'HYDRO MAST',
            'hint'            => 'Puget hydro live',
            'freq'            => 'H2O.002',
            'mode'            => 'stream',
            'format'          => 'hls',
            'orcasound_node'  => 'rpi_mast_center',
            'map_lat'         => 47.3492,
            'map_lon'         => -122.3251,
            'source'          => 'Orcasound — MaST Center',
            'source_url'      => 'https://live.orcasound.net/listen/mast-center',
            'pin_tier'        => 'hydro',
        ),
        'sound_skytrain' => array(
            'key'         => 'sound_skytrain',
            'label'       => 'SKYTRAIN',
            'hint'        => 'Rumble loop bed',
            'freq'        => 'LOOP.01',
            'mode'        => 'soundscape',
            'format'      => 'mp3',
            'stream_url'  => $theme_uri . '/assets/audio/yvr/skytrain-rumble.mp3',
            'loop'        => true,
            'map_lat'     => 49.2857,
            'map_lon'     => -123.1119,
            'source'      => 'Site soundscape',
            'source_url'  => home_url( '/' ),
            'pin_tier'    => 'bed',
        ),
        'sound_rain'     => array(
            'key'         => 'sound_rain',
            'label'       => 'RAIN',
            'hint'        => 'West coast bed',
            'freq'        => 'LOOP.02',
            'mode'        => 'soundscape',
            'format'      => 'mp3',
            'stream_url'  => $theme_uri . '/assets/audio/yvr/rain-loop.mp3',
            'loop'        => true,
            'map_lat'     => 49.2827,
            'map_lon'     => -123.1207,
            'source'      => 'Site soundscape',
            'source_url'  => home_url( '/' ),
            'pin_tier'    => 'bed',
        ),
        'sound_ferry'    => array(
            'key'         => 'sound_ferry',
            'label'       => 'FERRY HORN',
            'hint'        => 'Terminal bed',
            'freq'        => 'LOOP.03',
            'mode'        => 'soundscape',
            'format'      => 'mp3',
            'stream_url'  => $theme_uri . '/assets/audio/yvr/ferry-horn.mp3',
            'loop'        => true,
            'map_lat'     => 49.0067,
            'map_lon'     => -123.1292,
            'source'      => 'Site soundscape',
            'source_url'  => home_url( '/' ),
            'pin_tier'    => 'bed',
        ),
    );
}

function se_broadcaster_resolve_audio_channel( array $channel ): array {
    $out = $channel;
    $out['stream_url'] = '';
    $out['stream_ok']  = false;

    if ( $channel['mode'] === 'stream' || $channel['mode'] === 'soundscape' ) {
        if ( ! empty( $channel['orcasound_node'] ) ) {
            $resolved = se_broadcaster_orcasound_live_hls_url( (string) $channel['orcasound_node'] );
            if ( $resolved ) {
                $out['stream_url'] = $resolved;
                $out['format']     = 'hls';
                $out['stream_ok']  = true;
            }
        } elseif ( ! empty( $channel['stream_url'] ) ) {
            $out['stream_url'] = (string) $channel['stream_url'];
            $out['stream_ok']  = true;
        }
    }

    if ( $channel['mode'] === 'broadcastify' ) {
        $feed_id = (int) ( $channel['broadcastify_id'] ?? 0 );
        $resolved = se_broadcaster_broadcastify_stream_url( $feed_id );
        if ( $resolved ) {
            $out['stream_url'] = $resolved;
            $out['format']     = 'mp3';
            $out['stream_ok']  = true;
        }
    }

    return $out;
}

function se_broadcaster_audio_channels_for_client(): array {
    $catalog = se_broadcaster_audio_channel_catalog();
    $out     = array();

    foreach ( $catalog as $key => $channel ) {
        $resolved = se_broadcaster_resolve_audio_channel( $channel );
        $out[ $key ] = array(
            'key'        => $key,
            'label'      => $channel['label'],
            'hint'       => $channel['hint'],
            'freq'       => $channel['freq'],
            'mode'       => $channel['mode'],
            'format'     => $resolved['format'] ?? ( $channel['format'] ?? 'mp3' ),
            'stream_url' => $resolved['stream_url'],
            'stream_ok'  => (bool) $resolved['stream_ok'],
            'loop'       => ! empty( $channel['loop'] ),
            'link_url'   => $channel['link_url'] ?? '',
            'link_label' => $channel['link_label'] ?? '',
            'source'     => $channel['source'] ?? '',
            'source_url' => $channel['source_url'] ?? '',
            'pin_tier'   => $channel['pin_tier'] ?? 'radio',
            'map_lat'    => (float) ( $channel['map_lat'] ?? 0 ),
            'map_lon'    => (float) ( $channel['map_lon'] ?? 0 ),
        );
    }

    return $out;
}

function se_broadcaster_audio_map_anchors(): array {
    $anchors = array();
    foreach ( se_broadcaster_audio_channel_catalog() as $channel ) {
        $anchors[] = array(
            'key'   => $channel['key'],
            'label' => $channel['label'],
            'hint'  => $channel['hint'],
            'lat'   => (float) $channel['map_lat'],
            'lon'   => (float) $channel['map_lon'],
            'tier'  => $channel['pin_tier'] ?? 'radio',
        );
    }
    return $anchors;
}

function se_broadcaster_dave_ambient_cycle_keys(): array {
    return array( 'hydro_bush', 'hydro_mast', 'sound_rain', 'sound_skytrain', 'sound_ferry' );
}

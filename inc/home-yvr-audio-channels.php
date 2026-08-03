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

function se_broadcaster_broadcastify_api_key(): string {
    if ( defined( 'SE_BROADCASTIFY_API_KEY' ) && SE_BROADCASTIFY_API_KEY ) {
        return (string) SE_BROADCASTIFY_API_KEY;
    }
    return '';
}

function se_broadcaster_liveatc_embed_enabled(): bool {
    if ( defined( 'SE_LIVEATC_EMBED' ) ) {
        return (bool) SE_LIVEATC_EMBED;
    }
    return true;
}

function se_broadcaster_broadcastify_feed_online( int $feed_id ): ?bool {
    $status = se_broadcaster_broadcastify_feed_status( $feed_id );
    if ( is_array( $status ) && array_key_exists( 'online', $status ) ) {
        return (bool) $status['online'];
    }

    $api_key = se_broadcaster_broadcastify_api_key();
    if ( ! $api_key || $feed_id < 1 ) {
        return null;
    }

    $cache_key = 'se_bcfy_online_' . $feed_id;
    $cached    = get_transient( $cache_key );
    if ( is_bool( $cached ) ) {
        return $cached;
    }

    $url = add_query_arg(
        array(
            'a'      => 'feed',
            'feedId' => $feed_id,
            'type'   => 'json',
            'key'    => $api_key,
        ),
        'https://api.broadcastify.com/audio/'
    );

    $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
    if ( is_wp_error( $response ) ) {
        return null;
    }

    $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || empty( $body['feed'] ) || ! is_array( $body['feed'] ) ) {
        return null;
    }

    $online = ! empty( $body['feed']['online'] ) || (string) ( $body['feed']['status'] ?? '' ) === '1';
    set_transient( $cache_key, $online, 2 * MINUTE_IN_SECONDS );

    return $online;
}

function se_broadcaster_broadcastify_feed_status( int $feed_id ): ?array {
    if ( $feed_id < 1 ) {
        return null;
    }

    $cache_key = 'se_bcfy_status_' . $feed_id;
    $cached    = get_transient( $cache_key );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    $response = wp_remote_get(
        'https://status.broadcastify.com/feed-status/' . $feed_id,
        array(
            'timeout' => 8,
            'headers' => array(
                'User-Agent' => 'suzyeastonca-yvr-bcast/1.0',
            ),
        )
    );
    if ( is_wp_error( $response ) ) {
        return null;
    }

    $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) ) {
        return null;
    }

    $out = array(
        'online'    => (int) ( $body['status'] ?? 0 ) === 1,
        'listeners' => (int) ( $body['listeners'] ?? 0 ),
    );
    set_transient( $cache_key, $out, 90 );

    return $out;
}

function se_broadcaster_broadcastify_hls_url_from_page( int $feed_id ): ?string {
    $page_url = 'https://www.broadcastify.com/listen/feed/' . $feed_id;
    $page     = wp_remote_get(
        $page_url,
        array(
            'timeout' => 12,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (compatible; suzyeastonca-yvr-bcast/1.0)',
            ),
        )
    );
    if ( is_wp_error( $page ) ) {
        return null;
    }

    $body = (string) wp_remote_retrieve_body( $page );
    if ( preg_match( '/hlsUrl:\s*"([^"]+)"/', $body, $match ) ) {
        return str_replace( '\\/', '/', $match[1] );
    }

    return null;
}

function se_broadcaster_broadcastify_hls_playlist_url( int $feed_id ): string {
    $from_page = se_broadcaster_broadcastify_hls_url_from_page( $feed_id );
    if ( $from_page ) {
        return $from_page;
    }

    return sprintf( 'https://hls-o1.broadcastify.com/s2/feed/%d/playlist.m3u8', $feed_id );
}

function se_broadcaster_broadcastify_hls_has_segments( string $playlist_url ): bool {
    $response = wp_remote_get(
        $playlist_url,
        array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'suzyeastonca-yvr-bcast/1.0',
            ),
        )
    );
    if ( is_wp_error( $response ) ) {
        return false;
    }

    $body = (string) wp_remote_retrieve_body( $response );
    return strpos( $body, '.ts' ) !== false;
}

function se_broadcaster_broadcastify_stream_url( int $feed_id ): ?string {
    if ( $feed_id < 1 ) {
        return null;
    }

    $cache_key = 'se_bcfy_stream_' . $feed_id;
    $cached    = get_transient( $cache_key );
    if ( is_string( $cached ) && $cached !== '' ) {
        if ( se_broadcaster_broadcastify_hls_has_segments( $cached ) ) {
            return $cached;
        }
        delete_transient( $cache_key );
    }

    $playlist = se_broadcaster_broadcastify_hls_playlist_url( $feed_id );
    if ( ! se_broadcaster_broadcastify_hls_has_segments( $playlist ) ) {
        set_transient( $cache_key, '', 3 * MINUTE_IN_SECONDS );
        return null;
    }

    set_transient( $cache_key, $playlist, 10 * MINUTE_IN_SECONDS );

    return $playlist;
}

function se_broadcaster_marine_broadcastify_feed_ids(): array {
    // 47189 = Vancouver port ch 11/12/16 (often offline). Fallbacks: Comox, Sointula, EC marine WX.
    return array( 47189, 32393, 32901, 44288 );
}

function se_broadcaster_channel_deck_notes(): array {
    return array(
        'cknw'           => array(
            'deck_copy' => 'CKNW 980 — Vancouver news talk. Traffic, politics, local chaos. LeanStream relay.',
            'deck_note' => 'In-page stream. No new tabs.',
        ),
        'cbc'            => array(
            'deck_copy' => 'CBC Radio One Vancouver — national + local news on the HLS feed.',
            'deck_note' => 'In-page stream. No new tabs.',
        ),
        'yvr_tower'      => array(
            'deck_copy' => 'YVR approach — planes lining up over the Fraser. LiveATC embed.',
            'deck_note' => 'In-page stream. Attribution on screen.',
        ),
        'yvr_ground'     => array(
            'deck_copy' => 'YVR ground and tower mix from LiveATC. Taxi, takeoff, tower handoffs.',
            'deck_note' => 'In-page stream.',
        ),
        'yvr_combo'      => array(
            'deck_copy' => 'YVR combo — clearance, ground, tower, approach on one mount. Busy hours only.',
            'deck_note' => 'Hidden gem. LiveATC mash feed.',
        ),
        'yvr_dep2'       => array(
            'deck_copy' => 'YVR departure lane two — second approach/dep frequency when it splits.',
            'deck_note' => 'LiveATC. Quieter than the combo mount.',
        ),
        'vzvr_acc'       => array(
            'deck_copy' => 'Vancouver area control — high-altitude handoffs around YVR airspace.',
            'deck_note' => 'LiveATC ACC feed.',
        ),
        'burnaby_fire'   => array(
            'deck_copy' => 'Burnaby Fire dispatch — municipal fire/EMS scanner. Often the liveliest public-safety feed near YVR.',
            'deck_note' => 'Broadcastify scanner. Footer link opens source only if you tap it.',
        ),
        'marine_vhf'     => array(
            'deck_copy' => 'Marine VHF — port traffic, Coast Guard, distress. Tries Vancouver ch 11/12/16 first, then Comox, Sointula, EC marine weather WX.',
            'deck_note' => 'Broadcastify chain. Stays on Dave — footer link is optional.',
        ),
        'hydro_bush'     => array(
            'deck_copy' => 'Orcasound hydrophone at Bush Point — Salish Sea live. Whales, ships, weird water noise.',
            'deck_note' => 'HLS from Orcasound. Field mic, not radio.',
        ),
        'hydro_mast'     => array(
            'deck_copy' => 'Orcasound MaST Center hydrophone — Puget-side listen. Same vibe, different inlet.',
            'deck_note' => 'HLS from Orcasound.',
        ),
        'sound_skytrain' => array(
            'deck_copy' => 'SkyTrain rumble loop — field bed, not live airwaves.',
            'deck_note' => 'Soundscape only. Dave ambient cycle.',
        ),
        'sound_rain'     => array(
            'deck_copy' => 'West coast rain bed — looped field recording.',
            'deck_note' => 'Soundscape only.',
        ),
        'sound_ferry'    => array(
            'deck_copy' => 'Ferry horn bed — terminal whistle loop when marine scanners are quiet.',
            'deck_note' => 'Soundscape fallback for ferries pin.',
        ),
    );
}

function se_broadcaster_pin_deck_copy( string $pin_key ): string {
    $copy = array(
        'translink' => 'TransLink alerts on screen. Audio tries CKNW, then marine VHF, then skytrain bed.',
        'drivers'   => 'DriveBC incidents on the map dots and bulletin. CKNW for road/traffic radio.',
        'ferries'   => 'BC Ferries sailings + capacity bulletin. Live marine VHF first, ferry horn bed if scanners are down.',
        'weather'   => 'Environment Canada weather alerts bulletin. CBC Radio One for live forecast chatter.',
        'wildfire'  => 'BC wildfire incidents bulletin. Burnaby FD + BCWS repeater chain underneath.',
        'air'       => 'Metro Vancouver AQHI bulletin. Salish hydrophones when you want ambience with the data.',
    );
    return $copy[ $pin_key ] ?? '';
}

function se_broadcaster_apply_channel_deck_notes( array $channel ): array {
    $notes = se_broadcaster_channel_deck_notes();
    $key   = (string) ( $channel['key'] ?? '' );
    if ( isset( $notes[ $key ] ) ) {
        $channel['deck_copy'] = $notes[ $key ]['deck_copy'];
        $channel['deck_note'] = $notes[ $key ]['deck_note'];
    }
    return $channel;
}

function se_broadcaster_audio_channel_catalog(): array {
    $theme_uri = get_template_directory_uri();

    $channels = array(
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
        'yvr_tower'      => se_broadcaster_liveatc_embed_enabled()
            ? array(
                'key'              => 'yvr_tower',
                'label'            => 'YVR APP',
                'hint'             => 'LiveATC approach',
                'freq'             => '119.300',
                'mode'             => 'stream',
                'format'           => 'mp3',
                'stream_url'       => 'https://d.liveatc.net/cyvr1_app',
                'link_url'         => 'https://www.liveatc.net/hlisten.php?mount=cyvr1_app&icao=cyvr',
                'link_label'       => 'LiveATC.net',
                'map_lat'          => 49.1939,
                'map_lon'          => -123.1764,
                'source'           => 'LiveATC.net',
                'source_url'       => 'https://www.liveatc.net/search/?icao=CYVR',
                'pin_tier'         => 'atc',
                'attribution'      => 'Audio via LiveATC.net — not affiliated. Do not redistribute.',
                'attribution_url'  => 'https://www.liveatc.net/',
            )
            : array(
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
        'yvr_ground'     => array(
            'key'              => 'yvr_ground',
            'label'            => 'YVR GND',
            'hint'             => 'LiveATC ground/twr',
            'freq'             => '121.700',
            'mode'             => 'stream',
            'format'           => 'mp3',
            'stream_url'       => 'https://d.liveatc.net/cyvr1_gnd_twr',
            'link_url'         => 'https://www.liveatc.net/hlisten.php?mount=cyvr1_gnd_twr&icao=cyvr',
            'link_label'       => 'LiveATC.net',
            'map_lat'          => 49.1945,
            'map_lon'          => -123.1750,
            'source'           => 'LiveATC.net',
            'source_url'       => 'https://www.liveatc.net/search/?icao=CYVR',
            'pin_tier'         => 'atc',
            'attribution'      => 'Audio via LiveATC.net — not affiliated. Do not redistribute.',
            'attribution_url'  => 'https://www.liveatc.net/',
            'embed_only'       => true,
        ),
        'yvr_combo'      => array(
            'key'              => 'yvr_combo',
            'label'            => 'YVR MIX',
            'hint'             => 'Del/gnd/twr/app mash',
            'freq'             => 'MULTI',
            'mode'             => 'stream',
            'format'           => 'mp3',
            'stream_url'       => 'https://d.liveatc.net/cyvr_s',
            'link_url'         => 'https://www.liveatc.net/hlisten.php?mount=cyvr_s&icao=cyvr',
            'link_label'       => 'LiveATC combo feed',
            'map_lat'          => 49.1939,
            'map_lon'          => -123.1764,
            'source'           => 'LiveATC.net',
            'source_url'       => 'https://www.liveatc.net/search/?icao=CYVR',
            'pin_tier'         => 'atc',
            'attribution'      => 'Audio via LiveATC.net — not affiliated. Do not redistribute.',
            'attribution_url'  => 'https://www.liveatc.net/',
        ),
        'yvr_dep2'       => array(
            'key'              => 'yvr_dep2',
            'label'            => 'YVR DEP2',
            'hint'             => 'App/dep lane two',
            'freq'             => '126.125',
            'mode'             => 'stream',
            'format'           => 'mp3',
            'stream_url'       => 'https://d.liveatc.net/cyvr2',
            'link_url'         => 'https://www.liveatc.net/hlisten.php?mount=cyvr2&icao=cyvr',
            'link_label'       => 'LiveATC App/Dep #2',
            'map_lat'          => 49.1939,
            'map_lon'          => -123.1764,
            'source'           => 'LiveATC.net',
            'source_url'       => 'https://www.liveatc.net/search/?icao=CYVR',
            'pin_tier'         => 'atc',
            'attribution'      => 'Audio via LiveATC.net — not affiliated. Do not redistribute.',
            'attribution_url'  => 'https://www.liveatc.net/',
        ),
        'vzvr_acc'       => array(
            'key'              => 'vzvr_acc',
            'label'            => 'VAN ACC',
            'hint'             => 'Vancouver area control',
            'freq'             => '132.550',
            'mode'             => 'stream',
            'format'           => 'mp3',
            'stream_url'       => 'https://d.liveatc.net/cyvr1_ctr',
            'link_url'         => 'https://www.liveatc.net/hlisten.php?mount=cyvr1_ctr&icao=cyvr',
            'link_label'       => 'LiveATC Vancouver ACC',
            'map_lat'          => 49.2500,
            'map_lon'          => -123.1000,
            'source'           => 'LiveATC.net',
            'source_url'       => 'https://www.liveatc.net/search/?icao=CYVR',
            'pin_tier'         => 'atc',
            'attribution'      => 'Audio via LiveATC.net — not affiliated. Do not redistribute.',
            'attribution_url'  => 'https://www.liveatc.net/',
        ),
        'wildfire_fraser'     => array(
            'key'               => 'wildfire_fraser',
            'label'             => 'WF FRASER',
            'hint'              => 'BCWS repeater scan',
            'freq'              => '164.145',
            'mode'              => 'broadcastify',
            'broadcastify_id'   => 47304,
            'broadcastify_ids'  => array( 47304 ),
            'link_url'          => 'https://www.broadcastify.com/listen/feed/47304',
            'link_label'        => 'Fraser district wildfire feed',
            'map_lat'           => 49.2480,
            'map_lon'           => -122.8900,
            'source'            => 'Broadcastify — BC Wildfire Fraser',
            'source_url'        => 'https://www.broadcastify.com/listen/feed/47304',
            'pin_tier'          => 'scan',
            'pin_only'          => true,
        ),
        'wildfire_seatosky'   => array(
            'key'               => 'wildfire_seatosky',
            'label'             => 'WF SEA-SKY',
            'hint'              => 'BCWS repeater scan',
            'freq'              => '164.115',
            'mode'              => 'broadcastify',
            'broadcastify_id'   => 47305,
            'broadcastify_ids'  => array( 47305 ),
            'link_url'          => 'https://www.broadcastify.com/listen/feed/47305',
            'link_label'        => 'Sea to Sky wildfire feed',
            'map_lat'           => 49.2480,
            'map_lon'           => -122.8900,
            'source'            => 'Broadcastify — BC Wildfire Sea to Sky',
            'source_url'        => 'https://www.broadcastify.com/listen/feed/47305',
            'pin_tier'          => 'scan',
            'pin_only'          => true,
        ),
        'wildfire_island'     => array(
            'key'               => 'wildfire_island',
            'label'             => 'WF ISLAND',
            'hint'              => 'BCWS repeater scan',
            'freq'              => '164.265',
            'mode'              => 'broadcastify',
            'broadcastify_id'   => 47303,
            'broadcastify_ids'  => array( 47303 ),
            'link_url'          => 'https://www.broadcastify.com/listen/feed/47303',
            'link_label'        => 'South Island wildfire feed',
            'map_lat'           => 49.2480,
            'map_lon'           => -122.8900,
            'source'            => 'Broadcastify — BC Wildfire S. Island',
            'source_url'        => 'https://www.broadcastify.com/listen/feed/47303',
            'pin_tier'          => 'scan',
            'pin_only'          => true,
        ),
        'burnaby_fire'        => array(
            'key'               => 'burnaby_fire',
            'label'             => 'BURNABY FD',
            'hint'              => 'Fire dispatch live',
            'freq'              => '154.400',
            'mode'              => 'broadcastify',
            'broadcastify_id'   => 38213,
            'broadcastify_ids'  => array( 38213 ),
            'link_url'          => 'https://www.broadcastify.com/listen/feed/38213',
            'link_label'        => 'Burnaby Fire Dispatch',
            'map_lat'           => 49.2480,
            'map_lon'           => -123.0000,
            'source'            => 'Broadcastify — Burnaby Fire',
            'source_url'        => 'https://www.broadcastify.com/listen/feed/38213',
            'pin_tier'          => 'scan',
        ),
        'marine_vhf'     => array(
            'key'               => 'marine_vhf',
            'label'             => 'MARINE VHF',
            'hint'              => 'Port + coast VHF',
            'freq'              => '156.800',
            'mode'              => 'broadcastify',
            'broadcastify_id'   => 47189,
            'broadcastify_ids'  => se_broadcaster_marine_broadcastify_feed_ids(),
            'link_url'          => 'https://www.broadcastify.com/listen/feed/47189',
            'link_label'        => 'Vancouver marine feed (Broadcastify)',
            'map_lat'           => 49.2700,
            'map_lon'           => -123.1500,
            'source'            => 'Broadcastify — BC marine VHF chain',
            'source_url'        => 'https://www.broadcastify.com/listen/stid/102/marine',
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
            'source'      => 'Wikimedia Commons — electric train pass',
            'source_url'  => 'https://commons.wikimedia.org/wiki/File:Sound_of_a_MoHa_201_series_electric_multiple_unit_train_on_the_Ch%C5%AB%C5%8D_Main_Line_(Ochanomizu%E2%80%93Yotsuya),_Tokyo,_Japan_-_20101010.ogg',
            'credit'      => 'Tokyo Chuo Line field recording, CC BY-SA 3.0',
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
            'source'      => 'Effib — Wikimedia Commons',
            'source_url'  => 'https://commons.wikimedia.org/wiki/File:Sound_of_rain.ogg',
            'credit'      => 'Sound of rain by Effib, CC BY-SA 3.0',
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
            'source'      => 'Wikimedia Commons — steamboat whistle',
            'source_url'  => 'https://commons.wikimedia.org/wiki/File:WWS_Steamwhistle.ogg',
            'credit'      => 'WWS Steamwhistle, CC BY-SA 4.0',
            'pin_tier'    => 'bed',
        ),
    );

    if ( ! se_broadcaster_liveatc_embed_enabled() ) {
        unset( $channels['yvr_ground'] );
        if ( isset( $channels['yvr_tower'] ) && $channels['yvr_tower']['mode'] === 'stream' ) {
            $channels['yvr_tower'] = array(
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
            );
        }
    }

    return $channels;
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
        $feed_ids = $channel['broadcastify_ids'] ?? array( (int) ( $channel['broadcastify_id'] ?? 0 ) );
        foreach ( $feed_ids as $feed_id ) {
            $feed_id = (int) $feed_id;
            if ( $feed_id < 1 ) {
                continue;
            }
            $resolved = se_broadcaster_broadcastify_stream_url( $feed_id );
            if ( $resolved ) {
                $out['stream_url'] = $resolved;
                $out['format']     = ( strpos( $resolved, '.m3u8' ) !== false ) ? 'hls' : 'mp3';
                $out['stream_ok']  = true;
                break;
            }
        }
    }

    return $out;
}

function se_broadcaster_audio_channels_for_client(): array {
    $catalog = se_broadcaster_audio_channel_catalog();
    $out     = array();

    foreach ( $catalog as $key => $channel ) {
        $channel  = se_broadcaster_apply_channel_deck_notes( $channel );
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
            'credit'     => $channel['credit'] ?? '',
            'attribution' => $channel['attribution'] ?? '',
            'attribution_url' => $channel['attribution_url'] ?? '',
            'pin_tier'   => $channel['pin_tier'] ?? 'radio',
            'deck_copy'  => $channel['deck_copy'] ?? '',
            'deck_note'  => $channel['deck_note'] ?? '',
            'broadcastify_ids' => array_values(
                array_map(
                    'intval',
                    $channel['broadcastify_ids'] ?? array( (int) ( $channel['broadcastify_id'] ?? 0 ) )
                )
            ),
            'map_lat'    => (float) ( $channel['map_lat'] ?? 0 ),
            'map_lon'    => (float) ( $channel['map_lon'] ?? 0 ),
        );
    }

    return $out;
}

function se_broadcaster_audio_map_anchors(): array {
    $anchors = array();
    foreach ( se_broadcaster_audio_channel_catalog() as $channel ) {
        if ( ! empty( $channel['pin_only'] ) ) {
            continue;
        }
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

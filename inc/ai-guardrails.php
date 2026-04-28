<?php
/**
 * Centralized AI guardrails for public theme features.
 */

if ( ! function_exists( 'se_ai_get_client_fingerprint' ) ) {
    function se_ai_get_client_fingerprint() {
        $user_id = get_current_user_id();
        if ( $user_id ) {
            return 'u_' . wp_hash( 'user:' . $user_id );
        }

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        return 'g_' . hash_hmac( 'sha256', $ip . '|' . $ua, wp_salt( 'auth' ) );
    }
}

if ( ! function_exists( 'se_ai_log_event' ) ) {
    function se_ai_log_event( $feature, $status, $meta = array() ) {
        $safe = array(
            'feature'   => sanitize_key( (string) $feature ),
            'status'    => sanitize_key( (string) $status ),
            'timestamp' => gmdate( 'c' ),
        );

        $allow_meta = array( 'model', 'rate_limit_hit', 'http_code', 'error_code', 'fingerprint' );
        foreach ( $allow_meta as $key ) {
            if ( isset( $meta[ $key ] ) ) {
                $safe[ $key ] = is_scalar( $meta[ $key ] ) ? sanitize_text_field( (string) $meta[ $key ] ) : '';
            }
        }

        error_log( 'SE_AI ' . wp_json_encode( $safe ) );
    }
}

if ( ! function_exists( 'se_ai_public_error_response' ) ) {
    function se_ai_public_error_response( $message, $status = 429 ) {
        return new WP_Error(
            'se_ai_public_error',
            sanitize_text_field( (string) $message ),
            array( 'status' => max( 400, min( 503, (int) $status ) ) )
        );
    }
}

if ( ! function_exists( 'se_ai_rate_limit' ) ) {
    function se_ai_rate_limit( $bucket, $limit, $window_seconds ) {
        $bucket = sanitize_key( (string) $bucket );
        $limit = max( 1, (int) $limit );
        $window_seconds = max( 1, (int) $window_seconds );
        $fingerprint = se_ai_get_client_fingerprint();
        $key = 'se_ai_rl_' . md5( $bucket . '|' . $fingerprint );

        $count = (int) get_transient( $key );
        if ( $count >= $limit ) {
            se_ai_log_event( $bucket, 'rate_limited', array( 'rate_limit_hit' => 'yes', 'fingerprint' => substr( $fingerprint, 0, 16 ) ) );
            return se_ai_public_error_response( 'This tool is cooling down for a bit. Try again later.', 429 );
        }

        set_transient( $key, $count + 1, $window_seconds );
        return true;
    }
}

if ( ! function_exists( 'se_ai_daily_limit' ) ) {
    function se_ai_daily_limit( $bucket, $limit ) {
        $bucket = sanitize_key( (string) $bucket );
        $limit = max( 1, (int) $limit );
        $day_key = gmdate( 'Ymd' );
        $key = 'se_ai_daily_' . md5( $bucket . '|' . $day_key );

        $count = (int) get_transient( $key );
        if ( $count >= $limit ) {
            se_ai_log_event( $bucket, 'daily_limited', array( 'rate_limit_hit' => 'yes' ) );
            return se_ai_public_error_response( 'This tool is cooling down for a bit. Try again later.', 429 );
        }

        set_transient( $key, $count + 1, DAY_IN_SECONDS + HOUR_IN_SECONDS );
        return true;
    }
}

if ( ! function_exists( 'se_ai_get_text_limit' ) ) {
    function se_ai_get_text_limit( $feature ) {
        $map = array(
            'albini_question'    => 600,
            'riff_summary'       => 800,
            'asmr_prompt'        => 1200,
            'gastown_npc_prompt' => 500,
            'track_transcript'   => 8000,
            'tts_text'           => 500,
        );

        $feature = sanitize_key( (string) $feature );
        return isset( $map[ $feature ] ) ? $map[ $feature ] : 500;
    }
}

if ( ! function_exists( 'se_ai_trim_text' ) ) {
    function se_ai_trim_text( $text, $max_chars ) {
        $text = wp_strip_all_tags( (string) $text );
        $text = preg_replace( '/\s+/u', ' ', $text );
        $text = trim( (string) $text );
        $max_chars = max( 1, (int) $max_chars );

        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $text, 0, $max_chars );
        }

        return substr( $text, 0, $max_chars );
    }
}

if ( ! function_exists( 'se_ai_should_use_openai' ) ) {
    function se_ai_should_use_openai( $feature ) {
        if ( defined( 'SE_AI_DISABLE_ALL' ) && SE_AI_DISABLE_ALL ) {
            return false;
        }

        $feature = sanitize_key( (string) $feature );
        $map = array(
            'track_analyzer' => 'SE_AI_DISABLE_TRACK_ANALYZER',
            'albini' => 'SE_AI_DISABLE_ALBINI',
            'riff' => 'SE_AI_DISABLE_RIFF',
            'asmr' => 'SE_AI_DISABLE_ASMR',
            'gastown' => 'SE_AI_DISABLE_GASTOWN',
        );

        if ( isset( $map[ $feature ] ) && defined( $map[ $feature ] ) && constant( $map[ $feature ] ) ) {
            return false;
        }

        return true;
    }
}

if ( ! function_exists( 'se_verify_rest_nonce_if_present' ) ) {
    function se_verify_rest_nonce_if_present( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'x_wp_nonce' );
        if ( ! $nonce ) {
            return true;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
            return se_ai_public_error_response( 'Security check failed. Please refresh and try again.', 403 );
        }

        return true;
    }
}

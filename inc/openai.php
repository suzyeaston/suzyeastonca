<?php
/**
 * Shared OpenAI helper functions.
 */

if ( ! function_exists( 'se_openai_sanitize_error_message' ) ) {
    function se_openai_sanitize_error_message( $message ) {
        $message = wp_strip_all_tags( (string) $message );
        return mb_substr( preg_replace( '/\s+/u', ' ', trim( $message ) ), 0, 180 );
    }
}

if ( ! function_exists( 'se_openai_chat' ) ) {
    /**
     * Send a chat completion request to OpenAI.
     */
    function se_openai_chat( $messages, $options = array(), $http_options = array() ) {
        if ( ! defined( 'OPENAI_API_KEY' ) || ! OPENAI_API_KEY ) {
            return new WP_Error( 'no_key', __( 'OpenAI API key is not configured.', 'suzys-music-theme' ) );
        }

        if ( empty( $messages ) || ! is_array( $messages ) ) {
            return new WP_Error( 'invalid_messages', __( 'Invalid chat payload.', 'suzys-music-theme' ) );
        }

        $http_timeout = 15;
        if ( isset( $http_options['timeout'] ) ) {
            $http_timeout = (int) $http_options['timeout'];
            unset( $http_options['timeout'] );
        }
        if ( isset( $options['timeout'] ) ) {
            $http_timeout = (int) $options['timeout'];
            unset( $options['timeout'] );
        }
        $http_timeout = max( 3, min( 40, $http_timeout ) );

        $allowed_models = array( 'gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1' );
        $model = isset( $options['model'] ) ? sanitize_text_field( $options['model'] ) : 'gpt-4o-mini';
        if ( ! in_array( $model, $allowed_models, true ) ) {
            $model = 'gpt-4o-mini';
        }

        $max_tokens = isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 300;
        $max_cap = isset( $options['max_tokens_cap'] ) ? max( 1, (int) $options['max_tokens_cap'] ) : 1600;
        unset( $options['max_tokens_cap'] );
        $max_tokens = max( 1, min( $max_tokens, $max_cap ) );

        $temperature = isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.7;

        $allowed_keys = array_flip(
            array(
                'messages',
                'temperature',
                'max_tokens',
                'top_p',
                'frequency_penalty',
                'presence_penalty',
                'stop',
                'response_format',
                'tools',
                'tool_choice',
                'seed',
                'user',
            )
        );

        $body = array_merge(
            array(
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $max_tokens,
            ),
            array_intersect_key( $options, $allowed_keys )
        );

        $body['messages'] = $messages;
        $body['model'] = $model;
        $body['max_tokens'] = $max_tokens;

        if ( ! isset( $body['user'] ) || '' === trim( (string) $body['user'] ) ) {
            if ( isset( $options['safety_identifier'] ) && '' !== trim( (string) $options['safety_identifier'] ) ) {
                $body['user'] = sanitize_text_field( (string) $options['safety_identifier'] );
            } elseif ( function_exists( 'se_ai_get_client_fingerprint' ) ) {
                $body['user'] = se_ai_get_client_fingerprint();
            }
        }

        $feature = sanitize_key( (string) ( $options['feature'] ?? 'chat' ) );

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array_merge(
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . OPENAI_API_KEY,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode( $body ),
                    'timeout' => $http_timeout,
                ),
                $http_options
            )
        );

        if ( is_wp_error( $response ) ) {
            $message = se_openai_sanitize_error_message( $response->get_error_message() );
            if ( function_exists( 'se_ai_log_event' ) ) {
                se_ai_log_event( $feature, 'http_error', array( 'http_code' => '0', 'error_code' => 'openai_http_error' ) );
            }
            error_log( sprintf( 'SE OpenAI chat error feature=%s code=0 err=%s', $feature, $message ) );
            return new WP_Error( 'openai_http_error', __( 'The AI layer is offline, but the fallback version still works.', 'suzys-music-theme' ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw_body, true );

        if ( $code < 200 || $code >= 300 ) {
            $error_code = is_array( $data ) && isset( $data['error']['code'] ) ? sanitize_key( $data['error']['code'] ) : 'openai_http_error';
            $message = is_array( $data ) && isset( $data['error']['message'] ) ? se_openai_sanitize_error_message( $data['error']['message'] ) : 'unexpected';
            if ( function_exists( 'se_ai_log_event' ) ) {
                se_ai_log_event( $feature, 'http_error', array( 'http_code' => (string) $code, 'error_code' => $error_code, 'model' => $model ) );
            }
            error_log( sprintf( 'SE OpenAI chat error feature=%s code=%s err=%s', $feature, $code, $message ) );
            return new WP_Error( 'openai_http_error', __( 'The AI layer is offline, but the fallback version still works.', 'suzys-music-theme' ) );
        }

        if ( null === $data || json_last_error() !== JSON_ERROR_NONE ) {
            if ( function_exists( 'se_ai_log_event' ) ) {
                se_ai_log_event( $feature, 'json_error', array( 'http_code' => (string) $code, 'error_code' => 'openai_json_error', 'model' => $model ) );
            }
            return new WP_Error( 'openai_json_error', __( 'Invalid OpenAI response.', 'suzys-music-theme' ) );
        }

        return $data;
    }
}

if ( ! function_exists( 'se_openai_tts' ) ) {
    /**
     * Send a text-to-speech request to OpenAI.
     */
    function se_openai_tts( $text, $options = array(), $http_options = array() ) {
        if ( ! defined( 'OPENAI_API_KEY' ) || ! OPENAI_API_KEY ) {
            return new WP_Error( 'no_key', __( 'OpenAI API key is not configured.', 'suzys-music-theme' ) );
        }

        $max_text = isset( $options['max_text_chars'] ) ? (int) $options['max_text_chars'] : se_ai_get_text_limit( 'tts_text' );
        $text = function_exists( 'se_ai_trim_text' ) ? se_ai_trim_text( $text, $max_text ) : trim( wp_strip_all_tags( (string) $text ) );
        if ( '' === $text ) {
            return new WP_Error( 'invalid_text', __( 'Speech text is empty.', 'suzys-music-theme' ) );
        }

        $http_timeout = 20;
        if ( isset( $http_options['timeout'] ) ) {
            $http_timeout = max( 3, min( 20, (int) $http_options['timeout'] ) );
            unset( $http_options['timeout'] );
        }

        $format = isset( $options['response_format'] ) ? sanitize_key( $options['response_format'] ) : 'mp3';
        if ( ! in_array( $format, array( 'mp3', 'wav' ), true ) ) {
            $format = 'mp3';
        }

        $body = array(
            'model'           => isset( $options['model'] ) ? sanitize_text_field( $options['model'] ) : 'gpt-4o-mini-tts',
            'voice'           => isset( $options['voice'] ) ? sanitize_text_field( $options['voice'] ) : 'alloy',
            'input'           => $text,
            'response_format' => $format,
        );

        if ( ! empty( $options['instructions'] ) ) {
            $body['instructions'] = se_ai_trim_text( (string) $options['instructions'], 300 );
        }

        $response = wp_remote_post(
            'https://api.openai.com/v1/audio/speech',
            array_merge(
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . OPENAI_API_KEY,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode( $body ),
                    'timeout' => $http_timeout,
                ),
                $http_options
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'openai_tts_http_error', __( 'The AI layer is offline, but the fallback version still works.', 'suzys-music-theme' ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $audio = wp_remote_retrieve_body( $response );
        if ( $code < 200 || $code >= 300 || '' === $audio ) {
            return new WP_Error( 'openai_tts_http_error', __( 'The AI layer is offline, but the fallback version still works.', 'suzys-music-theme' ) );
        }

        return array(
            'audio'  => $audio,
            'format' => $body['response_format'],
            'model'  => $body['model'],
            'voice'  => $body['voice'],
        );
    }
}

if ( ! function_exists( 'se_openai_moderate_text' ) ) {
    function se_openai_moderate_text( $text, $feature = 'general' ) {
        if ( defined( 'SE_AI_DISABLE_MODERATION' ) && SE_AI_DISABLE_MODERATION ) {
            return array( 'ok' => true, 'flagged' => false );
        }

        if ( ! defined( 'OPENAI_API_KEY' ) || ! OPENAI_API_KEY ) {
            return new WP_Error( 'no_key', __( 'OpenAI API key is not configured.', 'suzys-music-theme' ) );
        }

        $text = se_ai_trim_text( $text, 1200 );
        if ( '' === $text ) {
            return array( 'ok' => true, 'flagged' => false );
        }

        $response = wp_remote_post(
            'https://api.openai.com/v1/moderations',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . OPENAI_API_KEY,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode(
                    array(
                        'model' => 'omni-moderation-latest',
                        'input' => $text,
                    )
                ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'moderation_http_error', __( 'Moderation unavailable.', 'suzys-music-theme' ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
            return new WP_Error( 'moderation_http_error', __( 'Moderation unavailable.', 'suzys-music-theme' ) );
        }

        $flagged = ! empty( $data['results'][0]['flagged'] );
        return array( 'ok' => true, 'flagged' => $flagged, 'feature' => sanitize_key( $feature ) );
    }
}

<?php
/**
 * Shared OpenAI helper functions.
 */

if ( ! function_exists( 'se_openai_chat' ) ) {
    /**
     * Send a chat completion request to OpenAI.
     *
     * @param array $messages Chat messages [{ role, content }].
     * @param array $options      Additional request options (model, max_tokens, temperature, etc.).
     * @param array $http_options Optional HTTP transport options for wp_remote_post.
     *
     * @return array|WP_Error Decoded response array on success or WP_Error on failure.
     */
    function se_openai_chat( $messages, $options = array(), $http_options = array() ) {
        if ( ! defined( 'OPENAI_API_KEY' ) || ! OPENAI_API_KEY ) {
            return new WP_Error( 'no_key', __( 'OpenAI API key is not configured.', 'suzys-music-theme' ) );
        }

        if ( empty( $messages ) || ! is_array( $messages ) ) {
            return new WP_Error( 'invalid_messages', __( 'Invalid chat payload.', 'suzys-music-theme' ) );
        }

        $http_timeout = 30;
        if ( isset( $http_options['timeout'] ) ) {
            $http_timeout = (int) $http_options['timeout'];
            unset( $http_options['timeout'] );
        }
        if ( isset( $options['timeout'] ) ) {
            $http_timeout = (int) $options['timeout'];
            unset( $options['timeout'] );
        }

        $allowed_keys = array_flip(
            array(
                'model',
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
                'model'    => 'gpt-4o',
                'messages' => $messages,
            ),
            array_intersect_key( $options, $allowed_keys )
        );

        // Always use the provided messages to avoid accidental overrides.
        $body['messages'] = $messages;

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
            $message = wp_strip_all_tags( $response->get_error_message() );
            error_log( 'OpenAI HTTP 0: ' . $message );
            return new WP_Error( 'openai_http_error', sprintf( 'OpenAI HTTP 0: %s', $message ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code < 200 || $code >= 300 ) {
            $message = '';
            if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
                $message = $data['error']['message'];
            }
            $message = $message ? wp_strip_all_tags( $message ) : __( 'Unexpected OpenAI response.', 'suzys-music-theme' );
            $message = wp_trim_words( $message, 30, '...' );

            error_log( sprintf( 'OpenAI HTTP %s: %s', $code, $message ) );
            return new WP_Error( 'openai_http_error', sprintf( 'OpenAI HTTP %s: %s', $code, $message ) );
        }

        if ( null === $data || json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'OpenAI JSON error: ' . json_last_error_msg() );
            return new WP_Error( 'openai_json_error', __( 'Invalid OpenAI response.', 'suzys-music-theme' ) );
        }

        return $data;
    }
}

<?php
/**
 * Shared OpenAI helper functions.
 */

if ( ! function_exists( 'se_openai_chat' ) ) {
    /**
     * Send a chat completion request to OpenAI.
     *
     * @param array $messages Chat messages [{ role, content }].
     * @param array $options  Additional request options (model, max_tokens, temperature, etc.).
     *
     * @return array|WP_Error Decoded response array on success or WP_Error on failure.
     */
    function se_openai_chat( $messages, $options = array() ) {
        if ( ! defined( 'OPENAI_API_KEY' ) || ! OPENAI_API_KEY ) {
            return new WP_Error( 'no_key', __( 'OpenAI API key is not configured.', 'suzys-music-theme' ) );
        }

        if ( empty( $messages ) || ! is_array( $messages ) ) {
            return new WP_Error( 'invalid_messages', __( 'Invalid chat payload.', 'suzys-music-theme' ) );
        }

        $body = array_merge(
            array(
                'model'    => 'gpt-4o',
                'messages' => $messages,
            ),
            $options
        );

        // Always use the provided messages to avoid accidental overrides.
        $body['messages'] = $messages;

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . OPENAI_API_KEY,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => isset( $options['timeout'] ) ? (int) $options['timeout'] : 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            $message = is_array( $data ) && isset( $data['error']['message'] )
                ? $data['error']['message']
                : wp_remote_retrieve_body( $response );

            return new WP_Error( 'openai_http_error', sprintf( 'OpenAI HTTP %s: %s', $code, $message ) );
        }

        if ( null === $data || json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'openai_json_error', __( 'Invalid OpenAI response.', 'suzys-music-theme' ) );
        }

        return $data;
    }
}

<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

/**
 * Perform an HTTP GET request with sane defaults and graceful error handling.
 */
function http_get(string $url, array $args = []): array {
    $force_ipv4 = ! empty($args['force_ipv4']);
    if ($force_ipv4) {
        unset($args['force_ipv4']);
    }

    $defaults = [
        'timeout'     => 12,
        'redirection' => 3,
        'httpversion' => '1.1',
        'headers'     => [
            'User-Agent'    => 'LousyOutages/1.0 (+https://suzyeaston.ca)',
            'Accept'        => 'application/json, application/xml, text/xml;q=0.9,*/*;q=0.8',
            'Cache-Control' => 'no-cache',
        ],
        'sslverify'   => true,
        'decompress'  => true,
    ];

    $merged         = array_replace($defaults, $args);
    $merged['timeout']     = isset($merged['timeout']) ? max(1, (int) $merged['timeout']) : $defaults['timeout'];
    $merged['redirection'] = isset($merged['redirection']) ? max(0, (int) $merged['redirection']) : $defaults['redirection'];
    $merged['headers']     = isset($merged['headers']) && is_array($merged['headers'])
        ? array_merge($defaults['headers'], $merged['headers'])
        : $defaults['headers'];

    if ($force_ipv4) {
        $merged['force_ipv4'] = true;
        $curl_response = http_get_via_curl($url, $merged, 'forced_ipv4');
        if (null !== $curl_response) {
            return $curl_response;
        }
        unset($merged['force_ipv4']);
    }

    if (! function_exists('wp_remote_get')) {
        if (function_exists('Lousy\\http_get')) {
            // LO: test harness stub.
            return \Lousy\http_get($url, $merged);
        }

        return [
            'ok'      => false,
            'status'  => 0,
            'error'   => 'request_failed',
            'message' => 'wp_remote_get unavailable',
            'body'    => null,
        ];
    }

    $response = wp_remote_get($url, $merged);
    if (is_wp_error($response)) {
        $fallback = http_get_via_curl($url, $merged, $response->get_error_message());
        if (null !== $fallback) {
            return $fallback;
        }

        return [
            'ok'      => false,
            'status'  => 0,
            'error'   => 'request_failed',
            'message' => $response->get_error_message(),
            'body'    => null,
        ];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 400) {
        return [
            'ok'      => false,
            'status'  => $code,
            'error'   => 'http_error',
            'message' => sprintf('HTTP %d response', $code),
            'body'    => $body,
        ];
    }

    return [
        'ok'      => true,
        'status'  => $code,
        'error'   => null,
        'message' => null,
        'body'    => $body,
    ];
}

/**
 * Attempt a direct cURL request when wp_remote_get() fails.
 */
function http_get_via_curl(string $url, array $args, string $reason): ?array {
    if (!function_exists('curl_init')) {
        return null;
    }

    $handle = curl_init($url);
    if (false === $handle) {
        return null;
    }

    $timeout  = isset($args['timeout']) ? max(1, (int) $args['timeout']) : 12;
    $headers  = [];
    $ua       = $args['headers']['User-Agent'] ?? 'LousyOutages/1.0 (+https://suzyeaston.ca)';
    $sslcheck = isset($args['sslverify']) ? (bool) $args['sslverify'] : true;

    foreach (($args['headers'] ?? []) as $key => $value) {
        $headers[] = $key . ': ' . $value;
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => isset($args['redirection']) ? max(0, (int) $args['redirection']) : 3,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_ENCODING       => '',
    ];

    if (!empty($args['force_ipv4']) && defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
        $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }
    if (defined('CURLOPT_HTTP_VERSION_1_1')) {
        $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
    }

    if (!empty($headers)) {
        $options[CURLOPT_HTTPHEADER] = $headers;
    }

    if ($sslcheck) {
        $options[CURLOPT_SSL_VERIFYPEER] = true;
        $options[CURLOPT_SSL_VERIFYHOST] = 2;
    } else {
        $options[CURLOPT_SSL_VERIFYPEER] = false;
        $options[CURLOPT_SSL_VERIFYHOST] = 0;
    }

    curl_setopt_array($handle, $options);

    $body = curl_exec($handle);
    if (false === $body) {
        $error = curl_error($handle) ?: $reason;
        curl_close($handle);
        return [
            'ok'      => false,
            'status'  => 0,
            'error'   => 'request_failed',
            'message' => $error,
            'body'    => null,
        ];
    }

    $status = curl_getinfo($handle, defined('CURLINFO_RESPONSE_CODE') ? CURLINFO_RESPONSE_CODE : CURLINFO_HTTP_CODE);
    curl_close($handle);

    if ($status < 200 || $status >= 400) {
        return [
            'ok'      => false,
            'status'  => (int) $status,
            'error'   => 'http_error',
            'message' => sprintf('HTTP %d response', $status),
            'body'    => $body,
        ];
    }

    return [
        'ok'      => true,
        'status'  => (int) $status,
        'error'   => null,
        'message' => null,
        'body'    => $body,
    ];
}

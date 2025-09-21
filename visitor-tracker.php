<?php
$countFile = __DIR__ . '/visitor-count.json';
$data = file_exists($countFile)
    ? json_decode(file_get_contents($countFile), true)
    : ['count' => 0, 'locations' => [], 'ipCache' => [], 'visits' => []];

if (!is_array($data)) {
    $data = ['count' => 0, 'locations' => [], 'ipCache' => [], 'visits' => []];
}

$data['count']++;
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (isset($data['ipCache'][$ip])) {
    $country = $data['ipCache'][$ip];
} else {
    $country = fetch_country();
    $data['ipCache'][$ip] = $country;
}

if (!isset($data['locations'][$country])) {
    $data['locations'][$country] = 0;
}
$data['locations'][$country]++;

$data['visits'][] = ['time' => date('c'), 'country' => $country];

file_put_contents($countFile, json_encode($data), LOCK_EX);
return $data;

function fetch_country() {
    $urls = ['https://ipapi.co/json/', 'https://ipinfo.io/json'];

    // Prefer WordPress HTTP API when available to avoid dependency issues.
    if (
        function_exists('wp_remote_get') &&
        function_exists('wp_remote_retrieve_body') &&
        function_exists('is_wp_error')
    ) {
        foreach ($urls as $url) {
            $response = wp_remote_get($url, ['timeout' => 3]);
            if (is_wp_error($response)) {
                continue;
            }
            $body = wp_remote_retrieve_body($response);
            $country = parse_country_from_response($body);
            if ($country !== null) {
                return $country;
            }
        }
    }

    // Fallback to cURL when available.
    if (function_exists('curl_init')) {
        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $resp = curl_exec($ch);
            curl_close($ch);
            if ($resp !== false) {
                $country = parse_country_from_response($resp);
                if ($country !== null) {
                    return $country;
                }
            }
        }
    }

    // Final fallback: attempt simple HTTP requests if allowed.
    if (ini_get('allow_url_fopen')) {
        foreach ($urls as $url) {
            $resp = @file_get_contents($url);
            if ($resp !== false) {
                $country = parse_country_from_response($resp);
                if ($country !== null) {
                    return $country;
                }
            }
        }
    }

    return 'Unknown';
}

function parse_country_from_response($resp) {
    if ($resp === null || $resp === '') {
        return null;
    }

    $info = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($info)) {
        return null;
    }

    if (!empty($info['country_name'])) {
        return $info['country_name'];
    }

    if (!empty($info['country'])) {
        return $info['country'];
    }

    return null;
}
?>

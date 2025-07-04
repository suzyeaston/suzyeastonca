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

file_put_contents($countFile, json_encode($data));
return $data;

function fetch_country() {
    $urls = ['https://ipapi.co/json/', 'https://ipinfo.io/json'];
    foreach ($urls as $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $resp = curl_exec($ch);
        curl_close($ch);
        if ($resp !== false) {
            $info = json_decode($resp, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (!empty($info['country_name'])) return $info['country_name'];
                if (!empty($info['country'])) return $info['country'];
            }
        }
    }
    return 'Unknown';
}
?>

<?php
$countFile = __DIR__ . '/visitor-count.json';
$data = file_exists($countFile)
    ? json_decode(file_get_contents($countFile), true)
    : ['count' => 0, 'locations' => []];

$data['count']++;

$country = 'Unknown';
$apiResp = @file_get_contents('https://ipapi.co/json/');
if ($apiResp !== false) {
    $info = json_decode($apiResp, true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($info['country_name'])) {
        $country = $info['country_name'];
    }
}

if (!isset($data['locations'][$country])) {
    $data['locations'][$country] = 0;
}
$data['locations'][$country]++;

file_put_contents($countFile, json_encode($data));
return $data;

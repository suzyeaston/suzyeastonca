<?php
$countFile = __DIR__ . '/visitor-count.json';
$data = file_exists($countFile) ? json_decode(file_get_contents($countFile), true) : ['count' => 0, 'locations' => []];
$data['count']++;

$country = 'Unknown';
if (!isset($data['locations'][$country])) {
    $data['locations'][$country] = 0;
}
$data['locations'][$country]++;
file_put_contents($countFile, json_encode($data));
return $data;

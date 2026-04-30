<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$key = getenv('WEATHER_KEY') ?: '';

if (empty($key)) {
    http_response_code(500);
    echo json_encode(['error' => 'Weather API key not configured']);
    exit;
}

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$clientIp = filter_var($clientIp, FILTER_VALIDATE_IP) ?: '';

$url = 'https://restapi.amap.com/v3/ip?key=' . $key;
if (!empty($clientIp) && $clientIp !== '127.0.0.1') {
    $url .= '&ip=' . $clientIp;
}

$response = @file_get_contents($url);
if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to detect location']);
    exit;
}

$data = json_decode($response, true);
if (($data['status'] ?? '0') !== '1' || empty($data['city'])) {
    echo json_encode(['city' => '北京']);
    exit;
}

$city = preg_replace('/[省市]$/u', '', $data['city']);
echo json_encode(['city' => $city, 'adcode' => $data['adcode'] ?? '']);

<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SnowmanNunu\Weather\Weather;
use SnowmanNunu\Weather\Providers\AMapProvider;
use SnowmanNunu\Weather\Providers\QWeatherProvider;
use SnowmanNunu\Weather\Providers\OpenWeatherMapProvider;

header('Content-Type: application/json; charset=utf-8');

$providerName = getenv('WEATHER_PROVIDER') ?: 'amap';
$city = $_GET['city'] ?? '';
$lang = $_GET['lang'] ?? 'zh';
putenv('WEATHER_LANG=' . $lang);

$key = match ($providerName) {
    'qweather' => getenv('QWEATHER_KEY') ?: getenv('WEATHER_KEY') ?: '',
    'openweathermap' => getenv('OPENWEATHERMAP_KEY') ?: getenv('WEATHER_KEY') ?: '',
    default => getenv('WEATHER_KEY') ?: '',
};

if (empty($key)) {
    http_response_code(500);
    echo json_encode(['error' => 'Weather API key not configured']);
    exit;
}

if (empty($city)) {
    http_response_code(400);
    echo json_encode(['error' => 'City parameter is required']);
    exit;
}

try {
    $provider = match ($providerName) {
        'qweather' => new QWeatherProvider($key),
        'openweathermap' => new OpenWeatherMapProvider($key),
        default => new AMapProvider($key),
    };
    $weather = new Weather($provider);

    $all = $weather->getAll($city);

    echo json_encode([
        'success' => true,
        'provider' => $weather->getName(),
        'current' => $all['current'] ? $all['current']->toArray() : null,
        'forecast' => $all['forecast'] ? $all['forecast']->toArray() : null,
        'indices' => array_map(static fn ($i) => $i->toArray(), $all['indices'] ?? []),
        'aqi' => $all['aqi'] ? $all['aqi']->toArray() : null,
        'alerts' => array_map(static fn ($a) => $a->toArray(), $all['alerts'] ?? []),
        'minutely' => array_map(static fn ($m) => $m->toArray(), $all['minutely'] ?? []),
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

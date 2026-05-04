<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SnowmanNunu\Weather\Weather;
use SnowmanNunu\Weather\Providers\AMapProvider;
use SnowmanNunu\Weather\Providers\QWeatherProvider;
use SnowmanNunu\Weather\Providers\OpenWeatherMapProvider;

header('Content-Type: application/json; charset=utf-8');

$providerName = getenv('WEATHER_PROVIDER') ?: 'amap';
$key = getenv('WEATHER_KEY') ?: '';
$city = $_GET['city'] ?? '';

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

    $current = $weather->getLiveWeather($city);
    $forecast = $weather->getForecastsWeather($city);
    $indices = $weather->getLifeIndices($city);
    $aqi = $weather->getAirQuality($city);
    $alerts = $weather->getAlerts($city);

    echo json_encode([
        'success' => true,
        'provider' => $weather->getName(),
        'current' => $current->toArray(),
        'forecast' => $forecast->toArray(),
        'indices' => array_map(static fn ($i) => $i->toArray(), $indices),
        'aqi' => $aqi ? $aqi->toArray() : null,
        'alerts' => array_map(static fn ($a) => $a->toArray(), $alerts),
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

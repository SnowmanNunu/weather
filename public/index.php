<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SnowmanNunu\Weather\Weather;
use SnowmanNunu\Weather\Providers\AMapProvider;
use SnowmanNunu\Weather\Providers\QWeatherProvider;

$providerName = getenv('WEATHER_PROVIDER') ?: 'amap';
$key = getenv('WEATHER_KEY') ?: '';

$weather = null;
$error = null;
$current = null;
$forecast = null;
$city = $_GET['city'] ?? '';

if (!empty($key)) {
    $provider = match ($providerName) {
        'qweather' => new QWeatherProvider($key),
        default => new AMapProvider($key),
    };
    $weather = new Weather($provider);
} else {
    $error = '请在服务器环境变量中配置 WEATHER_KEY';
}

if ($weather !== null && !empty($city)) {
    try {
        $current = $weather->getLiveWeather($city);
        $forecast = $weather->getForecastsWeather($city);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$defaultCity = $city ?: '北京';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weather - SnowmanNunu</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🌤 天气查询</h1>
            <p class="subtitle">支持高德地图、和风天气等多数据源</p>
        </header>

        <form class="search-box" method="get" action="">
            <input
                type="text"
                name="city"
                value="<?php echo htmlspecialchars($defaultCity, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="输入城市名称，如：北京、上海、深圳"
                required
            >
            <button type="submit">查询</button>
        </form>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($current !== null): ?>
            <div class="card current">
                <div class="current-main">
                    <div class="city-name"><?php echo htmlspecialchars($current->city, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="temperature"><?php echo htmlspecialchars((string)$current->temperature, ENT_QUOTES, 'UTF-8'); ?>°</div>
                    <div class="weather-desc"><?php echo htmlspecialchars($current->weather, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="current-meta">
                    <div class="meta-item">
                        <span class="meta-label">湿度</span>
                        <span class="meta-value"><?php echo $current->humidity !== null ? htmlspecialchars((string)$current->humidity, ENT_QUOTES, 'UTF-8') . '%' : '-'; ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">风向</span>
                        <span class="meta-value"><?php echo htmlspecialchars($current->windDirection, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">风力</span>
                        <span class="meta-value"><?php echo htmlspecialchars($current->windPower, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">更新时间</span>
                        <span class="meta-value"><?php echo htmlspecialchars($current->updateTime, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($forecast !== null && !empty($forecast->casts)): ?>
            <h2 class="section-title">未来预报</h2>
            <div class="forecast-grid">
                <?php foreach ($forecast->casts as $cast): ?>
                    <div class="card forecast">
                        <div class="forecast-date"><?php echo htmlspecialchars($cast->date, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="forecast-day"><?php echo htmlspecialchars($cast->week, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="forecast-weather">☀ <?php echo htmlspecialchars($cast->dayWeather, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="forecast-temp">
                            <?php echo htmlspecialchars((string)$cast->dayTemp, ENT_QUOTES, 'UTF-8'); ?>° /
                            <?php echo htmlspecialchars((string)$cast->nightTemp, ENT_QUOTES, 'UTF-8'); ?>°
                        </div>
                        <div class="forecast-wind"><?php echo htmlspecialchars($cast->dayWind, ENT_QUOTES, 'UTF-8'); ?>风 <?php echo htmlspecialchars($cast->dayPower, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <footer>
            <p>Powered by <a href="https://github.com/SnowmanNunu/weather" target="_blank">SnowmanNunu/Weather</a></p>
        </footer>
    </div>
</body>
</html>

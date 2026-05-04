# 🌤 SnowmanNunu/Weather

[![Tests](https://github.com/SnowmanNunu/weather/actions/workflows/tests.yml/badge.svg)](https://github.com/SnowmanNunu/weather/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/badge/php-%5E8.0-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

一个支持多数据源、带缓存、提供标准化 DTO 响应的 PHP 天气 SDK。内置 Web 演示站点，可一键部署为天气查询服务。

**在线演示**：http://weather.snowmannunu.top/

---

## ✨ 特性

- **多数据源支持**：高德地图、和风天气、OpenWeatherMap，可自由切换
- **标准化响应**：统一 `CurrentWeather` / `Forecast` DTO，无需适配各平台差异
- **PSR-16 缓存**：支持 Redis / File / Array 等缓存驱动，减少 API 调用
- **Laravel 集成**：ServiceProvider + Facade 开箱即用
- **Web 演示**：内置 SPA 天气查询页面，支持 IP 自动定位与中英文切换
- **多语言 i18n**：SDK 与演示站点均支持 `zh` / `en`，通过环境变量或 URL 参数切换
- **并发请求**：内置 `fetchAll()` 批量接口，6 个 API 并行发送，响应时间从 ~1.5s 降至 ~0.3s
- **自动重试**：网络超时 / 服务端 5xx / 429 限流时自动指数退避重试（最多 2 次）
- **精细化异常**：`InvalidKeyException` / `RateLimitException` / `HttpException`，便于调用方精准处理
- **代码质量**：PHPUnit + PHPStan Level 8 + PHPCS PSR-12，CI 全自动检查

---

## 📦 安装

```bash
composer require snowmannunu/weather
```

---

## 🚀 快速开始

### 基础用法

```php
use SnowmanNunu\Weather\Weather;
use SnowmanNunu\Weather\Providers\AMapProvider;

// 使用高德地图（默认）
$weather = new Weather('your-amap-key');
$current = $weather->getLiveWeather('北京');

echo $current->city;        // 北京市
echo $current->temperature; // 26
echo $current->weather;     // 晴

// 获取未来预报
$forecast = $weather->getForecastsWeather('北京');
foreach ($forecast->casts as $day) {
    echo $day->date . ' ' . $day->dayWeather . ' ' . $day->dayTemp . '°';
}
```

### 切换数据源

```php
use SnowmanNunu\Weather\Providers\QWeatherProvider;
use SnowmanNunu\Weather\Providers\OpenWeatherMapProvider;

$weather = new Weather(new QWeatherProvider('your-qweather-key'));
$current = $weather->getLiveWeather('深圳');

// OpenWeatherMap（海外城市更准确）
$weather = new Weather(new OpenWeatherMapProvider('your-owm-key'));
$current = $weather->getLiveWeather('London');
```

### 添加缓存

```php
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

$redis = new RedisAdapter($redisConnection);
$cache = new Psr16Cache($redis);

$weather = new Weather('your-key');
$weather->withCache($cache, 600); // 缓存 10 分钟
```

### 切换语言

SDK 支持中英文输出，通过 `setLang()` 链式调用即可切换：

```php
use SnowmanNunu\Weather\Weather;
use SnowmanNunu\Weather\Providers\QWeatherProvider;

$weather = (new Weather(new QWeatherProvider('your-key')))->setLang('en');
$current = $weather->getLiveWeather('Beijing');

echo $current->weather;     // Sunny
echo $current->windDirection; // NW
```

> 支持的 Provider：和风天气（`zh`/`en`）、OpenWeatherMap（多语言）、高德地图（返回中文）。
> 语言信息会自动写入缓存 key，中英文缓存互不干扰。

### 批量并发查询

一次性获取所有数据（实时天气 + 预报 + 生活指数 + AQI + 预警 + 分钟降水），SDK 内部并行发送请求：

```php
$all = $weather->getAll('北京');

echo $all['current']->temperature;   // 26
echo $all['forecast']->casts[0]->dayWeather; // 晴
echo $all['aqi']->aqi;              // 45
```

### 异常处理

SDK 对常见错误做了分类，方便调用方精准处理：

```php
use SnowmanNunu\Weather\Exceptions\InvalidKeyException;
use SnowmanNunu\Weather\Exceptions\RateLimitException;
use SnowmanNunu\Weather\Exceptions\HttpException;

try {
    $current = $weather->getLiveWeather('北京');
} catch (InvalidKeyException $e) {
    // API Key 无效
} catch (RateLimitException $e) {
    // 超过调用频次，建议稍后重试
} catch (HttpException $e) {
    // 网络错误或其他 HTTP 异常
}
```

---

## 🔌 Laravel 集成

在 `config/services.php` 中添加：

```php
'weather' => [
    'key'    => env('WEATHER_KEY'),
    'driver' => env('WEATHER_DRIVER', 'amap'),
    'cache'  => [
        'store' => env('WEATHER_CACHE_STORE'),
        'ttl'   => env('WEATHER_CACHE_TTL', 300),
    ],
],
```

```php
// 任意位置调用
app('weather')->getLiveWeather('上海');
```

---

## 🌐 Web 演示部署

项目内置 `public/` 目录可直接作为站点根目录部署：

```bash
cp public/.env.example public/.env
# 编辑 .env 填入 WEATHER_KEY、WEATHER_PROVIDER 和可选的 WEATHER_LANG
```

| 环境变量 | 说明 | 默认值 |
|---|---|---|
| `WEATHER_KEY` | Provider API Key | 必填 |
| `WEATHER_PROVIDER` | 数据源：`amap` / `qweather` / `openweathermap` | `amap` |
| `WEATHER_LANG` | 语言：`zh` / `en` | `zh` |
| `QWEATHER_API_HOST` | 和风天气专属 API Host（含 https://） | `https://devapi.qweather.com` |

Nginx 配置示例：

```nginx
server {
    listen 80;
    server_name weather.example.com;
    root /www/wwwroot/weather/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-82.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param WEATHER_KEY your_api_key;
        fastcgi_param WEATHER_PROVIDER amap;
        include fastcgi_params;
    }
}
```

---

## 🧪 测试

```bash
composer test          # PHPUnit
composer phpstan       # 静态分析
composer phpcs         # 代码风格
composer check         # 全部检查
```

---

## 📋 支持的 Provider

| Provider | 标识 | 覆盖范围 | 免费额度 |
|---|---|---|---|
| 高德地图 | `amap` | 中国大陆 | 5000 次/天 |
| 和风天气 | `qweather` | 全球 | 1000 次/天 |
| OpenWeatherMap | `openweathermap` | 全球 | 1000 次/天 |

### 各 Provider API 支持对比

| 接口 | 高德 AMap | 和风 QWeather | OpenWeatherMap |
|---|---|---|---|
| **实时天气** `getLiveWeather` | ✅ 支持 | ✅ 支持 | ✅ 支持 |
| **天气预报** `getForecastsWeather` | ✅ 支持（3/7 天） | ✅ 支持（7 天） | ✅ 支持（5 天/3h） |
| **生活指数** `getLifeIndices` | ✅ 支持（运动/洗车/穿衣等） | ✅ 支持 | ❌ 返回空数组 |
| **空气质量** `getAirQuality` | ⚠️ 支持（部分 Key 无权限） | ⚠️ 支持（免费版 403） | ❌ 返回 null |
| **天气预警** `getAlerts` | ❌ 不支持（返回 `[]`） | ⚠️ 支持（免费版 403） | ❌ 返回空数组 |
| **分钟降水** `getMinutelyPrecipitation` | ❌ 不支持（返回 `[]`） | ⚠️ 支持（需经纬度） | ❌ 返回空数组 |

> **说明**
> - **高德**：生活指数和 AQI 取决于 Key 是否开通对应权限，SDK 已做容错（报错时返回空，不抛异常）
> - **和风**：AQI 和预警接口免费开发版直接 403；分钟降水需传入经纬度坐标，传 LocationID 会报 400
> - **OpenWeatherMap**：只覆盖了实时天气 + 预报，其余 feature 没有对应免费接口

---

## 📄 License

MIT

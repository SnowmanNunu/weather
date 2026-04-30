

# 天气 SDK

基于高德开放平台的 PHP 天气信息组件

[![Tests](https://github.com/SnowmanNunu/weather/actions/workflows/tests.yml/badge.svg)](https://github.com/SnowmanNunu/weather/actions/workflows/tests.yml)
[![Latest Stable Version](http://poser.pugx.org/snowmannunu/weather/v)](https://packagist.org/packages/snowmannunu/weather)
[![Total Downloads](http://poser.pugx.org/snowmannunu/weather/downloads)](https://packagist.org/packages/snowmannunu/weather)
[![License](http://poser.pugx.org/snowmannunu/weather/license)](https://packagist.org/packages/snowmannunu/weather)

## 简介

这是一个简单易用的 PHP 天气信息 SDK，基于高德开放平台的天气查询 API 开发。你可以通过该组件轻松获取指定城市的实时天气和预报天气数据。

## 功能特性

- 获取城市实时天气数据
- 获取城市天气预报数据
- 完全支持 PSR 标准，可与任何 PSR 兼容框架集成
- 支持 Laravel 框架
- 支持自定义 Guzzle HTTP 客户端配置
- 完善的错误处理机制

## 环境要求

- PHP >= 7.2
- Guzzle HTTP >= 6.0
- Composer

## 安装

通过 Composer 安装：

```shell
$ composer require snowmannunu/weather -vvv
```

## 配置

在使用本扩展之前，你需要前往 [高德开放平台](https://lbs.amap.com/) 注册账号，然后创建应用，获取应用的 API Key。

## 快速开始

```php
use SnowmanNunu\Weather\Weather;

$key = 'your key';

$weather = new Weather($key);
```

### 获取实时天气

```php
$response = $weather->getLiveWeather('深圳');
```

### 获取近期天气预报

```php
$response = $weather->getForecastsWeather('深圳');
```

## 高级用法

### 错误处理

```php
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Exceptions\HttpException;

try {
    $response = $weather->getLiveWeather('深圳');
} catch (InvalidArgumentException $e) {
    // 参数错误（如城市为空、格式不支持等）
    echo $e->getMessage();
} catch (HttpException $e) {
    // 网络请求异常
    echo $e->getMessage();
}
```

### 自定义 Guzzle 配置

你可以自定义 Guzzle HTTP 客户端的配置选项：

```php
// 设置超时时间（单位：秒）
$weather->setGuzzleOptions(['timeout' => 5.0]);

// 或者设置代理
$weather->setGuzzleOptions([
    'proxy' => 'http://proxy.example.com:8080',
]);
```

### 自定义 HTTP 客户端

如果你需要使用自定义的 HTTP 客户端：

```php
use GuzzleHttp\Client;

$client = new Client(['timeout' => 10]);
$weather->setHttpClient($client);
```

## 在 Laravel 中使用

在 Laravel 中使用也是同样的安装方式，配置写在 `config/services.php` 中：

```php
'weather' => [
   'key' => env('WEATHER_API_KEY'),
],
```

在 `.env` 中配置 `WEATHER_API_KEY`：

```env
WEATHER_API_KEY=your key
```

然后在代码中通过依赖注入使用：

```php
use SnowmanNunu\Weather\Weather;

// 依赖注入
public function index(Weather $weather)
{
    $live = $weather->getLiveWeather('深圳');
    $forecast = $weather->getForecastsWeather('深圳');
}
```

或者通过 Facade 方式使用：

```php
use SnowmanNunu\Weather\Weather;

$live = Weather::getLiveWeather('深圳');
$forecast = Weather::getForecastsWeather('深圳');
```

## 贡献指南

欢迎通过以下方式贡献代码：

1. 在 [issue tracker](https://github.com/snowmannunu/weather/issues) 中提交 bug 报告
2. 回答或修复 issue tracker 中的问题
3. 贡献新功能或更新文档

代码贡献过程不是很复杂，只需要确保遵循 PSR-0、PSR-1 和 PSR-2 编码规范。任何新的代码贡献都必须包含相应的单元测试。

## 开源许可

MIT License
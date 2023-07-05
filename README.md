<h1 align="center">基于高德开放平台的PHP天气信息组件 </h1>

[![Tests](https://github.com/SnowmanNunu/weather/actions/workflows/tests.yml/badge.svg)](https://github.com/SnowmanNunu/weather/actions/workflows/tests.yml)
![StyleCI](https://github.styleci.io/repos/661587647/shield)
[![Latest Stable Version](http://poser.pugx.org/snowmannunu/weather/v)](https://packagist.org/packages/snowmannunu/weather) 
[![Total Downloads](http://poser.pugx.org/snowmannunu/weather/downloads)](https://packagist.org/packages/snowmannunu/weather) 
[![License](http://poser.pugx.org/snowmannunu/weather/license)](https://packagist.org/packages/snowmannunu/weather)
<p align="center">A weather SDK</p>


## Installing

```shell
$ composer require snowmannunu/weather -vvv
```

## Config

在使用本扩展之前，你需要去 [高德开放平台](https://lbs.amap.com/ "高德开放平台") 注册账号，然后创建应用，获取应用的 API Key。


## Usage

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

## Used in Laravel

在 Laravel 中使用也是同样的安装方式，配置写在 config/services.php 中：
```php
'weather' => [
   'key' => env('WEATHER_API_KEY'),
],
```
在 .env 中配置 WEATHER_API_KEY ：
```php
WEATHER_API_KEY=your key
```

TODO

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/snowmannunu/weather/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/snowmannunu/weather/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT

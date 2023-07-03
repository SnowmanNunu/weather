<h1 align="center"> 基于高德开放平台的PHP天气信息组件 </h1>

<p align="center"> A weather SDK.</p>


## Installing

```shell
$ composer require snowmannunu/weather -vvv
```

## Usage

```php
use SnomanNunu\Weather\Weather;

$key = 'xxxxxxxxxxxxxxxxxxxxxxxxxxx';

$weather = new Weather($key);

$response = $weather->getWeather('深圳');

$response = $weather->getWeather('深圳', 'all');

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
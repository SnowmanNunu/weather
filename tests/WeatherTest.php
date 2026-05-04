<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Tests;

use GuzzleHttp\Client;
use SnowmanNunu\Weather\Contracts\Provider;
use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;
use SnowmanNunu\Weather\DTO\ForecastDay;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Providers\AMapProvider;
use SnowmanNunu\Weather\Providers\QWeatherProvider;
use SnowmanNunu\Weather\Weather;
use PHPUnit\Framework\TestCase;

class WeatherTest extends TestCase
{
    public function testConstructorWithStringKey()
    {
        $w = new Weather('mock-key');
        $this->assertSame('amap', $w->getName());
        $this->assertInstanceOf(AMapProvider::class, $w->getProvider());
    }

    public function testConstructorWithProvider()
    {
        $provider = new QWeatherProvider('mock-key');
        $w = new Weather($provider);
        $this->assertSame('qweather', $w->getName());
        $this->assertSame($provider, $w->getProvider());
    }

    public function testConstructorWithEmptyKey()
    {
        $this->expectException(InvalidArgumentException::class);
        new Weather('');
    }

    public function testSetProvider()
    {
        $w = new Weather('mock-key');
        $newProvider = new QWeatherProvider('another-key');

        $w->setProvider($newProvider);

        $this->assertSame($newProvider, $w->getProvider());
        $this->assertSame('qweather', $w->getName());
    }

    public function testSetLang()
    {
        $w = new Weather('mock-key');
        $result = $w->setLang('en');

        $this->assertSame($w, $result);
        $this->assertSame('en', $w->getLang());
        $this->assertSame('en', $w->getProvider()->getLang());
    }

    public function testDelegationToProvider()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $provider->expects()->getLiveWeather('深圳')->andReturn(
            new CurrentWeather('深圳市', '440300', 26.0, '晴', '东', '≤3')
        );

        $w = new Weather($provider);
        $result = $w->getLiveWeather('深圳');

        $this->assertInstanceOf(CurrentWeather::class, $result);
        $this->assertSame('深圳市', $result->city);
    }

    public function testCacheHit()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $provider->allows()->setLang(\Mockery::any());
        $provider->shouldNotReceive('getLiveWeather');

        $cachedWeather = new CurrentWeather('深圳市', '440300', 26.0, '晴', '东', '≤3');

        $cache = \Mockery::mock(\Psr\SimpleCache\CacheInterface::class);
        $cacheKey = 'weather:mock:zh:' . md5('深圳') . ':live';
        $cache->allows()->get($cacheKey)->andReturn($cachedWeather);

        $w = new Weather($provider);
        $w->withCache($cache);

        $result = $w->getLiveWeather('深圳');
        $this->assertSame($cachedWeather, $result);
    }

    public function testCacheMissAndStore()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $weather = new CurrentWeather('深圳市', '440300', 26.0, '晴', '东', '≤3');
        $provider->expects()->getLiveWeather('深圳')->once()->andReturn($weather);

        $cache = \Mockery::mock(\Psr\SimpleCache\CacheInterface::class);
        $cacheKey = 'weather:mock:zh:' . md5('深圳') . ':live';
        $cache->allows()->get($cacheKey)->andReturn(null);
        $cache->expects()->set($cacheKey, $weather, 300)->once();

        $w = new Weather($provider);
        $w->withCache($cache);

        $this->assertSame($weather, $w->getLiveWeather('深圳'));
    }

    public function testGetForecastsWeatherDelegates()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $forecast = new Forecast('北京市', '110000', [
            new ForecastDay('2024-01-01', '周一', '晴', '多云', 10.0, 0.0, '北', '南', '≤3', '≤3'),
        ]);
        $provider->expects()->getForecastsWeather('北京')->andReturn($forecast);

        $w = new Weather($provider);
        $this->assertSame($forecast, $w->getForecastsWeather('北京'));
    }

    public function testBackwardCompatGetWeather()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $weather = new CurrentWeather('深圳市', '440300', 26.0, '晴', '东', '≤3');
        $provider->expects()->getLiveWeather('深圳')->andReturn($weather);

        $w = new Weather($provider);
        $array = $w->getWeather('深圳', 'base');

        $this->assertSame('深圳市', $array['city']);
        $this->assertSame(26.0, $array['temperature']);
    }

    public function testBackwardCompatHttpClientWithAMap()
    {
        $w = new Weather('mock-key');
        $client = new Client();
        $w->setHttpClient($client);

        $this->assertSame($client, $w->getHttpClient());
    }

    public function testBackwardCompatGuzzleOptionsWithAMap()
    {
        $w = new Weather('mock-key');
        $w->setGuzzleOptions(['timeout' => 10]);

        $this->assertSame(10, $w->getHttpClient()->getConfig('timeout'));
    }

    public function testGetLifeIndicesDelegates()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $index = new \SnowmanNunu\Weather\DTO\LifeIndex('运动指数', '1', '适宜', '天气不错', 'sport');
        $provider->expects()->getLifeIndices('北京')->andReturn([$index]);

        $w = new Weather($provider);
        $result = $w->getLifeIndices('北京');

        $this->assertCount(1, $result);
        $this->assertSame('运动指数', $result[0]->name);
    }

    public function testGetAirQualityDelegates()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $aqi = new \SnowmanNunu\Weather\DTO\AirQuality('北京市', 45, '1', '优');
        $provider->expects()->getAirQuality('北京')->andReturn($aqi);

        $w = new Weather($provider);
        $result = $w->getAirQuality('北京');

        $this->assertInstanceOf(\SnowmanNunu\Weather\DTO\AirQuality::class, $result);
        $this->assertSame(45, $result->aqi);
    }

    public function testGetAlertsDelegates()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $alert = new \SnowmanNunu\Weather\DTO\WeatherAlert('暴雨预警', '暴雨', '黄色', '预计未来有强降水', '2024-01-01 10:00');
        $provider->expects()->getAlerts('北京')->andReturn([$alert]);

        $w = new Weather($provider);
        $result = $w->getAlerts('北京');

        $this->assertCount(1, $result);
        $this->assertSame('暴雨预警', $result[0]->title);
    }

    public function testGetMinutelyPrecipitationDelegates()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $item = new \SnowmanNunu\Weather\DTO\Precipitation('2024-01-01T14:00+08:00', 'rain', 0.5);
        $provider->expects()->getMinutelyPrecipitation('北京')->andReturn([$item]);

        $w = new Weather($provider);
        $result = $w->getMinutelyPrecipitation('北京');

        $this->assertCount(1, $result);
        $this->assertSame('2024-01-01T14:00+08:00', $result[0]->time);
    }

    public function testHttpClientThrowsForNonAMap()
    {
        $provider = new QWeatherProvider('key');
        $w = new Weather($provider);

        $this->expectException(\BadMethodCallException::class);
        $w->getHttpClient();
    }

    public function testGetAllDelegatesToProvider()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $provider->allows()->setLang(\Mockery::any());

        $current = new CurrentWeather('深圳市', '440300', 26.0, '晴', '东', '≤3');
        $forecast = new Forecast('深圳市', '440300', []);
        $provider->expects()->fetchAll('深圳')->once()->andReturn([
            'current' => $current,
            'forecast' => $forecast,
            'indices' => [],
            'aqi' => null,
            'alerts' => [],
            'minutely' => [],
        ]);

        $w = new Weather($provider);
        $result = $w->getAll('深圳');

        $this->assertSame($current, $result['current']);
        $this->assertSame($forecast, $result['forecast']);
    }
}

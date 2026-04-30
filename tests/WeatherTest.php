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
        $provider->shouldNotReceive('getLiveWeather');

        $cachedWeather = new CurrentWeather('深圳市', '440300', 26.0, '晴', '东', '≤3');

        $cache = \Mockery::mock(\Psr\SimpleCache\CacheInterface::class);
        $cacheKey = 'weather:mock:' . md5('深圳') . ':live';
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
        $cacheKey = 'weather:mock:' . md5('深圳') . ':live';
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

    public function testHttpClientThrowsForNonAMap()
    {
        $provider = new QWeatherProvider('key');
        $w = new Weather($provider);

        $this->expectException(\BadMethodCallException::class);
        $w->getHttpClient();
    }
}

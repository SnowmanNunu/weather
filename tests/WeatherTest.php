<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Tests;

use GuzzleHttp\Client;
use SnowmanNunu\Weather\Contracts\Provider;
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
        $provider->expects()->getWeather('深圳', 'base', 'json')->andReturn(['status' => '1']);

        $w = new Weather($provider);
        $result = $w->getWeather('深圳');

        $this->assertSame(['status' => '1'], $result);
    }

    public function testCacheHit()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $provider->shouldNotReceive('getWeather');

        $cache = \Mockery::mock(\Psr\SimpleCache\CacheInterface::class);
        $cacheKey = 'weather:mock:' . md5('深圳') . ':base:json';
        $cache->allows()->get($cacheKey)->andReturn(['cached' => true]);

        $w = new Weather($provider);
        $w->withCache($cache);

        $this->assertSame(['cached' => true], $w->getWeather('深圳'));
    }

    public function testCacheMissAndStore()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $provider->expects()->getWeather('深圳', 'base', 'json')->once()->andReturn(['live' => true]);

        $cache = \Mockery::mock(\Psr\SimpleCache\CacheInterface::class);
        $cacheKey = 'weather:mock:' . md5('深圳') . ':base:json';
        $cache->allows()->get($cacheKey)->andReturn(null);
        $cache->expects()->set($cacheKey, ['live' => true], 300)->once();

        $w = new Weather($provider);
        $w->withCache($cache);

        $this->assertSame(['live' => true], $w->getWeather('深圳'));
    }

    public function testGetLiveWeatherDelegates()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $provider->expects()->getWeather('北京', 'base', 'json')->andReturn(['temp' => '25']);

        $w = new Weather($provider);
        $this->assertSame(['temp' => '25'], $w->getLiveWeather('北京'));
    }

    public function testGetForecastsWeatherDelegates()
    {
        $provider = \Mockery::mock(Provider::class);
        $provider->allows()->getName()->andReturn('mock');
        $provider->expects()->getWeather('北京', 'all', 'json')->andReturn(['forecasts' => []]);

        $w = new Weather($provider);
        $this->assertSame(['forecasts' => []], $w->getForecastsWeather('北京'));
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

<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Mockery\Matcher\AnyArgs;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Providers\AMapProvider;
use PHPUnit\Framework\TestCase;

class AMapProviderTest extends TestCase
{
    public function testGetWeather()
    {
        // json
        $response = new Response(200, [], '{"success": true}');
        $client = \Mockery::mock(Client::class);
        $client->allows()->get('https://restapi.amap.com/v3/weather/weatherInfo', [
            'query' => [
                'key' => 'mock-key',
                'city' => '深圳',
                'output' => 'json',
                'extensions' => 'base',
            ],
        ])->andReturn($response);

        $w = new AMapProvider('mock-key');
        $w->setHttpClient($client);

        $this->assertSame(['success' => true], $w->getWeather('深圳'));

        // xml
        $response = new Response(200, [], '<hello>content</hello>');
        $client = \Mockery::mock(Client::class);
        $client->allows()->get('https://restapi.amap.com/v3/weather/weatherInfo', [
            'query' => [
                'key' => 'mock-key',
                'city' => '深圳',
                'extensions' => 'all',
                'output' => 'xml',
            ],
        ])->andReturn($response);

        $w = new AMapProvider('mock-key');
        $w->setHttpClient($client);

        $this->assertSame('<hello>content</hello>', $w->getWeather('深圳', 'all', 'xml'));
    }

    public function testGetHttpClient()
    {
        $w = new AMapProvider('mock-key');
        $this->assertInstanceOf(ClientInterface::class, $w->getHttpClient());
    }

    public function testGetWeatherWithEmptyCity()
    {
        $w = new AMapProvider('mock-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('City name cannot be empty.');

        $w->getWeather('');
    }

    public function testGetHttpClientReturnsSameInstance()
    {
        $w = new AMapProvider('mock-key');

        $client1 = $w->getHttpClient();
        $client2 = $w->getHttpClient();

        $this->assertSame($client1, $client2);
        $this->assertInstanceOf(ClientInterface::class, $client1);
    }

    public function testSetHttpClient()
    {
        $w = new AMapProvider('mock-key');
        $customClient = new Client(['timeout' => 10]);

        $w->setHttpClient($customClient);

        $this->assertSame($customClient, $w->getHttpClient());
    }

    public function testSetGuzzleOptions()
    {
        $w = new AMapProvider('mock-key');

        $this->assertNull($w->getHttpClient()->getConfig('timeout'));

        $w->setGuzzleOptions(['timeout' => 5000]);

        $this->assertSame(5000, $w->getHttpClient()->getConfig('timeout'));
    }

    public function testGetWeatherWithInvalidType()
    {
        $w = new AMapProvider('mock-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid type value(base/all): foo');

        $w->getWeather('深圳', 'foo');

        $this->fail('Failed to assert getWeather throw exception with invalid argument.');
    }

    public function testGetWeatherWithInvalidFormat()
    {
        $w = new AMapProvider('mock-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid response format: array');

        $w->getWeather('深圳', 'base', 'array');

        $this->fail('Failed to assert getWeather throw exception with invalid argument.');
    }

    public function testGetWeatherWithGuzzleRuntimeException()
    {
        $client = \Mockery::mock(Client::class);
        $request = new \GuzzleHttp\Psr7\Request('GET', 'test');
        $client->allows()
            ->get(new AnyArgs())
            ->andThrow(new \GuzzleHttp\Exception\ConnectException('request timeout', $request));

        $w = new AMapProvider('mock-key');
        $w->setHttpClient($client);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('request timeout');

        $w->getWeather('深圳');
    }

    public function testGetLiveWeather()
    {
        $w = \Mockery::mock(AMapProvider::class, ['mock-key'])->makePartial();
        $w->expects()->getWeather('深圳', 'base', 'json')->andReturn(['success' => true]);

        $this->assertSame(['success' => true], $w->getLiveWeather('深圳'));
    }

    public function testGetForecastsWeather()
    {
        $w = \Mockery::mock(AMapProvider::class, ['mock-key'])->makePartial();
        $w->expects()->getWeather('深圳', 'all', 'json')->andReturn(['success' => true]);

        $this->assertSame(['success' => true], $w->getForecastsWeather('深圳'));
    }
}

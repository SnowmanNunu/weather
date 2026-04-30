<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Providers\QWeatherProvider;
use PHPUnit\Framework\TestCase;

class QWeatherProviderTest extends TestCase
{
    public function testGetLiveWeather()
    {
        $response = new Response(200, [], '{"code":"200","now":{"temp":"26"}}');
        $client = \Mockery::mock(Client::class);
        $client->allows()->get('https://devapi.qweather.com/v7/weather/now', [
            'query' => [
                'key' => 'mock-key',
                'location' => '深圳',
            ],
        ])->andReturn($response);

        $w = new QWeatherProvider('mock-key');
        $w->setHttpClient($client);

        $this->assertSame(['code' => '200', 'now' => ['temp' => '26']], $w->getLiveWeather('深圳'));
    }

    public function testGetForecastsWeather()
    {
        $response = new Response(200, [], '{"code":"200","daily":[{"fxDate":"2024-01-01"}]}');
        $client = \Mockery::mock(Client::class);
        $client->allows()->get('https://devapi.qweather.com/v7/weather/7d', [
            'query' => [
                'key' => 'mock-key',
                'location' => '深圳',
            ],
        ])->andReturn($response);

        $w = new QWeatherProvider('mock-key');
        $w->setHttpClient($client);

        $this->assertSame(['code' => '200', 'daily' => [['fxDate' => '2024-01-01']]], $w->getForecastsWeather('深圳'));
    }

    public function testEmptyCityThrows()
    {
        $w = new QWeatherProvider('mock-key');
        $this->expectException(InvalidArgumentException::class);
        $w->getLiveWeather('');
    }

    public function testInvalidFormatThrows()
    {
        $w = new QWeatherProvider('mock-key');
        $this->expectException(InvalidArgumentException::class);
        $w->getLiveWeather('深圳', 'yaml');
    }

    public function testHttpException()
    {
        $client = \Mockery::mock(Client::class);
        $request = new \GuzzleHttp\Psr7\Request('GET', 'test');
        $client->allows()->get(\Mockery::any(), \Mockery::any())
            ->andThrow(new \GuzzleHttp\Exception\ConnectException('timeout', $request));

        $w = new QWeatherProvider('mock-key');
        $w->setHttpClient($client);

        $this->expectException(HttpException::class);
        $w->getLiveWeather('深圳');
    }

    public function testName()
    {
        $w = new QWeatherProvider('key');
        $this->assertSame('qweather', $w->getName());
    }
}

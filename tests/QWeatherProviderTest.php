<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Providers\QWeatherProvider;
use PHPUnit\Framework\TestCase;

class QWeatherProviderTest extends TestCase
{
    protected function mockClient(array $responseData): Client
    {
        $response = new Response(200, [], json_encode($responseData));
        $client = \Mockery::mock(Client::class);
        $client->allows()->get(\Mockery::any(), \Mockery::any())->andReturn($response);

        return $client;
    }

    public function testGetLiveWeather()
    {
        $client = $this->mockClient([
            'code' => '200',
            'now' => [
                'temp' => '26',
                'text' => '晴',
                'windDir' => '东风',
                'windScale' => '3',
                'humidity' => '65',
                'pressure' => '1010',
                'vis' => '10',
                'feelsLike' => '28',
                'obsTime' => '2024-01-01T14:30+08:00',
                'icon' => '100',
            ],
        ]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $weather = $provider->getLiveWeather('深圳');

        $this->assertInstanceOf(CurrentWeather::class, $weather);
        $this->assertSame('深圳', $weather->city);
        $this->assertSame(26.0, $weather->temperature);
        $this->assertSame('晴', $weather->weather);
        $this->assertSame('东风', $weather->windDirection);
        $this->assertSame(65, $weather->humidity);
        $this->assertSame(1010, $weather->pressure);
    }

    public function testGetForecastsWeather()
    {
        $client = $this->mockClient([
            'code' => '200',
            'daily' => [
                [
                    'fxDate' => '2024-01-01',
                    'week' => '1',
                    'textDay' => '晴',
                    'textNight' => '多云',
                    'tempMax' => '28',
                    'tempMin' => '18',
                    'windDirDay' => '东风',
                    'windDirNight' => '西风',
                    'windScaleDay' => '1-2',
                    'windScaleNight' => '1-2',
                    'iconDay' => '100',
                    'iconNight' => '150',
                ],
            ],
        ]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $forecast = $provider->getForecastsWeather('深圳');

        $this->assertInstanceOf(Forecast::class, $forecast);
        $this->assertSame('深圳', $forecast->city);
        $this->assertCount(1, $forecast->casts);
        $this->assertSame('周一', $forecast->casts[0]->week);
        $this->assertSame(28.0, $forecast->casts[0]->dayTemp);
    }

    public function testEmptyCityThrows()
    {
        $provider = new QWeatherProvider('mock-key');
        $this->expectException(InvalidArgumentException::class);
        $provider->getLiveWeather('');
    }

    public function testApiErrorThrows()
    {
        $client = $this->mockClient(['code' => '404', 'daily' => []]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $this->expectException(HttpException::class);
        $provider->getLiveWeather('深圳');
    }

    public function testHttpException()
    {
        $request = new \GuzzleHttp\Psr7\Request('GET', 'test');
        $client = \Mockery::mock(Client::class);
        $client->allows()->get(\Mockery::any(), \Mockery::any())
            ->andThrow(new \GuzzleHttp\Exception\ConnectException('timeout', $request));

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $this->expectException(HttpException::class);
        $provider->getLiveWeather('深圳');
    }

    public function testName()
    {
        $provider = new QWeatherProvider('key');
        $this->assertSame('qweather', $provider->getName());
    }
}

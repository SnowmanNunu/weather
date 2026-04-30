<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Providers\OpenWeatherMapProvider;
use PHPUnit\Framework\TestCase;

class OpenWeatherMapProviderTest extends TestCase
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
            'name' => 'Beijing',
            'id' => 1816670,
            'dt' => 1640995200,
            'weather' => [
                ['main' => 'Clear', 'description' => 'clear sky', 'icon' => '01d'],
            ],
            'main' => [
                'temp' => 26.5,
                'feels_like' => 28.0,
                'humidity' => 65,
                'pressure' => 1010,
            ],
            'wind' => [
                'speed' => 3.5,
                'deg' => 90,
            ],
            'visibility' => 10000,
        ]);

        $provider = new OpenWeatherMapProvider('mock-key');
        $provider->setHttpClient($client);

        $weather = $provider->getLiveWeather('Beijing');

        $this->assertInstanceOf(CurrentWeather::class, $weather);
        $this->assertSame('Beijing', $weather->city);
        $this->assertSame(26.5, $weather->temperature);
        $this->assertSame('clear sky', $weather->weather);
        $this->assertSame('东', $weather->windDirection);
        $this->assertSame(65, $weather->humidity);
        $this->assertSame(1010, $weather->pressure);
    }

    public function testGetForecastsWeather()
    {
        $client = $this->mockClient([
            'city' => [
                'name' => 'Beijing',
                'id' => 1816670,
            ],
            'list' => [
                [
                    'dt_txt' => '2024-01-01 12:00:00',
                    'main' => [
                        'temp_max' => 28.0,
                        'temp_min' => 18.0,
                    ],
                    'weather' => [
                        ['description' => 'clear sky', 'icon' => '01d'],
                    ],
                    'wind' => [
                        'speed' => 3.5,
                        'deg' => 90,
                    ],
                ],
                [
                    'dt_txt' => '2024-01-01 18:00:00',
                    'main' => [
                        'temp_max' => 25.0,
                        'temp_min' => 18.0,
                    ],
                    'weather' => [
                        ['description' => 'few clouds', 'icon' => '02n'],
                    ],
                    'wind' => [
                        'speed' => 2.0,
                        'deg' => 180,
                    ],
                ],
            ],
        ]);

        $provider = new OpenWeatherMapProvider('mock-key');
        $provider->setHttpClient($client);

        $forecast = $provider->getForecastsWeather('Beijing');

        $this->assertInstanceOf(Forecast::class, $forecast);
        $this->assertSame('Beijing', $forecast->city);
        $this->assertCount(1, $forecast->casts);
        $this->assertSame('2024-01-01', $forecast->casts[0]->date);
        $this->assertSame('clear sky', $forecast->casts[0]->dayWeather);
        $this->assertSame('few clouds', $forecast->casts[0]->nightWeather);
    }

    public function testEmptyCityThrows()
    {
        $provider = new OpenWeatherMapProvider('mock-key');
        $this->expectException(InvalidArgumentException::class);
        $provider->getLiveWeather('');
    }

    public function testHttpException()
    {
        $request = new \GuzzleHttp\Psr7\Request('GET', 'test');
        $client = \Mockery::mock(Client::class);
        $client->allows()->get(\Mockery::any(), \Mockery::any())
            ->andThrow(new \GuzzleHttp\Exception\ConnectException('timeout', $request));

        $provider = new OpenWeatherMapProvider('mock-key');
        $provider->setHttpClient($client);

        $this->expectException(HttpException::class);
        $provider->getLiveWeather('Beijing');
    }

    public function testGetLifeIndicesReturnsEmpty()
    {
        $provider = new OpenWeatherMapProvider('key');
        $indices = $provider->getLifeIndices('Beijing');

        $this->assertIsArray($indices);
        $this->assertCount(0, $indices);
    }

    public function testName()
    {
        $provider = new OpenWeatherMapProvider('key');
        $this->assertSame('openweathermap', $provider->getName());
    }
}

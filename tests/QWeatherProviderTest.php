<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use SnowmanNunu\Weather\DTO\AirQuality;
use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;
use SnowmanNunu\Weather\DTO\LifeIndex;
use SnowmanNunu\Weather\DTO\Precipitation;
use SnowmanNunu\Weather\DTO\WeatherAlert;
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

    public function testGetLifeIndices()
    {
        $client = $this->mockClient([
            'code' => '200',
            'daily' => [
                [
                    'name' => '运动指数',
                    'level' => '1',
                    'category' => '适宜',
                    'text' => '天气不错，适宜户外运动。',
                    'type' => '1',
                ],
            ],
        ]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $indices = $provider->getLifeIndices('深圳');

        $this->assertCount(1, $indices);
        $this->assertInstanceOf(LifeIndex::class, $indices[0]);
        $this->assertSame('运动指数', $indices[0]->name);
        $this->assertSame('适宜', $indices[0]->category);
    }

    public function testGetLifeIndicesReturnsEmptyOnError()
    {
        $client = $this->mockClient(['code' => '404', 'daily' => []]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $indices = $provider->getLifeIndices('深圳');

        $this->assertIsArray($indices);
        $this->assertCount(0, $indices);
    }

    public function testGetAirQuality()
    {
        $client = $this->mockClient([
            'code' => '200',
            'now' => [
                'pubTime' => '2024-01-01T14:00+08:00',
                'aqi' => '93',
                'level' => '2',
                'category' => '良',
                'primary' => 'PM2.5',
                'pm2p5' => '35',
                'pm10' => '60',
                'no2' => '30',
                'so2' => '10',
                'co' => '0.8',
                'o3' => '100',
            ],
        ]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $aqi = $provider->getAirQuality('深圳');

        $this->assertInstanceOf(AirQuality::class, $aqi);
        $this->assertSame(93, $aqi->aqi);
        $this->assertSame('良', $aqi->category);
        $this->assertSame('PM2.5', $aqi->primaryPollutant);
    }

    public function testGetAirQualityReturnsNullOnError()
    {
        $client = $this->mockClient(['code' => '404', 'now' => []]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $aqi = $provider->getAirQuality('深圳');

        $this->assertNull($aqi);
    }

    public function testGetAlerts()
    {
        $client = $this->mockClient([
            'code' => '200',
            'warning' => [
                [
                    'title' => '深圳市发布暴雨黄色预警',
                    'typeName' => '暴雨',
                    'level' => '黄色',
                    'text' => '预计未来3小时将有强降水。',
                    'pubTime' => '2024-01-01T10:00+08:00',
                    'status' => 'active',
                    'sender' => '深圳市气象台',
                    'startTime' => '2024-01-01T10:00+08:00',
                    'endTime' => '2024-01-01T16:00+08:00',
                ],
            ],
        ]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $alerts = $provider->getAlerts('深圳');

        $this->assertCount(1, $alerts);
        $this->assertInstanceOf(WeatherAlert::class, $alerts[0]);
        $this->assertSame('深圳市发布暴雨黄色预警', $alerts[0]->title);
        $this->assertSame('暴雨', $alerts[0]->type);
    }

    public function testGetAlertsReturnsEmptyOnError()
    {
        $client = $this->mockClient(['code' => '404', 'warning' => []]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $alerts = $provider->getAlerts('深圳');

        $this->assertIsArray($alerts);
        $this->assertCount(0, $alerts);
    }

    public function testGetMinutelyPrecipitation()
    {
        $client = $this->mockClient([
            'code' => '200',
            'minutely' => [
                [
                    'fxTime' => '2024-01-01T14:00+08:00',
                    'type' => 'rain',
                    'precip' => '0.5',
                ],
                [
                    'fxTime' => '2024-01-01T14:05+08:00',
                    'type' => 'rain',
                    'precip' => '1.2',
                ],
            ],
        ]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $items = $provider->getMinutelyPrecipitation('深圳');

        $this->assertCount(2, $items);
        $this->assertInstanceOf(Precipitation::class, $items[0]);
        $this->assertSame('2024-01-01T14:00+08:00', $items[0]->time);
        $this->assertSame(0.5, $items[0]->precipitation);
    }

    public function testGetMinutelyPrecipitationReturnsEmptyOnError()
    {
        $client = $this->mockClient(['code' => '404', 'minutely' => []]);

        $provider = new QWeatherProvider('mock-key');
        $provider->setHttpClient($client);

        $items = $provider->getMinutelyPrecipitation('深圳');

        $this->assertIsArray($items);
        $this->assertCount(0, $items);
    }

    public function testName()
    {
        $provider = new QWeatherProvider('key');
        $this->assertSame('qweather', $provider->getName());
    }
}

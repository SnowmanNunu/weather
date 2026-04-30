<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;
use SnowmanNunu\Weather\DTO\LifeIndex;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Providers\AMapProvider;
use PHPUnit\Framework\TestCase;

class AMapProviderTest extends TestCase
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
            'status' => '1',
            'lives' => [
                [
                    'city' => '深圳市',
                    'adcode' => '440300',
                    'weather' => '晴',
                    'temperature' => '26',
                    'winddirection' => '东',
                    'windpower' => '≤3',
                    'humidity' => '65',
                    'reporttime' => '2024-01-01 14:30:00',
                ],
            ],
        ]);

        $provider = new AMapProvider('mock-key');
        $provider->setHttpClient($client);

        $weather = $provider->getLiveWeather('深圳');

        $this->assertInstanceOf(CurrentWeather::class, $weather);
        $this->assertSame('深圳市', $weather->city);
        $this->assertSame(26.0, $weather->temperature);
        $this->assertSame('晴', $weather->weather);
        $this->assertSame('东', $weather->windDirection);
    }

    public function testGetForecastsWeather()
    {
        $client = $this->mockClient([
            'status' => '1',
            'forecasts' => [
                [
                    'city' => '深圳市',
                    'adcode' => '440300',
                    'casts' => [
                        [
                            'date' => '2024-01-01',
                            'week' => '1',
                            'dayweather' => '晴',
                            'nightweather' => '多云',
                            'daytemp' => '28',
                            'nighttemp' => '18',
                            'daywind' => '东',
                            'nightwind' => '西',
                            'daypower' => '≤3',
                            'nightpower' => '≤3',
                        ],
                    ],
                ],
            ],
        ]);

        $provider = new AMapProvider('mock-key');
        $provider->setHttpClient($client);

        $forecast = $provider->getForecastsWeather('深圳');

        $this->assertInstanceOf(Forecast::class, $forecast);
        $this->assertSame('深圳市', $forecast->city);
        $this->assertCount(1, $forecast->casts);
        $this->assertSame('周一', $forecast->casts[0]->week);
        $this->assertSame(28.0, $forecast->casts[0]->dayTemp);
    }

    public function testGetHttpClient()
    {
        $provider = new AMapProvider('mock-key');
        $this->assertInstanceOf(ClientInterface::class, $provider->getHttpClient());
    }

    public function testGetWeatherWithEmptyCity()
    {
        $provider = new AMapProvider('mock-key');

        $this->expectException(InvalidArgumentException::class);
        $provider->getLiveWeather('');
    }

    public function testGetHttpClientReturnsSameInstance()
    {
        $provider = new AMapProvider('mock-key');

        $client1 = $provider->getHttpClient();
        $client2 = $provider->getHttpClient();

        $this->assertSame($client1, $client2);
        $this->assertInstanceOf(ClientInterface::class, $client1);
    }

    public function testSetHttpClient()
    {
        $provider = new AMapProvider('mock-key');
        $customClient = new Client(['timeout' => 10]);

        $provider->setHttpClient($customClient);

        $this->assertSame($customClient, $provider->getHttpClient());
    }

    public function testSetGuzzleOptions()
    {
        $provider = new AMapProvider('mock-key');

        $this->assertNull($provider->getHttpClient()->getConfig('timeout'));

        $provider->setGuzzleOptions(['timeout' => 5000]);

        $this->assertSame(5000, $provider->getHttpClient()->getConfig('timeout'));
    }

    public function testApiErrorThrowsHttpException()
    {
        $client = $this->mockClient(['status' => '0', 'info' => 'INVALID_PARAMS']);

        $provider = new AMapProvider('mock-key');
        $provider->setHttpClient($client);

        $this->expectException(HttpException::class);
        $provider->getLiveWeather('深圳');
    }

    public function testGetLifeIndices()
    {
        $client = $this->mockClient([
            'status' => '1',
            'lifestyles' => [
                [
                    'name' => '运动指数',
                    'level' => '1',
                    'category' => '适宜',
                    'text' => '天气不错，适宜户外运动。',
                    'type' => 'sport',
                ],
            ],
        ]);

        $provider = new AMapProvider('mock-key');
        $provider->setHttpClient($client);

        $indices = $provider->getLifeIndices('深圳');

        $this->assertCount(1, $indices);
        $this->assertInstanceOf(LifeIndex::class, $indices[0]);
        $this->assertSame('运动指数', $indices[0]->name);
        $this->assertSame('适宜', $indices[0]->category);
    }

    public function testGetLifeIndicesReturnsEmptyOnError()
    {
        $client = $this->mockClient(['status' => '0', 'info' => 'SERVICE_NOT_AVAILABLE']);

        $provider = new AMapProvider('mock-key');
        $provider->setHttpClient($client);

        $indices = $provider->getLifeIndices('深圳');

        $this->assertIsArray($indices);
        $this->assertCount(0, $indices);
    }

    public function testGetWeatherWithGuzzleRuntimeException()
    {
        $request = new \GuzzleHttp\Psr7\Request('GET', 'test');
        $client = \Mockery::mock(Client::class);
        $client->allows()
            ->get(\Mockery::any(), \Mockery::any())
            ->andThrow(new \GuzzleHttp\Exception\ConnectException('request timeout', $request));

        $provider = new AMapProvider('mock-key');
        $provider->setHttpClient($client);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('request timeout');

        $provider->getLiveWeather('深圳');
    }
}

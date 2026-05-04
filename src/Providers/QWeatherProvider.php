<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use SnowmanNunu\Weather\Contracts\Provider;
use SnowmanNunu\Weather\DTO\AirQuality;
use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;
use SnowmanNunu\Weather\DTO\ForecastDay;
use SnowmanNunu\Weather\DTO\LifeIndex;
use SnowmanNunu\Weather\DTO\WeatherAlert;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;

class QWeatherProvider implements Provider
{
    protected string $key;

    /** @var array<string, mixed> */
    protected array $guzzleOptions = [];

    protected ?Client $httpClient = null;

    protected string $baseUri = 'https://devapi.qweather.com/v7';

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function getHttpClient(): Client
    {
        if (!$this->httpClient instanceof Client) {
            $this->httpClient = new Client(array_merge([
                'timeout' => 10,
                'connect_timeout' => 5,
            ], $this->guzzleOptions));
        }

        return $this->httpClient;
    }

    public function setHttpClient(Client $client): void
    {
        $this->httpClient = $client;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setGuzzleOptions(array $options): void
    {
        $this->guzzleOptions = $options;
        $this->httpClient = null;
    }

    public function getName(): string
    {
        return 'qweather';
    }

    public function getLiveWeather(string $city): CurrentWeather
    {
        $data = $this->request('/weather/now', $city);

        if (($data['code'] ?? '') !== '200' || empty($data['now'])) {
            throw new HttpException('QWeather API returned error code: ' . ($data['code'] ?? 'unknown'));
        }

        $now = $data['now'];

        return new CurrentWeather(
            city: $city,
            adcode: $data['location']['id'] ?? '',
            temperature: (float) ($now['temp'] ?? 0),
            weather: $now['text'] ?? '',
            windDirection: $now['windDir'] ?? '',
            windPower: $now['windScale'] ?? '',
            humidity: isset($now['humidity']) ? (int) $now['humidity'] : null,
            pressure: isset($now['pressure']) ? (int) $now['pressure'] : null,
            visibility: $now['vis'] ?? null,
            feelsLike: isset($now['feelsLike']) ? (float) $now['feelsLike'] : null,
            updateTime: $now['obsTime'] ?? '',
            icon: $now['icon'] ?? '',
        );
    }

    public function getForecastsWeather(string $city): Forecast
    {
        $data = $this->request('/weather/7d', $city);

        if (($data['code'] ?? '') !== '200' || empty($data['daily'])) {
            throw new HttpException('QWeather API returned error code: ' . ($data['code'] ?? 'unknown'));
        }

        $casts = [];

        foreach ($data['daily'] as $day) {
            $casts[] = new ForecastDay(
                date: $day['fxDate'] ?? '',
                week: $this->mapWeek($day['week'] ?? ''),
                dayWeather: $day['textDay'] ?? '',
                nightWeather: $day['textNight'] ?? '',
                dayTemp: (float) ($day['tempMax'] ?? 0),
                nightTemp: (float) ($day['tempMin'] ?? 0),
                dayWind: $day['windDirDay'] ?? '',
                nightWind: $day['windDirNight'] ?? '',
                dayPower: $day['windScaleDay'] ?? '',
                nightPower: $day['windScaleNight'] ?? '',
                iconDay: $day['iconDay'] ?? '',
                iconNight: $day['iconNight'] ?? '',
            );
        }

        return new Forecast(
            city: $city,
            adcode: $data['location']['id'] ?? '',
            casts: $casts,
        );
    }

    public function getLifeIndices(string $city): array
    {
        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $url = $this->baseUri . '/indices/1d';
        $types = '1,2,3,5,6,8,9';

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => [
                    'key' => $this->key,
                    'location' => $city,
                    'type' => $types,
                ],
            ])->getBody()->getContents();

            $data = json_decode($response, true);

            if (!is_array($data) || ($data['code'] ?? '') !== '200') {
                return [];
            }

            $indices = [];
            foreach ($data['daily'] ?? [] as $item) {
                $indices[] = new LifeIndex(
                    name: $item['name'] ?? '',
                    level: $item['level'] ?? '',
                    category: $item['category'] ?? '',
                    advice: $item['text'] ?? '',
                    type: $item['type'] ?? '',
                );
            }

            return $indices;
        } catch (TransferException $e) {
            return [];
        }
    }

    public function getAirQuality(string $city): ?AirQuality
    {
        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $url = $this->baseUri . '/air/now';

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => [
                    'key' => $this->key,
                    'location' => $city,
                ],
            ])->getBody()->getContents();

            $data = json_decode($response, true);

            if (!is_array($data) || ($data['code'] ?? '') !== '200') {
                return null;
            }

            $now = $data['now'] ?? [];

            return new AirQuality(
                city: $city,
                aqi: isset($now['aqi']) ? (int) $now['aqi'] : null,
                level: $now['level'] ?? null,
                category: $now['category'] ?? null,
                primaryPollutant: $now['primary'] ?? null,
                pm25: isset($now['pm2p5']) ? (float) $now['pm2p5'] : null,
                pm10: isset($now['pm10']) ? (float) $now['pm10'] : null,
                no2: isset($now['no2']) ? (float) $now['no2'] : null,
                so2: isset($now['so2']) ? (float) $now['so2'] : null,
                co: isset($now['co']) ? (float) $now['co'] : null,
                o3: isset($now['o3']) ? (float) $now['o3'] : null,
                updateTime: $now['pubTime'] ?? null,
            );
        } catch (TransferException $e) {
            return null;
        }
    }

    public function getAlerts(string $city): array
    {
        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $url = $this->baseUri . '/warning/now';

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => [
                    'key' => $this->key,
                    'location' => $city,
                ],
            ])->getBody()->getContents();

            $data = json_decode($response, true);

            if (!is_array($data) || ($data['code'] ?? '') !== '200') {
                return [];
            }

            $alerts = [];
            foreach ($data['warning'] ?? [] as $item) {
                $alerts[] = new WeatherAlert(
                    title: $item['title'] ?? '',
                    type: $item['typeName'] ?? '',
                    level: $item['level'] ?? '',
                    content: $item['text'] ?? '',
                    pubTime: $item['pubTime'] ?? '',
                    status: $item['status'] ?? 'active',
                    sender: $item['sender'] ?? '',
                    startTime: $item['startTime'] ?? '',
                    endTime: $item['endTime'] ?? '',
                );
            }

            return $alerts;
        } catch (TransferException $e) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     * @throws HttpException
     * @throws InvalidArgumentException
     */
    protected function request(string $endpoint, string $city): array
    {
        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $url = $this->baseUri . $endpoint;

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => array_filter([
                    'key' => $this->key,
                    'location' => $city,
                ]),
            ])->getBody()->getContents();

            $decoded = json_decode($response, true);

            return is_array($decoded) ? $decoded : [];
        } catch (TransferException $e) {
            throw new HttpException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    protected function mapWeek(string $week): string
    {
        $map = [
            '1' => '周一',
            '2' => '周二',
            '3' => '周三',
            '4' => '周四',
            '5' => '周五',
            '6' => '周六',
            '7' => '周日',
        ];

        return $map[$week] ?? $week;
    }
}

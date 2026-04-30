<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use SnowmanNunu\Weather\Contracts\Provider;
use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;
use SnowmanNunu\Weather\DTO\ForecastDay;
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

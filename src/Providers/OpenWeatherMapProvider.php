<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Utils;
use SnowmanNunu\Weather\Contracts\Provider;
use SnowmanNunu\Weather\DTO\AirQuality;
use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;
use SnowmanNunu\Weather\DTO\ForecastDay;
use SnowmanNunu\Weather\DTO\LifeIndex;
use SnowmanNunu\Weather\DTO\WeatherAlert;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Exceptions\InvalidKeyException;
use SnowmanNunu\Weather\Exceptions\RateLimitException;

class OpenWeatherMapProvider implements Provider
{
    protected string $key;

    /** @var array<string, mixed> */
    protected array $guzzleOptions = [];

    protected ?Client $httpClient = null;

    protected string $baseUri = 'https://api.openweathermap.org/data/2.5';

    protected ?string $lang = null;

    public function __construct(string $key)
    {
        $this->key = $key;
        $this->lang = getenv('WEATHER_LANG') ?: null;
    }

    public function getHttpClient(): Client
    {
        if (!$this->httpClient instanceof Client) {
            $stack = HandlerStack::create();
            $stack->push(Middleware::retry(
                function ($retries, $request, $response = null, $exception = null) {
                    if ($retries >= 2) {
                        return false;
                    }
                    if ($exception instanceof TransferException) {
                        return true;
                    }
                    if ($response && $response->getStatusCode() >= 500) {
                        return true;
                    }
                    if ($response && $response->getStatusCode() === 429) {
                        return true;
                    }

                    return false;
                },
                function ($retries) {
                    return 100 * (2 ** $retries);
                }
            ));

            $this->httpClient = new Client(array_merge([
                'timeout' => 10,
                'connect_timeout' => 5,
                'handler' => $stack,
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
        return 'openweathermap';
    }

    public function setLang(string $lang): void
    {
        $this->lang = $lang;
    }

    public function getLang(): string
    {
        return $this->lang ?? 'zh';
    }

    public function getLiveWeather(string $city): CurrentWeather
    {
        $data = $this->request('/weather', $city);

        return $this->normalizeCurrent($data);
    }

    public function getForecastsWeather(string $city): Forecast
    {
        $data = $this->request('/forecast', $city);

        return $this->normalizeForecast($data);
    }

    public function getLifeIndices(string $city): array
    {
        return [];
    }

    public function getAirQuality(string $city): ?AirQuality
    {
        return null;
    }

    public function getAlerts(string $city): array
    {
        return [];
    }

    public function getMinutelyPrecipitation(string $city): array
    {
        return [];
    }

    public function fetchAll(string $city): array
    {
        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $url = $this->baseUri;
        $lang = $this->lang === 'zh' ? 'zh_cn' : ($this->lang ?: 'zh_cn');
        $query = [
            'q' => $city,
            'appid' => $this->key,
            'units' => 'metric',
            'lang' => $lang,
        ];
        $client = $this->getHttpClient();

        $promises = [
            'current' => $client->getAsync($url . '/weather', ['query' => $query])
                ->then(
                    function ($response) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
                            return $this->normalizeCurrent(is_array($data) ? $data : []);
                        } catch (\Throwable $e) {
                            return null;
                        }
                    },
                    fn () => null,
                ),
            'forecast' => $client->getAsync($url . '/forecast', ['query' => $query])
                ->then(
                    function ($response) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
                            return $this->normalizeForecast(is_array($data) ? $data : []);
                        } catch (\Throwable $e) {
                            return null;
                        }
                    },
                    fn () => null,
                ),
            'indices' => \GuzzleHttp\Promise\Create::promiseFor([]),
            'aqi' => \GuzzleHttp\Promise\Create::promiseFor(null),
            'alerts' => \GuzzleHttp\Promise\Create::promiseFor([]),
            'minutely' => \GuzzleHttp\Promise\Create::promiseFor([]),
        ];

        return Utils::unwrap($promises);
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
            $lang = $this->lang === 'zh' ? 'zh_cn' : ($this->lang ?: 'zh_cn');
            $response = $this->getHttpClient()->get($url, [
                'query' => [
                    'q' => $city,
                    'appid' => $this->key,
                    'units' => 'metric',
                    'lang' => $lang,
                ],
            ]);

            $decoded = json_decode($response->getBody()->getContents(), true);

            return is_array($decoded) ? $decoded : [];
        } catch (TransferException $e) {
            if ($e instanceof \GuzzleHttp\Exception\ClientException && $e->getResponse()) {
                $status = $e->getResponse()->getStatusCode();
                $message = 'OpenWeatherMap API error: HTTP ' . $status;
                if ($status === 401) {
                    throw new InvalidKeyException($message, $status, $e);
                }
                if ($status === 429) {
                    throw new RateLimitException($message, $status, $e);
                }
            }
            throw new HttpException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function normalizeCurrent(array $data): CurrentWeather
    {
        $weather = $data['weather'][0] ?? [];
        $main = $data['main'] ?? [];
        $wind = $data['wind'] ?? [];

        return new CurrentWeather(
            city: $data['name'] ?? '',
            adcode: (string) ($data['id'] ?? ''),
            temperature: (float) ($main['temp'] ?? 0),
            weather: $weather['description'] ?? '',
            windDirection: $this->degToDirection((int) ($wind['deg'] ?? 0)),
            windPower: $this->speedToPower((float) ($wind['speed'] ?? 0)),
            humidity: isset($main['humidity']) ? (int) $main['humidity'] : null,
            pressure: isset($main['pressure']) ? (int) $main['pressure'] : null,
            visibility: isset($data['visibility']) ? (string) ((int) $data['visibility'] / 1000) : null,
            feelsLike: isset($main['feels_like']) ? (float) $main['feels_like'] : null,
            updateTime: isset($data['dt']) ? date('Y-m-d H:i:s', (int) $data['dt']) : '',
            icon: $weather['icon'] ?? '',
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function normalizeForecast(array $data): Forecast
    {
        $cityInfo = $data['city'] ?? [];
        $list = $data['list'] ?? [];
        $casts = [];

        $grouped = [];
        foreach ($list as $item) {
            $date = substr($item['dt_txt'] ?? '', 0, 10);
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $item;
        }

        foreach ($grouped as $date => $items) {
            $dayItem = $items[0] ?? [];
            $nightItem = $items[count($items) - 1] ?? [];
            $dayWeather = $dayItem['weather'][0] ?? [];
            $nightWeather = $nightItem['weather'][0] ?? [];

            $casts[] = new ForecastDay(
                date: $date,
                week: $this->dateToWeek($date),
                dayWeather: $dayWeather['description'] ?? '',
                nightWeather: $nightWeather['description'] ?? '',
                dayTemp: (float) ($dayItem['main']['temp_max'] ?? 0),
                nightTemp: (float) ($nightItem['main']['temp_min'] ?? 0),
                dayWind: $this->degToDirection((int) ($dayItem['wind']['deg'] ?? 0)),
                nightWind: $this->degToDirection((int) ($nightItem['wind']['deg'] ?? 0)),
                dayPower: $this->speedToPower((float) ($dayItem['wind']['speed'] ?? 0)),
                nightPower: $this->speedToPower((float) ($nightItem['wind']['speed'] ?? 0)),
                iconDay: $dayWeather['icon'] ?? '',
                iconNight: $nightWeather['icon'] ?? '',
            );
        }

        return new Forecast(
            city: $cityInfo['name'] ?? '',
            adcode: (string) ($cityInfo['id'] ?? ''),
            casts: $casts,
        );
    }

    protected function degToDirection(int $deg): string
    {
        $directions = ['北', '东北', '东', '东南', '南', '西南', '西', '西北'];
        $index = (int) round($deg / 45) % 8;

        return $directions[$index];
    }

    protected function speedToPower(float $speed): string
    {
        if ($speed < 2) {
            return '≤3';
        }
        if ($speed < 6) {
            return '3-4';
        }
        if ($speed < 11) {
            return '4-5';
        }

        return '≥5';
    }

    protected function dateToWeek(string $date): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        $week = date('N', $timestamp);
        $map = ['1' => '周一', '2' => '周二', '3' => '周三', '4' => '周四', '5' => '周五', '6' => '周六', '7' => '周日'];

        return $map[$week];
    }
}

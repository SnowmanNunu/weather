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
use SnowmanNunu\Weather\DTO\Precipitation;
use SnowmanNunu\Weather\DTO\WeatherAlert;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Exceptions\InvalidKeyException;
use SnowmanNunu\Weather\Exceptions\RateLimitException;

class QWeatherProvider implements Provider
{
    protected string $key;

    /** @var array<string, mixed> */
    protected array $guzzleOptions = [];

    protected ?Client $httpClient = null;

    protected string $baseUri;

    protected ?string $lang = null;

    /** @var array<string, string> */
    protected array $locationCache = [];

    public function __construct(string $key)
    {
        $this->key = $key;
        $this->lang = getenv('WEATHER_LANG') ?: null;
        $host = getenv('QWEATHER_API_HOST') ?: 'https://devapi.qweather.com';
        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'https://' . $host;
        }
        $this->baseUri = rtrim($host, '/') . '/v7';
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
                'headers' => ['Connection' => 'close'],
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
        return 'qweather';
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
                week: $this->mapWeek($day['week'] ?? $this->weekFromDate($day['fxDate'] ?? '')),
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
        $location = $this->resolveCity($city);

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => $this->withLang([
                    'key' => $this->key,
                    'location' => $location,
                    'type' => $types,
                ]),
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
        $location = $this->resolveCity($city);

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => $this->withLang([
                    'key' => $this->key,
                    'location' => $location,
                ]),
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
        $location = $this->resolveCity($city);

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => $this->withLang([
                    'key' => $this->key,
                    'location' => $location,
                ]),
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

    public function getMinutelyPrecipitation(string $city): array
    {
        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $url = $this->baseUri . '/minutely/5m';
        $location = $this->resolveCity($city);

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => $this->withLang([
                    'key' => $this->key,
                    'location' => $location,
                ]),
            ])->getBody()->getContents();

            $data = json_decode($response, true);

            if (!is_array($data) || ($data['code'] ?? '') !== '200') {
                return [];
            }

            $items = [];
            foreach ($data['minutely'] ?? [] as $item) {
                $items[] = new Precipitation(
                    time: $item['fxTime'] ?? '',
                    type: $item['type'] ?? '',
                    precipitation: isset($item['precip']) ? (float) $item['precip'] : 0.0,
                );
            }

            return $items;
        } catch (TransferException $e) {
            return [];
        }
    }

    public function fetchAll(string $city): array
    {
        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $location = $this->resolveCity($city);
        $baseQuery = $this->withLang(['key' => $this->key, 'location' => $location]);
        $client = $this->getHttpClient();

        $promises = [
            'current' => $client->getAsync($this->baseUri . '/weather/now', ['query' => $baseQuery])
                ->then(
                    function ($response) use ($city) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
                            if (($data['code'] ?? '') !== '200' || empty($data['now'])) {
                                return null;
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
                        } catch (\Throwable $e) {
                            return null;
                        }
                    },
                    fn () => null,
                ),
            'forecast' => $client->getAsync($this->baseUri . '/weather/7d', ['query' => $baseQuery])
                ->then(
                    function ($response) use ($city) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
                            if (($data['code'] ?? '') !== '200' || empty($data['daily'])) {
                                return null;
                            }
                            $casts = [];
                            foreach ($data['daily'] as $day) {
                                $casts[] = new ForecastDay(
                                    date: $day['fxDate'] ?? '',
                                    week: $this->mapWeek($day['week'] ?? $this->weekFromDate($day['fxDate'] ?? '')),
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
                        } catch (\Throwable $e) {
                            return null;
                        }
                    },
                    fn () => null,
                ),
            'indices' => $client->getAsync(
                $this->baseUri . '/indices/1d',
                ['query' => array_merge($baseQuery, ['type' => '1,2,3,5,6,8,9'])]
            )
                ->then(
                    function ($response) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
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
                        } catch (\Throwable $e) {
                            return [];
                        }
                    },
                    fn () => [],
                ),
            'aqi' => $client->getAsync($this->baseUri . '/air/now', ['query' => $baseQuery])
                ->then(
                    function ($response) use ($city) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
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
                        } catch (\Throwable $e) {
                            return null;
                        }
                    },
                    fn () => null,
                ),
            'alerts' => $client->getAsync($this->baseUri . '/warning/now', ['query' => $baseQuery])
                ->then(
                    function ($response) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
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
                        } catch (\Throwable $e) {
                            return [];
                        }
                    },
                    fn () => [],
                ),
            'minutely' => $client->getAsync($this->baseUri . '/minutely/5m', ['query' => $baseQuery])
                ->then(
                    function ($response) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
                            if (!is_array($data) || ($data['code'] ?? '') !== '200') {
                                return [];
                            }
                            $items = [];
                            foreach ($data['minutely'] ?? [] as $item) {
                                $items[] = new Precipitation(
                                    time: $item['fxTime'] ?? '',
                                    type: $item['type'] ?? '',
                                    precipitation: isset($item['precip']) ? (float) $item['precip'] : 0.0,
                                );
                            }

                            return $items;
                        } catch (\Throwable $e) {
                            return [];
                        }
                    },
                    fn () => [],
                ),
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
        $location = $this->resolveCity($city);

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => array_filter([
                    'key' => $this->key,
                    'location' => $location,
                    'lang' => $this->lang,
                ]),
            ])->getBody()->getContents();

            $decoded = json_decode($response, true);
            $data = is_array($decoded) ? $decoded : [];
            $this->throwOnErrorCode($data);

            return $data;
        } catch (TransferException $e) {
            throw new HttpException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @throws InvalidKeyException
     * @throws RateLimitException
     * @throws HttpException
     */
    protected function throwOnErrorCode(array $data): void
    {
        $code = $data['code'] ?? '';
        if ($code === '200' || $code === '') {
            return;
        }

        $message = 'QWeather API returned error code: ' . $code;

        if ($code === '401') {
            throw new InvalidKeyException($message);
        }

        if ($code === '402') {
            throw new RateLimitException($message);
        }

        throw new HttpException($message);
    }

    protected function withLang(array $query): array
    {
        if ($this->lang !== null && $this->lang !== '') {
            $query['lang'] = $this->lang;
        }
        return $query;
    }

    protected function resolveCity(string $city): string
    {
        if (preg_match('/^\d+$/', $city)) {
            return $city;
        }

        if (isset($this->locationCache[$city])) {
            return $this->locationCache[$city];
        }

        try {
            $host = getenv('QWEATHER_API_HOST') ?: 'https://devapi.qweather.com';
            if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
                $host = 'https://' . $host;
            }
            $url = rtrim($host, '/') . '/geo/v2/city/lookup';

            $response = $this->getHttpClient()->get($url, [
                'query' => $this->withLang([
                    'key' => $this->key,
                    'location' => $city,
                ]),
            ])->getBody()->getContents();

            $data = json_decode($response, true);
            if (is_array($data) && ($data['code'] ?? '') === '200' && !empty($data['location'][0]['id'])) {
                $this->locationCache[$city] = $data['location'][0]['id'];
                return $this->locationCache[$city];
            }
        } catch (\Throwable $e) {
            // fallback to original city name
        }

        return $city;
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

    protected function weekFromDate(string $date): string
    {
        if (empty($date)) {
            return '';
        }
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        if ($this->lang === 'en') {
            return date('D', $timestamp);
        }
        $map = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
        return $map[(int) date('w', $timestamp)];
    }
}

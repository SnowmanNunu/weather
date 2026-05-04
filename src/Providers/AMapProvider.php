<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
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

class AMapProvider implements Provider
{
    protected string $key;

    /** @var array<string, mixed> */
    protected array $guzzleOptions = [];

    protected ?Client $httpClient = null;

    protected string $lang = 'zh';

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function setLang(string $lang): void
    {
        $this->lang = $lang;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function getHttpClient(): Client
    {
        if (!$this->httpClient instanceof Client) {
            $this->httpClient = new Client($this->guzzleOptions);
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
        return 'amap';
    }

    public function getLiveWeather(string $city): CurrentWeather
    {
        $data = $this->request($city, 'base');

        if (($data['status'] ?? '0') !== '1' || empty($data['lives'][0])) {
            throw new HttpException('AMap API returned invalid status or empty data.');
        }

        $live = $data['lives'][0];

        return new CurrentWeather(
            city: $live['city'] ?? $city,
            adcode: $live['adcode'] ?? '',
            temperature: (float) ($live['temperature'] ?? 0),
            weather: $live['weather'] ?? '',
            windDirection: $live['winddirection'] ?? '',
            windPower: $live['windpower'] ?? '',
            humidity: isset($live['humidity']) ? (int) $live['humidity'] : null,
            updateTime: $live['reporttime'] ?? '',
        );
    }

    public function getForecastsWeather(string $city): Forecast
    {
        $data = $this->request($city, 'all');

        if (($data['status'] ?? '0') !== '1' || empty($data['forecasts'][0])) {
            throw new HttpException('AMap API returned invalid status or empty forecast data.');
        }

        $forecast = $data['forecasts'][0];
        $casts = [];

        foreach ($forecast['casts'] ?? [] as $cast) {
            $casts[] = new ForecastDay(
                date: $cast['date'] ?? '',
                week: $this->mapWeek($cast['week'] ?? ''),
                dayWeather: $cast['dayweather'] ?? '',
                nightWeather: $cast['nightweather'] ?? '',
                dayTemp: (float) ($cast['daytemp'] ?? 0),
                nightTemp: (float) ($cast['nighttemp'] ?? 0),
                dayWind: $cast['daywind'] ?? '',
                nightWind: $cast['nightwind'] ?? '',
                dayPower: $cast['daypower'] ?? '',
                nightPower: $cast['nightpower'] ?? '',
            );
        }

        return new Forecast(
            city: $forecast['city'] ?? $city,
            adcode: $forecast['adcode'] ?? '',
            casts: $casts,
        );
    }

    /**
     * @return array<string, mixed>
     * @throws HttpException
     * @throws InvalidArgumentException
     */
    protected function request(string $city, string $type): array
    {
        $url = 'https://restapi.amap.com/v3/weather/weatherInfo';

        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $type = strtolower($type);

        if (!in_array($type, ['base', 'all'], true)) {
            throw new InvalidArgumentException('Invalid type value(base/all): ' . $type);
        }

        $query = array_filter([
            'key' => $this->key,
            'city' => $city,
            'extensions' => $type,
        ]);

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => $query,
            ])->getBody()->getContents();

            $decoded = json_decode($response, true);

            return is_array($decoded) ? $decoded : [];
        } catch (TransferException $e) {
            throw new HttpException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function getLifeIndices(string $city): array
    {
        $url = 'https://restapi.amap.com/v3/weather/lifestyle';

        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => [
                    'key' => $this->key,
                    'city' => $city,
                ],
            ])->getBody()->getContents();

            $data = json_decode($response, true);

            if (!is_array($data) || ($data['status'] ?? '0') !== '1') {
                return [];
            }

            $indices = [];
            foreach ($data['lifestyles'] ?? [] as $item) {
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
        $url = 'https://restapi.amap.com/v3/air/quality';

        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => [
                    'key' => $this->key,
                    'city' => $city,
                ],
            ])->getBody()->getContents();

            $data = json_decode($response, true);

            if (!is_array($data) || ($data['status'] ?? '0') !== '1') {
                return null;
            }

            $aqi = $data['aqi'] ?? [];

            return new AirQuality(
                city: $data['city']['name'] ?? $city,
                aqi: isset($aqi['aqi']) ? (int) $aqi['aqi'] : null,
                level: $aqi['level'] ?? null,
                category: $aqi['category'] ?? null,
                primaryPollutant: $aqi['primary'] ?? null,
                pm25: isset($aqi['pm25']) ? (float) $aqi['pm25'] : null,
                pm10: isset($aqi['pm10']) ? (float) $aqi['pm10'] : null,
                no2: isset($aqi['no2']) ? (float) $aqi['no2'] : null,
                so2: isset($aqi['so2']) ? (float) $aqi['so2'] : null,
                co: isset($aqi['co']) ? (float) $aqi['co'] : null,
                o3: isset($aqi['o3']) ? (float) $aqi['o3'] : null,
                updateTime: $aqi['pub_time'] ?? null,
            );
        } catch (TransferException $e) {
            return null;
        }
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

        $client = $this->getHttpClient();
        $baseQuery = ['key' => $this->key, 'city' => $city];

        $promises = [
            'current' => $client->getAsync('https://restapi.amap.com/v3/weather/weatherInfo', ['query' => array_merge($baseQuery, ['extensions' => 'base'])])
                ->then(
                    function ($response) use ($city) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
                            if (($data['status'] ?? '0') !== '1' || empty($data['lives'][0])) {
                                return null;
                            }
                            $live = $data['lives'][0];

                            return new CurrentWeather(
                                city: $live['city'] ?? $city,
                                adcode: $live['adcode'] ?? '',
                                temperature: (float) ($live['temperature'] ?? 0),
                                weather: $live['weather'] ?? '',
                                windDirection: $live['winddirection'] ?? '',
                                windPower: $live['windpower'] ?? '',
                                humidity: isset($live['humidity']) ? (int) $live['humidity'] : null,
                                updateTime: $live['reporttime'] ?? '',
                            );
                        } catch (\Throwable $e) {
                            return null;
                        }
                    },
                    fn () => null,
                ),
            'forecast' => $client->getAsync('https://restapi.amap.com/v3/weather/weatherInfo', ['query' => array_merge($baseQuery, ['extensions' => 'all'])])
                ->then(
                    function ($response) use ($city) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
                            if (($data['status'] ?? '0') !== '1' || empty($data['forecasts'][0])) {
                                return null;
                            }
                            $forecast = $data['forecasts'][0];
                            $casts = [];
                            foreach ($forecast['casts'] ?? [] as $cast) {
                                $casts[] = new ForecastDay(
                                    date: $cast['date'] ?? '',
                                    week: $this->mapWeek($cast['week'] ?? ''),
                                    dayWeather: $cast['dayweather'] ?? '',
                                    nightWeather: $cast['nightweather'] ?? '',
                                    dayTemp: (float) ($cast['daytemp'] ?? 0),
                                    nightTemp: (float) ($cast['nighttemp'] ?? 0),
                                    dayWind: $cast['daywind'] ?? '',
                                    nightWind: $cast['nightwind'] ?? '',
                                    dayPower: $cast['daypower'] ?? '',
                                    nightPower: $cast['nightpower'] ?? '',
                                );
                            }

                            return new Forecast(
                                city: $forecast['city'] ?? $city,
                                adcode: $forecast['adcode'] ?? '',
                                casts: $casts,
                            );
                        } catch (\Throwable $e) {
                            return null;
                        }
                    },
                    fn () => null,
                ),
            'indices' => $client->getAsync('https://restapi.amap.com/v3/weather/lifestyle', ['query' => $baseQuery])
                ->then(
                    function ($response) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
                            if (!is_array($data) || ($data['status'] ?? '0') !== '1') {
                                return [];
                            }
                            $indices = [];
                            foreach ($data['lifestyles'] ?? [] as $item) {
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
            'aqi' => $client->getAsync('https://restapi.amap.com/v3/air/quality', ['query' => $baseQuery])
                ->then(
                    function ($response) use ($city) {
                        try {
                            $data = json_decode($response->getBody()->getContents(), true);
                            if (!is_array($data) || ($data['status'] ?? '0') !== '1') {
                                return null;
                            }
                            $aqi = $data['aqi'] ?? [];

                            return new AirQuality(
                                city: $data['city']['name'] ?? $city,
                                aqi: isset($aqi['aqi']) ? (int) $aqi['aqi'] : null,
                                level: $aqi['level'] ?? null,
                                category: $aqi['category'] ?? null,
                                primaryPollutant: $aqi['primary'] ?? null,
                                pm25: isset($aqi['pm25']) ? (float) $aqi['pm25'] : null,
                                pm10: isset($aqi['pm10']) ? (float) $aqi['pm10'] : null,
                                no2: isset($aqi['no2']) ? (float) $aqi['no2'] : null,
                                so2: isset($aqi['so2']) ? (float) $aqi['so2'] : null,
                                co: isset($aqi['co']) ? (float) $aqi['co'] : null,
                                o3: isset($aqi['o3']) ? (float) $aqi['o3'] : null,
                                updateTime: $aqi['pub_time'] ?? null,
                            );
                        } catch (\Throwable $e) {
                            return null;
                        }
                    },
                    fn () => null,
                ),
            'alerts' => \GuzzleHttp\Promise\Create::promiseFor([]),
            'minutely' => \GuzzleHttp\Promise\Create::promiseFor([]),
        ];

        return Utils::unwrap($promises);
    }

    protected function mapWeek(string $week): string
    {
        if ($this->lang === 'en') {
            $map = ['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '7' => 'Sun'];
            return $map[$week] ?? $week;
        }

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

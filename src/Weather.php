<?php

/*
 * This file is part of the snowmannunu/weather.
 *
 * (c) SnowmanNunu<345750542@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SnowmanNunu\Weather;

use GuzzleHttp\Client;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;

class Weather
{
    protected $key;
    protected $guzzleOptions = [];
    protected $httpClient;

    public function __construct(string $key)
    {
        $this->key = $key;
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

    public function setGuzzleOptions(array $options): void
    {
        $this->guzzleOptions = $options;
        $this->httpClient = null;
    }

    public function getLiveWeather(string $city, string $format = 'json')
    {
        return $this->getWeather($city, 'base', $format);
    }

    public function getForecastsWeather(string $city, string $format = 'json')
    {
        return $this->getWeather($city, 'all', $format);
    }

    public function getWeather(string $city, string $type = 'base', string $format = 'json')
    {
        $url = 'https://restapi.amap.com/v3/weather/weatherInfo';

        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $format = \strtolower($format);
        $type = \strtolower($type);

        if (!\in_array($format, ['xml', 'json'], true)) {
            throw new InvalidArgumentException('Invalid response format: '.$format);
        }

        if (!\in_array($type, ['base', 'all'], true)) {
            throw new InvalidArgumentException('Invalid type value(base/all): '.$type);
        }

        $query = array_filter([
            'key' => $this->key,
            'city' => $city,
            'output' => $format,
            'extensions' => $type,
        ]);

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => $query,
            ])->getBody()->getContents();

            return 'json' === $format ? \json_decode($response, true) : $response;
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }
    }
}

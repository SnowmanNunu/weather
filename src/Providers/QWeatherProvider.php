<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use SnowmanNunu\Weather\Contracts\Provider;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;

class QWeatherProvider implements Provider
{
    protected string $key;
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

    public function setGuzzleOptions(array $options): void
    {
        $this->guzzleOptions = $options;
        $this->httpClient = null;
    }

    public function getName(): string
    {
        return 'qweather';
    }

    public function getLiveWeather(string $city, string $format = 'json')
    {
        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $format = strtolower($format);
        if (!in_array($format, ['json', 'xml'], true)) {
            throw new InvalidArgumentException('Invalid response format: ' . $format);
        }

        // QWeather uses location ID; for simplicity accept city name
        // via geo lookup or pass raw location. Here we use city name as location.
        $url = $this->baseUri . '/weather/now';
        if ($format === 'xml') {
            $url = str_replace('devapi', 'devapi', $url); // no xml endpoint variant, keep json
        }

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => array_filter([
                    'key' => $this->key,
                    'location' => $city,
                ]),
            ])->getBody()->getContents();

            return json_decode($response, true);
        } catch (TransferException $e) {
            throw new HttpException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function getForecastsWeather(string $city, string $format = 'json')
    {
        if (empty($city)) {
            throw new InvalidArgumentException('City name cannot be empty.');
        }

        $format = strtolower($format);
        if (!in_array($format, ['json', 'xml'], true)) {
            throw new InvalidArgumentException('Invalid response format: ' . $format);
        }

        $url = $this->baseUri . '/weather/7d';

        try {
            $response = $this->getHttpClient()->get($url, [
                'query' => array_filter([
                    'key' => $this->key,
                    'location' => $city,
                ]),
            ])->getBody()->getContents();

            return json_decode($response, true);
        } catch (TransferException $e) {
            throw new HttpException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function getWeather(string $city, string $type = 'base', string $format = 'json')
    {
        return $type === 'all'
            ? $this->getForecastsWeather($city, $format)
            : $this->getLiveWeather($city, $format);
    }
}

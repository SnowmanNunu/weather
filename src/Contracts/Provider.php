<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Contracts;

interface Provider
{
    /**
     * Get current weather (live) for a city.
     *
     * @param string $city City name or adcode
     * @param string $format Response format: json|xml
     * @return array|string Parsed array for json, raw string for xml
     * @throws \SnowmanNunu\Weather\Exceptions\HttpException
     * @throws \SnowmanNunu\Weather\Exceptions\InvalidArgumentException
     */
    public function getLiveWeather(string $city, string $format = 'json');

    /**
     * Get weather forecast for a city.
     *
     * @param string $city City name or adcode
     * @param string $format Response format: json|xml
     * @return array|string Parsed array for json, raw string for xml
     * @throws \SnowmanNunu\Weather\Exceptions\HttpException
     * @throws \SnowmanNunu\Weather\Exceptions\InvalidArgumentException
     */
    public function getForecastsWeather(string $city, string $format = 'json');

    /**
     * Get weather with full control.
     *
     * @param string $city City name or adcode
     * @param string $type base (live) | all (forecast)
     * @param string $format json | xml
     * @return array|string
     * @throws \SnowmanNunu\Weather\Exceptions\HttpException
     * @throws \SnowmanNunu\Weather\Exceptions\InvalidArgumentException
     */
    public function getWeather(string $city, string $type = 'base', string $format = 'json');

    /**
     * Provider name identifier.
     */
    public function getName(): string;
}

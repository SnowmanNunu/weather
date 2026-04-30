<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Contracts;

use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;

interface Provider
{
    /**
     * Get current weather (live) for a city.
     *
     * @param string $city City name or adcode
     * @return CurrentWeather Normalized current weather DTO
     * @throws \SnowmanNunu\Weather\Exceptions\HttpException
     * @throws \SnowmanNunu\Weather\Exceptions\InvalidArgumentException
     */
    public function getLiveWeather(string $city): CurrentWeather;

    /**
     * Get weather forecast for a city.
     *
     * @param string $city City name or adcode
     * @return Forecast Normalized forecast DTO
     * @throws \SnowmanNunu\Weather\Exceptions\HttpException
     * @throws \SnowmanNunu\Weather\Exceptions\InvalidArgumentException
     */
    public function getForecastsWeather(string $city): Forecast;

    /**
     * Provider name identifier.
     */
    public function getName(): string;
}

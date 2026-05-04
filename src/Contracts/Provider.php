<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\Contracts;

use SnowmanNunu\Weather\DTO\AirQuality;
use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;
use SnowmanNunu\Weather\DTO\LifeIndex;
use SnowmanNunu\Weather\DTO\Precipitation;
use SnowmanNunu\Weather\DTO\WeatherAlert;

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
     * Get lifestyle indices for a city.
     *
     * @param string $city City name or adcode
     * @return LifeIndex[]
     * @throws \SnowmanNunu\Weather\Exceptions\HttpException
     * @throws \SnowmanNunu\Weather\Exceptions\InvalidArgumentException
     */
    public function getLifeIndices(string $city): array;

    /**
     * Get air quality for a city.
     *
     * @param string $city City name or adcode
     * @return AirQuality|null
     * @throws \SnowmanNunu\Weather\Exceptions\HttpException
     * @throws \SnowmanNunu\Weather\Exceptions\InvalidArgumentException
     */
    public function getAirQuality(string $city): ?AirQuality;

    /**
     * Get weather alerts for a city.
     *
     * @param string $city City name or adcode
     * @return WeatherAlert[]
     * @throws \SnowmanNunu\Weather\Exceptions\HttpException
     * @throws \SnowmanNunu\Weather\Exceptions\InvalidArgumentException
     */
    public function getAlerts(string $city): array;

    /**
     * Get minutely precipitation forecast for a city.
     *
     * @param string $city City name or adcode
     * @return Precipitation[]
     * @throws \SnowmanNunu\Weather\Exceptions\HttpException
     * @throws \SnowmanNunu\Weather\Exceptions\InvalidArgumentException
     */
    public function getMinutelyPrecipitation(string $city): array;

    /**
     * Provider name identifier.
     */
    public function getName(): string;

    /**
     * Set language for API requests.
     */
    public function setLang(string $lang): void;

    /**
     * Get current language.
     */
    public function getLang(): string;
}

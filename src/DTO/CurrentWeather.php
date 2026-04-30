<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\DTO;

class CurrentWeather
{
    public function __construct(
        public string $city,
        public string $adcode,
        public float $temperature,
        public string $weather,
        public string $windDirection,
        public string $windPower,
        public ?int $humidity = null,
        public ?int $pressure = null,
        public ?string $visibility = null,
        public ?float $feelsLike = null,
        public string $updateTime = '',
        public string $icon = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'city' => $this->city,
            'adcode' => $this->adcode,
            'temperature' => $this->temperature,
            'weather' => $this->weather,
            'wind_direction' => $this->windDirection,
            'wind_power' => $this->windPower,
            'humidity' => $this->humidity,
            'pressure' => $this->pressure,
            'visibility' => $this->visibility,
            'feels_like' => $this->feelsLike,
            'update_time' => $this->updateTime,
            'icon' => $this->icon,
        ];
    }
}

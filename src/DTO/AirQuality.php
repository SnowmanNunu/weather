<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\DTO;

class AirQuality
{
    public function __construct(
        public string $city,
        public ?int $aqi = null,
        public ?string $level = null,
        public ?string $category = null,
        public ?string $primaryPollutant = null,
        public ?float $pm25 = null,
        public ?float $pm10 = null,
        public ?float $no2 = null,
        public ?float $so2 = null,
        public ?float $co = null,
        public ?float $o3 = null,
        public ?string $updateTime = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'city' => $this->city,
            'aqi' => $this->aqi,
            'level' => $this->level,
            'category' => $this->category,
            'primary_pollutant' => $this->primaryPollutant,
            'pm25' => $this->pm25,
            'pm10' => $this->pm10,
            'no2' => $this->no2,
            'so2' => $this->so2,
            'co' => $this->co,
            'o3' => $this->o3,
            'update_time' => $this->updateTime,
        ];
    }
}

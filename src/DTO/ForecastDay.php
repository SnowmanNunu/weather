<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\DTO;

class ForecastDay
{
    public function __construct(
        public string $date,
        public string $week,
        public string $dayWeather,
        public string $nightWeather,
        public float $dayTemp,
        public float $nightTemp,
        public string $dayWind,
        public string $nightWind,
        public string $dayPower,
        public string $nightPower,
        public string $iconDay = '',
        public string $iconNight = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'week' => $this->week,
            'day_weather' => $this->dayWeather,
            'night_weather' => $this->nightWeather,
            'day_temp' => $this->dayTemp,
            'night_temp' => $this->nightTemp,
            'day_wind' => $this->dayWind,
            'night_wind' => $this->nightWind,
            'day_power' => $this->dayPower,
            'night_power' => $this->nightPower,
            'icon_day' => $this->iconDay,
            'icon_night' => $this->iconNight,
        ];
    }
}

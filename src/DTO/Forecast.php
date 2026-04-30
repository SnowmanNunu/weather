<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\DTO;

class Forecast
{
    /**
     * @param ForecastDay[] $casts
     */
    public function __construct(
        public string $city,
        public string $adcode,
        public array $casts,
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
            'casts' => array_map(static fn (ForecastDay $day) => $day->toArray(), $this->casts),
        ];
    }
}

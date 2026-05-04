<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\DTO;

class Precipitation
{
    public function __construct(
        public string $time,
        public string $type,
        public float $precipitation,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'time' => $this->time,
            'type' => $this->type,
            'precipitation' => $this->precipitation,
        ];
    }
}

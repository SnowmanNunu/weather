<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\DTO;

class LifeIndex
{
    public function __construct(
        public string $name,
        public string $level,
        public string $category,
        public string $advice,
        public string $type = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'level' => $this->level,
            'category' => $this->category,
            'advice' => $this->advice,
            'type' => $this->type,
        ];
    }
}

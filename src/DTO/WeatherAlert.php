<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather\DTO;

class WeatherAlert
{
    public function __construct(
        public string $title,
        public string $type,
        public string $level,
        public string $content,
        public string $pubTime,
        public string $status = 'active',
        public string $sender = '',
        public string $startTime = '',
        public string $endTime = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'type' => $this->type,
            'level' => $this->level,
            'content' => $this->content,
            'pub_time' => $this->pubTime,
            'status' => $this->status,
            'sender' => $this->sender,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }
}

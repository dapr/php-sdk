<?php

namespace Dapr\Actors;

use Dapr\Formats;

class Timer
{
    public function __construct(
        public string $name,
        public \DateInterval $due_time,
        public \DateInterval $period,
        public string $callback,
        public ?array $data = null
    ) {
    }

    public function to_array(): array
    {
        return [
            'dueTime'  => Formats::normalize_interval($this->due_time),
            'period'   => Formats::normalize_interval($this->period),
            'callback' => $this->callback,
            'data'     => $this->data,
        ];
    }
}

<?php

namespace Dapr\Actors;

use Dapr\Formats;
use DateInterval;
use JetBrains\PhpStorm\ArrayShape;

/**
 * Class Timer
 *
 * Abstracts actor timers.
 *
 * @package Dapr\Actors
 */
class Timer
{
    public function __construct(
        public string $name,
        public DateInterval $due_time,
        public DateInterval $period,
        public string $callback,
        public mixed $data = null
    ) {
    }

    #[ArrayShape(['dueTime' => "string", 'period' => "string", 'callback' => "string", 'data' => "array|null"])]
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

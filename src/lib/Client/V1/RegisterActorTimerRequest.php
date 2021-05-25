<?php

namespace Dapr\Client\V1;

/**
 * Class RegisterActorTimerRequest
 * @package Dapr\Client\V1
 */
class RegisterActorTimerRequest
{
    public function __construct(
        public string $actor_type,
        public string $actor_id,
        public string $name,
        public string $due_time,
        public string $period,
        public string $callback,
        public string $data
    ) {
    }
}

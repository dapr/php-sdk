<?php

namespace Dapr\Client\V1;

/**
 * Class RegisterActorReminderRequest
 * @package Dapr\Client\V1
 */
class RegisterActorReminderRequest
{
    public function __construct(
        public string $actor_type,
        public string $actor_id,
        public string $name,
        public string $due_time,
        public string $period,
        public string $data
    ) {
    }
}

<?php

namespace Dapr\Client\V1;

/**
 * Class UnregisterActorReminderRequest
 * @package Dapr\Client\V1
 */
class UnregisterActorReminderRequest
{
    public function __construct(public string $actor_type, public string $actor_id, public string $name)
    {
    }
}

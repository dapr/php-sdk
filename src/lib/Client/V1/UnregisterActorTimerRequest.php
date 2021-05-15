<?php

namespace Dapr\Client\V1;

/**
 * Class UnregisterActorTimerRequest
 * @package Dapr\Client\V1
 */
class UnregisterActorTimerRequest
{
    public function __construct(public string $actor_type, public string $actor_id, public string $name)
    {
    }
}

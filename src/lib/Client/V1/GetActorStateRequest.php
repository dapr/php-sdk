<?php

namespace Dapr\Client\V1;

/**
 * Class GetActorStateRequest
 * @package Dapr\Client\V1
 */
class GetActorStateRequest
{
    public function __construct(public string $actor_type, public string $actor_id, public string $key)
    {
    }
}

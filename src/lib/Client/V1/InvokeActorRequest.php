<?php

namespace Dapr\Client\V1;

/**
 * Class InvokeActorRequest
 * @package Dapr\Client\V1
 */
class InvokeActorRequest
{
    public function __construct(
        public string $actor_type,
        public string $actor_id,
        public string $method,
        public string $data
    ) {
    }
}

<?php

namespace Dapr\Client\V1;

/**
 * Class ExecuteActorStateTransactionRequest
 * @package Dapr\Client\V1
 */
class ExecuteActorStateTransactionRequest
{
    public function __construct(public string $actor_type, public string $actor_id, public array $operations)
    {
    }
}

<?php

namespace Dapr\Client\V1;

/**
 * Class TransactionalActorStateOperation
 * @package Dapr\Client\V1
 */
class TransactionalActorStateOperation
{
    public function __construct(public string $operation_type, public string $key, public mixed $data)
    {
    }
}

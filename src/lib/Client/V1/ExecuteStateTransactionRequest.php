<?php

namespace Dapr\Client\V1;

/**
 * Class ExecuteStateTransactionRequest
 * @package Dapr\Client\V1
 */
class ExecuteStateTransactionRequest
{
    public function __construct(public string $store_name, public array $operations, public array $metadata)
    {
    }
}

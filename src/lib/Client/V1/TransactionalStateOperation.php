<?php

namespace Dapr\Client\V1;

/**
 * Class TransactionalStateOperation
 * @package Dapr\Client\V1
 */
class TransactionalStateOperation {
    public function __construct(public string $operation_type, public StateItem $request) {}
}

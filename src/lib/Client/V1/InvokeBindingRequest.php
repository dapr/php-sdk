<?php

namespace Dapr\Client\V1;

/**
 * Class InvokeBindingRequest
 * @package Dapr\Client\V1
 */
class InvokeBindingRequest
{
    public function __construct(
        public string $name,
        public string $data,
        public string $operation,
        public array $metadata = []
    ) {
    }
}

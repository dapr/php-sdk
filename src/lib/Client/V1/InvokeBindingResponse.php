<?php

namespace Dapr\Client\V1;

/**
 * Class InvokeBindingResponse
 * @package Dapr\Client\V1
 */
class InvokeBindingResponse
{
    public function __construct(public string $data, public array $metadata = [])
    {
    }
}

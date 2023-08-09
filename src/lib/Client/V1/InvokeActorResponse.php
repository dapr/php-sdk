<?php

namespace Dapr\Client\V1;

/**
 * Class InvokeActorResponse
 * @package Dapr\Client\V1
 */
class InvokeActorResponse
{
    public function __construct(public string $data)
    {
    }
}

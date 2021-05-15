<?php

namespace Dapr\Client\V1;

/**
 * Class GetActorStateResponse
 * @package Dapr\Client\V1
 */
class GetActorStateResponse
{
    public function __construct(public string $data)
    {
    }
}

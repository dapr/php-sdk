<?php

namespace Dapr\Client\V1;

/**
 * Class SaveStateRequest
 * @package Dapr\Client\V1
 */
class SaveStateRequest
{
    public function __construct(public string $store_name, public array $states)
    {
    }
}

<?php

namespace Dapr\Client\V1;

/**
 * Class GetSecretRequest
 * @package Dapr\Client\V1
 */
class GetSecretRequest
{
    public function __construct(public string $store_name, public string $key, public array $metadata = [])
    {
    }
}

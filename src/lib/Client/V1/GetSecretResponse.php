<?php

namespace Dapr\Client\V1;

/**
 * Class GetSecretResponse
 * @package Dapr\Client\V1
 */
class GetSecretResponse
{
    public function __construct(public array $data)
    {
    }
}

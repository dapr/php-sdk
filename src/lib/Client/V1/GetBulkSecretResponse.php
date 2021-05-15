<?php

namespace Dapr\Client\V1;

/**
 * Class GetBulkSecretResponse
 * @package Dapr\Client\V1
 */
class GetBulkSecretResponse
{
    public function __construct(public array $data)
    {
    }
}

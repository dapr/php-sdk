<?php

namespace Dapr\Client\V1;

/**
 * Class GetBulkSecretRequest
 * @package Dapr\Client\V1
 */
class GetBulkSecretRequest
{
    public function __construct(public string $store_name, public array $metadata = [])
    {
    }
}

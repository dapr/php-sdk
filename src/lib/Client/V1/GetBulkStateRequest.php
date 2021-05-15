<?php

namespace Dapr\Client\V1;

/**
 * Class GetBulkStateRequest
 * @package Dapr\Client\V1
 */
class GetBulkStateRequest
{
    public function __construct(
        public string $store_name,
        public array $keys,
        public int $parallelism,
        public array $metadata
    ) {
    }
}

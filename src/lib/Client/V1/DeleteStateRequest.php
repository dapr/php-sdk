<?php

namespace Dapr\Client\V1;

use Dapr\consistency\Consistency;

/**
 * Class DeleteStateRequest
 * @package Dapr\Client\V1
 */
class DeleteStateRequest
{
    public function __construct(
        public string $store_name,
        public string $key,
        public string $etag,
        public Consistency $options,
        public array $metadata = []
    ) {
    }
}

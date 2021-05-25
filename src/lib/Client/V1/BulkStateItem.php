<?php

namespace Dapr\Client\V1;

/**
 * Class BulkStateItem
 * @package Dapr\Client\V1
 */
class BulkStateItem
{
    public function __construct(
        public string $key,
        public string $data,
        public string $etag,
        public string $error,
        public array $metadata
    ) {
    }
}

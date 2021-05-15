<?php

namespace Dapr\Client\V1;

/**
 * Class StateItem
 * @package Dapr\Client\V1
 */
class StateItem
{
    public function __construct(
        public string $key,
        public string $value,
        public string $etag,
        public array $metadata = [],
        public array $options = []
    ) {
    }
}

<?php

namespace Dapr;

/**
 * A parsed DAPR API response with metadata.
 * @package Dapr
 */
class DaprResponse
{
    public function __construct(
        public int $code = 0,
        public mixed $data = [],
        public string|null $etag = null,
        public array $headers = []
    ) {
    }
}

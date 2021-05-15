<?php

namespace Dapr\Client\V1;

/**
 * Class HttpExtension
 * @package Dapr\Client\V1
 */
class HttpExtension {
    public function __construct(public string $verb, public string|null $query_string) {}
}

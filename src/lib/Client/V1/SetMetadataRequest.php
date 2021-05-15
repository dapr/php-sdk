<?php

namespace Dapr\Client\V1;

/**
 * Class SetMetadataRequest
 * @package Dapr\Client\V1
 */
class SetMetadataRequest {
    public function __construct(public string $key, public string $value) {}
}

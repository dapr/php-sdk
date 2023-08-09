<?php

namespace Dapr\Client\V1;

use Dapr\consistency\Consistency;

/**
 * Class GetStateRequest
 * @package Dapr\Client\V1
 */
class GetStateRequest {
    public function __construct(public string $store_name, public string $key, public Consistency $consistency) {}
}

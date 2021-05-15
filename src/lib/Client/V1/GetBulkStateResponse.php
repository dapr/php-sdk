<?php

namespace Dapr\Client\V1;

/**
 * Class GetBulkStateResponse
 * @package Dapr\Client\V1
 */
class GetBulkStateResponse {
    public function __construct(public array $items) {}
}

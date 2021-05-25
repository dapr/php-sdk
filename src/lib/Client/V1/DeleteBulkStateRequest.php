<?php

namespace Dapr\Client\V1;

/**
 * Class DeleteBulkStateRequest
 * @package Dapr\Client\V1
 */
class DeleteBulkStateRequest {
    public function __construct(public string $store_name, public array $states) {}
}

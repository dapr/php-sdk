<?php

namespace Dapr\Client\V1;

/**
 * Class InvokeServiceRequest
 * @package Dapr\Client\V1
 */
class InvokeServiceRequest {
    public function __construct(public string $id, public $message) {}
}

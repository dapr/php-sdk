<?php

namespace Dapr\Client\V1;

/**
 * Class InvokeResponse
 * @package Dapr\Client\V1
 */
class InvokeResponse {
    public function __construct(public mixed $data, public string $content_type) {}
}

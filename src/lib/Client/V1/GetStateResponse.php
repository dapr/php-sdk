<?php

namespace Dapr\Client\V1;

/**
 * Class GetStateResponse
 * @package Dapr\Client\V1
 */
class GetStateResponse
{
    public function __construct(public string $data, public string $etag, public array $metadata)
    {
    }
}

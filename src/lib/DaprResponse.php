<?php

namespace Dapr;

/**
 * A parsed DAPR API response with metadata.
 * @package Dapr
 */
class DaprResponse
{
    /**
     * @var int
     */
    public int $code;

    /**
     * @var array
     */
    public array $data;

    /**
     * @var string
     */
    public string $etag;
}

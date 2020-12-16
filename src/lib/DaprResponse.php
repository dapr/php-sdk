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
    public $code;

    /**
     * @var array
     */
    public $data;

    /**
     * @var string
     */
    public $etag;
}

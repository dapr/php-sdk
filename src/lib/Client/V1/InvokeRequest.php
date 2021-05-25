<?php

namespace Dapr\Client\V1;

/**
 * Class InvokeRequest
 * @package Dapr\Client\V1
 */
class InvokeRequest
{
    /**
     * InvokeRequest constructor.
     *
     * @param string $method method is a method name which will be invoked by caller.
     * @param mixed $data This is passed unchanged to the underlying client
     * @param string|null $content_type The content type to call the invocation with (HTTP Compatability)
     * @param HttpExtension|null $extension (Http specific data)
     */
    public function __construct(
        public string $method,
        public mixed $data,
        public string|null $content_type = null,
        public HttpExtension|null $extension = null
    ) {
    }
}

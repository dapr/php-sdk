<?php

namespace Dapr\Middleware;

use Psr\Http\Message\ResponseInterface;

/**
 * Interface IResponseMiddleware
 * @package Dapr\Middleware
 */
interface IResponseMiddleware {
    /**
     * A function that intercepts a response
     *
     * @param ResponseInterface $response The response
     *
     * @return ResponseInterface The new response
     */
    public function response(ResponseInterface $response): ResponseInterface;
}

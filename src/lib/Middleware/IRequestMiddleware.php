<?php

namespace Dapr\Middleware;

use Psr\Http\Message\RequestInterface;

/**
 * Interface IRequestMiddleware
 * @package Dapr\Middleware
 */
interface IRequestMiddleware
{
    /**
     * A function that intercepts a request and returns a new request
     *
     * @param RequestInterface $request The request to modify
     *
     * @return RequestInterface The new request
     */
    public function request(RequestInterface $request): RequestInterface;
}

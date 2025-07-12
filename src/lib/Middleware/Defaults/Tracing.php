<?php

namespace Dapr\Middleware\Defaults;

use Dapr\Middleware\IRequestMiddleware;
use Dapr\Middleware\IResponseMiddleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Tracing
 * @package Dapr\Middleware\Defaults
 */
class Tracing implements IRequestMiddleware, IResponseMiddleware
{
    public string|null $trace_parent = null;
    public string|null $trace_state = null;

    #[\Override]
    public function request(RequestInterface $request): RequestInterface
    {
        if ($request->hasHeader('traceparent')) {
            $this->trace_parent = $request->getHeader('traceparent')[0];
            $this->trace_state  = $request->hasHeader('tracestate') ? $request->getHeader('tracestate')[0] : null;
        }

        return $request;
    }

    #[\Override]
    public function response(ResponseInterface $response): ResponseInterface
    {
        if ($this->trace_parent !== null) {
            $response = $response->withHeader('traceparent', $this->trace_parent);
            if ($this->trace_state !== null) {
                $response = $response->withHeader('tracestate', $this->trace_state);
            }
        }

        return $response;
    }
}

<?php

namespace Dapr\DistributedTracing;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ActiveTrace
 * @package Dapr\DistributedTracing
 */
class ActiveTrace
{
    /**
     * ActiveTrace constructor.
     *
     * @param string $trace_parent
     * @param string|null $trace_state
     */
    private function __construct(public string $trace_parent, public string|null $trace_state)
    {
    }

    /**
     * @param ActiveTrace|null $trace
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public static function decorate_response(ActiveTrace|null $trace, ResponseInterface $response)
    {
        if ($trace === null) {
            return $response;
        }
        if ($trace->trace_state) {
            return $response
                ->withHeader('traceparent', $trace->trace_parent)
                ->withHeader('tracestate', $trace->trace_state);
        }

        return $response->withHeader('traceparent', $trace->trace_parent);
    }

    /**
     * @param RequestInterface $request
     *
     * @return ActiveTrace|null
     */
    public static function get_from_request(RequestInterface $request): ActiveTrace|null
    {
        if ($request->hasHeader('traceparent')) {
            return new ActiveTrace(
                $request->getHeader('traceparent')[0],
                $request->hasHeader('tracestate') ? $request->getHeader('tracestate')[0] : null
            );
        }

        return null;
    }
}

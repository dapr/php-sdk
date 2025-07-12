<?php

namespace Dapr\Middleware\Defaults\Response;

use Dapr\Middleware\IResponseMiddleware;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ApplicationJson
 * @package Dapr\Middleware\Defaults\Response
 */
class ApplicationJson implements IResponseMiddleware
{
    #[\Override]
    public function response(ResponseInterface $response): ResponseInterface
    {
        if ($response->hasHeader('Content-Type')) {
            return $response;
        }

        return $response->withHeader('Content-Type', 'application/json');
    }
}

<?php

namespace Dapr\Middleware\Defaults;

use Dapr\Middleware\IRequestMiddleware;
use Dapr\Middleware\IResponseMiddleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ActorToken
 * @package Dapr\Middleware\Defaults
 */
class ActorToken implements IRequestMiddleware, IResponseMiddleware
{
    // this is intentionally left undefined in order to throw if the client is created before detecting a reentrant token
    public static array $token;

    public function request(RequestInterface $request): RequestInterface
    {
        if ($request->hasHeader('Dapr-Reentrancy-Id')) {
            self::$token = $request->getHeader('Dapr-Reentrancy-Id');
        } else {
            self::$token = [];
        }

        return $request;
    }

    public function response(ResponseInterface $response): ResponseInterface
    {
        if (!empty(self::$token)) {
            return $response->withHeader('Dapr-Reentrancy-Id', self::$token);
        }

        return $response;
    }
}

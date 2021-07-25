<?php

namespace Dapr\Middleware\Defaults;

use Dapr\Client\HttpTokenTrait;
use Dapr\exceptions\Http\NotFound;
use Dapr\Middleware\IRequestMiddleware;
use Psr\Http\Message\RequestInterface;

/**
 * Class TokenAuth
 * @package Dapr\Middleware\Defaults
 */
class TokenAuth implements IRequestMiddleware
{
    use HttpTokenTrait;

    public function request(RequestInterface $request): RequestInterface
    {
        $token = $this->getAppToken();
        if ($token === null) {
            return $request;
        }

        if (!$request->hasHeader('dapr-api-token')) {
            throw new NotFound();
        }

        if (!hash_equals($token, $request->getHeader('dapr-api-token')[0] ?? '')) {
            throw new NotFound();
        }

        return $request;
    }
}

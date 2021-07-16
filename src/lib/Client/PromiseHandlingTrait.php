<?php

namespace Dapr\Client;

use Dapr\exceptions\DaprException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait PromiseHandlingTrait
 * @package Dapr\Client
 */
trait PromiseHandlingTrait
{
    private function handlePromise(
        PromiseInterface $closure,
        callable|null $transformResult = null,
        callable|null $errorTransformer = null
    ): PromiseInterface {
        if (empty($transformResult)) {
            $transformResult = fn(
                ResponseInterface|DaprException $response
            ) => $response instanceof DaprException ? throw $response : $response;
        }
        if (empty($errorTransformer)) {
            $errorTransformer = fn(\Throwable $exception) => match ($exception::class) {
                ServerException::class, ClientException::class => throw new DaprException(
                    $exception->hasResponse()
                        ? $exception->getResponse()->getBody()->getContents()
                        : $exception->getMessage(),
                    $exception->getCode(),
                    $exception
                ),
                default => throw $exception
            };
        }
        return $closure->then(
            $transformResult,
            $errorTransformer
        );
    }
}

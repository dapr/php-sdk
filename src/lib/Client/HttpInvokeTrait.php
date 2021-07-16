<?php

namespace Dapr\Client;

use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use Dapr\Serialization\ISerializer;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait HttpInvokeTrait
 * @package Dapr\Client
 */
trait HttpInvokeTrait
{
    use PromiseHandlingTrait;

    public IDeserializer $deserializer;
    public ISerializer $serializer;
    private Client $httpClient;

    /**
     * @throws DaprException
     */
    public function invokeMethod(
        string $httpMethod,
        string $appId,
        string $methodName,
        mixed $data = null,
        array $metadata = []
    ): ResponseInterface {
        return $this->handlePromise(
            $this->invokeMethodAsync($httpMethod, $appId, $methodName, $data, $metadata)
        )->wait();
    }

    public function invokeMethodAsync(
        string $httpMethod,
        string $appId,
        string $methodName,
        mixed $data = null,
        array $metadata = []
    ): PromiseInterface {
        $options = [];
        if (!empty($data)) {
            $options['body'] = $this->serializer->as_json($data);
        }
        $options['headers'] = $metadata;
        $appId = rawurlencode($appId);
        $methodName = rawurlencode($methodName);
        return $this->handlePromise(
            $this->httpClient->requestAsync($httpMethod, "/v1.0/invoke/$appId/method/$methodName", $options)
        );
    }
}

<?php

namespace Dapr\Client;

use Dapr\Deserialization\IDeserializer;
use Dapr\Serialization\ISerializer;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait HttpSecretsTrait
 * @package Dapr\Client
 */
trait HttpSecretsTrait
{
    use PromiseHandlingTrait;

    public IDeserializer $deserializer;
    public ISerializer $serializer;
    private Client $httpClient;

    public function getSecret(string $storeName, string $key, array $metadata = []): array
    {
        return $this->getSecretAsync($storeName, $key, $metadata)->wait();
    }

    public function getSecretAsync(string $storeName, string $key, array $metadata = []): PromiseInterface
    {
        $storeName = rawurlencode($storeName);
        $key = rawurlencode($key);
        return $this->handlePromise(
            $this->httpClient->getAsync("/v1.0/secrets/$storeName/$key", ['query' => $metadata]),
            fn(ResponseInterface $response) => $this->deserializer->from_json(
                'array',
                $response->getBody()->getContents()
            )
        );
    }

    public function getBulkSecret(string $storeName, array $metadata = []): array
    {
        return $this->getBulkSecretAsync($storeName, $metadata)->wait();
    }

    public function getBulkSecretAsync(string $storeName, array $metadata = []): PromiseInterface
    {
        $storeName = rawurlencode($storeName);
        return $this->handlePromise(
            $this->httpClient->getAsync(
                "/v1.0/secrets/$storeName/bulk",
                [
                    'query' => $metadata
                ]
            ),
            fn(ResponseInterface $response) => $this->deserializer->from_json(
                'array',
                $response->getBody()->getContents()
            )
        );
    }
}

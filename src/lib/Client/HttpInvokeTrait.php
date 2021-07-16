<?php

namespace Dapr\Client;

use Dapr\Client\BindingRequest;
use Dapr\Client\BindingResponse;
use Dapr\Client\PromiseHandlingTrait;
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
trait HttpInvokeTrait {
    use PromiseHandlingTrait;

    public IDeserializer $deserializer;
    public ISerializer $serializer;
    private Client $httpClient;

    /**
     * @throws DaprException
     */
    public function invokeBinding(BindingRequest $bindingRequest, string $dataType = 'array'): BindingResponse
    {
        return $this->invokeBindingAsync($bindingRequest, $dataType)->wait();
    }

    public function invokeBindingAsync(BindingRequest $bindingRequest, string $dataType = 'array'): PromiseInterface
    {
        return $this->handlePromise(
            $this->httpClient->putAsync(
                '/v1.0/bindings/' . rawurlencode($bindingRequest->bindingName),
                [
                    'body' => $this->serializer->as_json(
                        [
                            'data' => $bindingRequest->data,
                            'metadata' => $bindingRequest->metadata,
                            'operation' => $bindingRequest->operation
                        ]
                    )
                ]
            ),
            fn(ResponseInterface $response) => new BindingResponse(
                $bindingRequest,
                $this->deserializer->from_json($dataType, $response->getBody()->getContents()),
                $response->getHeaders()
            )
        );
    }

    /**
     * @throws DaprException
     */
    public function publishEvent(string $pubsubName, string $topicName, mixed $data, array $metadata = []): void
    {
        $this->publishEventAsync($pubsubName, $topicName, $data, $metadata)->wait();
    }

    public function publishEventAsync(
        string $pubsubName,
        string $topicName,
        mixed $data,
        array $metadata = []
    ): PromiseInterface {
        $options = [
            'query' => $metadata,
            'body' => $this->serializer->as_json($data)
        ];
        $pubsubName = rawurlencode($pubsubName);
        $topicName = rawurlencode($topicName);
        return $this->handlePromise($this->httpClient->postAsync("/v1.0/publish/$pubsubName/$topicName", $options));
    }
}

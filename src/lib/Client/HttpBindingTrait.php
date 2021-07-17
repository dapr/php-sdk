<?php

namespace Dapr\Client;

use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use Dapr\Serialization\ISerializer;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait HttpBindingTrait
 * @package Dapr\Client
 */
trait HttpBindingTrait
{
    public ISerializer $serializer;
    public IDeserializer $deserializer;
    protected Client $client;

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
}

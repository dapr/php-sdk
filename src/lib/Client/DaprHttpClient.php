<?php

namespace Dapr\Client;

use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use Dapr\Serialization\ISerializer;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class DaprHttpClient
 * @package Dapr\Client
 */
class DaprHttpClient extends DaprClient
{
    use HttpStateTrait;
    use HttpSecretsTrait;

    private Client $httpClient;

    public function __construct(private string $baseHttpUri, IDeserializer $deserializer, ISerializer $serializer)
    {
        parent::__construct($deserializer, $serializer);
        if (str_ends_with($this->baseHttpUri, '/')) {
            $this->baseHttpUri = rtrim($this->baseHttpUri, '/');
        }
        $this->httpClient = new Client(
            [
                'base_uri' => $this->baseHttpUri,
                'allow_redirects' => false,
                'headers' => [
                    'User-Agent' => 'DaprPHPSDK/v2.0',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]
        );
    }

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
        return $this->handlePromise(
            $this->httpClient->requestAsync($httpMethod, "/v1.0/invoke/$appId/$methodName", $options)
        );
    }
}

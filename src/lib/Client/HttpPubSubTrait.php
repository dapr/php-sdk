<?php

namespace Dapr\Client;

use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use Dapr\Serialization\ISerializer;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Trait HttpPubSubTrait
 * @package Dapr\Client
 */
trait HttpPubSubTrait
{
    use PromiseHandlingTrait;

    public ISerializer $serializer;
    public IDeserializer $deserializer;
    private Client $client;

    /**
     * @throws DaprException
     */
    public function publishEvent(
        string $pubsubName,
        string $topicName,
        mixed $data,
        array $metadata = [],
        string $contentType = 'application/json'
    ): void {
        $this->publishEventAsync($pubsubName, $topicName, $data, $metadata, $contentType)->wait();
    }

    public function publishEventAsync(
        string $pubsubName,
        string $topicName,
        mixed $data,
        array $metadata = [],
        string $contentType = 'application/json'
    ): PromiseInterface {
        $options = [
            'query' => array_merge(
                ...array_map(fn($key, $value) => ["metadata.$key" => $value], array_keys($metadata), $metadata)
            ),
            'body' => $this->serializer->as_json($data),
            'header' => []
        ];
        $pubsubName = rawurlencode($pubsubName);
        $topicName = rawurlencode($topicName);
        return $this->handlePromise($this->httpClient->postAsync("/v1.0/publish/$pubsubName/$topicName", $options));
    }
}

<?php

namespace Dapr\Client;

use Dapr\Configuration\ConfigurationItem;
use Dapr\Deserialization\IDeserializer;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait HttpConfigurationTrait
 * @package Dapr\Client
 */
trait HttpConfigurationTrait
{
    use PromiseHandlingTrait;

    public IDeserializer $deserializer;
    protected Client $httpClient;

    /**
     * @return array<string, ConfigurationItem>
     */
    public function getConfiguration(string $storeName, array $keys = [], array $metadata = []): array
    {
        return $this->getConfigurationAsync($storeName, $keys, $metadata)->wait();
    }

    /**
     * @return PromiseInterface<array<string, ConfigurationItem>>
     */
    public function getConfigurationAsync(string $storeName, array $keys = [], array $metadata = []): PromiseInterface
    {
        $storeName = rawurlencode($storeName);
        $options = $this->buildConfigurationOptions($keys, $metadata);
        return $this->handlePromise(
            $this->httpClient->getAsync("/v1.0/configuration/$storeName", $options),
            fn(ResponseInterface $response) => $this->deserializer->from_array_of(
                ConfigurationItem::class,
                json_decode($response->getBody()->getContents(), true) ?? []
            )
        );
    }

    public function subscribeConfiguration(string $storeName, array $keys = [], array $metadata = []): string
    {
        return $this->subscribeConfigurationAsync($storeName, $keys, $metadata)->wait();
    }

    public function subscribeConfigurationAsync(
        string $storeName,
        array $keys = [],
        array $metadata = []
    ): PromiseInterface {
        $storeName = rawurlencode($storeName);
        $options = $this->buildConfigurationOptions($keys, $metadata);
        return $this->handlePromise(
            $this->httpClient->getAsync("/v1.0/configuration/$storeName/subscribe", $options),
            fn(ResponseInterface $response) => json_decode($response->getBody()->getContents(), true)['id']
        );
    }

    public function unsubscribeConfiguration(string $storeName, string $id): bool
    {
        return $this->unsubscribeConfigurationAsync($storeName, $id)->wait();
    }

    public function unsubscribeConfigurationAsync(string $storeName, string $id): PromiseInterface
    {
        $storeName = rawurlencode($storeName);
        $id = rawurlencode($id);
        return $this->handlePromise(
            $this->httpClient->getAsync("/v1.0/configuration/$storeName/$id/unsubscribe"),
            fn(ResponseInterface $response) => json_decode($response->getBody()->getContents(), true)['ok'] ?? false
        );
    }

    /**
     * Build the guzzle options for a configuration request. Keys are sent as repeated
     * `key=` query parameters, which Dapr expects, so the query string is built manually.
     *
     * @param array<array-key, string> $keys
     * @param array<array-key, string> $metadata
     *
     * @return array
     */
    private function buildConfigurationOptions(array $keys, array $metadata): array
    {
        $params = array_map(fn($key) => 'key=' . rawurlencode($key), $keys);
        foreach ($metadata as $key => $value) {
            $params[] = 'metadata.' . rawurlencode($key) . '=' . rawurlencode($value);
        }

        return empty($params) ? [] : ['query' => implode('&', $params)];
    }
}

<?php

namespace Dapr\Client;

use Dapr\consistency\Consistency;
use Dapr\consistency\EventualFirstWrite;
use Dapr\consistency\EventualLastWrite;
use Dapr\Deserialization\IDeserializer;
use Dapr\Serialization\ISerializer;
use Dapr\State\StateItem;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait HttpStateTrait
 * @package Dapr\Client
 */
trait HttpStateTrait
{
    use PromiseHandlingTrait;

    public IDeserializer $deserializer;
    public ISerializer $serializer;
    private Client $httpClient;

    public function getState(
        string $storeName,
        string $key,
        string $asType = 'array',
        Consistency $consistency = null,
        array $metadata = []
    ): mixed {
        return $this->getStateAsync($storeName, $key, $asType, $consistency, $metadata)->wait();
    }

    public function getStateAsync(
        string $storeName,
        string $key,
        string $asType = 'array',
        Consistency $consistency = null,
        array $metadata = []
    ): PromiseInterface {
        return $this->handlePromise(
            $this->getStateAndEtagAsync($storeName, $key, $asType, $consistency, $metadata),
            fn(array $result) => $result['value']
        );
    }

    public function getStateAndEtagAsync(
        string $storeName,
        string $key,
        string $asType = 'array',
        ?Consistency $consistency = null,
        array $metadata = []
    ): PromiseInterface {
        $options = [];
        $metadata = array_merge(
            ...array_map(fn($key, $value) => ["metadata.$key" => $value], array_keys($metadata), $metadata)
        );
        if (!empty($consistency)) {
            $options['consistency'] = $consistency->get_consistency();
            $options['concurrency'] = $consistency->get_concurrency();
        }
        $options = array_merge($options, $metadata);
        $storeName = rawurlencode($storeName);
        $key = rawurlencode($key);
        return $this->handlePromise(
            $this->httpClient->getAsync(
                "/v1.0/state/$storeName/$key",
                [
                    'query' => $options
                ]
            ),
            fn(ResponseInterface $response) => [
                'value' => $this->deserializer->from_json(
                    $asType,
                    $response->getBody()->getContents()
                ),
                'etag' => $response->getHeader('Etag')[0] ?? ''
            ]
        );
    }

    public function saveState(
        string $storeName,
        string $key,
        mixed $value,
        ?Consistency $consistency = null,
        array $metadata = []
    ): void {
        $this->saveStateAsync($storeName, $key, $value, $consistency, $metadata)->wait();
    }

    public function saveStateAsync(
        string $storeName,
        string $key,
        mixed $value,
        ?Consistency $consistency = null,
        array $metadata = []
    ): PromiseInterface {
        $item = new StateItem($key, $value, $consistency, null, $metadata);
        $storeName = rawurlencode($storeName);
        return $this->handlePromise(
            $this->httpClient->postAsync(
                "/v1.0/state/$storeName",
                [
                    'body' => $this->serializer->as_json([$item])
                ]
            )
        );
    }

    public function saveBulkState(string $storeName, array $stateItems): bool
    {
        return $this->saveBulkStateAsync($storeName, $stateItems)->wait();
    }

    public function saveBulkStateAsync(string $storeName, array $stateItems): PromiseInterface
    {
        $storeName = rawurlencode($storeName);
        return $this->handlePromise(
            $this->httpClient->postAsync("/v1.0/state/$storeName", ['body' => $this->serializer->as_json($stateItems)]),
            fn(ResponseInterface $response) => $response->getStatusCode() === 200,
            fn(\Throwable $ex) => false
        );
    }

    public function trySaveState(
        string $storeName,
        string $key,
        mixed $value,
        string $etag,
        ?Consistency $consistency = null,
        array $metadata = []
    ): bool {
        return $this->trySaveStateAsync($storeName, $key, $value, $etag, $consistency, $metadata)->wait();
    }

    public function trySaveStateAsync(
        string $storeName,
        string $key,
        mixed $value,
        string $etag,
        ?Consistency $consistency = null,
        array $metadata = []
    ): PromiseInterface {
        $item = new StateItem($key, $value, $consistency ?? new EventualFirstWrite(), $etag, $metadata);
        $storeName = rawurlencode($storeName);
        return $this->handlePromise(
            $this->httpClient->postAsync(
                "/v1.0/state/$storeName",
                [
                    'body' => $this->serializer->as_json([$item])
                ]
            ),
            fn(ResponseInterface $response) => true,
            fn(\Throwable $exception) => false
        );
    }

    public function getStateAndEtag(
        string $storeName,
        string $key,
        string $asType = 'array',
        ?Consistency $consistency = null,
        array $metadata = []
    ): array {
        return $this->getStateAndEtagAsync($storeName, $key, $asType, $consistency, $metadata)->wait();
    }

    public function executeStateTransaction(string $storeName, array $operations, array $metadata = []): void
    {
        $this->executeStateTransactionAsync($storeName, $operations, $metadata)->wait();
    }

    /**
     * @param string $storeName
     * @param StateTransactionRequest[] $operations
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<void>
     */
    public function executeStateTransactionAsync(
        string $storeName,
        array $operations,
        array $metadata = []
    ): PromiseInterface {
        $options = [
            'body' => $this->serializer->as_json(
                [
                    'operations' => array_map(
                        fn($operation) => [
                            'operation' => $operation->operationType,
                            'request' => array_merge(
                                [
                                    'key' => $operation->key,
                                ],
                                $operation instanceof UpsertTransactionRequest ? ['value' => $operation->value] : [],
                                empty($operation->etag) ? [] : ['etag' => $operation->etag],
                                empty($operation->metadata) ? [] : ['metadata' => $operation->metadata],
                                empty($operation->consistency) || empty($operation->etag) ? [] : [
                                    'options' => [
                                        'consistency' => $operation->consistency->get_consistency(),
                                        'concurrency' => $operation->consistency->get_concurrency(),
                                    ],
                                ],
                            ),
                        ],
                        $operations
                    ),
                    'metadata' => $metadata,
                ]
            ),
        ];
        $storeName = rawurlencode($storeName);
        return $this->handlePromise($this->httpClient->postAsync("/v1.0/state/$storeName/transaction", $options));
    }

    public function deleteState(
        string $storeName,
        string $key,
        Consistency $consistency = null,
        array $metadata = []
    ): void {
        $this->deleteStateAsync($storeName, $key, $consistency, $metadata)->wait();
    }

    public function deleteStateAsync(
        string $storeName,
        string $key,
        Consistency $consistency = null,
        array $metadata = []
    ): PromiseInterface {
        return $this->tryDeleteStateAsync($storeName, $key, null, $consistency ?? new EventualLastWrite(), $metadata);
    }

    public function tryDeleteStateAsync(
        string $storeName,
        string $key,
        string $etag,
        Consistency $consistency = null,
        array $metadata = []
    ): PromiseInterface {
        $consistency ??= new EventualFirstWrite();
        $storeName = rawurlencode($storeName);
        $key = rawurlencode($key);
        return $this->handlePromise(
            $this->httpClient->deleteAsync(
                "/v1.0/state/$storeName/$key",
                array_merge(
                    [
                        'query' => empty($consistency) ? [] : [
                            'consistency' => $consistency->get_consistency(),
                            'concurrency' => $consistency->get_concurrency(),
                        ],
                    ],
                    empty($etag) ? [] : [
                        'headers' => [
                            'If-Match' => $etag
                        ]
                    ]
                )
            ),
            fn(ResponseInterface $response) => true,
            fn(\Throwable $error) => false
        );
    }

    public function tryDeleteState(
        string $storeName,
        string $key,
        string $etag,
        Consistency $consistency = null,
        array $metadata = []
    ): bool {
        return $this->tryDeleteStateAsync($storeName, $key, $etag, $consistency, $metadata)->wait();
    }

    public function getBulkState(string $storeName, array $keys, int $parallelism = 10, array $metadata = []): array
    {
        return $this->getBulkStateAsync($storeName, $keys, $parallelism, $metadata)->wait();
    }

    public function getBulkStateAsync(
        string $storeName,
        array $keys,
        int $parallelism = 10,
        array $metadata = []
    ): PromiseInterface {
        $storeName = rawurlencode($storeName);
        return $this->handlePromise(
            $this->httpClient->postAsync(
                "/v1.0/state/$storeName/bulk",
                [
                    'body' => $this->serializer->as_json(
                        [
                            'keys' => $keys,
                            'parallelism' => $parallelism
                        ]
                    ),
                    'query' => array_merge(
                        ...array_map(fn($key, $value) => ["metadata.$key" => $value], array_keys($metadata), $metadata)
                    )
                ]
            ),
            fn(ResponseInterface $response) => array_merge(
                ...array_map(
                       fn($result) => [
                           $result['key'] => [
                               'value' => $result['data'] ?? null,
                               'etag' => $result['etag'] ?? null
                           ]
                       ],
                       $this->deserializer->from_json('array', $response->getBody()->getContents())
                   )
            )
        );
    }
}

<?php

namespace Dapr\Client;

use Dapr\Actors\IActorReference;
use Dapr\Actors\Reminder;
use Dapr\consistency\Consistency;
use Dapr\Deserialization\DeserializationConfig;
use Dapr\Deserialization\IDeserializer;
use Dapr\Serialization\ISerializer;
use Dapr\Serialization\SerializationConfig;
use Dapr\State\Internal\Transaction;
use Dapr\State\StateItem;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class DaprClient
 * @package Dapr\Client
 */
abstract class DaprClient
{
    public function __construct(
        public IDeserializer $deserializer,
        public ISerializer $serializer,
        public LoggerInterface $logger
    ) {
    }

    public static function clientBuilder(): DaprClientBuilder
    {
        return new DaprClientBuilder(
            defaultHttpHost: 'http://127.0.0.1:' . (getenv('DAPR_HTTP_PORT') ?: '3500'),
            deserializationConfig: new DeserializationConfig(),
            serializationConfig: new SerializationConfig(),
            logger: new NullLogger()
        );
    }

    /**
     * @template T
     * @psalm-param class-string<T>|'array'|'int'|'string'|'float' $dataType
     * @param BindingRequest $bindingRequest
     * @param string $dataType
     * @return BindingResponse<T>
     */
    abstract public function invokeBinding(
        BindingRequest $bindingRequest,
        string $dataType = 'array'
    ): BindingResponse;

    /**
     * @template T
     * @psalm-param class-string<T>|'array'|'int'|'string'|'float' $dataType
     * @param BindingRequest $bindingRequest
     * @param string $dataType
     * @return PromiseInterface<BindingResponse<T>>
     */
    abstract public function invokeBindingAsync(
        BindingRequest $bindingRequest,
        string $dataType = 'array'
    ): PromiseInterface;

    /**
     * @template T
     * @param string $bindingName
     * @param string $operation
     * @param T $data
     * @param array<array-key, string> $metadata
     * @return BindingRequest
     */
    public function createInvokeBindingRequest(
        string $bindingName,
        string $operation,
        mixed $data,
        array $metadata = []
    ): BindingRequest {
        return new BindingRequest($bindingName, $operation, $this->serializer->as_json($data), $metadata);
    }

    /**
     * @template T
     * @param string $pubsubName
     * @param string $topicName
     * @param T $data
     * @param array<array-key, string> $metadata
     */
    abstract public function publishEvent(
        string $pubsubName,
        string $topicName,
        mixed $data,
        array $metadata = [],
        string $contentType = 'application/json'
    ): void;

    /**
     * @template T
     * @param string $pubsubName
     * @param string $topicName
     * @param T $data
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<void>
     */
    abstract public function publishEventAsync(
        string $pubsubName,
        string $topicName,
        mixed $data,
        array $metadata = [],
        string $contentType = 'application/json'
    ): PromiseInterface;

    /**
     * @template T
     * @param string $httpMethod
     * @param string $appId
     * @param string $methodName
     * @param T|null $data
     * @param array<array-key, string> $metadata
     * @return ResponseInterface
     */
    abstract public function invokeMethod(
        string $httpMethod,
        AppId $appId,
        string $methodName,
        mixed $data = null,
        array $metadata = []
    ): ResponseInterface;

    /**
     * @template T
     * @param string $httpMethod
     * @param string $appId
     * @param string $methodName
     * @param T|null $data
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<ResponseInterface>
     */
    abstract public function invokeMethodAsync(
        string $httpMethod,
        AppId $appId,
        string $methodName,
        mixed $data = null,
        array $metadata = []
    ): PromiseInterface;

    /**
     * @template T
     * @psalm-param class-string<T>|'array'|'int'|'string'|'float' $asType
     * @param string $storeName
     * @param string $key
     * @param string $asType
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<T>
     */
    abstract public function getStateAsync(
        string $storeName,
        string $key,
        string $asType = 'array',
        Consistency $consistency = null,
        array $metadata = []
    ): PromiseInterface;

    /**
     * @param string $storeName
     * @param array<string> $keys
     * @param int $parallelism
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<array<array-key, array>>
     */
    abstract public function getBulkStateAsync(
        string $storeName,
        array $keys,
        int $parallelism = 10,
        array $metadata = []
    ): PromiseInterface;

    /**
     * @param string $storeName
     * @param array<string> $keys
     * @param int $parallelism
     * @param array<array-key, string> $metadata
     * @return array<array-key, array>
     */
    abstract public function getBulkState(
        string $storeName,
        array $keys,
        int $parallelism = 10,
        array $metadata = []
    ): array;

    /**
     * @template T
     * @psalm-param class-string<T>|'array'|'int'|'string'|'float' $asType
     * @param string $storeName
     * @param string $key
     * @param string $asType
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     * @return T
     */
    abstract public function getState(
        string $storeName,
        string $key,
        string $asType = 'array',
        Consistency $consistency = null,
        array $metadata = []
    ): mixed;

    /**
     * @template T
     * @param string $storeName
     * @param string $key
     * @param T $value
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<void>
     */
    abstract public function saveStateAsync(
        string $storeName,
        string $key,
        mixed $value,
        Consistency|null $consistency = null,
        array $metadata = []
    ): PromiseInterface;

    /**
     * @template T
     * @param string $storeName
     * @param string $key
     * @param T $value
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     */
    abstract public function saveState(
        string $storeName,
        string $key,
        mixed $value,
        Consistency|null $consistency = null,
        array $metadata = []
    ): void;

    /**
     * @template T
     * @param string $storeName
     * @param string $key
     * @param T $value
     * @param string $etag
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<bool>
     */
    abstract public function trySaveStateAsync(
        string $storeName,
        string $key,
        mixed $value,
        string $etag,
        Consistency|null $consistency = null,
        array $metadata = []
    ): PromiseInterface;

    /**
     * @template T
     * @param string $storeName
     * @param string $key
     * @param T $value
     * @param string $etag
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     * @return bool
     */
    abstract public function trySaveState(
        string $storeName,
        string $key,
        mixed $value,
        string $etag,
        Consistency|null $consistency = null,
        array $metadata = []
    ): bool;

    /**
     * @param string $storeName
     * @param StateItem[] $stateItems
     * @param array $metadata
     * @return bool
     */
    abstract public function saveBulkState(string $storeName, array $stateItems): bool;

    /**
     * @param string $storeName
     * @param StateItem[] $stateItems
     * @param array $metadata
     * @return bool
     */
    abstract public function saveBulkStateAsync(string $storeName, array $stateItems): PromiseInterface;

    /**
     * @template T
     * @psalm-param class-string<T>|'array'|'int'|'string'|'float' $asType
     * @param string $storeName
     * @param string $key
     * @param string $asType
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<array{value: T, etag: string}>
     */
    abstract public function getStateAndEtagAsync(
        string $storeName,
        string $key,
        string $asType = 'array',
        Consistency|null $consistency = null,
        array $metadata = []
    ): PromiseInterface;

    /**
     * @template T
     * @psalm-param class-string<T>|'array'|'int'|'string'|'float' $asType
     * @param string $storeName
     * @param string $key
     * @param string $asType
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     * @return array{value: T, etag: string}
     */
    abstract public function getStateAndEtag(
        string $storeName,
        string $key,
        string $asType = 'array',
        Consistency|null $consistency = null,
        array $metadata = []
    ): array;

    /**
     * @param string $storeName
     * @param StateTransactionRequest[] $operations
     * @param array<array-key, string> $metadata
     */
    abstract public function executeStateTransaction(string $storeName, array $operations, array $metadata = []): void;

    /**
     * @param string $storeName
     * @param StateTransactionRequest[] $operations
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<void>
     */
    abstract public function executeStateTransactionAsync(
        string $storeName,
        array $operations,
        array $metadata = []
    ): PromiseInterface;

    /**
     * @param string $storeName
     * @param string $key
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<void>
     */
    abstract public function deleteStateAsync(
        string $storeName,
        string $key,
        Consistency $consistency = null,
        array $metadata = []
    ): PromiseInterface;

    /**
     * @param string $storeName
     * @param string $key
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     */
    abstract public function deleteState(
        string $storeName,
        string $key,
        Consistency $consistency = null,
        array $metadata = []
    ): void;

    /**
     * @param string $storeName
     * @param string $key
     * @param string $etag
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<bool>
     */
    abstract public function tryDeleteStateAsync(
        string $storeName,
        string $key,
        string $etag,
        Consistency $consistency = null,
        array $metadata = []
    ): PromiseInterface;

    /**
     * @param string $storeName
     * @param string $key
     * @param string $etag
     * @param Consistency|null $consistency
     * @param array<array-key, string> $metadata
     * @return bool
     */
    abstract public function tryDeleteState(
        string $storeName,
        string $key,
        string $etag,
        Consistency $consistency = null,
        array $metadata = []
    ): bool;

    /**
     * @param string $storeName
     * @param string $key
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<array<array-key,string>>
     */
    abstract public function getSecretAsync(string $storeName, string $key, array $metadata = []): PromiseInterface;

    /**
     * @param string $storeName
     * @param string $key
     * @param array<array-key, string> $metadata
     * @return array<array-key, string>
     */
    abstract public function getSecret(string $storeName, string $key, array $metadata = []): array|null;

    /**
     * @param string $storeName
     * @param array<array-key, string> $metadata
     * @return PromiseInterface<null|array<array-key,array<array-key, string>>>
     */
    abstract public function getBulkSecretAsync(string $storeName, array $metadata = []): PromiseInterface;

    /**
     * @param string $storeName
     * @param array<array-key, string> $metadata
     * @return array<array-key, array<array-key, string>>
     */
    abstract public function getBulkSecret(string $storeName, array $metadata = []): array;

    /**
     * Check if the daprd instance is up and running.
     *
     * @return bool True if it is running, else false.
     */
    abstract public function isDaprHealthy(): bool;

    /**
     * Retrieve metadata from the sidecar
     *
     * @return MetadataResponse
     */
    abstract public function getMetadata(): MetadataResponse|null;

    /**
     * Shutdown the Daprd sidecar
     *
     * @param bool $afterRequest If true, schedules a php shutdown function, otherwise fires the request immediately.
     */
    abstract public function shutdown(bool $afterRequest = true): void;

    /**
     * Invoke an actor method
     *
     * @param string $httpMethod The HTTP method to use in the invocation
     * @param IActorReference $actor The actor reference to invoke
     * @param string $method The method of the actor to call
     * @param string $as The type to cast the result to using the deserialization config
     * @return mixed
     */
    abstract public function invokeActorMethod(
        string $httpMethod,
        IActorReference $actor,
        string $method,
        string $as = 'array'
    ): mixed;

    /**
     * Save a transaction to actor state
     *
     * @param IActorReference $actor The actor type
     * @param Transaction $transaction The transaction to store
     * @return bool Whether the state was successfully saved
     */
    abstract public function saveActorState(IActorReference $actor, Transaction $transaction): bool;

    /**
     * Retrieve actor state by key
     *
     * @param IActorReference $actor The actor reference
     * @param string $key The key to retrieve
     * @param string $as The type to deserialize to
     * @return mixed
     */
    abstract public function getActorState(IActorReference $actor, string $key, string $as = 'array'): mixed;

    /**
     * Set up an actor reminder.
     *
     * @param IActorReference $actor The actor reference
     * @param string $reminderName The reminder name to update/create
     * @param \DateTimeImmutable|\DateInterval $dueTime When to schedule the reminder for
     * @param \DateInterval|int|null $period How often to repeat
     * @return bool
     */
    abstract public function createActorReminder(
        IActorReference $actor,
        string $reminderName,
        \DateInterval|\DateTimeImmutable $dueTime,
        int|\DateInterval|null $period
    ): bool;

    abstract public function getActorReminder(IActorReference $actor, string $name): Reminder;

    abstract public function deleteActorReminder(IActorReference $actor, string $name): bool;

    abstract public function createActorTimer(
        IActorReference $actor,
        string $timerName,
        \DateInterval|\DateTimeImmutable $dueTime,
        int|\DateInterval|null $period,
        string|null $callback
    ): bool;

    abstract public function deleteActorTimer(IActorReference $actor, string $name): bool;

    /**
     * @param string $token
     * @return null|array{dapr-api-token: string}
     */
    protected function getDaprApiToken(string $token): array|null
    {
        if (empty($token)) {
            return null;
        }

        return ['dapr-api-token' => $token];
    }
}

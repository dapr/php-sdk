<?php

namespace Dapr\State;

use Dapr\Actors\Internal\Caches\KeyNotFound;
use Dapr\Actors\Internal\Caches\MemoryCache;
use Dapr\Client\DaprClient;
use Dapr\Client\DeleteTransactionRequest;
use Dapr\Client\UpsertTransactionRequest;
use Dapr\exceptions\StateAlreadyCommitted;
use Dapr\State\Internal\StateHelpers;
use Dapr\State\Internal\Transaction;
use DI\FactoryInterface;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use Psr\Container\ContainerInterface;

/**
 * Class TransactionalState
 * @package Dapr\State
 */
abstract class TransactionalState
{
    use StateHelpers;

    private DaprClient $client;
    private Transaction $transaction;
    private IManageState $stateManager;
    private \ReflectionClass $reflectionClass;
    private MemoryCache $cache;

    public function __construct(
        ContainerInterface|DaprClient $container,
        #[Deprecated(since: '1.2.0')] FactoryInterface|null $factory = null
    ) {
        if ($container instanceof ContainerInterface) {
            $this->client = $container->get(DaprClient::class);
        } else {
            $this->client = $container;
        }

        $this->stateManager = new StateManager($this->client);
        $this->reflectionClass = new \ReflectionClass($this);
        $this->cache = new MemoryCache('', '', '');
    }

    /**
     * Begin a transaction
     *
     * @param int $parallelism The parallelism for the initial loading of state
     * @param array|null $metadata Metadata for initial loading
     * @param string $prefix The key prefix
     */
    public function begin(int $parallelism = 10, ?array $metadata = null, string $prefix = ''): void
    {
        $this->transaction = new Transaction($this->client->serializer, $this->client->deserializer);
        $this->stateManager->load_object($this, $prefix, $parallelism, $metadata ?? []);

        foreach ($this->reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($this);
            unset($this->{$property->getName()});
            $this->cache->set_key($property->getName(), $value);
        }
    }

    public function __get(string $key): mixed
    {
        try {
            return $this->cache->get_key($key);
        } catch (KeyNotFound) {
            return null;
        }
    }

    public function __set(string $key, mixed $value): void
    {
        $this->throwIfCommitted();
        if (!$this->reflectionClass->hasProperty($key)) {
            $this->client->logger->critical(
                '{key} is not defined on transactional class and is not stored',
                ['key' => $key]
            );
            throw new InvalidArgumentException(
                "$key does not_exist on " . get_class($this) . " is not defined and thus will not be stored."
            );
        }
        $this->cache->set_key($key, $value);
        $this->transaction->upsert($key, $value);
    }

    private function throwIfCommitted(): void
    {
        if ($this->transaction->is_closed) {
            $this->client->logger->critical('Attempted to modify state after transaction is committed!');
            throw new StateAlreadyCommitted();
        }
    }

    public function __isset(string $key): bool
    {
        try {
            return $this->cache->get_key($key) !== null;
        } catch (KeyNotFound) {
            return false;
        }
    }

    public function __unset(string $key): void
    {
        $this->throwIfCommitted();
        $this->transaction->delete($key);
        $this->cache->evict($key);
    }

    public function commit(array|null $metadata = null): void
    {
        $this->throwIfCommitted();
        $store = self::get_description($this->reflectionClass);
        $operations = array_map(
            fn($operation) => match ($operation['operation']) {
                'upsert' => new UpsertTransactionRequest(
                    $operation['request']['key'],
                    $operation['request']['value'],
                    $this->get_etag_for_key($operation['request']['key']),
                    consistency: new $store->consistency
                ),
                'delete' => new DeleteTransactionRequest(
                    $operation['request']['key'],
                    $this->get_etag_for_key($operation['request']['key']),
                    consistency: new $store->consistency
                ),
            },
            $this->transaction->get_transaction()
        );
        if (!empty($operations)) {
            $this->client->executeStateTransaction($store->name, $operations, $metadata ?? []);
        }
        $this->transaction->is_closed = true;
    }

    private function get_etag_for_key(string $key): ?string
    {
        return (new class($key, $this) extends StateManager {
            public ?string $etag = '';

            public function __construct(string $key, $obj)
            {
                $this->etag = self::$objMap[$obj][$key] ?? '';
            }
        })->etag;
    }
}

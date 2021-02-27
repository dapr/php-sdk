<?php

namespace Dapr\Actors;

use Dapr\Actors\Internal\Caches\CacheInterface;
use Dapr\Actors\Internal\Caches\KeyNotFound;
use Dapr\Actors\Internal\Caches\MemoryCache;
use Dapr\Actors\Internal\Caches\NoCache;
use Dapr\Actors\Internal\KeyResponse;
use Dapr\Client\Interfaces\IClientV1;
use Dapr\DaprClient;
use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use Dapr\Proto\Runtime\V1\ExecuteActorStateTransactionRequest;
use Dapr\Proto\Runtime\V1\TransactionalActorStateOperation;
use Dapr\State\Internal\Transaction;
use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;
use Google\Protobuf\Any;
use Google\Protobuf\NullValue;
use GPBMetadata\Google\Protobuf\Duration;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Class ActorState
 *
 * Handles actor state transactions.
 *
 * @package Dapr\Actors
 */
abstract class ActorState
{
    /**
     * @var string[] Where values came from, to determine whether to load the value from the store.
     */
    private Transaction $transaction;
    private LoggerInterface $logger;
    private IDeserializer $deserializer;
    private ReflectionClass $reflection;
    private DaprClient $client;
    private \Dapr\Proto\Runtime\V1\DaprClient $nclient;
    private string $actor_id;
    private string $dapr_type;
    private CacheInterface $cache;

    public function __construct(private ContainerInterface $container, private FactoryInterface $factory)
    {
    }

    /**
     * Commits the current transaction
     *
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function save_state(): void
    {
        $this->logger->debug(
            'Committing transaction for {t}||{i}',
            ['t' => $this->dapr_type, 'i' => $this->actor_id]
        );
        $operations = $this->transaction->get_transaction();
        if (empty($operations)) {
            return;
        }

        $this->client->post("/actors/{$this->dapr_type}/{$this->actor_id}/state", $operations);
        $this->cache->flush_cache();
        $request = new ExecuteActorStateTransactionRequest();
        $operations = array_map(fn($operation) => (new TransactionalActorStateOperation())
            ->setKey($operation['key'])
            ->setValue( (new Any()))
            ->setOperationType($operation['type']), $operations);
        $request
            ->setActorId($this->actor_id)
            ->setActorType($this->dapr_type)
            ->setOperations($operations);
        $this->nclient->ExecuteActorStateTransaction($request);

        $this->begin_transaction($this->dapr_type, $this->actor_id);
    }

    /**
     * Begins a transaction
     *
     * @param string $dapr_type
     * @param string $actor_id
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function begin_transaction(string $dapr_type, string $actor_id)
    {
        $this->dapr_type    = $dapr_type;
        $this->actor_id     = $actor_id;
        $this->reflection   = new ReflectionClass($this);
        $this->logger       = $this->container->get(LoggerInterface::class);
        $this->deserializer = $this->container->get(IDeserializer::class);
        $this->client       = $this->container->get(DaprClient::class);
        $this->nclient = $this->container->get(IClientV1::class);

        foreach ($this->reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            unset($this->{$property->name});
        }
        $this->transaction = $this->factory->make(Transaction::class);
        $this->logger->debug(
            'Starting a new transaction for {t}||{i}',
            ['t' => $this->dapr_type, 'i' => $this->actor_id]
        );

        try {
            $cache_type = $this->container->get('dapr.actors.cache');
        } catch (DependencyException | NotFoundException) {
            $this->logger->warning('No cache type found, turning off actor state cache. Set `dapr.actors.cache`');
            $cache_type = MemoryCache::class;
        }
        $this->cache = new $cache_type($dapr_type, $actor_id, get_class($this));
    }

    /**
     * Rolls back all uncommitted state
     */
    public function roll_back(): void
    {
        // have to reset the cache, since we don't know the state because the transaction was rolled back
        $this->cache->reset();
        $this->logger->debug('Rolled back transaction');
        try {
            $this->transaction = $this->factory->make(Transaction::class);
        } catch (DependencyException | NotFoundException) {
        }
    }

    /**
     * Get a key from the store
     *
     * @param string $key
     *
     * @return mixed
     * @throws DaprException
     * @throws ReflectionException
     */
    public function __get(string $key): mixed
    {
        if ($this->reflection->hasProperty($key)) {
            try {
                return $this->cache->get_key($key);
            } catch (KeyNotFound) {
                return $this->_load_key($key);
            }
        }

        return null;
    }

    /**
     * Sets a value given a key
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set(string $key, mixed $value): void
    {
        if ( ! $this->reflection->hasProperty($key)) {
            throw new InvalidArgumentException(
                "$key on ".get_class($this)." is not defined and thus will not be stored."
            );
        }
        $this->cache->set_key($key, $value);
        $this->transaction->upsert($key, $value);
    }

    /**
     * Loads a key from the actor store
     *
     * @param string $key The key to load
     *
     * @throws DaprException
     * @throws ReflectionException
     */
    private function _load_key(string $key): mixed
    {
        $state    = $this->client->get("/actors/{$this->dapr_type}/{$this->actor_id}/state/$key");
        $property = $this->reflection->getProperty($key);

        $value = match ($state->code) {
            KeyResponse::SUCCESS => $this->deserializer->detect_from_property($property, $state->data),
            KeyResponse::KEY_NOT_FOUND => $property->hasDefaultValue() ? $property->getDefaultValue() : null,
            KeyResponse::ACTOR_NOT_FOUND => throw new DaprException('Actor not found!')
        };

        $this->cache->set_key($key, $value);

        return $value;
    }

    /**
     * Determine if a key exists
     *
     * @param string $key
     *
     * @return bool
     * @throws DaprException
     * @throws ReflectionException
     */
    public function __isset(string $key): bool
    {
        try {
            return $this->cache->get_key($key) !== null;
        } catch (KeyNotFound) {
            return $this->_load_key($key) !== null;
        }
    }

    /**
     * Delete a key
     *
     * @param string $key
     */
    public function __unset(string $key): void
    {
        $this->transaction?->delete($key);
        $this->cache->evict($key);
    }
}

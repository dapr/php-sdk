<?php

namespace Dapr\Actors;

use Dapr\Actors\Internal\Caches\CacheInterface;
use Dapr\Actors\Internal\Caches\KeyNotFound;
use Dapr\Client\DaprClient;
use Dapr\exceptions\DaprException;
use Dapr\State\Internal\Transaction;
use DI\DependencyException;
use DI\NotFoundException;
use InvalidArgumentException;
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
    private ReflectionClass $reflection;
    private DaprClient $client;
    private ActorReference $reference;
    private CacheInterface $cache;

    public function __construct()
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
        $this->client->logger->debug(
            'Committing transaction for {t}||{i}',
            ['t' => $this->dapr_type, 'i' => $this->actor_id]
        );

        $this->client->saveActorState($this->reference, $this->transaction);
        $this->cache->flush_cache();

        $this->begin_transaction($this->reference, $this->client, $this->cache);
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
    private function begin_transaction(ActorReference $reference, DaprClient $client, CacheInterface $cache)
    {
        $this->reference = $reference;
        $this->reflection = new ReflectionClass($this);
        $this->client = $client;

        foreach ($this->reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            unset($this->{$property->name});
        }
        $this->transaction = new Transaction($client->serializer, $client->deserializer);
        $client->logger->debug(
            'Starting a new transaction for {t}||{i}',
            ['t' => $this->dapr_type, 'i' => $this->actor_id]
        );

        $this->cache = $cache;
    }

    /**
     * Rolls back all uncommitted state
     */
    public function roll_back(): void
    {
        // have to reset the cache, since we don't know the state because the transaction was rolled back
        $this->cache->reset();
        $this->client->logger->debug('Rolled back transaction');
        $this->transaction = new Transaction($this->client->serializer, $this->client->deserializer);
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
        if (!$this->reflection->hasProperty($key)) {
            throw new InvalidArgumentException(
                "$key on " . get_class($this) . " is not defined and thus will not be stored."
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
        $property = $this->reflection->getProperty($key);
        $as = $this->client->deserializer->detect_type_name_from_property($property);

        try {
            $state = $this->client->getActorState($this->reference, $key, $as);
        } catch (KeyNotFound) {
            $state = $property->hasDefaultValue() ? $property->getDefaultValue() : null;
        }

        $this->cache->set_key($key, $state);

        return $state;
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

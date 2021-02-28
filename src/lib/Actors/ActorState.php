<?php

namespace Dapr\Actors;

use Dapr\Actors\Internal\Caches\CacheInterface;
use Dapr\Actors\Internal\Caches\KeyNotFound;
use Dapr\Actors\Internal\Caches\NoCache;
use Dapr\Actors\Internal\KeyResponse;
use Dapr\DaprClient;
use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use Dapr\State\Internal\Transaction;
use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;
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
    private array $_internal_data = [];
    private Transaction $transaction;
    private LoggerInterface $logger;
    private IDeserializer $deserializer;
    private ReflectionClass $reflection;
    private DaprClient $client;
    private string $actor_id;
    private string $dapr_type;
    private CacheInterface $cache;

    public function __construct(private ContainerInterface $container, private FactoryInterface $factory)
    {
        try {
            $this->cache = $this->container->get(CacheInterface::class);
        } catch (\Exception) {
            $this->cache = new NoCache('');
        }
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

        foreach ($this->reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            unset($this->{$property->name});
        }
        $this->transaction = $this->factory->make(Transaction::class);
        $this->logger->debug(
            'Starting a new transaction for {t}||{i}',
            ['t' => $this->dapr_type, 'i' => $this->actor_id]
        );
    }

    /**
     * Rolls back all uncommitted state
     */
    public function roll_back(): void
    {
        $this->logger->debug('Rolled back transaction');
        try {
            $this->transaction = $this->factory->make(Transaction::class);
        } catch (DependencyException | NotFoundException) {
        }
        $this->_internal_data = [];
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
            if ( ! isset($this->_internal_data[$key])) {
                $this->_load_key($key);
            }

            return $this->transaction->state[$key];
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
        if (empty($this->_internal_data[$key])) {
            $this->cache->set_key($key, $value);
            $this->_internal_data[$key] = 'override'; // must be anything other than null
        }
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
    private function _load_key(string $key): void
    {
        try {
            $data                           = $this->cache->get_key($key);
            $this->_internal_data[$key]     = 'cache';
            $this->transaction->state[$key] = $data;
        } catch (KeyNotFound) {
            $state = $this->client->get("/actors/{$this->dapr_type}/{$this->actor_id}/state/$key");
            if (isset($state->data)) {
                $property    = $this->reflection->getProperty($key);
                $state->data = $this->deserializer->detect_from_property($property, $state->data);
            }
            switch ($state?->code) {
                case KeyResponse::SUCCESS:
                    $this->_internal_data[$key]     = 'loaded';
                    $this->transaction->state[$key] = $state->data;
                    break;
                case KeyResponse::KEY_NOT_FOUND:
                    $this->_internal_data[$key]     = 'default';
                    $this->transaction->state[$key] = $this->reflection->getProperty($key)->getDefaultValue();
                    break;
                case KeyResponse::ACTOR_NOT_FOUND:
                default:
                    throw new DaprException('Actor not found!');
            }
        }
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
        if ( ! isset($this->_internal_data[$key])) {
            $this->_load_key($key);
        }

        return isset($this->transaction?->state[$key]);
    }

    /**
     * Delete a key
     *
     * @param string $key
     */
    public function __unset(string $key): void
    {
        if (empty($this->_internal_data[$key])) {
            $this->cache->set_key($key, null);
            $this->_internal_data[$key] = 'unset';
        }
        $this->transaction?->delete($key);
    }
}

<?php

namespace Dapr\Actors;

use Dapr\Actors\Internal\KeyResponse;
use Dapr\DaprClient;
use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use Dapr\State\Internal\Transaction;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

abstract class ActorState
{
    /**
     * @var Transaction The transaction to commit
     */
    private Transaction $_internal_transaction;

    /**
     * @var ReflectionClass
     */
    private ReflectionClass $_internal_reflection;

    /**
     * @var string[] Where values came from, to determine whether to load the value from the store.
     */
    private array $_internal_data = [];
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $_internal_logger;
    /**
     * @var DaprClient
     */
    private DaprClient $_internal_client;


    /**
     * ActorState constructor.
     *
     * @param string $_internal_dapr_type The Dapr Type to load state for
     * @param mixed $_internal_actor_id The ID of the actor to load state for
     */
    public function __construct(private string $_internal_dapr_type, private mixed $_internal_actor_id)
    {
        global $dapr_container;
        $this->_internal_reflection  = new ReflectionClass($this);
        $this->_internal_transaction = $dapr_container->make(Transaction::class);
        $this->_internal_logger      = $dapr_container->get(LoggerInterface::class);
        $this->_internal_client      = $dapr_container->get(DaprClient::class);

        foreach ($this->_internal_reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            unset($this->{$property->name});
        }
        $this->_internal_logger->debug(
            'Starting transaction for {t}||{i}',
            ['t' => $this->_internal_dapr_type, 'i' => $this->_internal_actor_id]
        );
    }

    /**
     * Commits the current transaction
     *
     * @throws DaprException
     */
    public function save_state(): void
    {
        $this->_internal_logger->debug(
            'Committing transaction for {t}||{i}',
            ['t' => $this->_internal_dapr_type, 'i' => $this->_internal_actor_id]
        );
        $operations = $this->_internal_transaction->get_transaction();
        if (empty($operations)) {
            return;
        }

        $this->_internal_client->post(
            $this->_internal_client->get_api_path(
                "/actors/{$this->_internal_dapr_type}/{$this->_internal_actor_id}/state"
            ),
            $operations
        );

        $this->roll_back();
    }

    /**
     * Rolls back all uncommitted state
     */
    public function roll_back(): void
    {
        global $dapr_container;
        $this->_internal_logger->debug('Rolled back transaction');
        $this->_internal_transaction = $dapr_container->make(Transaction::class);
        $this->_internal_data        = [];
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
        if ($this->_internal_reflection->hasProperty($key)) {
            if ( ! isset($this->_internal_data[$key])) {
                $this->_load_key($key);
            }

            return $this->_internal_transaction->state[$key];
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
        if ( ! $this->_internal_reflection->hasProperty($key)) {
            throw new InvalidArgumentException(
                "$key on ".get_class($this)." is not defined and thus will not be stored."
            );
        }
        if (empty($this->_internal_data[$key])) {
            $this->_internal_data[$key] = 'override'; // must be anything other than null
        }
        $this->_internal_transaction->upsert($key, $value);
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
        global $dapr_container;
        $deserializer = $dapr_container->get(IDeserializer::class);
        $state        = $this->_internal_client->get(
            $this->_internal_client->get_api_path(
                "/actors/{$this->_internal_dapr_type}/{$this->_internal_actor_id}/state/$key"
            )
        );
        if (isset($state->data)) {
            $property    = $this->_internal_reflection->getProperty($key);
            $state->data = $deserializer->detect_from_property($property, $state->data);
        }
        switch ($state?->code) {
            case KeyResponse::SUCCESS:
                $this->_internal_data[$key]               = 'loaded';
                $this->_internal_transaction->state[$key] = $state->data;
                break;
            case KeyResponse::KEY_NOT_FOUND:
                $this->_internal_data[$key]               = 'default';
                $this->_internal_transaction->state[$key] = $this->_internal_reflection->getProperty(
                    $key
                )->getDefaultValue();
                break;
            case KeyResponse::ACTOR_NOT_FOUND:
            default:
                throw new DaprException('Actor not found!');
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

        return isset($this->_internal_transaction->state[$key]);
    }

    /**
     * Delete a key
     *
     * @param string $key
     */
    public function __unset(string $key): void
    {
        if (empty($this->_internal_data[$key])) {
            $this->_internal_data[$key] = 'unset';
        }
        $this->_internal_transaction->delete($key);
    }
}

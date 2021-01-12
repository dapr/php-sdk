<?php

namespace Dapr\Actors;

use Dapr\DaprClient;
use Dapr\exceptions\DaprException;
use Dapr\Runtime;
use Dapr\State\Internal\Transaction;
use ReflectionClass;

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
     * ActorState constructor.
     *
     * @param string $_internal_dapr_type The Dapr Type to load state for
     * @param mixed $_internal_actor_id The ID of the actor to load state for
     */
    public function __construct(private string $_internal_dapr_type, private mixed $_internal_actor_id)
    {
        $this->_internal_reflection  = new ReflectionClass($this);
        $this->_internal_transaction = new Transaction();

        foreach ($this->_internal_reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            unset($this->{$property->name});
        }
        Runtime::$logger?->debug(
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
        Runtime::$logger?->debug(
            'Committing transaction for {t}||{i}',
            ['t' => $this->_internal_dapr_type, 'i' => $this->_internal_actor_id]
        );
        $operations = $this->_internal_transaction->get_transaction();
        if (empty($operations)) {
            return;
        }

        DaprClient::post(
            DaprClient::get_api("/actors/{$this->_internal_dapr_type}/{$this->_internal_actor_id}/state"),
            $operations
        );

        $this->roll_back();
    }

    /**
     * Rolls back all uncommitted state
     */
    public function roll_back(): void
    {
        Runtime::$logger?->debug('Rolled back transaction');
        $this->_internal_transaction = new Transaction();
        $this->_internal_data        = [];
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
            throw new \InvalidArgumentException(
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
     * @throws \ReflectionException
     */
    private function _load_key(string $key): void
    {
        $state = DaprClient::get(
            DaprClient::get_api("/actors/{$this->_internal_dapr_type}/{$this->_internal_actor_id}/state/$key")
        );
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
     * Get a key from the store
     *
     * @param string $key
     *
     * @return mixed
     * @throws DaprException
     * @throws \ReflectionException
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
     * Determine if a key exists
     *
     * @param string $key
     *
     * @return bool
     * @throws DaprException
     * @throws \ReflectionException
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

<?php

namespace Dapr\State;

use Dapr\DaprClient;
use Dapr\exceptions\StateAlreadyCommitted;
use Dapr\State\Internal\StateHelpers;
use Dapr\State\Internal\Transaction;
use ReflectionClass;
use ReflectionProperty;

/**
 * Class TransactionalState
 * @package Dapr\State
 */
abstract class TransactionalState
{
    use StateHelpers;

    /**
     * @var Transaction The current transaction
     */
    private Transaction $_internal_transaction;

    /**
     * @var ReflectionClass
     */
    private ReflectionClass $_internal_reflection;

    /**
     * TransactionalState constructor.
     */
    public function __construct()
    {
        $this->_internal_reflection = new ReflectionClass($this);
    }

    /**
     * Begin a transaction
     *
     * @param int $parallelism The amount of parallelism to use in loading the state
     * @param array|null $metadata Component specific metadata
     */
    public function begin(int $parallelism = 10, ?array $metadata = null): void
    {
        $this->_internal_transaction = new Transaction();
        State::load_state($this, $parallelism, $metadata);

        foreach ($this->_internal_reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $this->{$property->name};
            unset($this->{$property->name});
            $this->_internal_transaction->state[$property->name] = $value;
        }
    }

    /**
     * Upsert a value
     *
     * @param string $key
     * @param mixed $value
     *
     * @throws StateAlreadyCommitted
     */
    public function __set(string $key, mixed $value): void
    {
        $this->throw_if_committed();
        if ( ! $this->_internal_reflection->hasProperty($key)) {
            throw new \InvalidArgumentException(
                "$key on ".get_class($this)." is not defined and thus will not be stored."
            );
        }
        $this->_internal_transaction->upsert($key, $value);
    }

    public function __get(string $key): mixed
    {
        return $this->_internal_transaction->state[$key];
    }

    public function __isset(string $key): bool
    {
        return isset($this->_internal_transaction[$key]);
    }

    public function __unset(string $key): void
    {
        $this->throw_if_committed();
        $this->_internal_transaction->delete($key);
    }

    /**
     * Commit the transaction.
     * 
     * @param array|null $metadata Component specific metadata
     *
     * @throws StateAlreadyCommitted
     * @throws \Dapr\exceptions\DaprException
     */
    public function commit(?array $metadata = null): void
    {
        $this->throw_if_committed();
        $state_store = self::get_description($this->_internal_reflection);
        $transaction = [
            'operations' => array_map(
                fn($t) => State::get_etag($this, $t['request']['key']) ? array_merge(
                    $t,
                    [
                        'request' => array_merge(
                            $t['request'],
                            [
                                'etag'    => State::get_etag($this, $t['request']['key']),
                                'options' => [
                                    'consistency' => (new $state_store->consistency)->get_consistency(),
                                    'concurrency' => (new $state_store->consistency)->get_concurrency(),
                                ],
                            ]
                        ),
                    ]
                ) : $t,
                $this->_internal_transaction->get_transaction()
            ),
        ];
        if (isset($metadata)) {
            $transaction['metadata'] = $metadata;
        }

        if ( ! empty($transaction['operations'])) {
            DaprClient::post(DaprClient::get_api("/state/{$state_store->name}/transaction"), $transaction);
        }
        $this->_internal_transaction->is_closed = true;
    }

    /**
     * Throws an exception if this object has been committed.
     *
     * @return void
     */
    protected function throw_if_committed(): void
    {
        if ($this->_internal_transaction->is_closed) {
            throw new StateAlreadyCommitted();
        }
    }
}

<?php

namespace Dapr\Mocks;

use Dapr\exceptions\StateAlreadyCommitted;
use Dapr\Runtime;
use Dapr\State\Internal\Transaction;

trait TestTransactionalState
{
    private array $_internal_previous_commits = [];
    private Transaction $_internal_transaction;

    public function __construct()
    {
        Runtime::$logger?->debug('Overriding transactional state');
    }

    public function begin(int $parallelism = 10, ?array $metadata = null): void
    {
        $initial_state               = [];
        $this->_internal_transaction = new Transaction();
        foreach ((new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            unset($this->{$property->name});
            $initial_state[$property->name] = $property->getDefaultValue();
        }
        $this->_internal_transaction        = new Transaction();
        $this->_internal_transaction->state = $initial_state;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->throw_if_committed();
        if ( ! (new \ReflectionClass($this))->hasProperty($key)) {
            throw new \LogicException("$key on ".get_class($this)." is not defined and thus will not be stored.");
        }
        $this->_internal_transaction->upsert($key, $value);
    }

    public function __get(string $key): mixed
    {
        return $this->_internal_transaction->state[$key];
    }

    public function __isset(string $key): bool
    {
        return isset($this->_internal_transaction->state[$key]);
    }

    public function __unset(string $key): void
    {
        $this->throw_if_committed();
        $this->_internal_transaction->delete($key);
    }

    public function commit(?array $metadata = null): void
    {
        $this->throw_if_committed();
        $this->_internal_previous_commits[]     = $this->_internal_transaction->get_transaction();
        $this->_internal_transaction->is_closed = true;
    }

    public function helper_get_transactions(): array
    {
        return $this->_internal_previous_commits;
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

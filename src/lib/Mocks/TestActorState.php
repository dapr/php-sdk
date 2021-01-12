<?php

namespace Dapr\Mocks;

use Dapr\Runtime;
use Dapr\State\Internal\Transaction;
use ReflectionClass;

trait TestActorState
{
    /**
     * @var Transaction[]
     */
    private array $_internal_transaction = [];
    private ReflectionClass $_internal_reflection;
    private int $_on_transaction = 0;
    private array $loaded = [];

    public function __construct()
    {
        Runtime::$logger?->debug('Overriding Actor State');
        $this->_internal_transaction[] = new Transaction();
        $this->_internal_reflection    = new ReflectionClass($this);

        foreach ($this->_internal_reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            unset($this->{$property->name});
        }
    }

    public function __get(string $key): mixed
    {
        if (empty($this->loaded[$key])) {
            $this->_internal_transaction[$this->_on_transaction]->state[$key] = $this->_internal_reflection->getProperty(
                $key
            )->getDefaultValue();
            $this->loaded[$key]                                               = true;
        }

        return $this->_internal_transaction[$this->_on_transaction]->state[$key];
    }

    public function __set(string $key, mixed $value): void
    {
        $this->_internal_transaction[$this->_on_transaction]->upsert($key, $value);
    }

    public function __isset(string $key): bool
    {
        if (isset($this->loaded[$key])) {
            return isset($this->_internal_transaction[$this->_on_transaction]->state[$key]);
        }
        $value = $this->__get($key);

        return isset($value);
    }

    public function __unset(string $key): void
    {
        $this->_internal_transaction[$this->_on_transaction]->delete($key);
    }

    public function save_state(): void
    {
        $this->_on_transaction += 1;
        $this->_internal_transaction[$this->_on_transaction] = new Transaction();
        $this->loaded = [];
    }

    public function roll_back(): void
    {
        $this->_internal_transaction[$this->_on_transaction] = new Transaction();
        $this->loaded                                        = [];
    }

    public function helper_get_transaction(): array
    {
        $transactions = [];
        foreach ($this->_internal_transaction as $transaction) {
            $transactions[] = $transaction->get_transaction();
        }

        return $transactions;
    }
}

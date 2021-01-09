<?php

namespace Dapr\Actors;

use Dapr\DaprClient;
use Dapr\exceptions\DaprException;
use Dapr\State\Internal\StateHelpers;
use Dapr\State\Internal\Transaction;
use ReflectionClass;

abstract class ActorState
{
    use StateHelpers;

    private Transaction $_internal_transaction;
    private ReflectionClass $_internal_reflection;
    private array $_internal_data = [];

    public function __construct(private string $_internal_dapr_type, private string $_internal_actor_id)
    {
        $this->_internal_reflection  = new ReflectionClass($this);
        $this->_internal_transaction = new Transaction();

        foreach ($this->_internal_reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            unset($this->{$property->name});
        }
    }

    public function save_state(): void
    {
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

    public function roll_back(): void
    {
        $this->_internal_transaction = new Transaction();
        $this->_internal_data        = [];
    }

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

    private function _load_key($key): void
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

    public function __isset(string $key): bool
    {
        if ( ! isset($this->_internal_data[$key])) {
            $this->_load_key($key);
        }

        return isset($this->_internal_transaction->state[$key]);
    }

    public function __unset(string $key): void
    {
        if (empty($this->_internal_data[$key])) {
            $this->_internal_data[$key] = 'unset';
        }
        $this->_internal_transaction->delete($key);
    }
}

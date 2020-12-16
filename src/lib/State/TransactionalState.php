<?php

namespace Dapr\State;

use Dapr\consistency\Consistency;
use Dapr\consistency\EventualLastWrite;
use Dapr\DaprClient;
use Dapr\exceptions\CommitFailed;
use Dapr\exceptions\NoStorage;
use Dapr\exceptions\SaveStateFailure;
use Dapr\exceptions\StateAlreadyCommitted;
use Dapr\Serializer;
use ReflectionClass;
use ReflectionProperty;

class TransactionalState
{
    /**
     * @var array
     */
    protected array $transaction = [];

    /**
     * @var bool
     */
    protected bool $committed = false;

    /**
     * @var array
     */
    protected $consistency;

    /**
     * Create a new transactional state.
     *
     * @param State $state The state object.
     * @param string $store_name The store name.
     * @param Consistency $consistency_setting
     *
     * @todo handle null store names
     */
    protected function __construct(
        protected State $state,
        protected string $store_name,
        Consistency $consistency_setting
    ) {
        $this->consistency = [
            'consistency' => $consistency_setting->get_consistency(),
            'concurrency' => $consistency_setting->get_concurrency(),
        ];
    }

    /**
     * Begin a new transaction.
     *
     * @param string $type The type of state to store.
     * @param string|null $store_name The store name to use.
     * @param Consistency|null $consistency
     *
     * @return TransactionalState The transactional state.
     */
    public static function begin(
        string $type,
        ?string $store_name = null,
        ?Consistency $consistency = null
    ): TransactionalState {
        $state       = new $type($store_name);
        $consistency = $consistency ?? new EventualLastWrite();
        $state->load();

        return new TransactionalState($state, $store_name, $consistency);
    }

    /**
     * Commit a transaction.
     *
     * @param TransactionalState|State $state The transactional state to store.
     * @param array $metadata The metadata.
     *
     * @return true
     * @throws CommitFailed
     */
    public static function commit(State|TransactionalState $state, array $metadata = []): bool
    {
        try {
            return $state->_commit($metadata);
        } catch (NoStorage | SaveStateFailure $e) {
            throw new CommitFailed();
        }
    }

    /**
     * Commit the transaction.
     *
     * @param array $metadata Metadata to send to the storage.
     * @param bool $full
     *
     * @return true
     * @throws NoStorage
     * @throws SaveStateFailure
     */
    protected function _commit(array $metadata = [], bool $full = true): bool
    {
        $this->throw_if_committed();
        $this->filter_transaction();

        if (count($this->transaction) === 0) {
            return true;
        }

        if ($full) {
            $transaction = [
                'operations' => array_values($this->transaction),
                'metadata'   => (object)$metadata,
            ];
        } else {
            $transaction = $this->transaction;
        }

        // do not try to serialize here!
        // if metadata is empty, it needs to remain an object
        $result = DaprClient::post(DaprClient::get_api($this->get_save_endpoint()), $transaction);
        switch ($result->code) {
            case 201:
            case 204:
                return $this->committed = true;
            case 400:
                throw new NoStorage('State store is missing or misconfigured or malformed request');
            case 500:
            default:
                throw new SaveStateFailure('Request failed');
        }
    }

    /**
     * Run any special filters
     *
     * @return void
     */
    protected function filter_transaction(): void
    {
        $this->dedupe();
        $this->add_etags();
        $this->serialize();
    }

    /**
     * Dedupe the transaction log
     *
     * @return void
     */
    protected function dedupe(): void
    {
        $transaction = [];
        $seen_keys   = [];
        $total_ops   = count($this->transaction);
        for ($i = $total_ops - 1; $i >= 0; $i--) {
            $key = $this->transaction[$i]['request']['key'];
            if (in_array($key, $seen_keys)) {
                continue;
            }
            $seen_keys[]                      = $key;
            $transaction[$total_ops - $i - 1] = $this->transaction[$i];
        }
        $this->transaction = $transaction;
    }

    /**
     * Add etags to the transaction
     *
     * @return void
     */
    protected function add_etags(): void
    {
        foreach ($this->transaction as &$t) {
            $key = $t['request']['key'].'__etag';

            if (isset($this->state->$key)) {
                $t['request']['etag'] = $this->state->$key;
                if (isset($this->consistency)) {
                    $t['request']['options'] = $this->consistency;
                }
            }
        }
    }

    protected function serialize(): void
    {
        $this->transaction = Serializer::as_json($this->transaction);
    }

    /**
     * Get the save endpoint
     *
     * @return string The save endpoint
     */
    protected function get_save_endpoint(): string
    {
        $store = $this->store_name;

        return "/state/$store/transaction";
    }

    /**
     * Get a value from underlying state.
     *
     * @param string $name The key to retrieve.
     */
    public function __get($name)
    {
        return $this->state->$name;
    }

    /**
     * Set a value on the underlying state and add to transaction.
     *
     * @param string $name The key.
     * @param mixed $value The value to set.
     *
     * @throws StateAlreadyCommitted
     */
    public function __set($name, $value)
    {
        $this->throw_if_committed();
        $this->transaction[] = [
            'operation' => 'upsert',
            'request'   => [
                'key'   => $name,
                'value' => $value,
            ],
        ];

        return $this->state->$name = $value;
    }

    /**
     * Throws an exception if this object has been committed.
     *
     * @return void
     * @throws StateAlreadyCommitted
     */
    protected function throw_if_committed(): void
    {
        if ($this->committed) {
            throw new StateAlreadyCommitted();
        }
    }

    /**
     * Determine if an underlying value is set.
     *
     * @param string $name The key to check.
     *
     * @return bool
     */
    public function __isset($name): bool
    {
        return isset($this->state->$name);
    }

    /**
     * Delete an underlying value.
     *
     * @param string $name The key to delete.
     *
     * @throws StateAlreadyCommitted
     */
    public function __unset(string $name)
    {
        $this->throw_if_committed();
        unset($this->state->$name);
        $this->transaction[] = [
            'operation' => 'delete',
            'request'   => [
                'key' => $name,
            ],
        ];
    }

    /**
     * Proxy a method call to the underlying state and determine the transaction.
     *
     * @param string $name The method to call.
     * @param array $arguments Arguments to the method.
     *
     * @return mixed
     * @throws SaveStateFailure|StateAlreadyCommitted
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'save_state') {
            throw new SaveStateFailure('State cannot be manually saved during a commit.');
        }
        $initial                = $this->extract_state();
        $result                 = call_user_func_array([$this->state, $name], $arguments);
        $changed_state          = $this->extract_state();
        $additional_transaction = $this->extract_diff($initial, $changed_state);
        if (count($additional_transaction) > 0) {
            $this->throw_if_committed();
        }

        $this->transaction = array_merge($this->transaction, $additional_transaction);

        return $result;
    }

    /**
     * Extract a shallow state snapshot.
     * @return array A state snapshot.
     */
    protected function extract_state(): array
    {
        $state = [];
        foreach ($this->get_keys() as $key) {
            $state[$key] = $this->state->$key;
        }

        return $state;
    }

    /**
     * Get keys of the state class.
     * @return array The list of keys on the type.
     */
    protected function get_keys(): array
    {
        $keys = new ReflectionClass($this->state);

        return array_map(
            function (ReflectionProperty $item) {
                return $item->getName();
            },
            $keys->getProperties(ReflectionProperty::IS_PUBLIC)
        );
    }

    /**
     * Calculate a transaction based on changes in state snapshot.
     *
     * @param array $initial The initial state.
     * @param array $new The new state.
     *
     * @return array Additional transaction items.
     */
    protected function extract_diff(array $initial, array $new): array
    {
        $diff = [];
        foreach ($initial as $key => $value) {
            if (($new[$key] ?? null) !== $value) {
                $diff[$key] = $new[$key];
            }
        }
        $transaction = [];
        foreach ($diff as $changed_key => $changed_value) {
            if (isset($new[$changed_key])) {
                $transaction[] = [
                    'operation' => 'upsert',
                    'request'   => [
                        'key'   => $changed_key,
                        'value' => $changed_value,
                    ],
                ];
            } else {
                $transaction[] = [
                    'operation' => 'delete',
                    'request'   => [
                        'key' => $changed_key,
                    ],
                ];
            }
        }

        return $transaction;
    }
}

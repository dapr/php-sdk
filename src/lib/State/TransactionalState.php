<?php

namespace Dapr\State;

use Dapr\DaprClient;
use Dapr\exceptions\StateAlreadyCommitted;
use Dapr\State\Internal\StateHelpers;
use Dapr\State\Internal\Transaction;
use Psr\Log\LoggerInterface;
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
     * @var ReflectionClass
     */
    private ReflectionClass $_internal_reflection;

    /**
     * TransactionalState constructor.
     *
     * @param LoggerInterface $logger
     * @param DaprClient $client
     */
    public function __construct(
        private ?LoggerInterface $_internal_logger = null,
        private ?DaprClient $_internal_client = null,
        private ?IManageState $_inernal_state = null,
        private ?Transaction $_internal_transaction = null
    ) {
        global $dapr_container;
        $this->_internal_logger      ??= $dapr_container->get(LoggerInterface::class);
        $this->_internal_client      ??= $dapr_container->get(DaprClient::class);
        $this->_inernal_state        ??= $dapr_container->get(IManageState::class);
        $this->_internal_transaction ??= $dapr_container->make(Transaction::class);
        $this->_internal_reflection  = new ReflectionClass($this);
    }

    /**
     * Begin a transaction
     *
     * @param int $parallelism The amount of parallelism to use in loading the state
     * @param array|null $metadata Component specific metadata
     * @param string $prefix
     */
    public function begin(int $parallelism = 10, ?array $metadata = null, $prefix = ''): void
    {
        global $dapr_container;
        $this->_internal_logger->info('Beginning transaction');
        $this->_internal_transaction = $dapr_container->make(Transaction::class);
        $this->_inernal_state->load_object(
            $this,
            parallelism: $parallelism,
            metadata: $metadata ?? [],
            prefix: $prefix
        );

        foreach ($this->_internal_reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $this->{$property->name};
            unset($this->{$property->name});
            $this->_internal_transaction->state[$property->name] = $value;
        }
    }

    public function __get(string $key): mixed
    {
        $this->_internal_logger->debug('Getting value from transaction with key: {key}', ['key' => $key]);

        return $this->_internal_transaction->state[$key];
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
        $this->_internal_logger->debug('Attempting to set {key} to {value}', ['key' => $key, 'value' => $value]);
        $this->throw_if_committed();
        if ( ! $this->_internal_reflection->hasProperty($key)) {
            $this->_internal_logger->critical(
                '{key} is not defined on transactional class and is not stored',
                ['key' => $key]
            );
            throw new \InvalidArgumentException(
                "$key does not_exist on ".get_class($this)." is not defined and thus will not be stored."
            );
        }
        $this->_internal_transaction->upsert($key, $value);
    }

    /**
     * Throws an exception if this object has been committed.
     *
     * @return void
     */
    protected function throw_if_committed(): void
    {
        if ($this->_internal_transaction->is_closed) {
            $this->_internal_logger->critical('Attempted to modify state after transaction is committed!');
            throw new StateAlreadyCommitted();
        }
    }

    public function __isset(string $key): bool
    {
        $this->_internal_logger->debug('Checking {key} is set', ['key' => $key]);

        return isset($this->_internal_transaction->state[$key]);
    }

    public function __unset(string $key): void
    {
        $this->_internal_logger->debug('Deleting {key}', ['key' => $key]);
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
        $this->_internal_logger->debug('Committing transaction');
        $this->throw_if_committed();
        $state_store = self::get_description($this->_internal_reflection);
        $transaction = [
            'operations' => array_map(
                fn($t) => $this->get_etag_for_key($t['request']['key']) ? array_merge(
                    $t,
                    [
                        'request' => array_merge(
                            $t['request'],
                            [
                                'etag'    => $this->get_etag_for_key($t['request']['key']),
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
            $this->_internal_client->post(
                $this->_internal_client->get_api_path("/state/{$state_store->name}/transaction"),
                $transaction
            );
        }
        $this->_internal_transaction->is_closed = true;
    }

    private function get_etag_for_key(string $key): ?string
    {
        return (new class($this->_inernal_state, $key, $this) extends StateManager {
            public ?string $etag = '';

            public function __construct(IManageState $other, string $key, $obj)
            {
                $etag       = self::$obj_meta[$obj][$key] ?? [];
                $this->etag = $etag['etag'] ?? null;
            }
        })->etag;
    }
}

<?php

namespace Dapr\State;

use Dapr\DaprClient;
use Dapr\exceptions\DaprException;
use Dapr\exceptions\StateAlreadyCommitted;
use Dapr\State\Internal\StateHelpers;
use Dapr\State\Internal\Transaction;
use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
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

    private LoggerInterface $logger;
    private DaprClient $client;
    private IManageState $state;
    private Transaction $transaction;
    private ReflectionClass $reflection;

    /**
     * TransactionalState constructor.
     *
     * @param ContainerInterface $container
     * @param FactoryInterface $factory
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct(
        private ContainerInterface $container,
        private FactoryInterface $factory
    ) {
        $this->logger      = $this->container->get(LoggerInterface::class);
        $this->client      = $this->container->get(DaprClient::class);
        $this->state       = $this->container->get(IManageState::class);
        $this->transaction = $this->factory->make(Transaction::class);
        $this->reflection  = new ReflectionClass($this);
    }

    /**
     * Begin a transaction
     *
     * @param int $parallelism The amount of parallelism to use in loading the state
     * @param array|null $metadata Component specific metadata
     * @param string $prefix
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function begin(int $parallelism = 10, ?array $metadata = null, $prefix = ''): void
    {
        $this->logger->info('Beginning transaction');
        $this->transaction = $this->factory->make(Transaction::class);
        $this->state->load_object(
            $this,
            prefix: $prefix,
            parallelism: $parallelism,
            metadata: $metadata ?? []
        );

        foreach ($this->reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $this->{$property->name};
            unset($this->{$property->name});
            $this->transaction->state[$property->name] = $value;
        }
    }

    public function __get(string $key): mixed
    {
        $this->logger->debug('Getting value from transaction with key: {key}', ['key' => $key]);

        return $this->transaction->state[$key];
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
        $this->logger->debug('Attempting to set {key} to {value}', ['key' => $key, 'value' => $value]);
        $this->throw_if_committed();
        if ( ! $this->reflection->hasProperty($key)) {
            $this->logger->critical(
                '{key} is not defined on transactional class and is not stored',
                ['key' => $key]
            );
            throw new InvalidArgumentException(
                "$key does not_exist on ".get_class($this)." is not defined and thus will not be stored."
            );
        }
        $this->transaction->upsert($key, $value);
    }

    /**
     * Throws an exception if this object has been committed.
     *
     * @return void
     * @throws StateAlreadyCommitted
     */
    protected function throw_if_committed(): void
    {
        if ($this->transaction->is_closed) {
            $this->logger->critical('Attempted to modify state after transaction is committed!');
            throw new StateAlreadyCommitted();
        }
    }

    public function __isset(string $key): bool
    {
        $this->logger->debug('Checking {key} is set', ['key' => $key]);

        return isset($this->transaction->state[$key]);
    }

    /**
     * @param string $key
     *
     * @throws StateAlreadyCommitted
     */
    public function __unset(string $key): void
    {
        $this->logger->debug('Deleting {key}', ['key' => $key]);
        $this->throw_if_committed();
        $this->transaction->delete($key);
    }

    /**
     * Commit the transaction.
     *
     * @param array|null $metadata Component specific metadata
     *
     * @throws StateAlreadyCommitted
     * @throws DaprException
     */
    public function commit(?array $metadata = null): void
    {
        $this->logger->debug('Committing transaction');
        $this->throw_if_committed();
        $state_store = self::get_description($this->reflection);
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
                $this->transaction->get_transaction()
            ),
        ];
        if (isset($metadata)) {
            $transaction['metadata'] = $metadata;
        }

        if ( ! empty($transaction['operations'])) {
            $this->client->post("/state/{$state_store->name}/transaction", $transaction);
        }
        $this->transaction->is_closed = true;
    }

    private function get_etag_for_key(string $key): ?string
    {
        return (new class($key, $this) extends StateManager {
            public ?string $etag = '';

            public function __construct(string $key, $obj)
            {
                $etag       = self::$obj_meta[$obj][$key] ?? [];
                $this->etag = $etag['etag'] ?? null;
            }
        })->etag;
    }
}

<?php

namespace Dapr\Actors;

use Dapr\Actors\Internal\Caches\CacheInterface;
use Dapr\Actors\Internal\Caches\FileCache;
use Dapr\Client\DaprClient;
use Dapr\exceptions\DaprException;
use Dapr\exceptions\Http\NotFound;
use Dapr\exceptions\SaveStateFailure;
use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;
use Exception;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * Class ActorRuntime
 *
 * Handles activating, method calls, and deactivating actors.
 *
 * @package Dapr\Actors
 */
class ActorRuntime
{
    public function __construct(
        protected ActorConfig $actor_config,
        protected FactoryInterface $factory,
        protected ContainerInterface $container,
        protected DaprClient $client
    ) {
    }

    /**
     * Call a method on the actor
     *
     * @param IActor $actor The actor to call the method on
     * @param string $method The method to call
     * @param mixed $arg The argument to pass to the method
     *
     * @return mixed The result from the actor
     * @throws ReflectionException
     */
    public function do_method(IActor $actor, string $method, mixed $arg): mixed
    {
        $reflection = new ReflectionClass($actor);
        $method = $reflection->getMethod($method);
        if (empty($arg)) {
            return $method->invoke($actor);
        }

        $parameter = $method->getParameters()[0] ?? null;
        if (empty($parameter)) {
            return $method->invokeArgs($actor, [$arg]);
        }

        return $method->invokeArgs(
            $actor,
            [$parameter->getName() => $this->client->deserializer->detect_from_parameter($parameter, $arg)]
        );
    }

    public function deactivate_actor(IActor $actor, string $dapr_type): void
    {
        $id = $actor->get_id();
        $activation_tracker = hash('sha256', $dapr_type . $id);
        $activation_tracker = rtrim(
                sys_get_temp_dir(),
                DIRECTORY_SEPARATOR
            ) . DIRECTORY_SEPARATOR . 'dapr_' . $activation_tracker;

        $is_activated = file_exists($activation_tracker);

        if ($is_activated) {
            unlink($activation_tracker);
            $actor->on_deactivation();
            FileCache::clear_actor($dapr_type, $actor->get_id());
        }
    }

    /**
     * Resolve an actor, then calls your callback with the actor before committing any state changes
     *
     * @param ActorReference $reference
     * @param callable $loan A callback that takes an IActor
     *
     * @return mixed The result of you callback
     * @throws NotFound
     * @throws SaveStateFailure
     */
    public function resolve_actor(ActorReference $reference, callable $loan): mixed
    {
        try {
            $reflection = $this->locate_actor($reference);
            $this->validate_actor($reflection);
            $states = $this->get_states($reflection, $reference, $this->client);
            $actor = $this->get_actor($reflection, $reference, $states);
            // @codeCoverageIgnoreStart
        } catch (Exception $exception) {
            throw new NotFound('Actor could not be located', previous: $exception);
        }
        // @codeCoverageIgnoreEnd
        $result = $loan($actor);

        try {
            $this->commit($states);
            // @codeCoverageIgnoreStart
        } catch (DependencyException | DaprException | NotFoundException $e) {
            throw new SaveStateFailure('Failed to commit actor state', previous: $e);
        }

        // @codeCoverageIgnoreEnd

        return $result;
    }

    /**
     * Locates an actor implementation
     *
     * @param ActorReference $reference The actor reference
     * @return ReflectionClass
     * @throws NotFound
     * @throws ReflectionException
     */
    protected function locate_actor(ActorReference $reference): ReflectionClass
    {
        $type = $this->actor_config->get_actor_type_from_dapr_type($reference->get_actor_type());
        if (!class_exists($type)) {
            // @codeCoverageIgnoreStart
            $this->client->logger->critical('Unable to locate an actor for {t}', ['t' => $type]);
            throw new NotFound();
            // @codeCoverageIgnoreEnd
        }

        return new ReflectionClass($type);
    }

    /**
     * @param ReflectionClass $reflection
     *
     * @return bool
     * @throws NotFound
     */
    protected function validate_actor(ReflectionClass $reflection): bool
    {
        if ($reflection->implementsInterface('Dapr\Actors\IActor')
            && $reflection->isInstantiable() && $reflection->isUserDefined()) {
            return true;
        }

        // @codeCoverageIgnoreStart
        $this->client->logger->critical('Actor does not implement the IActor interface');
        throw new NotFound();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Retrieves an array of states for a located actor
     *
     * @param ReflectionClass $reflection The class we're loading states for
     * @param ActorReference $reference
     * @return array
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    protected function get_states(
        ReflectionClass $reflection,
        ActorReference $reference,
        DaprClient $client
    ): array {
        $constructor = $reflection->getMethod('__construct');
        $states = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType) {
                $type_name = $type->getName();
                if (class_exists($type_name)) {
                    $reflected_type = new ReflectionClass($type_name);
                    if ($reflected_type->isSubclassOf(ActorState::class)) {
                        $state = $this->container->make($type_name);
                        $this->begin_transaction(
                            $state,
                            $reflected_type,
                            $reference,
                            $client,
                            $this->get_cache_for_actor($reference, $type_name)
                        );

                        $states[$parameter->name] = $state;
                        $this->client->logger?->debug('Found state {t}', ['t' => $type_name]);
                    }
                }
            }
        }

        return $states;
    }

    /**
     * Begins an actor transaction
     *
     * @param ActorState $state The state to begin the transaction on
     * @param ReflectionClass $reflected_type The reflected class
     * @param string $dapr_type The dapr type
     * @param string $actor_id The actor id
     * @param ReflectionClass|null $original The child reflection
     *
     * @throws ReflectionException
     */
    protected function begin_transaction(
        ActorState $state,
        ReflectionClass $reflected_type,
        ActorReference $reference,
        DaprClient $client,
        CacheInterface $cache,
        ?ReflectionClass $original = null
    ): void {
        if ($reflected_type->name !== ActorState::class) {
            $this->begin_transaction(
                $state,
                $reflected_type->getParentClass(),
                $reference,
                $client,
                $cache,
                $original ?? $reflected_type
            );

            return;
        }
        $begin_transaction = $reflected_type->getMethod('begin_transaction');
        $begin_transaction->setAccessible(true);
        $begin_transaction->invoke($state, $reference, $client, $cache);
    }

    protected function get_cache_for_actor(ActorReference $reference, string $type_name): CacheInterface
    {
        return $this->factory->make(
            $this->container->get('dapr.actors.cache'),
            ['reference' => $reference, 'state_name' => $type_name]
        );
    }

    /**
     * Instantiates an actor implementation
     *
     * @param ReflectionClass $reflection
     * @param string $dapr_type
     * @param string $id
     * @param array $states
     *
     * @return IActor
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function get_actor(ReflectionClass $reflection, ActorReference $reference, array $states): IActor
    {
        $states['id'] = $reference->get_actor_id();
        $this->container->set(ActorReference::class, $reference);
        $actor = $this->factory->make($reflection->getName(), $states);
        $activation_tracker = hash('sha256', $reference->get_actor_type() . $reference->get_actor_id());
        $activation_tracker = rtrim(
                sys_get_temp_dir(),
                DIRECTORY_SEPARATOR
            ) . DIRECTORY_SEPARATOR . 'dapr_' . $activation_tracker;

        $is_activated = file_exists($activation_tracker);

        if (!$is_activated) {
            $this->client->logger?->info(
                'Activating {type}||{id}',
                ['type' => $reference->get_actor_type(), 'id' => $reference->get_actor_id()]
            );
            touch($activation_tracker);
            $actor->on_activation();
        }

        return $actor;
    }

    /**
     * @param ActorState[] $states
     *
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function commit(array $states): void
    {
        foreach ($states as $state) {
            $state->save_state();
        }
    }
}

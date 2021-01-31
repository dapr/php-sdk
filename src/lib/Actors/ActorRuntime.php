<?php

namespace Dapr\Actors;

use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\Http\NotFound;
use Dapr\Runtime;
use DI\Container;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionNamedType;

/**
 * The Actor Runtime
 */
class ActorRuntime
{
    public static string $input = 'php://input';
    public static array $actors = [];
    public static array $config = [
        'entities' => [],
    ];

    public function __construct(
        protected LoggerInterface $logger,
        protected ActorConfig $actor_config,
        protected Container $container,
        protected IDeserializer $deserializer,
    ) {
    }

    public function do_method(IActor $actor, string $method, mixed $arg)
    {
        $reflection = new ReflectionClass($actor);
        $method     = $reflection->getMethod($method);
        if (empty($arg)) {
            return $method->invoke($actor);
        }

        $parameter = $method->getParameters()[0] ?? null;
        if (empty($parameter)) {
            return $method->invokeArgs($actor, [$arg]);
        }

        return $method->invokeArgs(
            $actor,
            [$parameter->getName() => $this->deserializer->detect_from_parameter($parameter, $arg)]
        );
    }

    public function deactivate_actor(IActor $actor, string $dapr_type, string $id)
    {
        $activation_tracker = hash('sha256', $dapr_type.$id);
        $activation_tracker = rtrim(
                                  sys_get_temp_dir(),
                                  DIRECTORY_SEPARATOR
                              ).DIRECTORY_SEPARATOR.'dapr_'.$activation_tracker;

        $is_activated = file_exists($activation_tracker);

        if ($is_activated) {
            $actor->on_deactivation();
            unlink($activation_tracker);
        }
    }

    public function resolve_actor(string $dapr_type, string $id, callable $loan): mixed
    {
        $reflection = $this->locate_actor($dapr_type);
        $this->validate_actor($reflection);
        $states = $this->get_states($reflection, $dapr_type, $id);
        $actor  = $this->get_actor($reflection, $dapr_type, $id, $states);
        $result = $loan($actor);
        $this->commit($states);

        return $result;
    }

    protected function locate_actor(string $dapr_type): ReflectionClass
    {
        $type = $this->actor_config->get_actor_type_from_dapr_type($dapr_type);
        if ( ! class_exists($type)) {
            $this->logger->critical('Unable to locate an actor for {t}', ['t' => $type]);
            throw new NotFound();
        }

        return new ReflectionClass($type);
    }

    protected function validate_actor(ReflectionClass $reflection)
    {
        if ($reflection->implementsInterface('Dapr\Actors\IActor')
            && $reflection->isInstantiable() && $reflection->isUserDefined()) {
            return true;
        }

        $this->logger->critical('Actor does not implement the IActor interface');
        throw new NotFound();
    }

    protected function get_states(ReflectionClass $reflection, string $dapr_type, string $id): array
    {
        $constructor = $reflection->getMethod('__construct');
        $states      = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType) {
                $type_name = $type->getName();
                if (class_exists($type_name)) {
                    $reflected_type = new ReflectionClass($type_name);
                    if ($reflected_type->isSubclassOf(ActorState::class)) {
                        $state = $this->container->make($type_name);
                        $this->begin_transaction($state, $reflected_type, $dapr_type, $id);

                        $states[$parameter->name] = $state;
                        Runtime::$logger?->debug('Found state {t}', ['t' => $type_name]);
                    }
                }
            }
        }

        return $states;
    }

    protected function begin_transaction(
        ActorState $state,
        ReflectionClass $reflected_type,
        string $dapr_type,
        string $actor_id,
        ?ReflectionClass $original = null
    ) {
        if ($reflected_type->name !== ActorState::class) {
            $this->begin_transaction(
                $state,
                $reflected_type->getParentClass(),
                $dapr_type,
                $actor_id,
                $original ?? $reflected_type
            );

            return;
        }
        $begin_transaction = $reflected_type->getMethod('begin_transaction');
        $begin_transaction->setAccessible(true);
        $begin_transaction->invoke($state, $dapr_type, $actor_id);
    }

    protected function get_actor(ReflectionClass $reflection, string $dapr_type, string $id, array $states): IActor
    {
        $states['id']       = $id;
        $actor              = $this->container->make($reflection->getName(), $states);
        $activation_tracker = hash('sha256', $dapr_type.$id);
        $activation_tracker = rtrim(
                                  sys_get_temp_dir(),
                                  DIRECTORY_SEPARATOR
                              ).DIRECTORY_SEPARATOR.'dapr_'.$activation_tracker;

        $is_activated = file_exists($activation_tracker);

        if ( ! $is_activated) {
            Runtime::$logger?->info(
                'Activating {type}||{id}',
                ['type' => $dapr_type, 'id' => $id]
            );
            touch($activation_tracker);
            $actor->on_activation();
        }

        return $actor;
    }

    /**
     * @param ActorState[] $states
     */
    protected function commit(array $states)
    {
        foreach ($states as $state) {
            $state->save_state();
        }
    }
}

<?php

namespace Dapr\Actors;

use Dapr\Deserialization\Attributes\Union;
use Dapr\Deserialization\Deserializer;
use Dapr\Formats;
use Dapr\Runtime;
use Dapr\Serialization\Serializer;
use JetBrains\PhpStorm\ArrayShape;
use ReflectionClass;

/**
 * The Actor Runtime
 */
class ActorRuntime
{
    public static $input = 'php://input';
    public static $actors = [];
    public static $config = [
        'entities' => [],
    ];

    #[ArrayShape([
        'type'          => 'string|null',
        'dapr_type'     => 'string',
        'id'            => 'string|int',
        'function'      => 'string',
        'method_name'   => 'string|null',
        'reminder_name' => 'string|null',
        'body'          => 'array',
    ])]
    public static function extract_parts_from_request(
        string $http_method,
        string $uri
    ): ?array {
        if ( ! str_starts_with(haystack: $uri, needle: '/actors')) {
            return null;
        }
        $parts = array_values(array_filter(explode('/', $uri)));

        return [ // add try/catches
            'type'          => self::$actors[$parts[1]] ?? null,
            'dapr_type'     => $parts[1],
            'id'            => $parts[2],
            'function'      => match ($http_method) {
                'DELETE' => 'delete',
                default => $parts[3],
            },
            'method_name'   => $parts[4] ?? null,
            'reminder_name' => $parts[5] ?? null,
            'body'          => match ($http_method) {
                'POST', 'PUT' => json_decode(self::get_input(), true),
                default => null,
            },
        ];
    }

    public static function get_input(): string
    {
        return file_get_contents(self::$input);
    }

    #[ArrayShape(['code' => 'int', 'body' => 'null|array'])]
    public static function handle_invoke(
        #[ArrayShape([
            'type'          => 'string|null',
            'dapr_type'     => 'string',
            'id'            => 'string|int',
            'function'      => 'string',
            'method_name'   => 'string|null',
            'reminder_name' => 'string|null',
            'body'          => 'array',
        ])] array $description
    ): array {
        if ($description['type'] === null || ! class_exists($description['type'])) {
            Runtime::$logger?->critical('Unable to locate an actor for {t}', ['t' => $description['type']]);

            return [
                'code' => 404,
                'body' =>
                    Serializer::as_json(
                        new \UnexpectedValueException("class ${description['type']} not found")
                    ),
            ];
        }

        try {
            $reflection = new ReflectionClass($description['type']);

            /**
             * @var ActorState[] $states
             */
            $states   = self::get_state_types($description['type'], $description['dapr_type'], $description['id']);
            $is_actor = $reflection->implementsInterface('Dapr\Actors\IActor')
                        && $reflection->isInstantiable() && $reflection->isUserDefined();
        } catch (\ReflectionException $ex) {
            Runtime::$logger?->critical('{exception}', ['exception' => $ex]);

            return [
                'code' => 500,
                'body' => Serializer::as_json($ex),
            ];
        }

        if ( ! $is_actor) {
            Runtime::$logger?->critical('Actor does not implement the IActor interface');

            return [
                'code' => 404,
                'body' => Serializer::as_json(new \LogicException('Actor does not implement IActor interface.')),
            ];
        }

        $state_config = null;
        $params       = [$description['id']];

        foreach ($states as $state) {
            $params[] = $state;
        }

        $actor = new $description['type'](...$params);

        $activation_tracker = hash('sha256', $description['dapr_type'].$description['id']);
        $activation_tracker = rtrim(
                                  sys_get_temp_dir(),
                                  DIRECTORY_SEPARATOR
                              ).DIRECTORY_SEPARATOR.'dapr_'.$activation_tracker;

        $is_activated = file_exists($activation_tracker);

        if ( ! $is_activated) {
            Runtime::$logger?->info(
                'Activating {type}||{id}',
                ['type' => $description['dapr_type'], 'id' => $description['id']]
            );
            touch($activation_tracker);
            $actor->on_activation();
        }

        $return = [
            'code' => 200,
        ];

        switch ($description['function']) {
            case 'method':
                switch ($description['method_name']) {
                    case 'remind':
                        Runtime::$logger?->info(
                            'Reminding {t}||{i}',
                            ['t' => $description['dapr_type'], 'i' => $description['id']]
                        );
                        $data = $description['body'];
                        $actor->remind(
                            $description['reminder_name'],
                            json_decode($data['data'], true)
                        );
                        break;
                    case 'timer':
                        Runtime::$logger?->info(
                            'Timer callback {t}||{i}',
                            ['t' => $description['dapr_type'], 'i' => $description['id']]
                        );
                        $data     = $description['body'];
                        $callback = $data['callback'];
                        $args     = $data['data'];
                        self::call_method($reflection->getMethod($callback), $actor, $args);
                        break;
                    default:
                        Runtime::$logger?->info(
                            'Calling {t}||{i}->{m}',
                            [
                                't' => $description['dapr_type'],
                                'i' => $description['id'],
                                'm' => $description['method_name'],
                            ]
                        );

                        $method = $description['method_name'];
                        $args = $description['body'];
                        $result = self::call_method($reflection->getMethod($method), $actor, $args);

                        $return['body'] = Serializer::as_json($result);
                        break;
                }
                break;
            case 'delete':
                Runtime::$logger?->info(
                    'Deactivating {t}||{i}',
                    ['t' => $description['dapr_type'], 'i' => $description['id']]
                );
                $actor->on_deactivation();
                unlink($activation_tracker);
                break;
        }

        foreach ($states as $state) {
            $state->save_state();
        }

        return $return;
    }

    private static function call_method(\ReflectionMethod $method, $actor, $args): mixed {
        if(empty($args)) {
            return $method->invoke($actor);
        }

        $idx = 0;
        foreach($method->getParameters() as $parameter) {
            $p = $parameter->getName();
            if(isset($args[$idx])) {
                $p = $idx;
            }
            $args[$p] = Deserializer::detect_from_parameter($parameter, $args[$p]);
            $idx += 1;
        }

        return $method->invokeArgs($actor, $args);
    }

    /**
     * Read a state type from attributes
     *
     * @param string $type The type to read from.
     *
     * @return ActorState[] The state type definition
     * @throws \ReflectionException
     */
    private static function get_state_types(string $type, string $dapr_type, mixed $id): array
    {
        $reflection  = new ReflectionClass($type);
        $constructor = $reflection->getMethod('__construct');
        $states      = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType) {
                $type_name = $type->getName();
                if (class_exists($type_name)) {
                    $reflected_type = new ReflectionClass($type_name);
                    if ($reflected_type->isSubclassOf(ActorState::class)) {
                        $states[] = new $type_name($dapr_type, $id);
                        Runtime::$logger?->debug('Found state {t}', ['t' => $type_name]);
                    }
                }
            }
        }

        return $states;
    }

    /**
     * Register an actor that this app support.
     *
     * @param string $actor_type The actor to initialize when invoked
     *
     * @throws \ReflectionException
     */
    public static function register_actor(string $actor_type): void
    {
        Runtime::$logger?->debug('Registering {t}', ['t' => $actor_type]);
        $reflected_type             = new ReflectionClass($actor_type);
        $attributes                 = $reflected_type->getAttributes(DaprType::class);
        $dapr_type                  = ($attributes[0] ?? null)?->newInstance()->type ?? $reflected_type->getShortName();
        self::$actors[$dapr_type]   = $actor_type;
        self::$config['entities'][] = $dapr_type;
    }

    /**
     * A duration which specifies how often to scan for actors to deactivate idle actors. Actors that have been idle
     * longer than the actorIdleTimeout will be deactivated.
     *
     * @param \DateInterval $interval The scan interval
     */
    public static function set_scan_interval(\DateInterval $interval): void
    {
        $interval = Formats::normalize_interval($interval);
        Runtime::$logger?->debug('Setting scan interval {i}', ['i' => $interval]);
        self::$config['actorScanInterval'] = $interval;
    }

    /**
     * Specifies how long to wait before deactivating an idle actor. An actor is idle if no actor method calls and no
     * reminders have fired on it.
     *
     * @param \DateInterval $timeout The timeout
     */
    public static function set_idle_timeout(\DateInterval $timeout): void
    {
        $timeout = Formats::normalize_interval($timeout);
        Runtime::$logger?->debug('Setting idle timeout {t}', ['t' => $timeout]);
        self::$config['actorIdleTimeout'] = $timeout;
    }

    /**
     * A duration used when in the process of draining rebalanced actors. This specifies how long to wait for the
     * current active actor method to finish. If there is no current actor method call, this is ignored.
     *
     * @param \DateInterval $timeout The timeout
     */
    public static function set_drain_timeout(\DateInterval $timeout): void
    {
        $timeout = Formats::normalize_interval($timeout);
        Runtime::$logger?->debug('Setting drain timeout {t}', ['t' => $timeout]);
        self::$config['drainOngoingCallTimeout'] = $timeout;
    }

    /**
     * A bool. If true, Dapr will wait for drainOngoingCallTimeout to allow a current actor call to complete before
     * trying to deactivate an actor. If false, do not wait.
     *
     * @param bool $drain Whether to drain active actors
     */
    public static function do_drain_actors(bool $drain)
    {
        Runtime::$logger?->debug('Setting drain mode {m}', ['m' => $drain]);
        self::$config['drainRebalancedActors'] = $drain;
    }

    public static function handle_config(): array
    {
        return [
            'code' => 200,
            'body' => json_encode(self::$config),
        ];
    }
}

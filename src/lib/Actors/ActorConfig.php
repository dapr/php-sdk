<?php

namespace Dapr\Actors;

use Dapr\Actors\Attributes\DaprType;
use Dapr\Formats;
use Dapr\Serialization\ISerializer;
use Dapr\Serialization\Serializers\ISerialize;
use DateInterval;
use JetBrains\PhpStorm\ArrayShape;
use LogicException;
use ReflectionClass;
use ReflectionException;

/**
 * Class ActorConfig
 *
 * Handles configuration which is called on /dapr/config
 *
 * @package Dapr\Actors
 */
class ActorConfig implements ISerialize
{
    /**
     * ActorConfig constructor.
     *
     * @param array $actor_name_to_type An array of dapr types to concrete types
     * @param DateInterval|null $idle_timeout How long an actor may be idle before being deactivated
     * @param DateInterval|null $scan_interval How often to scan for actors to deactivate
     * @param DateInterval|null $drain_timeout How long to wait when draining, before ending the actor
     * @param bool|null $drain_enabled Whether draining actors is enabled
     */
    public function __construct(
        protected array $actor_name_to_type = [],
        protected DateInterval|null $idle_timeout = null,
        protected DateInterval|null $scan_interval = null,
        protected DateInterval|null $drain_timeout = null,
        protected bool|null $drain_enabled = null
    ) {
    }

    /**
     * Given a Dapr Type, returns the name of a concrete implementation
     *
     * @param string $dapr_type The dapr type
     *
     * @return string|null The concrete type, or null if not found
     * @throws ReflectionException | LogicException
     */
    public function get_actor_type_from_dapr_type(string $dapr_type): string|null
    {
        if($this->actor_name_to_type[$dapr_type] ?? false) return $this->actor_name_to_type[$dapr_type];

        $actors = array_combine($this->get_supported_actors(), $this->actor_name_to_type);

        return $actors[$dapr_type] ?? null;
    }

    /**
     * @return array An array of dapr types
     * @throws ReflectionException | LogicException
     * @codeCoverageIgnore
     */
    public function get_supported_actors(): array
    {
        $actors = array_map(
            fn($type) => ((new ReflectionClass($type))->getAttributes(DaprType::class) ?: throw new LogicException(
                "$type is missing DaprType attribute"
            ))[0]->newInstance()->type,
            $this->actor_name_to_type
        );

        return $actors;
    }

    /**
     * @return DateInterval|null The timeout or null for the framework default
     */
    public function get_idle_timeout(): DateInterval|null
    {
        return $this->idle_timeout ?? null;
    }

    /**
     * @return DateInterval|null The interval or null for the framework default
     */
    public function get_scan_interval(): DateInterval|null
    {
        return $this->scan_interval ?? null;
    }

    /**
     * @return DateInterval|null The timeout or null for the framework default
     */
    public function get_drain_timeout(): DateInterval|null
    {
        return $this->drain_timeout ?? null;
    }

    /**
     * @return bool|null Whether draining is enabled, or null for the framework default
     */
    public function drain_enabled(): bool|null
    {
        return $this->drain_enabled ?? null;
    }

    #[ArrayShape([
        'entities'                => "",
        'drainRebalancedActors'   => "",
        'drainOngoingCallTimeout' => "mixed",
        'actorScanInterval'       => "mixed",
        'actorIdleTimeout'        => "mixed",
    ])] public function serialize(
        mixed $value,
        ISerializer $serializer
    ): array {
        $return = [
            'entities' => $value->get_supported_actors(),
        ];
        if ($a = $value->get_idle_timeout()) {
            $return['actorIdleTimeout'] = Formats::normalize_interval($a);
        }
        if ($a = $value->get_scan_interval()) {
            $return['actorScanInterval'] = Formats::normalize_interval($a);
        }
        if ($a = $value->get_drain_timeout()) {
            $return['drainOngoingCallTimeout'] = Formats::normalize_interval($a);
        }
        if ($a = $value->drain_enabled()) {
            $return['drainRebalancedActors'] = $a;
        }

        return $return;
    }
}

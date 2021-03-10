<?php

namespace Dapr\Actors;

use Dapr\Actors\Attributes\DaprType;
use LogicException;
use ReflectionClass;

/**
 * Class ActorReference
 * @package Dapr\Actors
 */
final class ActorReference implements IActorReference
{
    public function __construct(public string $actor_id, public string $actor_type)
    {
    }

    /**
     * @inheritDoc
     */
    public static function get(mixed $actor): IActorReference
    {
        $id = $actor?->get_id();

        if ($id === null) {
            throw new LogicException('actor(proxy) must implement get_id()');
        }

        $type_attribute = self::get_dapr_type($actor);

        return new ActorReference($id, $type_attribute->type);
    }

    private static function get_dapr_type(object|string $type): DaprType
    {
        $reflector = new ReflectionClass($type);
        /**
         * @var DaprType|null $type_attribute
         */
        $type_attribute = ($reflector->getAttributes(DaprType::class)[0] ?? null)?->newInstance();

        if ($type_attribute === null) {
            throw new LogicException('Missing DaprType attribute on '.is_object($type) ? $type::class : $type);
        }

        return $type_attribute;
    }

    public static function get_from_interface(string $id, string $interface): IActorReference
    {
        return new ActorReference($id, self::get_dapr_type($interface)->type);
    }

    /**
     * @inheritDoc
     */
    public function get_id(): string
    {
        return $this->actor_id;
    }

    /**
     * @inheritDoc
     */
    public function get_type(): string
    {
        return $this->get_type();
    }
}

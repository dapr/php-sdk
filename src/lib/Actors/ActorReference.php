<?php

namespace Dapr\Actors;

use Dapr\Actors\Attributes\DaprType;
use Dapr\Actors\Generators\IGenerateProxy;
use Dapr\Actors\Generators\ProxyFactory;
use DI\Container;
use LogicException;
use ReflectionClass;

/**
 * Class ActorReference
 * @package Dapr\Actors
 */
final class ActorReference implements IActorReference
{
    public function __construct(public string $interface, public ActorAddress $address)
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

        $dapr_type = $actor->DAPR_TYPE ?? self::get_dapr_type($actor)->type;

        $address = new ActorAddress($id, $dapr_type);
        $interface = $actor->IMPLEMENTED_INTERFACE ?? $actor::class;

        return new self($interface, $address);
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

    /**
     * @inheritDoc
     */
    public static function bind(string $id, string $interface): IActorReference
    {
        return new self($interface, new ActorAddress($id, self::get_dapr_type($interface)->type));
    }

    /**
     * @inheritDoc
     */
    public function get_address(): ActorAddress
    {
        return $this->get_address();
    }

    /**
     * @inheritDoc
     */
    public function get_proxy(ProxyFactory $proxy_factory): mixed
    {
        return $proxy_factory->get_generator($this->interface, $this->address->actor_type)->get_proxy($this->address->id);
    }
}

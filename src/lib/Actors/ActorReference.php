<?php

namespace Dapr\Actors;

use Dapr\Actors\Attributes\DaprType;

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
        $id        = $actor?->get_id();

        if($id === null) {
            throw new \LogicException('actor(proxy) must implement get_id()');
        }

        $reflector = new \ReflectionClass($actor);
        /**
         * @var $type_attribute DaprType
         */
        $type_attribute = ($reflector->getAttributes(DaprType::class)[0] ?? null)?->newInstance();

        if ($type_attribute === null) {
            throw new \LogicException('Missing DaprType attribute on '.$actor::class);
        }

        return new ActorReference($id, $type_attribute->type);
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

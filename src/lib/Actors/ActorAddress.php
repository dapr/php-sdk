<?php

namespace Dapr\Actors;

use JetBrains\PhpStorm\Pure;

/**
 * Class ActorReference
 * @package Dapr\Actors
 */
final class ActorAddress
{
    private string $_id;
    private string $_actor_type;

    public function __construct(public string $id, public string $actor_type)
    {
        // make this an immutable type
        $this->_id = $id;
        unset($this->id);

        $this->_actor_type = $actor_type;
        unset($this->_actor_type);
    }

    #[Pure] public function __get(string $key): string
    {
        return match ($key) {
            'id' => $this->_id,
            'actor_type' => $this->_actor_type,
            default => throw new \RuntimeException('Not a valid property on an ActorAddress: '.$key),
        };
    }

    public function __set(string $key, mixed $_): void
    {
        throw new \LogicException('Cannot mutate an ActorAddress');
    }
}

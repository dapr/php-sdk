<?php

namespace Dapr\Actors;

use Dapr\Actors\Generators\ProxyFactory;

/**
 * Class IActorReference
 * @package Dapr\Actors
 */
interface IActorReference
{
    /**
     * Get a reference from a given actor that implements IActor
     *
     * @param IActor $actor The actor or actor interface to extract the reference from
     *
     * @return IActorReference The actor's reference
     */
    public static function get(mixed $actor): IActorReference;

    /**
     * Get an actor reference bound to a given interface.
     *
     * @param string $interface The interface of the actor
     * @param ProxyFactory $proxy_factory The proxy factory to use
     *
     * @return IActor The actor's reference
     */
    public function bind(string $interface, ProxyFactory $proxy_factory): mixed;

    /**
     * @return string The actor id
     */
    public function get_actor_id(): string;

    /**
     * @return string The actor type
     */
    public function get_actor_type(): string;
}

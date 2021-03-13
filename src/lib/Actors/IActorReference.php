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
     * @param string $id The id of the actor
     * @param string $interface The interface of the actor
     *
     * @return IActorReference The actor's reference
     */
    public static function bind(string $id, string $interface): IActorReference;

    /**
     * Get the actor address
     *
     * @return ActorAddress The actor address
     */
    public function get_address(): ActorAddress;

    /**
     * Get a proxy for communicating with the actor
     *
     * @param ProxyFactory $proxy_factory The actor proxy factory.
     *
     * @return IActor An actor proxy
     */
    public function get_proxy(ProxyFactory $proxy_factory): mixed;
}

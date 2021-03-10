<?php

namespace Dapr\Actors;

/**
 * Class IActorReference
 * @package Dapr\Actors
 */
interface IActorReference
{
    /**
     * Get a reference from a given actor that implements IActor
     *
     * @param IActor $actor The actor to extract the reference from
     *
     * @return IActorReference The actor's reference
     */
    public static function get(IActor $actor): IActorReference;

    /**
     * Get the actor id
     *
     * @return string The actor id
     */
    public function get_id(): string;

    /**
     * Get the dapr type of the actor
     *
     * @return string The dapr type
     */
    public function get_type(): string;
}

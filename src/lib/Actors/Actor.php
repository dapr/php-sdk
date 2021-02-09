<?php

namespace Dapr\Actors;

/**
 * Class Actor
 *
 * A base class to simplify user implementations.
 *
 * @package Dapr\Actors
 * @codeCoverageIgnore Not important
 */
abstract class Actor implements IActor
{
    use ActorTrait;

    public function __construct(protected string $id)
    {
    }

    public function get_id(): mixed
    {
        return $this->id;
    }

    public function remind(string $name, Reminder $data): void
    {
    }

    public function on_activation(): void
    {
    }

    public function on_deactivation(): void
    {
    }
}

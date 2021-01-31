<?php

namespace Dapr\Actors;

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

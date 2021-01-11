<?php

namespace Fixtures;

use Dapr\Actors\Actor;
use Dapr\Actors\ActorState;
use Dapr\Actors\DaprType;
use Dapr\Actors\IActor;

#[DaprType('TestActor')]
interface ITestActor extends IActor
{
    public function a_function($value): bool;
}

class TestActorState extends ActorState
{
    public string $value = "";
}

#[DaprType('TestActor')]
class ActorClass implements ITestActor
{
    use Actor;

    /**
     * ActorClass constructor.
     *
     * @param string $id
     * @param ActorState $state
     */
    public function __construct(private string $id, private TestActorState $state)
    {
    }

    public function a_function($value): bool
    {
        $this->state->value = $value;

        return true;
    }

    function get_id(): mixed
    {
        return $this->id;
    }

    function remind(string $name, $data): void
    {
        // TODO: Implement remind() method.
    }

    function on_activation(): void
    {
        // TODO: Implement on_activation() method.
    }

    function on_deactivation(): void
    {
        // TODO: Implement on_deactivation() method.
    }
}

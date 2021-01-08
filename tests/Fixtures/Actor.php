<?php

namespace Fixtures;

use Dapr\Actors\Actor;
use Dapr\Actors\ActorState;
use Dapr\Actors\DaprType;
use Dapr\Actors\IActor;
use Dapr\State\State;

#[DaprType('TestActor')]
interface ITestActor extends IActor
{
    public function a_function($value): bool;
}

class TestActorState
{
    public string $value = "";
}

#[DaprType('TestActor')]
#[ActorState('store', TestActorState::class)]
class ActorClass implements ITestActor
{
    use Actor;

    /**
     * ActorClass constructor.
     *
     * @param string $id
     * @param ActorState $state
     */
    public function __construct(private string $id, private $state)
    {
    }

    public function a_function($value): bool
    {
        $this->state->value = $value;

        return true;
    }

    function get_id(): mixed
    {
        // TODO: Implement get_id() method.
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

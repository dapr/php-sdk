<?php

namespace Fixtures;

use Dapr\Actors\Actor;
use Dapr\Actors\ActorState;
use Dapr\Actors\Attributes\DaprType;
use Dapr\Deserialization\Attributes\ArrayOf;
use Dapr\Deserialization\Attributes\AsClass;
use JetBrains\PhpStorm\Pure;
use SimpleObject;

#[DaprType('TestActor')]
interface ITestActor
{
    #[ArrayOf('string')]
    public function a_function(#[AsClass(SimpleObject::class)] $value): array;
}

class TestActorState extends ActorState
{
    public string $value = "";
}

#[DaprType('TestActor')]
class ActorClass extends Actor
{
    /**
     * ActorClass constructor.
     *
     * @param string $id
     * @param TestActorState $state
     */
    #[Pure] public function __construct(protected string $id, private TestActorState $state)
    {
        parent::__construct($id);
    }

    public function a_function($value): bool
    {
        $this->state->value = $value;

        return true;
    }

    function get_id(): string
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

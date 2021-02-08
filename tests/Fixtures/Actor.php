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
    public function a_function(
        #[AsClass(SimpleObject::class)] $value
    ): array;

    public function empty_func();
}

class TestActorState extends ActorState
{
    public string $value = "";
}

#[DaprType('TestActor')]
class ActorClass extends Actor implements ITestActor
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

    public function a_function($value): array
    {
        $this->state->value = $value;

        return [$value];
    }

    function get_id(): string
    {
        return $this->id;
    }

    public function empty_func()
    {
        return true;
    }
}

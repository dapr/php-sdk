<?php

use Dapr\Actors\Actor;
use Dapr\Actors\ActorState;
use Dapr\Actors\Attributes\DaprType;
use Dapr\Actors\Reminder;
use JetBrains\PhpStorm\Pure;

#[DaprType('SimpleActor')]
interface ISimpleActor
{
    function increment($amount = 1);

    function get_count(): int;

    function set_object(SimpleObject $object): void;

    function get_object(): SimpleObject;

    function a_function(): bool;
}

class SimpleObject
{
    public string $foo = "";
    public array $bar = [];
}

class SimpleActorState extends ActorState
{
    /**
     * @property int
     */
    public int $count = 0;

    public SimpleObject $complex_object;
}

#[DaprType('SimpleActor')]
class SimpleActor extends Actor
{
    /**
     * SimpleActor constructor.
     *
     * @param string $id
     * @param SimpleActorState $state
     */
    #[Pure] public function __construct(protected string $id, private SimpleActorState $state)
    {
        parent::__construct($id);
    }

    public function remind(string $name, Reminder $data): void
    {
        switch ($name) {
            case 'increment':
                $this->increment($data->data['amount'] ?? 1);
                break;
        }
    }

    /**
     * @param int $amount
     *
     * @return void
     */
    public function increment(int $amount = 1)
    {
        $this->state->count += $amount;
    }

    public function get_count(): int
    {
        return $this->state->count ?? 0;
    }

    function set_object(SimpleObject $object): void
    {
        $this->state->complex_object = $object;
    }

    function get_object(): SimpleObject
    {
        return $this->state->complex_object;
    }

    function a_function(): bool
    {
        return true;
    }
}

<?php

namespace Actor;

use Client\ICounter;
use Dapr\Actors\Actor;
use Dapr\Actors\ActorState;
use Dapr\Actors\DaprType;
use Dapr\consistency\StrongFirstWrite;

#[DaprType('Counter')]
#[ActorState('statestore', State::class)]
class Counter implements ICounter {

    use Actor;

    /**
     * Counter constructor.
     *
     * @param mixed $id The actor id
     * @param State $state
     */
    public function __construct(private mixed $id, private $state)
    {
    }

    function get_id(): mixed
    {
        return $this->id;
    }

    function remind(string $name, $data): void
    {
    }

    function on_activation(): void
    {
        error_log('Actor woke up: ' . $this->get_id());
    }

    function on_deactivation(): void
    {
        error_log('Actor going to sleep: ' . $this->get_id());
    }

    public function get_count(): int
    {
        return $this->state->count;
    }

    public function increment(int $amount): void
    {
        $this->state->count += $amount;
    }
}

<?php

namespace Actor;

use Client\ICounter;
use Dapr\Actors\Actor;
use Dapr\Actors\ActorState;
use Dapr\consistency\StrongFirstWrite;

class Counter implements ICounter {

    use Actor;
    use ActorState;

    public const STATE_TYPE = [
        'store' => 'statestore',
        'type' => State::class,
        'consistency' => StrongFirstWrite::class,
        'metadata' => []
    ];

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
    }

    function on_deactivation(): void
    {
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

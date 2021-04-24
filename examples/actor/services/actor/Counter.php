<?php

use Dapr\Actors\Actor;

/**
 * Class Counter
 */
#[\Dapr\Actors\Attributes\DaprType('Counter')]
class Counter extends Actor implements ICounter
{
    public function __construct(string $id, private State $state) {
        parent::__construct($id);
    }

    function get_count(): int
    {
        return $this->state->count;
    }

    function increment_and_get(): int
    {
        return $this->state->count += 1;
    }
}

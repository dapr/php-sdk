<?php

namespace Client;

use Dapr\Actors\DaprType;
use Dapr\Actors\IActor;

#[DaprType('Counter')]
interface ICounter extends IActor {
    /**
     * @return int The current count
     */
    public function get_count(): int;

    /**
     * @param int $amount Amount to increment by
     */
    public function increment(int $amount): void;
}

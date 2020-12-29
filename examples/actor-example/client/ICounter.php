<?php

namespace Client;

use Dapr\Actors\IActor;

interface ICounter extends IActor {
    public const DAPR_TYPE = 'Counter';

    /**
     * @return int The current count
     */
    public function get_count(): int;

    /**
     * @param int $amount Amount to increment by
     */
    public function increment(int $amount): void;
}

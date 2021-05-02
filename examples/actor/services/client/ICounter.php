<?php

/**
 * Interface ICounter
 */
#[\Dapr\Actors\Attributes\DaprType('Counter')]
interface ICounter
{
    /**
     * @return int The current count
     */
    function get_count(): int;

    /**
     * @return int The current count after incrementing
     */
    function increment_and_get(): int;
}

<?php

namespace Dapr\Actors;

/**
 * Class ReentrantConfig
 * @package Dapr\Actors
 */
class ReentrantConfig
{
    public function __construct(public int|null $max_stack_depth = null)
    {
    }
}

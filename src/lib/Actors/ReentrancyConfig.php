<?php

namespace Dapr\Actors;

/**
 * Class ReentrencyConfig
 * @package Dapr\Actors
 */
class ReentrancyConfig
{
    public function __construct(public bool $enabled, public int|null $max_stack_depth = null)
    {
    }
}

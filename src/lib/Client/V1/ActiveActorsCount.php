<?php

namespace Dapr\Client\V1;

/**
 * Class ActiveActorsCount
 * @package Dapr\Client\V1
 */
class ActiveActorsCount
{
    public function __construct(public string $type, public int $count)
    {
    }
}

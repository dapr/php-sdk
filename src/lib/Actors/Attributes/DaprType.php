<?php

namespace Dapr\Actors\Attributes;

use Attribute;

/**
 * Class DaprType
 *
 * Annotates a class as an actor that implements a specific Dapr Type.
 *
 * @package Dapr\Actors\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS)]
class DaprType
{
    public function __construct(public string $type)
    {
    }
}

<?php

namespace Dapr\Actors\Attributes;

use Attribute;

/**
 * Class DaprType
 * @package Dapr\Actors\Attributes
 */
#[Attribute(Attribute::TARGET_CLASS)]
class DaprType
{
    public function __construct(public string $type)
    {
    }
}

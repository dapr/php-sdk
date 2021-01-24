<?php

namespace Dapr\Actors\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class DaprType
{
    public function __construct(public string $type)
    {
    }
}

<?php

namespace Dapr\Actors;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class DaprType
{
    public function __construct(public string $type)
    {
    }
}

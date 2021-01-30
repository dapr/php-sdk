<?php

namespace Dapr\Deserialization\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class AsClass
{
    public function __construct(public string $type)
    {
    }
}

<?php

namespace Dapr\Deserialization\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ArrayOf
{
    public function __construct(public string $type)
    {
    }
}

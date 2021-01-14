<?php

namespace Dapr\Deserialization\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ArrayOf
{
    public function __construct(public string $type)
    {
    }
}

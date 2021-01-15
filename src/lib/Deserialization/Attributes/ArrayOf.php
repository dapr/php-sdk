<?php

namespace Dapr\Deserialization\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::TARGET_METHOD)]
final class ArrayOf
{
    public function __construct(public string $type)
    {
    }
}

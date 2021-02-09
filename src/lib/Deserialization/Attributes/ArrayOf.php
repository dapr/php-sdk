<?php

namespace Dapr\Deserialization\Attributes;

use Attribute;

/**
 * Class ArrayOf
 *
 * Indicates that a value is an array of a specific type
 *
 * @package Dapr\Deserialization\Attributes
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::TARGET_METHOD)]
final class ArrayOf
{
    public function __construct(public string $type)
    {
    }
}

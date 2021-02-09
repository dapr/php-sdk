<?php

namespace Dapr\Deserialization\Attributes;

use Attribute;

/**
 * Class AsClass
 *
 * Indicates that a value is a type of a specific class
 *
 * @package Dapr\Deserialization\Attributes
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::TARGET_METHOD)]
class AsClass
{
    public function __construct(public string $type)
    {
    }
}

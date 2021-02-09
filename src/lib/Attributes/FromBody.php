<?php

namespace Dapr\Attributes;

use Attribute;

/**
 * Class FromBody
 * @package Dapr\Attributes
 *
 * Indicates that the parameter should be deserialized from the body
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class FromBody
{
}

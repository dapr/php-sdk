<?php

namespace Dapr\Attributes;

use Attribute;

/**
 * Class FromRoute
 * @package Dapr\Attributes
 *
 * Indicates that a parameter should come from the route
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class FromRoute
{
}

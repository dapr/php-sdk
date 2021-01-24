<?php

namespace Dapr\Serialization\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY & \Attribute::TARGET_CLASS & \Attribute::TARGET_FUNCTION & \Attribute::TARGET_METHOD)]
class AlwaysObject
{
}

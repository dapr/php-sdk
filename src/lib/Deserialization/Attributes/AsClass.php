<?php

namespace Dapr\Deserialization\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class AsClass {
    public function __construct(public string $type) {}
}

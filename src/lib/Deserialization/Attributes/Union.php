<?php

namespace Dapr\Deserialization\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY & \Attribute::TARGET_PARAMETER)]
class Union {
    public array $types = [];
    public function __construct(public $discriminator, string ...$types) {
        $this->types = $types;
    }
}

<?php

namespace Dapr\State\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class StateStore {
    public function __construct(public string $name, public string $consistency) {}
}

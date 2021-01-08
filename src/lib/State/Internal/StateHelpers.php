<?php

namespace Dapr\State\Internal;

use Dapr\State\Attributes\StateStore;
use ReflectionClass;

trait StateHelpers {
    protected static function get_description(ReflectionClass $reflection): StateStore
    {
        foreach ($reflection->getAttributes(StateStore::class) as $attribute) {
            return $attribute->newInstance();
        }
        throw new \LogicException('Tried to load state without a Dapr\State\Attributes\StateStore attribute');
    }
}

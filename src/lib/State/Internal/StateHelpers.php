<?php

namespace Dapr\State\Internal;

use Dapr\State\Attributes\StateStore;
use LogicException;
use ReflectionClass;

trait StateHelpers
{
    /**
     * Get the StateStore attribute for the current class.
     *
     * @param ReflectionClass $reflection
     *
     * @return StateStore
     */
    protected static function get_description(ReflectionClass $reflection): StateStore
    {
        foreach ($reflection->getAttributes(StateStore::class) as $attribute) {
            return $attribute->newInstance();
        }
        throw new LogicException('Tried to load state without a Dapr\State\Attributes\StateStore attribute');
    }
}

<?php

namespace Dapr\Actors\Generators;

use InvalidArgumentException;

abstract class ProxyModes
{
    public const GENERATED = 0;
    public const GENERATED_CACHED = 1;
    public const DYNAMIC = 2;
    public const ONLY_EXISTING = 4;

    public static function get_generator(int $mode, $interface, $dapr_type): IGenerateProxy
    {
        global $dapr_container;
        $params = ['interface' => $interface, 'dapr_type' => $dapr_type];

        return match ($mode) {
            ProxyModes::DYNAMIC => $dapr_container->make(DynamicGenerator::class, $params),
            ProxyModes::GENERATED_CACHED => $dapr_container->make(CachedGenerator::class, $params),
            ProxyModes::GENERATED => $dapr_container->make(FileGenerator::class, $params),
            ProxyModes::ONLY_EXISTING => $dapr_container->make(ExistingOnly::class, $params),
            default => throw new InvalidArgumentException('mode must be a supported mode'),
        };
    }
}

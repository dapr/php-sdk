<?php

namespace Dapr\Actors\Generators;

abstract class ProxyModes {
    public const GENERATED = 0;
    public const GENERATED_CACHED = 1;
    public const DYNAMIC = 2;
    public const ONLY_EXISTING = 4;

    public static function get_generator(int $mode, $interface, $dapr_type): IGenerateProxy {
        global $dapr_container;
        $params = ['interface' => $interface, 'dapr_type' => $dapr_type];
        switch($mode) {
            case ProxyModes::DYNAMIC:
                return $dapr_container->make(DynamicGenerator::class, $params);
            case ProxyModes::GENERATED_CACHED:
                return $dapr_container->make(CachedGenerator::class, $params);
            case ProxyModes::GENERATED:
                return $dapr_container->make(FileGenerator::class, $params);
            case ProxyModes::ONLY_EXISTING:
                return $dapr_container->make(ExistingOnly::class, $params);
            default:
                throw new \InvalidArgumentException('mode must be a supported mode');
        }
    }
}

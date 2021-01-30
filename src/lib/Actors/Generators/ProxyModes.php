<?php

namespace Dapr\Actors\Generators;

abstract class ProxyModes {
    public const GENERATED = 0;
    public const GENERATED_CACHED = 1;
    public const DYNAMIC = 2;
    public const ONLY_EXISTING = 4;

    public static function get_generator(int $mode, $interface, $dapr_type): IGenerateProxy {
        switch($mode) {
            case ProxyModes::DYNAMIC:
                return new DynamicGenerator($interface, $dapr_type);
            case ProxyModes::GENERATED_CACHED:
                return new CachedGenerator($interface, $dapr_type);
            case ProxyModes::GENERATED:
                return new FileGenerator($interface, $dapr_type);
            case ProxyModes::ONLY_EXISTING:
                return new ExistingOnly($interface, $dapr_type);
            default:
                throw new \InvalidArgumentException('mode must be a supported mode');
        }
    }
}

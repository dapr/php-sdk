<?php

namespace Dapr\Actors\Generators;

use DI\Annotation\Inject;
use DI\Container;
use InvalidArgumentException;

class ProxyFactory
{
    public const GENERATED = 0;
    public const GENERATED_CACHED = 1;
    public const DYNAMIC = 2;
    public const ONLY_EXISTING = 4;

    /**
     * ProxyFactory constructor.
     *
     * @param Container $container
     * @param int $mode
     */
    public function __construct(private Container $container, private int $mode) {}

    public function get_generator($interface, $dapr_type): IGenerateProxy
    {
        $params = ['interface' => $interface, 'dapr_type' => $dapr_type];

        return match ($this->mode) {
            ProxyFactory::DYNAMIC => $this->container->make(DynamicGenerator::class, $params),
            ProxyFactory::GENERATED_CACHED => $this->container->make(CachedGenerator::class, $params),
            ProxyFactory::GENERATED => $this->container->make(FileGenerator::class, $params),
            ProxyFactory::ONLY_EXISTING => $this->container->make(ExistingOnly::class, $params),
            default => throw new InvalidArgumentException('mode must be a supported mode'),
        };
    }
}

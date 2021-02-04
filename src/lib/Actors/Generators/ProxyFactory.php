<?php

namespace Dapr\Actors\Generators;

use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;
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
     * @param FactoryInterface $factory
     * @param int $mode
     */
    public function __construct(private FactoryInterface $factory, private int $mode)
    {
    }

    /**
     * @param $interface
     * @param $dapr_type
     *
     * @return IGenerateProxy
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function get_generator(string $interface, string $dapr_type): IGenerateProxy
    {
        $params = ['interface' => $interface, 'dapr_type' => $dapr_type];

        return match ($this->mode) {
            ProxyFactory::DYNAMIC => $this->factory->make(DynamicGenerator::class, $params),
            ProxyFactory::GENERATED_CACHED => $this->factory->make(CachedGenerator::class, $params),
            ProxyFactory::GENERATED => $this->factory->make(FileGenerator::class, $params),
            ProxyFactory::ONLY_EXISTING => $this->factory->make(ExistingOnly::class, $params),
            default => throw new InvalidArgumentException('mode must be a supported mode'),
        };
    }
}

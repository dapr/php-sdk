<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\IActor;
use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;
use JetBrains\PhpStorm\Pure;
use LogicException;
use Nette\PhpGenerator\Method;
use Psr\Container\ContainerInterface;

class ExistingOnly extends GenerateProxy
{
    #[Pure] public function __construct(
        string $interface,
        string $dapr_type,
        FactoryInterface $factory,
        ContainerInterface $container
    ) {
        parent::__construct($interface, $dapr_type, $factory, $container);
    }

    /**
     * @param string $id
     *
     * @return IActor|mixed
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function get_proxy(string $id)
    {
        $proxy = $this->factory->make($this->get_full_class_name());
        $proxy->id = $id;

        return $proxy;
    }

    protected function generate_failure_method(Method $method): void
    {
        throw new LogicException();
    }

    protected function generate_proxy_method(Method $method, string $id): void
    {
        throw new LogicException();
    }

    protected function generate_get_id(Method $method, string $id): void
    {
        throw new LogicException();
    }
}

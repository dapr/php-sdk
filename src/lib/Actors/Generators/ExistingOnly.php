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

/**
 * Class ExistingOnly
 *
 * Only allows existing proxies to be used. Does not generate a proxy.
 *
 * @package Dapr\Actors\Generators
 */
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

    /**
     * @codeCoverageIgnore Never happens
     * @param Method $method
     */
    protected function generate_failure_method(Method $method): void
    {
        throw new LogicException();
    }

    /**
     * @codeCoverageIgnore Never happens
     * @param Method $method
     * @param string $id
     */
    protected function generate_proxy_method(Method $method, string $id): void
    {
        throw new LogicException();
    }

    /**
     * @codeCoverageIgnore Never happens
     * @param Method $method
     * @param string $id
     */
    protected function generate_get_id(Method $method, string $id): void
    {
        throw new LogicException();
    }
}

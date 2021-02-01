<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\IActor;
use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use JetBrains\PhpStorm\Pure;
use LogicException;
use Nette\PhpGenerator\Method;

class ExistingOnly extends GenerateProxy
{
    #[Pure] public function __construct(string $interface, string $dapr_type, Container $container)
    {
        parent::__construct($interface, $dapr_type, $container);
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
        $proxy     = $this->container->make($this->get_full_class_name());
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

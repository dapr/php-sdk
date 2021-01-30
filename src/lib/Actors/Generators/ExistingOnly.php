<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\IActor;
use DI\Container;
use Nette\PhpGenerator\Method;

class ExistingOnly extends GenerateProxy {
    public function __construct(string $interface, string $dapr_type, Container $container)
    {
        parent::__construct($interface, $dapr_type, $container);
    }

    protected function generate_failure_method(Method $method)
    {
        throw new \LogicException();
    }

    protected function generate_proxy_method(Method $method, string $id)
    {
        throw new \LogicException();
    }

    protected function generate_get_id(Method $method, string $id)
    {
        throw new \LogicException();
    }

    public function get_proxy(string $id)
    {
        $proxy = $this->container->make($this->get_full_class_name());
        $proxy->id = $id;
        return $proxy;
    }
}

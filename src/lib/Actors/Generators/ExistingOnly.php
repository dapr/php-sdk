<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\IActor;
use Nette\PhpGenerator\Method;

class ExistingOnly extends GenerateProxy {
    public function __construct(protected string $interface, protected string $dapr_type)
    {
        parent::__construct($interface, $dapr_type);
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
        $proxy = new ($this->get_full_class_name());
        $proxy->id = $id;
        return $proxy;
    }
}

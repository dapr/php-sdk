<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\Internal\InternalProxy;
use Dapr\DaprClient;
use Dapr\Deserialization\Deserializer;
use Dapr\Serialization\Serializer;
use LogicException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

class DynamicGenerator extends GenerateProxy
{
    private InternalProxy $current_proxy;

    public function __construct(protected string $interface, protected string $dapr_type)
    {
        parent::__construct($this->interface, $this->dapr_type);
    }

    protected function generate_failure_method(Method $method): callable
    {
        return function () use ($method) {
            throw new LogicException("Cannot call {$method->getName()} from outside the actor.");
        };
    }

    protected function generate_proxy_method(Method $method, string $id): callable
    {
        return function (...$params) use ($method, $id) {
            if ( ! empty($params)) {
                $params = Serializer::as_array($params[0]);
            }

            $result = DaprClient::post(
                DaprClient::get_api("/actors/{$this->dapr_type}/$id/method/{$method->getName()}"),
                Serializer::as_array($params)
            );

            $result->data = Deserializer::detect_from_parameter(
                $method,
                $result->data
            );

            return $result->data;
        };
    }

    protected function generate_get_id(Method $method, string $id): callable
    {
        return function () use ($id) {
            return $id;
        };
    }

    public function get_proxy(string $id): InternalProxy
    {
        $this->current_proxy = new InternalProxy();
        $interface           = ClassType::from($this->interface);
        $methods             = $this->get_methods($interface);
        $this->current_proxy->DAPR_TYPE = $this->dapr_type;
        foreach ($methods as $method) {
            $this->current_proxy->{$method->getName()} = $this->generate_method($method, $id);
        }

        return $this->current_proxy;
    }
}

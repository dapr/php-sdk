<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\IActor;
use DI\Container;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

abstract class GenerateProxy implements IGenerateProxy
{

    public function __construct(
        protected string $interface,
        protected string $dapr_type,
        protected Container $container
    ) {
    }

    protected function generate_method(Method $method, string $id)
    {
        switch ($method->getName()) {
            case 'remind':
            case 'on_activation':
            case 'on_deactivation':
                return $this->generate_failure_method($method);
            case 'create_reminder':
            case 'get_reminder':
            case 'delete_reminder':
            case 'create_timer':
            case 'delete_timer':
                return null;
            case 'get_id':
                return $this->generate_get_id($method, $id);
            default:
                return $this->generate_proxy_method($method, $id);
        }
    }

    /**
     * Generate a method that shouldn't be called from outside the actor.
     *
     * @param Method $method The method
     *
     * @return Method
     */
    protected abstract function generate_failure_method(Method $method);

    /**
     * Write a method to get the current actor id.
     *
     * @param Method $method
     *
     * @return Method
     */
    protected abstract function generate_get_id(Method $method, string $id);

    /**
     * Write a method that calls the actor.
     *
     * @param Method $method
     *
     * @return Method
     */
    protected abstract function generate_proxy_method(Method $method, string $id);

    /**
     * @return Method[] available methods for the interface
     */
    protected function get_methods(ClassType $interface): array
    {
        return array_merge($interface->getMethods(), ClassType::from(IActor::class)->getMethods());
    }

    protected function get_full_class_name()
    {
        return "\\".$this->get_namespace()."\\".$this->get_short_class_name();
    }

    protected function get_namespace()
    {
        return "Dapr\\Proxies";
    }

    protected function get_short_class_name()
    {
        $internal_type = preg_replace('/[^a-zA-Z0-9_]*/', '', $this->dapr_type);
        $proxy_type    = 'dapr_proxy_'.$internal_type;

        return $proxy_type;
    }
}

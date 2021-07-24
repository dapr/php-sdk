<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\ActorReference;
use Dapr\Actors\Attributes\Delete;
use Dapr\Actors\Attributes\Get;
use Dapr\Actors\Attributes\Post;
use Dapr\Actors\Attributes\Put;
use Dapr\Actors\Internal\InternalProxy;
use Dapr\Client\DaprClient;
use DI\FactoryInterface;
use JetBrains\PhpStorm\Pure;
use LogicException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Psr\Container\ContainerInterface;

/**
 * Class DynamicGenerator
 *
 * Uses some quirks of PHP magic functions to provide a proxy
 *
 * @package Dapr\Actors\Generators
 */
class DynamicGenerator extends GenerateProxy
{

    #[Pure] public function __construct(
        string $interface,
        string $dapr_type,
        FactoryInterface $factory,
        ContainerInterface $container
    ) {
        parent::__construct($interface, $dapr_type, $factory, $container);
    }

    public function get_proxy(string $id): InternalProxy
    {
        $current_proxy = new InternalProxy();
        $interface = ClassType::from($this->interface);
        $methods = $this->get_methods($interface);
        $current_proxy->DAPR_TYPE = $this->dapr_type;

        $reflection = new \ReflectionClass($current_proxy);
        $client =$reflection->getProperty('client');
        $client->setAccessible(true);
        $client->setValue($current_proxy, $this->container->get(DaprClient::class));
        $reference = $reflection->getProperty('reference');
        $actor_reference = new ActorReference($id, $this->dapr_type);
        $reference->setAccessible(true);
        $reference->setValue($current_proxy, $actor_reference);

        foreach ($methods as $method) {
            $current_proxy->{$method->getName()} = $this->generate_method($method, $id);
        }

        $current_proxy->_get_actor_reference = fn() => $actor_reference;

        return $current_proxy;
    }

    protected function generate_failure_method(Method $method): callable
    {
        return function () use ($method) {
            throw new LogicException("Cannot call {$method->getName()} from outside the actor.");
        };
    }

    protected function generate_proxy_method(Method $method, string $id): callable
    {
        $http_method = count($method->getParameters()) == 0 ? 'GET' : 'POST';
        foreach ($method->getAttributes() as $attribute) {
            $http_method = match ($attribute->getName()) {
                Get::class => 'GET',
                Post::class => 'POST',
                Put::class => 'PUT',
                Delete::class => 'DELETE',
                default => $http_method
            };
        }
        $reference = new ActorReference($id, $this->dapr_type);
        $actor_method = $method->getName();
        $return_type = $method->getReturnType();

        return function (...$params) use ($id, $http_method, $reference, $actor_method, $return_type) {
            /**
             * @var DaprClient $client
             */
            $client = $this->container->get(DaprClient::class);
            $result = $client->invokeActorMethod(
                $http_method,
                $reference,
                $actor_method,
                $params[0] ?? null,
                $return_type ?? 'array'
            );

            if ($return_type) {
                return $result;
            }

            return;
        };
    }

    protected function generate_get_id(Method $method, string $id): callable
    {
        return function () use ($id) {
            return $id;
        };
    }
}

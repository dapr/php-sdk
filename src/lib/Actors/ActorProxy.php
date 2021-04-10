<?php

namespace Dapr\Actors;

use Dapr\Actors\Attributes\DaprType;
use Dapr\Actors\Generators\ProxyFactory;
use DI\DependencyException;
use DI\NotFoundException;
use JetBrains\PhpStorm\Deprecated;
use LogicException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Class ActorProxy
 *
 * Used by the DynamicGenerator proxy
 *
 * @codeCoverageIgnore
 * @package Dapr
 */
class ActorProxy
{
    public function __construct(protected ProxyFactory $proxyFactory, protected LoggerInterface $logger)
    {
    }

    /**
     * Returns an actor proxy
     *
     * @param string $interface
     * @param string $id
     * @param string|null $override_type Allow overriding the Dapr type for a given interface
     *
     * @return object
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function get(string $interface, string $id, string|null $override_type = null): object
    {
        $this->logger?->debug('Getting actor proxy for {i}||{id}', ['i' => $interface, 'id' => $id]);

        $reflected_interface = new ReflectionClass($interface);
        $type                = $override_type ?? ($reflected_interface->getAttributes(
                    DaprType::class
                )[0] ?? null)?->newInstance()->type;

        if (empty($type)) {
            $this->logger?->critical('{i} is missing a DaprType attribute', ['i' => $interface]);
            throw new LogicException("$interface must have a DaprType attribute");
        }

        $generator = $this->proxyFactory->get_generator($interface, $type);

        return $generator->get_proxy($id);
    }
}

<?php

namespace Dapr\Actors\Generators;

use Dapr\Actors\IActor;
use Dapr\Client\DaprClient;
use DI\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Interface IGenerateProxy
 *
 * All generators implement this interface.
 *
 * @package Dapr\Actors\Generators
 */
interface IGenerateProxy
{
    public function __construct(
        string $interface,
        string $dapr_type,
        DaprClient $client
    );

    /**
     * Get a proxied type
     *
     * @param string $id The id of the type
     *
     * @return IActor An actor
     */
    public function get_proxy(string $id);
}

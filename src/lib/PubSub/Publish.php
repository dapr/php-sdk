<?php

namespace Dapr\PubSub;

use Dapr\Client\DaprClient;
use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;
use JetBrains\PhpStorm\Deprecated;
use Psr\Container\ContainerInterface;

/**
 * Class Publish
 * @package Dapr\PubSub
 */
#[Deprecated(since: '1.2.0', replacement: Topic::class)]
class Publish
{
    /**
     * Publish constructor.
     *
     * @param string $pubsub
     * @param FactoryInterface $factory
     * @param ContainerInterface $container
     */
    public function __construct(
        private string $pubsub,
        private FactoryInterface $factory,
        private ContainerInterface $container
    ) {
    }

    /**
     * @param string $topic
     *
     * @return Topic
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function topic(string $topic): Topic
    {
        return $this->factory->make(
            Topic::class,
            [
                'pubsub' => $this->pubsub,
                'topic' => $topic,
                'client' => $this->container->get(\Dapr\DaprClient::class)
            ]
        );
    }
}

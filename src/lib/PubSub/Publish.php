<?php

namespace Dapr\PubSub;

use DI\DependencyException;
use DI\FactoryInterface;
use DI\NotFoundException;

class Publish
{
    /**
     * Publish constructor.
     *
     * @param string $pubsub
     * @param FactoryInterface $container
     */
    public function __construct(private string $pubsub, private FactoryInterface $container)
    {
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
        return $this->container->make(Topic::class, ['pubsub' => $this->pubsub, 'topic' => $topic]);
    }
}

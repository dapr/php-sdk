<?php

namespace Dapr\PubSub;

use DI\Container;

class Publish
{
    /**
     * Publish constructor.
     *
     * @param string $pubsub
     */
    public function __construct(private string $pubsub, private Container $container)
    {
    }

    public function topic(string $topic): Topic
    {
        return $this->container->make(Topic::class, ['pubsub' => $this->pubsub, 'topic' => $topic]);
    }
}

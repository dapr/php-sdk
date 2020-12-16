<?php

namespace Dapr\PubSub;

class Publish
{
    /**
     * Publish constructor.
     *
     * @param string $pubsub
     */
    public function __construct(private string $pubsub)
    {
    }

    public function topic(string $topic): Topic
    {
        return new Topic($this->pubsub, $topic);
    }
}

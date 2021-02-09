<?php

namespace Dapr\PubSub;

/**
 * Class Subscription
 * @package Dapr\PubSub
 * @codeCoverageIgnore via integration tests
 */
class Subscription
{
    public function __construct(public string $pubsubname, public string $topic, public string $route)
    {
    }
}

<?php

namespace Dapr\PubSub;

class Subscription
{
    public function __construct(public string $pubsubname, public string $topic, public string $route)
    {
    }
}

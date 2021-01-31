<?php

namespace Dapr\PubSub;

/**
 * Class Subscriptions
 * @package Dapr\PubSub
 */
class Subscriptions
{
    /**
     * @var Subscription[]
     */
    public array $subscriptions;

    /**
     * Subscriptions constructor.
     *
     * @param Subscription ...$subscriptions Subscriptions
     */
    public function __construct(Subscription ...$subscriptions)
    {
        $this->subscriptions = $subscriptions;
    }
}

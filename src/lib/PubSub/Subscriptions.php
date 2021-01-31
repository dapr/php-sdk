<?php

namespace Dapr\PubSub;

use Dapr\Serialization\ISerializer;
use Dapr\Serialization\Serializers\ISerialize;

/**
 * Class Subscriptions
 * @package Dapr\PubSub
 */
class Subscriptions implements ISerialize
{
    /**
     * Subscriptions constructor.
     *
     * @param Subscription[] $subscriptions
     */
    public function __construct(public array $subscriptions = [])
    {
    }

    public function serialize(mixed $value, ISerializer $serializer): mixed
    {
        return $serializer->as_array($this->subscriptions);
    }
}

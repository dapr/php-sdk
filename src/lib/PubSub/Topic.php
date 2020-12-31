<?php

namespace Dapr\PubSub;

use Dapr\DaprClient;
use Dapr\exceptions\DaprException;

class Topic
{
    public function __construct(private string $pubsub, private string $topic)
    {
    }

    /**
     * Publish an event to the topic
     *
     * @param CloudEvent|mixed $event The event to publish
     *
     * @return bool Whether the event was successfully dispatched
     */
    public function publish(mixed $event): bool
    {
        if ($event instanceof CloudEvent) {
            $event = $event->to_array();
        }

        try {
            $result = DaprClient::post(DaprClient::get_api("/publish/{$this->pubsub}/{$this->topic}"), $event);
            return true;
        } catch (DaprException $exception) {
            return false;
        }
    }
}

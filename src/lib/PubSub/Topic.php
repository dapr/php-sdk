<?php

namespace Dapr\PubSub;

use Dapr\DaprClient;

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

        $result = DaprClient::post(DaprClient::get_api("/publish/{$this->pubsub}/{$this->topic}"), $event);
        switch ($result->code) {
            case 200:
                return true;
            case 500:
            default:
                return false;
        }
    }
}

<?php

namespace Dapr\PubSub;

use Dapr\DaprClient;
use Dapr\exceptions\DaprException;
use Dapr\Runtime;

class Topic
{
    public function __construct(private string $pubsub, private string $topic, private DaprClient $client)
    {
    }

    /**
     * Publish an event to the topic
     *
     * @param CloudEvent|mixed $event The event to publish
     * @param array|null $metadata Additional metadata to pass to the component
     *
     * @return bool Whether the event was successfully dispatched
     */
    public function publish(mixed $event, ?array $metadata = null): bool
    {
        Runtime::$logger?->debug('Sending {event} to {topic}', ['event' => $event, 'topic' => $this->topic]);
        if ($event instanceof CloudEvent) {
            $this->client->extra_headers = [
                'Content-Type: application/cloudevents+json',
            ];

            $event = $event->to_array();
        }

        try {
            $this->client->post("/publish/{$this->pubsub}/{$this->topic}", $event, $metadata);

            $this->client->extra_headers = [];

            return true;
        } catch (DaprException) {
            return false;
        }
    }
}

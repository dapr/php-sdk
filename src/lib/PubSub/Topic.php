<?php

namespace Dapr\PubSub;

use Dapr\DaprClient;
use Dapr\exceptions\DaprException;
use Psr\Log\LoggerInterface;

/**
 * Class Topic
 * @package Dapr\PubSub
 */
class Topic
{
    public function __construct(
        private string $pubsub,
        private string $topic,
        private DaprClient $client,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Publish an event to the topic
     *
     * @param CloudEvent|mixed $event The event to publish
     * @param array|null $metadata Additional metadata to pass to the component
     * @param string $content_type The header to include in the publish request. Ignored when $event is a CloudEvent
     *
     * @return bool Whether the event was successfully dispatched
     */
    public function publish(mixed $event, ?array $metadata = null, $content_type = 'application/json'): bool
    {
        $this->logger->debug('Sending {event} to {topic}', ['event' => $event, 'topic' => $this->topic]);
        if ($event instanceof CloudEvent) {
            $this->client->extra_headers = [
                'Content-Type: application/cloudevents+json',
            ];

            $event = $event->to_array();
        }

        try {
            $this->client->post("/publish/{$this->pubsub}/{$this->topic}", $event, $metadata);

            $this->client->extra_headers = ['Content-Type: '.$content_type];

            return true;
        } catch (DaprException) { // @codeCoverageIgnoreStart
            return false;
        } // @codeCoverageIgnoreEnd
    }
}

<?php

namespace Dapr\PubSub;

use Dapr\Client\DaprClient as NewClient;
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
        private DaprClient|NewClient $client,
        private LoggerInterface|null $logger = null
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
    public function publish(mixed $event, ?array $metadata = null, string $content_type = 'application/json'): bool
    {
        $this->logger->debug('Sending {event} to {topic}', ['event' => $event, 'topic' => $this->topic]);
        if ($event instanceof CloudEvent) {
            $this->client->extra_headers = [
                'Content-Type: application/cloudevents+json',
            ];

            $event = $event->to_array();
        }

        if ($this->client instanceof DaprClient) {
            try {
                $this->client->post("/publish/{$this->pubsub}/{$this->topic}", $event, $metadata);

                $this->client->extra_headers = ['Content-Type: ' . $content_type];

                return true;
            } catch (DaprException) { // @codeCoverageIgnoreStart
                return false;
            } // @codeCoverageIgnoreEnd
        } elseif ($this->client instanceof NewClient) {
            try {
                $this->client->publishEvent($this->pubsub, $this->topic, $event, $metadata);
                return true;
            } catch (DaprException) {
                return false;
            }
        }

        return false;
    }
}

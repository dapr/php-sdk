<?php

namespace Dapr\PubSub;

use Dapr\Runtime;
use Dapr\Serialization\Serializer;
use JetBrains\PhpStorm\ArrayShape;

abstract class Subscribe
{
    private static array $subscribed_topics = [];
    private static array $handlers = [];

    /**
     * @param string $pubsub
     * @param string $topic
     * @param callable $handler
     */
    public static function to_topic(string $pubsub, string $topic, callable $handler): void
    {
        Runtime::$logger?->debug('Subscribing to {topic} on {p}', ['topic' => $topic, 'p' => $pubsub]);
        self::$subscribed_topics[] = [
            'pubsubname' => $pubsub,
            'topic'      => $topic,
            'route'      => '/dapr/runtime/sub/'.$topic,
        ];
        self::$handlers[$topic]    = $handler;
    }

    /**
     * @return array
     */
    #[ArrayShape(['code' => "int", 'body' => "false|string"])]
    public static function get_subscriptions(): array
    {
        return [
            'code' => 200,
            'body' => json_encode(array_values(self::$subscribed_topics)),
        ];
    }

    /**
     * @param $id
     * @param $event
     *
     * @return array
     */
    #[ArrayShape(['code' => "int", 'body' => "false|string"])]
    public static function handle_subscription(
        $id,
        $event
    ): array {
        if (isset(self::$handlers[$id])) {
            try {
                $result = call_user_func(self::$handlers[$id], $event);
            } catch (\Exception $exception) {
                Runtime::$logger?->critical(
                    'Failed to handle message {id}: {exception}',
                    ['id' => $id, 'exception' => $exception]
                );

                return ['code' => 500, 'body' => Serializer::as_json($exception)];
            }

            return ['code' => 200, 'body' => Serializer::as_json($result)];
        }

        return [
            'code' => 404,
            'body' => Serializer::as_json(new \BadFunctionCallException('Unable to handle subscription')),
        ];
    }
}

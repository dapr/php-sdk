<?php

namespace Dapr\PubSub;

abstract class Subscribe
{
    private static array $subscribed_topics = [];
    private static array $handlers = [];

    public static function to_topic(string $pubsub, string $topic, callable $handler)
    {
        self::$subscribed_topics[] = [
            'pubsubname' => $pubsub,
            'topic'      => $topic,
            'route'      => '/dapr/runtime/sub/'.$topic,
        ];
        self::$handlers[$topic]    = $handler;
    }

    public static function get_subscriptions(): array
    {
        return [
            'code' => 200,
            'body' => json_encode(array_values(self::$subscribed_topics)),
        ];
    }

    public static function handle_subscription($id, $event)
    {
        if (isset(self::$handlers[$id])) {
            $result = call_user_func(self::$handlers[$id], $event);

            return ['code' => 200, 'body' => json_encode($result)];
        }

        return ['code' => 404];
    }
}

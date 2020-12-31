<?php

namespace Dapr\PubSub;

use Dapr\Serializer;
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
    public static function handle_subscription($id, $event): array
    {
        if (isset(self::$handlers[$id])) {
            try {
                $result = call_user_func(self::$handlers[$id], $event);
            } catch (\Exception $exception) {
                return ['code' => 500, 'body' => json_encode(Serializer::as_json($exception))];
            }

            return ['code' => 200, 'body' => json_encode(Serializer::as_json($result))];
        }

        return [
            'code' => 404,
            'body' => json_encode(Serializer::as_json(new \BadFunctionCallException('Unable to handle subscription'))),
        ];
    }
}

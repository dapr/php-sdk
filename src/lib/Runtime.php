<?php

namespace Dapr;

use Dapr\Actors\ActorRuntime;
use Dapr\PubSub\CloudEvent;
use Dapr\PubSub\Subscribe;
use JetBrains\PhpStorm\ArrayShape;

abstract class Runtime
{
    #[ArrayShape(['callable'])]
    private static array $health_checks = [];

    #[ArrayShape(['string' => 'callable'])]
    private static array $methods = [];

    public static function add_health_check(callable $callback)
    {
        self::$health_checks[] = $callback;
    }

    public static function register_method(string $method, ?callable $callback = null)
    {
        if ($callback === null) {
            $callback = $method;
        }
        self::$methods[$method] = $callback;
    }

    public static function invoke_method(
        string $app_id,
        string $method,
        array $param = [],
        $http_method = 'POST'
    ): DaprResponse {
        $url = "/invoke/$app_id/$method";
        switch ($http_method) {
            case 'GET':
                return DaprClient::get(DaprClient::get_api($url, $param));
            case 'POST':
            case 'PUT':
                return DaprClient::post(DaprClient::get_api($url), $param);
            case 'DELETE':
                return DaprClient::delete(DaprClient::get_api($url));
            default:
                trigger_error('Unknown HTTP method: '.$http_method, E_USER_ERROR);
        }
    }

    public static function get_handler_for_route(string $http_method, string $uri)
    {
        $handler = self::find_handler($http_method, $uri);

        return function () use ($handler) {
            $result = $handler();
            if (isset($result['body']) && ! is_string($result['body'])) {
                $result['body'] = json_encode($result['body']);
            }

            return $result;
        };
    }

    private static function find_handler(string $http_method, string $uri): callable|null
    {
        if (str_starts_with(haystack: $uri, needle: '/actors')) {
            return function () use ($http_method, $uri) {
                $parts = ActorRuntime::extract_parts_from_request($http_method, $uri);

                return ActorRuntime::handle_invoke($parts);
            };
        }
        if (str_starts_with(haystack: $uri, needle: '/dapr/runtime/sub/')) {
            $id = str_replace('/dapr/runtime/sub/', '', $uri);
            $event = CloudEvent::parse(ActorRuntime::get_input());

            return function () use ($id, $event) {
                return Subscribe::handle_subscription($id, $event);
            };
        }
        switch ($uri) {
            case '/dapr/config':
                return [ActorRuntime::class, 'handle_config'];
            case '/dapr/subscribe':
                return [Subscribe::class, 'get_subscriptions'];
            case '/healthz':
                return function () {
                    try {
                        foreach (self::$health_checks as $callback) {
                            $callback();
                        }
                    } catch (\Throwable $ex) {
                        return ['code' => 500];
                    }

                    return ['code' => 200];
                };
            default:
                $method_parts = explode('/', $uri, 3);
                $body         = Deserializer::maybe_deserialize(json_decode(ActorRuntime::get_input(), true));
                if (count($method_parts) === 2) {
                    if ($http_method === 'OPTIONS' && Binding::has_binding($method_parts[1])) {
                        return function () {
                            return ['code' => 200];
                        };
                    }
                    if (Binding::has_binding($method_parts[1])) {
                        return function () use ($method_parts, $body) {
                            return Binding::handle_method($method_parts[1], $body);
                        };
                    }

                    return function () use ($method_parts, $body) {
                        return self::handle_method($method_parts[1], $body);
                    };
                }

                return null;
        }
    }

    public static function handle_method(string $method, mixed $params): array
    {
        if ( ! isset(self::$methods[$method])) {
            return ['code' => 404];
        }
        if (is_array($params)) {
            $result = call_user_func_array(self::$methods[$method], $params);
        } else {
            $result = call_user_func(self::$methods[$method], $params);
        }

        if (is_array($result) && isset($result['code'])) {
            return $result;
        }

        return ['code' => 200, 'body' => $result];
    }
}

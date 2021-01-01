<?php

namespace Dapr;

use Closure;
use Dapr\Actors\ActorRuntime;
use Dapr\exceptions\DaprException;
use Dapr\PubSub\CloudEvent;
use Dapr\PubSub\Subscribe;
use JetBrains\PhpStorm\ArrayShape;

abstract class Runtime
{
    #[ArrayShape(['callable'])]
    private static array $health_checks = [];

    #[ArrayShape(['string' => 'callable'])]
    private static array $methods = [];

    /**
     * Register a method for determining health checks
     *
     * @param callable $callback
     */
    public static function add_health_check(callable $callback)
    {
        self::$health_checks[] = $callback;
    }

    /**
     * Register a method to be called by the runtime
     *
     * @param string $method_name
     * @param callable|null $callback
     * @param string $http_method
     */
    public static function register_method(
        string $method_name,
        ?callable $callback = null,
        string $http_method = 'POST'
    ): void {
        if ($callback === null) {
            $callback = $method_name;
        }
        self::$methods[$http_method][$method_name] = $callback;
    }

    /**
     * Invoke a remote method
     *
     * @param string $app_id
     * @param string $method
     * @param mixed|array $param
     * @param string $http_method
     *
     * @return DaprResponse
     * @throws DaprException
     */
    public static function invoke_method(
        string $app_id,
        string $method,
        mixed $param = [],
        $http_method = 'POST'
    ): DaprResponse {
        $url = "/invoke/$app_id/method/$method";
        switch ($http_method) {
            case 'GET':
                return DaprClient::get(DaprClient::get_api($url, $param));
            case 'POST':
            case 'PUT':
                return DaprClient::post(DaprClient::get_api($url), $param);
            case 'DELETE':
                return DaprClient::delete(DaprClient::get_api($url));
            default:
                throw new DaprException('Unknown http method: '.$http_method);
        }
    }

    /**
     * Determine the route handler
     *
     * @param string $http_method
     * @param string $uri
     *
     * @return Closure
     */
    public static function get_handler_for_route(string $http_method, string $uri): Closure
    {
        $handler = self::find_handler($http_method, $uri);

        return function () use ($handler) {
            try {
                $result = $handler();
                if (isset($result['body']) && ! is_string($result['body'])) {
                    $result['body'] = json_encode($result['body']);
                }

                return $result;
            } catch (\Exception $exception) {
                return ['code' => 500, 'body' => json_encode(Serializer::as_json($exception))];
            }
        };
    }

    /**
     * Find a handler
     *
     * @param string $http_method
     * @param string $uri
     *
     * @return callable|null
     */
    private static function find_handler(string $http_method, string $uri): callable|null
    {
        if (str_starts_with(haystack: $uri, needle: '/actors')) {
            return function () use ($http_method, $uri) {
                $parts = ActorRuntime::extract_parts_from_request($http_method, $uri);

                return ActorRuntime::handle_invoke($parts);
            };
        }
        if (str_starts_with(haystack: $uri, needle: '/dapr/runtime/sub/')) {
            $id    = str_replace('/dapr/runtime/sub/', '', $uri);
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
                        return ['code' => 500, 'body' => json_encode(Serializer::as_json($ex))];
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

                    return function () use ($method_parts, $body, $http_method) {
                        return self::handle_method($http_method, $method_parts[1], $body);
                    };
                }

                return null;
        }
    }

    public static function handle_method(string $http_method, string $method, mixed $params): array
    {
        if ( ! isset(self::$methods[$http_method][$method])) {
            return [
                'code' => 404,
                'body' => json_encode(
                    Serializer::as_json(new \BadFunctionCallException('unable to locate handler for method'))
                ),
            ];
        }

        try {
            if (is_array($params)) {
                $result = call_user_func_array(self::$methods[$http_method][$method], $params);
            } else {
                $result = call_user_func(self::$methods[$http_method][$method], $params);
            }

            if (is_array($result) && isset($result['code'])) {
                return $result;
            }
        } catch (\Exception $exception) {
            return ['code' => 500, 'body' => json_encode(Serializer::as_json($exception))];
        }

        return ['code' => 200, 'body' => $result];
    }
}

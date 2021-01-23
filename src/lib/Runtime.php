<?php

namespace Dapr;

use Closure;
use Dapr\Actors\ActorRuntime;
use Dapr\Attributes\FromRoute;
use Dapr\Deserialization\Deserializer;
use Dapr\exceptions\DaprException;
use Dapr\PubSub\CloudEvent;
use Dapr\PubSub\Subscribe;
use Dapr\Serialization\Serializer;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Log\LoggerInterface;

abstract class Runtime
{
    #[ArrayShape(['callable'])]
    private static array $health_checks = [];

    #[ArrayShape(['string' => 'callable'])]
    private static array $methods = [];

    public static LoggerInterface|null $logger = null;

    /**
     * Register a method for determining health checks
     *
     * @param callable $callback
     */
    public static function add_health_check(callable $callback): void
    {
        self::$logger?->info('Setting custom health check');
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
        self::$logger?->info("Registered method: {method_name}", ['method_name' => $method_name]);
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
        self::$logger?->info(
            "Invoking {http_method} {app_id}.{method}",
            ['http_method' => $http_method, 'app_id' => $app_id, 'method' => $method]
        );
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
        self::$logger?->info(
            "Determining handler for {http_method} {uri}",
            ['http_method' => $http_method, 'uri' => $uri]
        );
        $handler = self::find_handler($http_method, $uri);

        return function () use ($handler) {
            try {
                $result = $handler();
                if (isset($result['body']) && ! is_string($result['body'])) {
                    $result['body'] = json_encode($result['body']);
                }

                return $result;
            } catch (\Exception $exception) {
                return ['code' => 500, 'body' => Serializer::as_json($exception)];
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
            self::$logger?->debug('Using actor handler');

            return function () use ($http_method, $uri) {
                $parts = ActorRuntime::extract_parts_from_request($http_method, $uri);

                return ActorRuntime::handle_invoke($parts);
            };
        }
        if (str_starts_with(haystack: $uri, needle: '/dapr/runtime/sub/')) {
            $id = str_replace('/dapr/runtime/sub/', '', $uri);
            self::$logger?->debug('Using pubsub handler');
            $event = CloudEvent::parse(ActorRuntime::get_input());

            return function () use ($id, $event) {
                return Subscribe::handle_subscription($id, $event);
            };
        }
        switch ($uri) {
            case '/dapr/config':
                self::$logger?->debug('Returning actor config');

                return [ActorRuntime::class, 'handle_config'];
            case '/dapr/subscribe':
                self::$logger?->debug('Returning subscriptions');

                return [Subscribe::class, 'get_subscriptions'];
            case '/healthz':
                return function () {
                    self::$logger?->debug('Running health checks');
                    try {
                        foreach (self::$health_checks as $callback) {
                            $callback();
                        }
                    } catch (\Throwable $ex) {
                        self::$logger?->critical('Health check failed: {exception}', ['exception' => $ex]);

                        return ['code' => 500, 'body' => Serializer::as_json($ex)];
                    }

                    return ['code' => 200];
                };
            default:
                $method_parts = explode('/', $uri, 3);
                $body         = json_decode(ActorRuntime::get_input(), true);
                if (count($method_parts) >= 2) {
                    if ($http_method === 'OPTIONS' && Binding::has_binding($method_parts[1])) {
                        self::$logger?->debug('Found binding');

                        return function () {
                            return ['code' => 200];
                        };
                    }
                    if (Binding::has_binding($method_parts[1])) {
                        self::$logger?->debug('Using binding handler');

                        return function () use ($method_parts, $body) {
                            return Binding::handle_method($method_parts[1], $body);
                        };
                    }

                    self::$logger?->debug('Using method handler');

                    return function () use ($method_parts, $body, $http_method, $uri) {
                        return self::handle_method($http_method, $method_parts[1], $body, $method_parts[2] ?? null);
                    };
                }

                return null;
        }
    }

    public static function handle_method(string $http_method, string $method, mixed $body, string $uri): array
    {
        if ( ! isset(self::$methods[$http_method][$method])) {
            self::$logger?->critical(
                'Did not find a method for {http_method} {method}',
                ['http_method' => $http_method, 'method' => $method]
            );

            return [
                'code' => 404,
                'body' => Serializer::as_json(new \BadFunctionCallException('unable to locate handler for method')),
            ];
        }

        try {
            $callback             = self::$methods[$http_method][$method];
            if($callback instanceof Closure || is_string($callback)) {
                $reflection = new \ReflectionFunction($callback);
            } else if(is_array($callback)) {
                $reflection = new \ReflectionMethod($callback[0], $callback[1]);
            }
            $parameter_reflection = $reflection->getParameters();
            $params               = [];
            $uri                  = explode('/', $uri);
            foreach ($parameter_reflection as $parameter) {
                if (count($parameter->getAttributes(FromRoute::class))) {
                    $params[$parameter->name] = Deserializer::detect_from_parameter($parameter, array_shift($uri));
                } else {
                    $params[$parameter->name] = Deserializer::detect_from_parameter($parameter, $body);
                }
            }

            $result = call_user_func_array($callback, $params);

            if (is_array($result) && isset($result['code'])) {
                return $result;
            }
        } catch (\Exception $exception) {
            self::$logger?->critical('Method failed: {exception}', ['exception' => $exception]);

            return ['code' => 500, 'body' => Serializer::as_json($exception)];
        }

        return ['code' => 200, 'body' => Serializer::as_json($result)];
    }

    public static function set_logger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }
}

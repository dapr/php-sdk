<?php

namespace Dapr;

abstract class Binding
{
    private static array $bindings = [];

    public static function register_input_binding(string $name, callable $callback)
    {
        self::$bindings[$name] = $callback;
    }

    public static function invoke_output(
        string $name,
        string $operation,
        array $metadata = [],
        array $data = []
    ): DaprResponse {
        $payload = [
            'data'      => (object)Serializer::as_json($data),
            'metadata'  => (object)Serializer::as_json($metadata),
            'operation' => $operation,
        ];

        return DaprClient::post(DaprClient::get_api("/bindings/$name"), $payload);
    }

    public static function has_binding(string $name): bool
    {
        return isset(self::$bindings[$name]);
    }

    public static function handle_method(string $method, mixed $params): array
    {
        if ( ! isset(self::$bindings[$method])) {
            return ['code' => 404];
        }
        if (is_array($params)) {
            $result = call_user_func_array(self::$bindings[$method], $params);
        } else {
            $result = call_user_func(self::$bindings[$method], $params);
        }

        if (is_array($result) && isset($result['code'])) {
            return $result;
        }

        return ['code' => 200, 'body' => $result];
    }
}

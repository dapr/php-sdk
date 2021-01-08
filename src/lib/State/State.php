<?php

namespace Dapr\State;

use Dapr\DaprClient;
use Dapr\Serializer;
use Dapr\State\Internal\StateHelpers;
use ReflectionClass;
use ReflectionProperty;

/**
 * Base class for handling state.
 * @package Dapr
 * @see https://v1-rc1.docs.dapr.io/reference/api/state_api/
 */
final class State
{
    use StateHelpers;
    private static \WeakMap $data;

    public static function save_state(object $obj, ?array $metadata = null): void
    {
        $map = self::$data ?? new \WeakMap();
        $reflection = new ReflectionClass($obj);
        $store = self::get_description($reflection);
        $keys = $map[$obj] ?? [];
        $request = [];
        foreach($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $key = $property->name;
            $item = [
                'key' => $key,
                'value' => Serializer::as_json($obj->$key),
            ];

            if(isset($keys[$key]['etag'])) {
                $item['etag'] = $keys[$key]['etag'];
                $item['options'] = [
                    'consistency' => (new $store->consistency)->get_consistency(),
                    'concurrency' => (new $store->consistency)->get_concurrency(),
                ];
            }

            if(isset($metadata)) $item['metadata'] = $metadata;
            $request[] = $item;
        }

        DaprClient::post(DaprClient::get_api("/state/{$store->name}"), $request);
    }

    public static function get_etag(object $obj, string $key) {
        return ((self::$data[$obj] ?? [])[$key] ?? [])['etag'] ?? null;
    }

    public static function load_state(object $obj, int $parallelism = 10, ?array $metadata = null): void
    {
        $map        = self::$data ?? new \WeakMap();
        $reflection = new ReflectionClass($obj);
        $store      = self::get_description($reflection);
        $keys       = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $keys[$property->name] = [
                'etag' => null,
            ];
        }
        $result = DaprClient::post(
            DaprClient::get_api("/state/{$store->name}/bulk", $metadata),
            [
                'keys'        => array_keys($keys),
                'parallelism' => $parallelism,
            ]
        );

        foreach ($result->data as $value) {
            $key = $value['key'];
            if ($value['data'] ?? null !== null) {
                $obj->$key          = $value['data'];
                $keys[$key]['etag'] = $value['etag'];
            } elseif (isset($value['etag'])) {
                // there's an etag set, but no value, set it to null
                $obj->$key          = null;
                $keys[$key]['etag'] = $value['etag'];
            }
        }

        $map[$obj]  = $keys;
        self::$data = $map;
    }
}

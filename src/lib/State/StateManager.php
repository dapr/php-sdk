<?php

namespace Dapr\State;

use Dapr\Client\DaprClient;
use Dapr\consistency\Consistency;
use Dapr\State\Internal\StateHelpers;

/**
 * Class StateManager
 * @package Dapr\State
 */
class StateManager implements IManageState
{
    use StateHelpers;

    protected static \WeakMap $objMap;

    public function __construct(protected DaprClient $client)
    {
        self::$objMap ??= new \WeakMap();
    }

    public function save_state(string $store_name, StateItem $item): void
    {
        $this->client->trySaveState(
            $store_name,
            $item->key,
            $item->value,
            $item->etag,
            $item->consistency,
            $item->metadata
        );
    }

    public function load_state(
        string $store_name,
        string $key,
        mixed $default_value = null,
        array $metadata = [],
        ?Consistency $consistency = null
    ): mixed {
        $response = $this->client->getStateAndEtag($store_name, $key, consistency: $consistency, metadata: $metadata);
        return new StateItem($key, $response['value'], $consistency, $response['etag'] ?: null, $metadata);
    }

    public function delete_keys(string $store_name, array $keys, array $metadata = []): void
    {
        foreach ($keys as $key) {
            $this->client->tryDeleteState($store_name, $key, '', metadata: $metadata);
        }
    }

    public function save_object(
        object $item,
        string $prefix = '',
        ?array $metadata = null,
        ?Consistency $consistency = null
    ): void {
        $reflection = new \ReflectionClass($item);
        $store = self::get_description($reflection);
        $keys = self::$objMap[$item] ?? [];
        $items = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $key = $property->getName();
            $items[] = new StateItem(
                "$prefix$key",
                $property->getValue($item),
                new $store->consistency,
                $keys[$key] ?? null,
                $metadata ?? []
            );
        }
        $this->client->saveBulkState($store->name, $items);
    }

    public function load_object(
        object|string $into,
        string $prefix = '',
        int $parallelism = 10,
        array $metadata = []
    ): void {
        $reflection = new \ReflectionClass($into);
        if (is_string($into)) {
            $into = $reflection->newInstanceWithoutConstructor();
        }
        $store = self::get_description($reflection);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $etags = [];
        $results = $this->client->getBulkState(
            $store->name,
            array_map(fn($key) => $prefix . $key, array_column($properties, 'name')),
            $parallelism,
            $metadata
        );
        foreach ($results as $key => $item) {
            $etags[$key] = $item['etag'];
            $property = str_replace($prefix, '', $key);
            $value = $this->client->deserializer->detect_from_property(
                $reflection->getProperty($property),
                $item['value']
            );
            if (isset($value) && $value !== null) {
                $into->$property = $value;
                $etags[$property] = $item['etag'];
            } elseif (isset($item['etag'])) {
                $into->$property = $value;
                $etags[$property] = $item['etag'];
            }
        }

        self::$objMap[$into] = $etags;
    }
}

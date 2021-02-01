<?php

namespace Dapr\State;

use Dapr\Actors\Internal\KeyResponse;
use Dapr\consistency\Consistency;
use Dapr\consistency\EventualLastWrite;
use Dapr\DaprClient;
use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use Dapr\Serialization\ISerializer;
use Dapr\State\Internal\StateHelpers;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use WeakMap;

class StateManager implements IManageState
{
    use StateHelpers;

    protected static WeakMap $obj_meta;

    public function __construct(
        protected LoggerInterface $logger,
        protected ISerializer $serializer,
        protected IDeserializer $deserializer,
        protected DaprClient $client
    ) {
        if ( ! isset(self::$obj_meta)) {
            self::$obj_meta = new WeakMap();
        }
    }

    /**
     * @inheritDoc
     * @throws DaprException
     */
    public function save_state(
        string $store_name,
        StateItem $item
    ): void {
        $request = [
            $this->serializer->as_array($item),
        ];
        $this->client->post("/state/$store_name", $request);
    }

    /**
     * @inheritDoc
     * @throws DaprException
     */
    public function load_state(
        string $store_name,
        string $key,
        mixed $default_value = null,
        array $metadata = [],
        ?Consistency $consistency = null
    ): StateItem {
        $data = $this->client->get("/state/$store_name/$key", $metadata);
        switch ($data->code) {
            case KeyResponse::KEY_NOT_FOUND:
                return new StateItem($key, $default_value, $consistency, $data->etag);
            case KeyResponse::SUCCESS:
                return new StateItem($key, $data->data, $consistency, $data->etag);
        }
        throw new DaprException('Failed to retrieve state');
    }

    public function delete_keys(string $store_name, array $keys, array $metadata = []): void
    {
        foreach ($keys as $key) {
            $this->client->delete("/state/$store_name/$key", $metadata);
        }
    }

    /**
     * @inheritDoc
     * @throws DaprException
     */
    public function save_object(
        object $item,
        string $prefix = '',
        ?array $metadata = null,
        ?Consistency $consistency = null
    ): void {
        $this->logger->debug('Saving state');
        $reflection = new ReflectionClass($item);
        $store      = self::get_description($reflection);
        $keys       = self::$obj_meta[$item] ?? [];
        $request    = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $key   = $prefix.$property->getName();
            $value = [
                'key'   => $key,
                'value' => $this->serializer->as_array($item->{$property->getName()} ?? null),
            ];

            if (isset($keys[$key]['etag'])) {
                $value['etag']    = $keys[$key]['etag'];
                $value['options'] = [
                    'consistency' => ($consistency ?? new EventualLastWrite())->get_consistency(),
                    'concurrency' => ($consistency ?? new EventualLastWrite())->get_concurrency(),
                ];
            }
            if (isset($metadata)) {
                $value['metadata'] = $metadata;
            }
            $request[] = $value;
        }

        $this->client->post("/state/{$store->name}", $request);
    }

    /**
     * @inheritDoc
     * @throws DaprException|ReflectionException
     */
    public function load_object(object $into, string $prefix = '', int $parallelism = 10, array $metadata = []): void
    {
        $this->logger->debug('Loading state');
        $reflection = new ReflectionClass($into);
        $store_name = self::get_description($reflection)->name;
        $keys       = [];
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result     = $this->client->post(
            "/state/$store_name/bulk",
            [
                'keys'        => array_map(
                    fn($key) => $prefix.$key,
                    array_column($properties, 'name')
                ),
                'parallelism' => $parallelism,
            ],
            $metadata
        );
        foreach ($result->data as $value) {
            $key       = $value['key'];
            $prop_name = empty($prefix) ? $key : substr($key, strlen($prefix));
            if (isset($value['data'])) {
                $value['data'] = $this->deserializer->detect_from_property(
                    $reflection->getProperty($prop_name),
                    $value['data']
                );
            }
            if (isset($value['data']) && $value['data'] !== null) {
                $into->$prop_name   = $value['data'];
                $keys[$key]['etag'] = $value['etag'];
            } elseif (isset($value['etag'])) {
                $into->$prop_name   = null;
                $keys[$key]['etag'] = $value['etag'];
            }
        }

        self::$obj_meta[$into] = $keys;
    }
}
